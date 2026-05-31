#!/usr/bin/env python3
"""Cek dependensi dan impor aplikasi."""

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

errors = []

for pkg in ("flask", "werkzeug", "dotenv"):
    try:
        __import__(pkg)
        print(f"OK  {pkg}")
    except ImportError:
        errors.append(pkg)
        print(f"MISSING  {pkg}")

try:
    import psycopg  # noqa: F401

    print("OK  psycopg (PostgreSQL)")
except ImportError:
    print("SKIP psycopg (gunakan USE_SQLITE=1 jika tanpa Postgres)")

try:
    from app import app

    client = app.test_client()
    anonymous_expect = {
        "/tarombo": 302,
        "/api/tarombo-tree": 302,
        "/hasil": 302,
    }
    for path in ("/", "/register", "/login"):
        code = client.get(path).status_code
        print(f"OK  GET {path} -> {code}")
    for path, want in anonymous_expect.items():
        code = client.get(path).status_code
        label = "OK " if code == want else "NOTE "
        print(f"{label} GET {path} -> {code} (ekspektasi tanpa login: {want})")
except Exception as exc:
    errors.append(f"app: {exc}")
    print(f"FAIL app: {exc}")

if errors:
    print("\nPerbaiki dengan: pip install -r requirements.txt")
    sys.exit(1)

print("\nSemua pemeriksaan dasar lulus.")
