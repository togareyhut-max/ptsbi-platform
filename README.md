# PTSBI Platform — Tarombo + WordPress

Monorepo untuk **ptsbi.org** dan **tarombo.ptsbi.org**.

| Folder | Isi |
|--------|-----|
| `/` (root) | Aplikasi Tarombo (Flask): `app.py`, `services/`, `templates/` |
| `wordpress/ptsbi-premium/` | Plugin WordPress resmi (Premium Organization) |
| `deploy/tarombo-app/` | Paket production Tarombo (hasil `PACK-UBUNTU.bat`) |
| `deploy-ubuntu/` | Script backup/restore server |

Arsip ZIP, draft lama, dan situs WordPress lain **tidak** di-commit (lihat `.gitignore`).

---

# Tarombo — Development (Lokal Windows)

Aplikasi silsilah marga Batak Samosir (PTSBI): pendaftaran, admin, pohon `/tarombo` (member aktif), panggoaran Opung, sundut.

## Jalankan lokal

1. **`JALANKAN.bat`** — setup + server (paling mudah)
2. Atau: **`SETUP_LOKAL.bat`** lalu **`START_APP.bat`**

Gunakan **`.venv\Scripts\python.exe`**, jangan `python` sistem.

http://127.0.0.1:5000

## Paket server Ubuntu 24 (production)

Jangan upload folder dev ini langsung ke server.

Jalankan **`PACK-UBUNTU.bat`** → menghasilkan:

```text
deploy/tarombo-app/
deploy/tarombo-app.zip
```

Upload zip ke server. Production vm21197 (Traefik): `unzip -o tarombo-app.zip -d /home/togaa` → `cd /home/togaa/tarombo-app` → `docker compose -f docker-compose.server.yml up -d --build`. (Alternatif standalone: unzip ke `/opt`; lihat `DEPLOY-UBUNTU.md`.)

| Isi paket | Keterangan |
|-----------|------------|
| PostgreSQL 16 | Via Docker Compose |
| Gunicorn | Production WSGI |
| `data/uploads/` | Logo PNG kustom (volume persisten) |
| Schema + app lengkap | Semua `services/`, template, JS pohon |

## Admin — logo situs

**Admin → Logo Situs** (`/admin/logo`): unggah PNG (maks. 2 MB). Tampil di header seluruh halaman.

## Akun dev (password `12345678`)

- Admin: `admin@ptsbi.org`
- Pengurus: `pengurus@ptsbi.org`
- Anggota: `anggota@ptsbi.org`
- Developer: `developer@ptsbi.org`

## Folder dev vs deploy

| Folder | Pemakaian |
|--------|-----------|
| `empty-window` (ini) | Dev lokal SQLite |
| `deploy/tarombo-app` | Production Ubuntu + PostgreSQL |
