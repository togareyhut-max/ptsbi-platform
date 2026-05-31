import os
import sys
import traceback
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
os.environ["USE_SQLITE"] = "1"
os.environ.pop("DATABASE_URL", None)

out = ROOT / "test_hasil_result.txt"
lines = []

try:
    from app import app, bootstrap_database

    with app.app_context():
        bootstrap_database()
    client = app.test_client()
    paths_anon = [("/", 200), ("/hasil", 302), ("/tarombo", 302), ("/api/tarombo-tree", 302)]
    for path, _ in paths_anon:
        try:
            r = client.get(path)
            lines.append(f"[anon] {path} -> {r.status_code}")
            if path == "/" and r.status_code != 200:
                lines.append(r.data.decode("utf-8", errors="replace")[:2000])
        except Exception:
            lines.append(f"[anon] {path} -> EXCEPTION")
            lines.append(traceback.format_exc())
    client.post(
        "/login",
        data={"email": "admin@ptsbi.org", "password": "12345678"},
        follow_redirects=False,
    )
    for path in ["/tarombo", "/api/tarombo-tree", "/hasil"]:
        try:
            r = client.get(path)
            lines.append(f"[admin] {path} -> {r.status_code}")
            if r.status_code >= 400:
                lines.append(r.data.decode("utf-8", errors="replace")[:2000])
        except Exception:
            lines.append(f"[admin] {path} -> EXCEPTION")
            lines.append(traceback.format_exc())
except Exception:
    lines.append("FATAL")
    lines.append(traceback.format_exc())

out.write_text("\n".join(lines), encoding="utf-8")
print("written", out)
