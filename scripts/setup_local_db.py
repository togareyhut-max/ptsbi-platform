#!/usr/bin/env python3
"""Setup database lokal SQLite + verifikasi halaman utama."""

from __future__ import annotations

import os
import sys
import traceback
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

os.environ.setdefault("USE_SQLITE", "1")
os.environ.pop("DATABASE_URL", None)


def main():
    print("=== Tarombo PTSBI — setup lokal (SQLite) ===\n")

    try:
        from app import app, bootstrap_database
        from db import SQLITE_PATH, connect
    except Exception:
        print("ERROR: tidak bisa import app. Pastikan dependensi terpasang:")
        print("  .venv\\Scripts\\python.exe -m pip install -r requirements-sqlite.txt")
        traceback.print_exc()
        return 1

    if SQLITE_PATH.exists():
        print(f"Database ada: {SQLITE_PATH}")
    else:
        print(f"Membuat database baru: {SQLITE_PATH}")

    try:
        with app.app_context():
            bootstrap_database()
            db = connect()
            try:
                users = int(db.fetchone("SELECT COUNT(*) AS c FROM users")["c"])
                people = int(db.fetchone("SELECT COUNT(*) AS c FROM people WHERE approved = 1")["c"])
                print(f"Users: {users}, Orang approved: {people}")
            finally:
                db._conn.close()
    except Exception:
        print("\nERROR saat setup database:")
        traceback.print_exc()
        return 1

    client = app.test_client()

    # Tanpa login: pohon (/tarombo) & API mengarahkan ke login (302).
    expectations = {
        "/": [200],
        "/register": [200],
        "/login": [200],
        "/tarombo": [302],
        "/tarombo/layar-penuh": [302],
        "/hasil": [302],
        "/hasil/layar-penuh": [302],
        "/api/tarombo-tree": [302],
    }
    print("\nTes halaman (belum login):")
    failed = False
    for path, ok_codes in expectations.items():
        try:
            response = client.get(path)
            code = response.status_code
        except Exception:
            print(f"  GAGAL  {path} -> EXCEPTION")
            traceback.print_exc()
            failed = True
            continue
        ok = code in ok_codes
        status = "OK" if ok else "GAGAL"
        print(f"  {status}  {path} -> {code} (harap {ok_codes})")
        if not ok:
            failed = True
            body = response.data.decode("utf-8", errors="replace")
            if body:
                print(body[:1500])

    print("\nTes halaman (sesudah login admin):")
    try:
        lr = client.post(
            "/login",
            data={"email": "admin@ptsbi.org", "password": "12345678"},
            follow_redirects=False,
        )
        if lr.status_code not in (302, 303):
            print(f"  GAGAL  POST /login -> {lr.status_code}")
            failed = True
        else:
            auth_checks = [
                ("/hasil", [302]),
                ("/hasil/layar-penuh", [302]),
                ("/tarombo", [200]),
                ("/tarombo/layar-penuh", [200]),
                ("/api/tarombo-tree", [200]),
            ]
            for path, ok_codes in auth_checks:
                response = client.get(path)
                code = response.status_code
                ok = code in ok_codes
                status = "OK" if ok else "GAGAL"
                print(f"  {status}  {path} -> {code} (harap {ok_codes})")
                if not ok:
                    failed = True
                    print(response.data.decode("utf-8", errors="replace")[:1500])
    except Exception:
        print("  GAGAL  login admin / tes terautentikasi:")
        traceback.print_exc()
        failed = True

    if failed:
        print("\nSetup database selesai tetapi ada halaman yang gagal.")
        return 1

    print("\nSetup selesai. Jalankan: START_APP.bat atau python app.py")
    print("Buka http://127.0.0.1:5000/login kemudian /tarombo (member aktif atau admin).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
