#!/usr/bin/env python3
"""Salin file RBAC ke deploy/tarombo-app."""
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]
DEPLOY = ROOT / "deploy" / "tarombo-app"

pairs = [
    (ROOT / "app.py", DEPLOY / "app.py"),
    (ROOT / "db.py", DEPLOY / "db.py"),
    (ROOT / "schema.sql", DEPLOY / "schema.sql"),
    (ROOT / "schema_sqlite.sql", DEPLOY / "schema_sqlite.sql"),
    (ROOT / "services" / "auth_roles.py", DEPLOY / "services" / "auth_roles.py"),
]
for src, dst in pairs:
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(src, dst)
    print(f"copied {src.name} -> {dst}")

for name in ("base.html", "member_dashboard.html", "admin.html"):
    src = ROOT / "templates" / name
    dst = DEPLOY / "templates" / name
    shutil.copy2(src, dst)
    print(f"copied {name}")

print("done")
