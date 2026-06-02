# Production baseline (plugin WordPress)

Lihat **`BASELINE.md`** di root repo untuk status verifikasi dan titik awal.

## Branch & tag

| Ref | Fungsi |
|-----|--------|
| `production-live` | Snapshot plugin dari website live |
| `baseline-v3.1.1` | Tag penanda baseline Jun 2026 |
| `main` | Repo utama — plugin harus sama dengan `production-live` |

## Sync live → repo

**GitHub → Actions → Sync Production Baseline → Run workflow**

Atau manual (butuh SSH):

```bash
export SSH_PRIVATE_KEY='...'
bash wordpress/pull-plugin-ptsbi-from-production.sh
git checkout -B production-live
git add wordpress/ptsbi-premium
git commit -m "chore(production-live): snapshot dari server"
git push origin refs/heads/production-live --force
```

## Rollback repo

```bash
bash wordpress/rollback-plugin-to-production-baseline.sh
git add wordpress/ptsbi-premium
git commit -m "revert(plugin): kembalikan ke production-live"
git push origin main
```

## Deploy ke server

Hanya manual: **Actions → Deploy ptsbi-premium → ketik `DEPLOY`**.

Tidak ada deploy otomatis saat push.
