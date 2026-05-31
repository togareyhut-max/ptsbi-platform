#!/usr/bin/env python3
"""Debug GET /tarombo (setelah login admin) → diag_hasil_out.txt"""
import os
import sys
import traceback
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "diag_hasil_out.txt"
sys.path.insert(0, str(ROOT))
os.environ["USE_SQLITE"] = "1"
os.environ.pop("DATABASE_URL", None)

lines = []


def log(msg):
    lines.append(str(msg))


try:
    from app import app, bootstrap_database

    with app.app_context():
        bootstrap_database()
    client = app.test_client()
    login = client.post(
        "/login",
        data={"email": "admin@ptsbi.org", "password": "12345678"},
        follow_redirects=False,
    )
    log(f"login_status={login.status_code}")
    r = client.get("/tarombo")
    log(f"/tarombo status={r.status_code}")
    if r.status_code != 200:
        log(r.data.decode("utf-8", errors="replace")[:8000])
except Exception:
    log("EXCEPTION")
    log(traceback.format_exc())

OUT.write_text("\n".join(lines), encoding="utf-8")
