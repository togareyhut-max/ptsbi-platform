# Baseline production (website live)

Repo **ptsbi-platform** memisahkan:

| Sumber | Arti |
|--------|------|
| **Website live** (ptsbi.org) | Keadaan sebenarnya di server — acuan rollback |
| **Branch `production-live`** | Snapshot plugin yang diambil dari server |
| **Branch `main`** | Disamakan dengan `production-live` saat sync; jangan deploy eksperimen tanpa perlu |

## Kondisi saat ini (Jun 2026)

| Item | Nilai |
|------|-------|
| Plugin production | **3.1.1** |
| Branch acuan | **`production-live`** |
| Folder repo | `wordpress/ptsbi-premium/` (= isi live) |

## Alur sync (website live → repo)

### Otomatis (GitHub Actions)

1. **Actions → Sync Production Baseline → Run workflow**
2. Centang **Perbarui juga branch main**
3. Workflow akan: tarik plugin dari volume Docker server → kosongkan & timpa `wordpress/ptsbi-premium` → push branch **`production-live`** + tag

### Manual (SSH)

```bash
export SSH_PRIVATE_KEY='...'
bash wordpress/pull-plugin-ptsbi-from-production.sh
git checkout -B production-live
git add wordpress/ptsbi-premium
git commit -m "chore(production-live): snapshot plugin dari server"
git push origin production-live --force
```

## Rollback repo ke kondisi live

```bash
bash wordpress/rollback-plugin-to-production-baseline.sh
git add wordpress/ptsbi-premium
git commit -m "revert(plugin): kembalikan ke baseline production-live"
git push origin main
```

Deploy ke server **hanya** jika ingin mengirim ulang baseline (workflow Deploy ptsbi-premium).

## Catatan

- Baseline ini hanya **plugin** `wordpress/ptsbi-premium`, bukan database WordPress.
- Skrip pull mengosongkan folder lokal (`rsync --delete`) sebelum menulis isi live.
