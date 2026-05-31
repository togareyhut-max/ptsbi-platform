#!/usr/bin/env python3
"""Setup SQLite, bebaskan port 5000, jalankan Flask (lokal Windows)."""
from __future__ import annotations

import os
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PY = ROOT / ".venv" / "Scripts" / "python.exe"
PORT = int(os.environ.get("PORT", "5000"))


def run(cmd, **kwargs):
    print(">", " ".join(str(c) for c in cmd))
    return subprocess.run(cmd, cwd=ROOT, **kwargs)


def kill_port(port: int):
    if os.name != "nt":
        return
    try:
        out = subprocess.check_output(
            f'netstat -ano | findstr ":{port}"',
            shell=True,
            text=True,
            errors="ignore",
        )
    except subprocess.CalledProcessError:
        return
    pids = set()
    for line in out.splitlines():
        if "LISTENING" not in line.upper():
            continue
        parts = line.split()
        if parts:
            pids.add(parts[-1])
    for pid in pids:
        if pid.isdigit() and pid != "0":
            subprocess.run(
                ["taskkill", "/F", "/PID", pid],
                cwd=ROOT,
                capture_output=True,
            )


def main():
    if not PY.exists():
        print("Membuat venv...")
        run([sys.executable, "-m", "venv", str(ROOT / ".venv")], check=True)

    run([str(PY), "-m", "pip", "install", "-q", "-r", "requirements-sqlite.txt"], check=True)

    env = os.environ.copy()
    env["USE_SQLITE"] = "1"
    env.pop("DATABASE_URL", None)
    env["FLASK_DEBUG"] = "1"
    env["PORT"] = str(PORT)

    kill_port(PORT)
    run([str(PY), str(ROOT / "scripts" / "setup_local_db.py")], env=env, check=True)

    print(f"\nServer: http://127.0.0.1:{PORT}/  (login → /tarombo untuk member aktif atau admin)\n")
    os.chdir(ROOT)
    os.execve(
        str(PY),
        [str(PY), str(ROOT / "app.py")],
        env,
    )


if __name__ == "__main__":
    main()
