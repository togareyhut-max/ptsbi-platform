# PTSBI Platform — Tarombo + WordPress

Monorepo untuk **ptsbi.org** dan **tarombo.ptsbi.org**.

> **Titik awal:** baca [`BASELINE.md`](BASELINE.md) — plugin **v3.1.1**, live = repo = `production-live`.

| Folder | Isi |
|--------|-----|
| `/` (root) | Aplikasi Tarombo (Flask): `app.py`, `services/`, `templates/` |
| `wordpress/ptsbi-premium/` | Plugin WordPress (Premium Organization) |
| `wordpress/PRODUCTION-BASELINE.md` | Sync & rollback plugin |
| `deploy/tarombo-app/` | Paket production Tarombo |

---

## Tarombo — development lokal

1. **`JALANKAN.bat`** — setup + server
2. Atau: **`SETUP_LOKAL.bat`** lalu **`START_APP.bat`**

http://127.0.0.1:5000

## Tarombo — production

Jalankan **`PACK-UBUNTU.bat`** → upload `deploy/tarombo-app.zip` ke server.

## WordPress plugin

- **Baseline:** v3.1.1 (`production-live` branch)
- **Deploy ke ptsbi.org:** GitHub Actions → Deploy ptsbi-premium → ketik `DEPLOY`
- **Sync dari live:** GitHub Actions → Sync Production Baseline
