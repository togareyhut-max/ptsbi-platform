#!/usr/bin/env python3
"""Bangun folder deploy/tarombo-app (+ zip) siap upload ke Ubuntu 24."""

from __future__ import annotations

import shutil
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DST = ROOT / "deploy" / "tarombo-app"
ZIP_PATH = ROOT / "deploy" / "tarombo-app.zip"
LEGACY_DST = ROOT / "deploy" / "tarombo-ptsbi-ubuntu"
DEPLOY_SRC = ROOT / "deploy-ubuntu"

COPY_ROOT_FILES = [
    "app.py",
    "db.py",
    "schema.sql",
]

COPY_DIRS = ["services", "templates", "static"]

SKIP_NAMES = {
    "__pycache__",
    ".pyc",
    "tarombo.js",
    ".cursor",
    "agent-transcripts",
    "mcps",
    "terminals",
    "backups",
}


def _ignore(_dir_path, names):
    return [n for n in names if n in SKIP_NAMES or n.endswith(".pyc")]


def copy_tree(src: Path, dest: Path):
    if dest.exists():
        shutil.rmtree(dest)
    shutil.copytree(src, dest, ignore=_ignore)


def write_mulai_disini():
    (DST / "MULAI-DISINI.txt").write_text(
        """Tarombo PTSBI — paket deploy Ubuntu 24.04
==========================================

PRODUCTION vm21197 (Traefik) — mode yang dipakai sekarang:

1. Upload folder ini (atau file tarombo-app.zip) ke server.

2. Unzip ke /home/togaa/tarombo-app:
   unzip -o tarombo-app.zip -d /home/togaa
   cd /home/togaa/tarombo-app
   cp .env.example .env
   nano .env
   # Ganti POSTGRES_PASSWORD dan SECRET_KEY (USE_SQLITE=0)

3. Pasang Docker (jika belum):
   sudo apt update
   sudo apt install -y docker.io docker-compose-plugin
   sudo usermod -aG docker $USER
   # logout/login

4. Jalankan via Traefik (tarombo.ptsbi.org):
   docker compose -f docker-compose.server.yml up -d --build
   docker compose -f docker-compose.server.yml logs -f web

5. Buka https://tarombo.ptsbi.org

(Alternatif STANDALONE tanpa Traefik: unzip ke /opt, lalu
 `docker compose up -d --build` -> http://IP:5000 + Nginx. Lihat DEPLOY-UBUNTU.md)

Di .env wajib: USE_SQLITE=0 dan DATABASE_URL PostgreSQL

Akun awal (password 12345678, ganti setelah login):
  admin@ptsbi.org / pengurus@ptsbi.org / anggota@ptsbi.org / developer@ptsbi.org

Panduan lengkap: DEPLOY-UBUNTU.md
""",
        encoding="utf-8",
    )


def make_zip():
    if ZIP_PATH.exists():
        ZIP_PATH.unlink()
    file_count = 0
    with zipfile.ZipFile(ZIP_PATH, "w", zipfile.ZIP_DEFLATED) as zf:
        for path in sorted(DST.rglob("*")):
            if path.is_file():
                arc = path.relative_to(DST.parent)
                zf.write(path, arc.as_posix())
                file_count += 1
    return file_count


def count_files() -> int:
    return sum(1 for p in DST.rglob("*") if p.is_file())


def main():
    if LEGACY_DST.exists():
        shutil.rmtree(LEGACY_DST)
    legacy_zip = ROOT / "deploy" / "tarombo-ptsbi-ubuntu.zip"
    if legacy_zip.exists():
        legacy_zip.unlink()
    if DST.exists():
        shutil.rmtree(DST)
    DST.mkdir(parents=True)

    for name in COPY_ROOT_FILES:
        shutil.copy2(ROOT / name, DST / name)

    for dirname in COPY_DIRS:
        copy_tree(ROOT / dirname, DST / dirname)

    (DST / "data" / "uploads").mkdir(parents=True, exist_ok=True)
    gitkeep = ROOT / "data" / "uploads" / ".gitkeep"
    if gitkeep.exists():
        shutil.copy2(gitkeep, DST / "data" / "uploads" / ".gitkeep")
    else:
        (DST / "data" / "uploads" / ".gitkeep").touch()

    (DST / "scripts").mkdir(exist_ok=True)
    for sh in sorted((DEPLOY_SRC / "scripts").glob("*.sh")):
        shutil.copy2(sh, DST / "scripts" / sh.name)

    (DST / "deploy").mkdir(exist_ok=True)
    shutil.copy2(
        DEPLOY_SRC / "deploy" / "nginx-tarombo.conf.example",
        DST / "deploy" / "nginx-tarombo.conf.example",
    )

    for name in (
        "Dockerfile",
        "docker-compose.yml",
        "docker-compose.server.yml",
        ".env.example",
        ".gitignore",
        "README.md",
        "DEPLOY-UBUNTU.md",
    ):
        shutil.copy2(DEPLOY_SRC / name, DST / name)

    shutil.copy2(DEPLOY_SRC / "requirements.txt", DST / "requirements.txt")
    write_mulai_disini()

    n = count_files()
    zip_n = make_zip()

    print("")
    print("=" * 60)
    print("  PAKET UBUNTU 24 SIAP UPLOAD")
    print("=" * 60)
    print(f"  Folder : {DST}")
    print(f"  Zip    : {ZIP_PATH}")
    print(f"  Berkas : {n} file di folder, {zip_n} di zip")
    print("")
    print("  Upload zip ke server (production vm21197 / Traefik), lalu:")
    print("    unzip -o tarombo-app.zip -d /home/togaa")
    print("    cd /home/togaa/tarombo-app")
    print("    cp .env.example .env && nano .env")
    print("    docker compose -f docker-compose.server.yml up -d --build")
    print("=" * 60)


if __name__ == "__main__":
    main()
