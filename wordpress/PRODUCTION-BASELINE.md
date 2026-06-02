# Production baseline

Lihat **[BASELINE.md](../BASELINE.md)** di root repo.

| Ref | Fungsi |
|-----|--------|
| `baseline-v3.1.1` | Tag penanda baseline (= live Jun 2026) |
| `production-live` | Branch snapshot plugin website live |
| `main` | Repo utama — harus sama dengan live |

## Sync live → repo

```bash
bash wordpress/pull-plugin-ptsbi-from-production.sh   # butuh SSH
# atau: GitHub Actions → Sync Production Baseline
```

## Rollback repo

```bash
bash wordpress/rollback-plugin-to-production-baseline.sh
git add wordpress/ptsbi-premium && git commit -m "revert: ke production-live"
git push origin main
```

## Cek keselarasan

```bash
bash wordpress/verify-baseline.sh
```
