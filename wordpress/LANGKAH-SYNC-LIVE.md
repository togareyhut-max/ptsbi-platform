# Langkah sync baseline (website live → repo)

Ikuti urutan ini **sekali** agar repo = kondisi website ptsbi.org saat ini.

## 1. Sync dari server

1. Buka: https://github.com/togareyhut-max/ptsbi-platform/actions/workflows/sync-production-baseline.yml
2. Klik **Run workflow** (kanan atas).
3. Branch: **main**
4. Centang **Perbarui juga branch main** (biarkan aktif).
5. Klik hijau **Run workflow**.
6. Tunggu status **hijau** (±1–2 menit).

## 2. Cek hasil

- Tab **Actions** → run terakhir **Sync Production Baseline** → sukses.
- Repo punya branch **`production-live`** (lihat dropdown branch di GitHub).
- File `wordpress/ptsbi-premium/ptsbi-premium.php` di branch itu = versi di server.

## 3. Mulai kerja / agent baru

```bash
git fetch origin production-live
git checkout production-live
# atau tetap di main setelah sync (main sudah disamakan)
```

## 4. Deploy

- **Otomatis:** setiap push ke `main` yang mengubah folder plugin → workflow deploy jalan.
- **Manual:** https://github.com/togareyhut-max/ptsbi-platform/actions/workflows/deploy-ptsbi-premium.yml → ketik **`DEPLOY`**.

**[SELESAI Jun 2026]** Pengurus pusat live di ptsbi.org (plugin 3.1.9): `/ptsbi-pusat/`, `/pengurus-pusat/`, shortcode `[ptprm_board region="pusat"]`.

## 5. Rollback jika percobaan gagal

```bash
bash wordpress/rollback-plugin-to-production-baseline.sh
git commit -am "revert: kembalikan plugin ke production-live"
git push origin main
```

Lalu workflow **Deploy** dengan konfirmasi `DEPLOY` (hanya jika ingin server ikut baseline).

## Masalah?

| Gejala | Solusi |
|--------|--------|
| Workflow sync gagal (merah) | Pastikan secret `SSH_PRIVATE_KEY` ada di Settings → Secrets |
| Branch production-live tidak muncul | Jalankan ulang sync; lihat log step "Tarik plugin" |
| Website rusak setelah deploy | Sync lagi dari backup server, atau rollback + Deploy |
