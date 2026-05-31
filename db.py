"""Database layer — PostgreSQL (production) with optional SQLite (local fallback)."""

from __future__ import annotations

import os
import sqlite3
from contextlib import contextmanager
from pathlib import Path
from typing import Any

BASE_DIR = Path(__file__).resolve().parent
SQLITE_PATH = BASE_DIR / "tarombo.db"
SQLITE_SCHEMA_PATH = BASE_DIR / "schema_sqlite.sql"
POSTGRES_SCHEMA_PATH = BASE_DIR / "schema.sql"

def _use_sqlite() -> bool:
    return os.environ.get("USE_SQLITE", "").lower() in ("1", "true", "yes")


def _database_url() -> str:
    return os.environ.get("DATABASE_URL", "").strip()


def _as_dict(row):
    if row is None:
        return None
    if isinstance(row, dict):
        return row
    return dict(row)


class DbConnection:
    """Thin wrapper so app code can use execute/fetchone/commit similarly."""

    def __init__(self, conn, backend: str):
        self._conn = conn
        self.backend = backend

    def execute(self, sql: str, params=None):
        params = params or ()
        if self.backend == "postgres":
            sql = sql.replace("?", "%s")
        cur = self._conn.execute(sql, params)
        return cur

    def fetchone(self, sql: str, params=None):
        cur = self.execute(sql, params)
        return _as_dict(cur.fetchone())

    def fetchall(self, sql: str, params=None):
        cur = self.execute(sql, params)
        return [_as_dict(row) for row in cur.fetchall()]

    def commit(self):
        self._conn.commit()

    def rollback(self):
        self._conn.rollback()

    @property
    def lastrowid(self):
        # Prefer RETURNING id in inserts; this is for legacy paths.
        if self.backend == "sqlite":
            return self._conn.execute("SELECT last_insert_rowid()").fetchone()[0]
        raise RuntimeError("Use RETURNING id for PostgreSQL inserts")


def _connect_postgres():
    try:
        import psycopg
        from psycopg.rows import dict_row
    except ImportError as exc:
        raise RuntimeError(
            "Paket psycopg belum terpasang. Jalankan: pip install -r requirements.txt "
            "atau untuk dev lokal: set USE_SQLITE=1 dan pip install -r requirements-sqlite.txt"
        ) from exc

    conn = psycopg.connect(_database_url(), row_factory=dict_row)
    return DbConnection(conn, "postgres")


def _connect_sqlite():
    conn = sqlite3.connect(SQLITE_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return DbConnection(conn, "sqlite")


def connect() -> DbConnection:
    """Lokal: SQLite jika USE_SQLITE=1 atau DATABASE_URL kosong."""
    if _use_sqlite() or not _database_url():
        return _connect_sqlite()
    return _connect_postgres()


def reset_sqlite_database_file():
    """Hapus DB SQLite rusak (dev lokal saja)."""
    if SQLITE_PATH.exists():
        SQLITE_PATH.unlink()


def sqlite_schema_ok(db: DbConnection) -> bool:
    if db.backend != "sqlite":
        return True
    try:
        row = db.fetchone(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='people'"
        )
        if not row:
            return False
        cols = {r[1] for r in db._conn.execute("PRAGMA table_info(people)").fetchall()}
        required = {"panggoaran", "name_normalized", "panggoaran_type", "approved"}
        return required.issubset(cols)
    except Exception:
        return False


def insert_returning_id(db: DbConnection, sql: str, params) -> int:
    """INSERT ... RETURNING id (Postgres) or lastrowid (SQLite)."""
    if db.backend == "postgres":
        if "RETURNING" not in sql.upper():
            sql = sql.rstrip().rstrip(";") + " RETURNING id"
        row = db.fetchone(sql, params)
        return int(row["id"])
    cur = db.execute(sql, params)
    return int(cur.lastrowid)


def schema_path_for(db: DbConnection) -> Path:
    return SQLITE_SCHEMA_PATH if db.backend == "sqlite" else POSTGRES_SCHEMA_PATH


def sql_approved(db: DbConnection, alias: str = "") -> str:
    prefix = f"{alias}." if alias else ""
    return f"{prefix}approved IS TRUE" if db.backend == "postgres" else f"{prefix}approved = 1"


def sql_not_approved(db: DbConnection, alias: str = "") -> str:
    prefix = f"{alias}." if alias else ""
    return f"{prefix}approved IS NOT TRUE" if db.backend == "postgres" else f"{prefix}approved = 0"


def coerce_submitted_by(db: DbConnection, user_id: int | None) -> int | None:
    """
    people.submitted_by → users.id (FK).
    Setelah migrasi/seed, id lama (mis. 2) bisa tidak ada — kembalikan None, bukan id invalid.
    """
    if user_id is None:
        return None
    try:
        uid = int(user_id)
    except (TypeError, ValueError):
        return None
    row = db.fetchone("SELECT id FROM users WHERE id = ?", (uid,))
    return int(row["id"]) if row else None


def migrate_consolidate_spouse_fields(db: DbConnection):
    """Salin data istri lama ke pasangan; sembunyikan field istri di form."""
    try:
        db.execute(
            """
            UPDATE people
            SET spouse_name = wife_name
            WHERE (spouse_name IS NULL OR TRIM(spouse_name) = '')
              AND wife_name IS NOT NULL AND TRIM(wife_name) != ''
            """
        )
        db.execute(
            """
            UPDATE people
            SET spouse_marga = wife_marga
            WHERE (spouse_marga IS NULL OR TRIM(spouse_marga) = '')
              AND wife_marga IS NOT NULL AND TRIM(wife_marga) != ''
            """
        )
    except Exception:
        pass
    vis_off = "is_visible = FALSE" if db.backend == "postgres" else "is_visible = 0"
    try:
        db.execute(
            f"""
            UPDATE form_field_configs
            SET {vis_off}
            WHERE field_key IN ('wife_name', 'wife_marga')
            """
        )
    except Exception:
        pass


def init_schema(db: DbConnection):
    sql = schema_path_for(db).read_text(encoding="utf-8")
    if db.backend == "postgres":
        statements = [s.strip() for s in sql.split(";") if s.strip()]
        with db._conn.cursor() as cur:
            for stmt in statements:
                cur.execute(stmt)
        db.commit()
    else:
        db._conn.executescript(sql)
        db.commit()


def migrate_user_roles(db: DbConnection):
    """Izinkan peran pengurus & developer (selain member & admin)."""
    if db.backend == "postgres":
        for stmt in (
            "ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check",
            """
            ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('member', 'pengurus', 'admin', 'developer'))
            """,
        ):
            try:
                db.execute(stmt)
            except Exception:
                pass
        return

    row = db.fetchone("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")
    if not row or not row.get("sql") or "pengurus" in row["sql"]:
        return
    db._conn.executescript(
        """
        PRAGMA foreign_keys=off;
        CREATE TABLE users_new (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          full_name TEXT NOT NULL,
          email TEXT NOT NULL UNIQUE,
          password_hash TEXT NOT NULL,
          role TEXT NOT NULL CHECK(role IN ('member','pengurus','admin','developer')),
          member_status TEXT NOT NULL DEFAULT 'pending'
            CHECK(member_status IN ('pending','active','rejected')),
          created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        INSERT INTO users_new (id, full_name, email, password_hash, role, member_status, created_at)
        SELECT id, full_name, email, password_hash, role, member_status, created_at FROM users;
        DROP TABLE users;
        ALTER TABLE users_new RENAME TO users;
        PRAGMA foreign_keys=on;
        """
    )


def migrate_schema(db: DbConnection):
    """Add columns introduced after initial deploy."""
    alters = [
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS name_normalized TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS father_name_normalized TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS birth_date DATE",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS panggoaran TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS panggoaran_type TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS opung_source TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS last_synced_at TIMESTAMPTZ",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS sundut INTEGER",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS sundut_locked BOOLEAN NOT NULL DEFAULT FALSE",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS child_order INTEGER",
        "ALTER TABLE submitted_children ADD COLUMN IF NOT EXISTS child_order INTEGER",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS mother_name_normalized TEXT",
    ]
    sqlite_alters = [
        ("name_normalized", "TEXT"),
        ("father_name_normalized", "TEXT"),
        ("mother_name_normalized", "TEXT"),
        ("birth_date", "TEXT"),
        ("panggoaran", "TEXT"),
        ("panggoaran_type", "TEXT"),
        ("opung_source", "TEXT"),
        ("last_synced_at", "TEXT"),
        ("sundut", "INTEGER"),
        ("sundut_locked", "INTEGER NOT NULL DEFAULT 0"),
        ("child_order", "INTEGER"),
    ]

    if db.backend == "postgres":
        for stmt in alters:
            try:
                db.execute(stmt.replace(" IF NOT EXISTS", ""))
            except Exception:
                try:
                    db.execute(stmt)
                except Exception:
                    pass
        try:
            db.execute(
                """
                CREATE TABLE IF NOT EXISTS form_field_configs (
                  id SERIAL PRIMARY KEY,
                  section TEXT NOT NULL,
                  field_key TEXT NOT NULL UNIQUE,
                  label TEXT NOT NULL,
                  field_type TEXT NOT NULL DEFAULT 'text',
                  placeholder TEXT,
                  is_required BOOLEAN NOT NULL DEFAULT TRUE,
                  is_visible BOOLEAN NOT NULL DEFAULT TRUE,
                  display_order INTEGER NOT NULL DEFAULT 0,
                  options_json TEXT,
                  help_text TEXT,
                  default_value TEXT,
                  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
                """
            )
        except Exception:
            pass
        try:
            db.execute(
                """
                CREATE TABLE IF NOT EXISTS sundut_entries (
                  id SERIAL PRIMARY KEY,
                  sundut_number INTEGER NOT NULL UNIQUE,
                  title TEXT NOT NULL,
                  description TEXT,
                  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
                """
            )
        except Exception:
            pass
        try:
            db.execute(
                """
                CREATE TABLE IF NOT EXISTS app_settings (
                  key TEXT PRIMARY KEY,
                  value TEXT,
                  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
                """
            )
        except Exception:
            pass
        migrate_consolidate_spouse_fields(db)
        migrate_user_roles(db)
        db.commit()
    else:
        people_exists = db.fetchone(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='people'"
        )
        if not people_exists:
            db.commit()
            return

        db._conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS form_field_configs (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              section TEXT NOT NULL,
              field_key TEXT NOT NULL UNIQUE,
              label TEXT NOT NULL,
              field_type TEXT NOT NULL DEFAULT 'text',
              placeholder TEXT,
              is_required INTEGER NOT NULL DEFAULT 1,
              is_visible INTEGER NOT NULL DEFAULT 1,
              display_order INTEGER NOT NULL DEFAULT 0,
              options_json TEXT,
              help_text TEXT,
              default_value TEXT,
              updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            """
        )
        cols = {row[1] for row in db._conn.execute("PRAGMA table_info(people)").fetchall()}
        for name, typ in sqlite_alters:
            if name not in cols:
                db.execute(f"ALTER TABLE people ADD COLUMN {name} {typ}")
        sc_cols = {row[1] for row in db._conn.execute("PRAGMA table_info(submitted_children)").fetchall()}
        if "child_order" not in sc_cols:
            db.execute("ALTER TABLE submitted_children ADD COLUMN child_order INTEGER")
        db._conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS sundut_entries (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              sundut_number INTEGER NOT NULL UNIQUE,
              title TEXT NOT NULL,
              description TEXT,
              created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS app_settings (
              key TEXT PRIMARY KEY,
              value TEXT,
              updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            """
        )
        migrate_consolidate_spouse_fields(db)
        migrate_user_roles(db)
        db.commit()
