"""Backup, restore, dan reset database Tarombo."""

from __future__ import annotations

import json
import os
import shutil
import sqlite3
import subprocess
from datetime import datetime, timezone
from pathlib import Path

from db import BASE_DIR, SQLITE_PATH, _use_sqlite, init_schema, migrate_schema

BACKUP_DIR = BASE_DIR / "backups"
METADATA_SUFFIX = ".json"


def _utc_stamp() -> str:
    return datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")


def _write_meta(path: Path, payload: dict):
    meta_path = path.with_suffix(path.suffix + METADATA_SUFFIX)
    meta_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")


def _read_meta(data_path: Path) -> dict | None:
    meta_path = data_path.with_suffix(data_path.suffix + METADATA_SUFFIX)
    if not meta_path.exists():
        return None
    try:
        return json.loads(meta_path.read_text(encoding="utf-8"))
    except (json.JSONDecodeError, OSError):
        return None


def list_backups() -> list[dict]:
    if not BACKUP_DIR.exists():
        return []
    items = []
    for path in sorted(BACKUP_DIR.iterdir(), reverse=True):
        if path.suffix == ".db" or path.suffix == ".sql":
            meta = _read_meta(path) or {}
            items.append(
                {
                    "filename": path.name,
                    "path": str(path),
                    "size_bytes": path.stat().st_size,
                    "created_at": meta.get("created_at") or datetime.fromtimestamp(
                        path.stat().st_mtime, tz=timezone.utc
                    ).isoformat(),
                    "backend": meta.get("backend", "unknown"),
                }
            )
    return items


def create_backup(db) -> dict:
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    stamp = _utc_stamp()
    if db.backend == "sqlite":
        dest = BACKUP_DIR / f"tarombo_{stamp}.db"
        try:
            db._conn.commit()
        except Exception:
            pass
        src = sqlite3.connect(str(SQLITE_PATH))
        try:
            dst = sqlite3.connect(str(dest))
            try:
                src.backup(dst)
                dst.commit()
            finally:
                dst.close()
        finally:
            src.close()
        payload = {
            "created_at": datetime.now(timezone.utc).isoformat(),
            "backend": "sqlite",
            "source": str(SQLITE_PATH),
        }
        _write_meta(dest, payload)
        return {"filename": dest.name, "backend": "sqlite"}

    dest = BACKUP_DIR / f"tarombo_{stamp}.sql"
    database_url = os.environ.get("DATABASE_URL", "").strip()
    if not database_url:
        raise RuntimeError("DATABASE_URL tidak diset untuk backup PostgreSQL.")
    env = os.environ.copy()
    result = subprocess.run(
        ["pg_dump", database_url, "-f", str(dest), "--no-owner", "--no-acl"],
        capture_output=True,
        text=True,
        env=env,
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or "pg_dump gagal.")
    payload = {
        "created_at": datetime.now(timezone.utc).isoformat(),
        "backend": "postgres",
    }
    _write_meta(dest, payload)
    return {"filename": dest.name, "backend": "postgres"}


def resolve_backup_path(filename: str) -> Path:
    safe = Path(filename).name
    path = BACKUP_DIR / safe
    if not path.exists() or path.parent.resolve() != BACKUP_DIR.resolve():
        raise FileNotFoundError("File backup tidak ditemukan.")
    return path


def _restore_sqlite_file(source: Path) -> None:
    """Ganti tarombo.db setelah semua koneksi ditutup (Windows perlu unlink dulu)."""
    if not _use_sqlite() and os.environ.get("DATABASE_URL", "").strip():
        raise RuntimeError(
            "Restore file .db hanya untuk mode SQLite lokal. Set USE_SQLITE=1 di .env."
        )
    import time

    sidecars = (
        SQLITE_PATH,
        Path(str(SQLITE_PATH) + "-wal"),
        Path(str(SQLITE_PATH) + "-shm"),
        SQLITE_PATH.with_suffix(".db-wal"),
        SQLITE_PATH.with_suffix(".db-shm"),
    )
    for attempt in range(8):
        try:
            for old in sidecars:
                if old.exists():
                    old.unlink()
            shutil.copy2(source, SQLITE_PATH)
            for extra in sidecars[1:]:
                if extra.exists() and extra != SQLITE_PATH:
                    try:
                        extra.unlink()
                    except OSError:
                        pass
            return
        except PermissionError as exc:
            close_errors.append(str(exc))
            time.sleep(0.2 * (attempt + 1))
        except OSError as exc:
            if SQLITE_PATH.exists() and attempt < 7:
                time.sleep(0.2 * (attempt + 1))
                continue
            raise RuntimeError(
                "Gagal menimpa database SQLite. Tutup aplikasi lain yang memakai tarombo.db, "
                "lalu coba restore lagi."
            ) from exc
    raise RuntimeError(
        "Database masih terkunci (file sedang dipakai). Refresh halaman, tunggu sebentar, "
        "lalu ulangi restore — atau restart server Flask lalu restore."
    )


def restore_backup(filename: str, *, close_db_fn) -> dict:
    path = resolve_backup_path(filename)
    close_db_fn()
    if path.suffix == ".db":
        _restore_sqlite_file(path)
        return {"backend": "sqlite", "filename": path.name}
    database_url = os.environ.get("DATABASE_URL", "").strip()
    if not database_url:
        raise RuntimeError("DATABASE_URL tidak diset untuk restore PostgreSQL.")
    result = subprocess.run(
        ["psql", database_url, "-f", str(path)],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or "psql restore gagal.")
    return {"backend": "postgres", "filename": path.name}


def restore_uploaded_file(upload_path: Path, original_name: str, *, close_db_fn) -> dict:
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    ext = Path(original_name).suffix.lower()
    if ext not in {".db", ".sql"}:
        raise ValueError("Hanya file .db (SQLite) atau .sql (PostgreSQL) yang didukung.")
    stamp = _utc_stamp()
    stored = BACKUP_DIR / f"upload_{stamp}{ext}"
    shutil.copy2(upload_path, stored)
    return restore_backup(stored.name, close_db_fn=close_db_fn)


def wipe_all_data(db):
    """Hapus semua data aplikasi (bukan struktur tabel)."""
    if db.backend == "postgres":
        db.execute(
            """
            TRUNCATE TABLE
              audit_logs,
              relationships,
              submitted_children,
              people,
              form_field_configs,
              sundut_entries,
              users
            RESTART IDENTITY CASCADE
            """
        )
    else:
        for table in (
            "audit_logs",
            "relationships",
            "submitted_children",
            "people",
            "form_field_configs",
            "sundut_entries",
            "users",
        ):
            db.execute(f"DELETE FROM {table}")
    db.commit()


def reset_database_file_sqlite():
    if SQLITE_PATH.exists():
        SQLITE_PATH.unlink()


def reset_database_sqlite(*, close_db_fn, init_fn, seed_admin_fn):
    close_db_fn()
    reset_database_file_sqlite()
    db = init_fn(force=True)
    seed_admin_fn(db)
    return {"backend": "sqlite"}


def reset_database_postgres(db, seed_admin_fn):
    wipe_all_data(db)
    seed_admin_fn(db)
    return {"backend": "postgres"}
