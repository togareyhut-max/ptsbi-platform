# Baseline — titik awal (Jun 2026)

**Status: TERVERIFIKASI** — website live, production server, dan repo GitHub selaras.

| Sumber | Versi plugin | Commit / ref |
|--------|--------------|--------------|
| **ptsbi.org (live)** | **3.1.1** | CSS `frontend.css?ver=3.1.1` |
| **Branch `production-live`** | **3.1.1** | Snapshot plugin = live |
| **Branch `main`** | **3.1.1** | Plugin sama dengan `production-live` |
| **Tag `baseline-v3.1.1`** | **3.1.1** | Penanda titik awal ini |

## Verifikasi (2026-06-02)

```
Repo  vs production-live : wordpress/ptsbi-premium/ identik (0 diff)
Repo  vs live            : SHA256 frontend.css cocok
Live  versi              : 3.1.1
```

## Folder acuan

| Path | Isi |
|------|-----|
| `wordpress/ptsbi-premium/` | Plugin Premium Organization — **satu-satunya** sumber plugin |
| `wordpress/PRODUCTION-BASELINE.md` | Cara sync & rollback |
| `deploy/tarombo-app/` | Paket production Tarombo |
| `/` (root) | Aplikasi Tarombo (Flask) |

## Aturan kerja setelah baseline

1. **Baseline asli:** tag `baseline-v3.1.1` — plugin sebelum perbaikan save pengurus.
2. **Perbaikan save pengurus:** v3.1.2 (render server-side, tolak JSON kosong).
3. **Deploy ke ptsbi.org** — GitHub Actions → Deploy ptsbi-premium → ketik `DEPLOY`.

## Yang dibuang saat baseline

- Branch `cursor/*` (eksperimen agent)
- Dokumen migrasi lama (aagit, migrasi malam)
- Skrip inventaris / agent sekali pakai
- Deploy otomatis saat push ke `main`

---

Semua pekerjaan baru dimulai **dari titik ini**.
