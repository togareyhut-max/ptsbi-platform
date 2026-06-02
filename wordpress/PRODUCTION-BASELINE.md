# Baseline production (website live)

Repo **ptsbi-platform** memisahkan:

| Sumber | Arti |
|--------|------|
| **Website live** (ptsbi.org) | Keadaan sebenarnya di server — acuan rollback |
| **Branch `production-live`** | Snapshot plugin yang diambil dari server |
| **Branch `main`** | Pengembangan; deploy ke server **hanya manual** |

## Alur yang disarankan

### 1. Sinkronkan repo dari website live (wajib setelah masalah / sebelum eksperimen)

**GitHub → Actions → Sync Production Baseline → Run workflow**

Ini akan:

- Menarik `ptsbi-premium` dari container `wordpress-ptsbi` di server
- Memperbarui branch **`production-live`**
- Memperbarui tag **`production-live`**
- (Opsional) Memperbarui **`main`** agar sama dengan live

### 2. Deploy perubahan ke server (hanya jika sengaja)

**GitHub → Actions → Deploy ptsbi-premium → Run workflow**

Ketik `DEPLOY` pada konfirmasi.

Deploy **tidak** jalan otomatis saat push ke `main`.

### 3. Rollback repo ke kondisi live

```bash
bash wordpress/rollback-plugin-to-production-baseline.sh
git add wordpress/ptsbi-premium
git commit -m "revert(plugin): kembalikan ke baseline production-live"
git push origin main
```

Lalu jalankan workflow **Deploy** jika ingin server ikut kembali ke baseline.

### 4. Tarik live ke laptop (butuh SSH)

```bash
export SSH_PRIVATE_KEY='...'   # kunci deploy
bash wordpress/pull-plugin-ptsbi-from-production.sh
```

## Agent / sesi baru

Untuk agent baru dengan kondisi sama seperti website:

1. Checkout branch `production-live`, atau
2. Jalankan workflow **Sync Production Baseline** lalu kerja dari `main` yang sudah disamakan.

Rollback gagal = kembalikan `wordpress/ptsbi-premium` dari `production-live`, bukan dari commit eksperimen lama.

## Catatan

- Baseline ini hanya **plugin** `wordpress/ptsbi-premium`, bukan database WordPress.
- Backup database tetap lewat hosting / plugin backup WordPress.
