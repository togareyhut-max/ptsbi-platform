# Baseline — titik awal (live ptsbi.org)

**Status: TERVERIFIKASI** — website live, branch `production-live`, dan `main` selaras.

| Sumber | Versi | Ref |
|--------|-------|-----|
| **ptsbi.org (live)** | **3.1.1** | `frontend.css?ver=3.1.1` |
| **Branch `main`** | **3.1.1** | Commit baseline di bawah |
| **Branch `production-live`** | **3.1.1** | = `main` |
| **Tag `baseline-v3.1.1`** | **3.1.1** | Penanda titik awal |

## Verifikasi terakhir

Jalankan: `bash wordpress/verify-baseline.sh`

```
Repo versi   : 3.1.1
Live versi   : 3.1.1
Repo CSS hash = Live CSS hash  ✓
```

## Aturan setelah baseline

1. **Acuan rollback:** `git checkout baseline-v3.1.1` atau branch `production-live`
2. **Sync live → repo:** Actions → Sync Production Baseline
3. **Deploy repo → live:** push ke `main` (folder plugin) atau Actions → Deploy → `DEPLOY`
4. **Eksperimen:** branch terpisah; jangan biarkan branch sementara menumpuk di GitHub

## Folder acuan

| Path | Isi |
|------|-----|
| `wordpress/ptsbi-premium/` | Plugin WordPress (Premium Organization) |
| `wordpress/PRODUCTION-BASELINE.md` | Sync & rollback |
| `deploy/tarombo-app/` | Paket Tarombo production |

---

Semua pekerjaan baru dimulai **dari titik ini**.
