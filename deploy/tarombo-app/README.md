# Tarombo PTSBI — Paket Production (Ubuntu 24)

Folder ini adalah **salinan siap deploy** dari aplikasi Tarombo PTSBI.

## Mulai cepat

```bash
cp .env.example .env
nano .env
docker compose up -d --build
```

Panduan lengkap: **DEPLOY-UBUNTU.md**

## Struktur

- `app.py`, `db.py`, `schema.sql` — aplikasi & database
- `services/`, `templates/`, `static/` — kode & aset
- `data/uploads/` — logo PNG kustom (volume persisten)
- `scripts/docker-entrypoint.sh` — init DB + Gunicorn

## Pengembangan lokal

Gunakan folder induk `empty-window` dengan SQLite (`USE_SQLITE=1`), bukan folder ini.
