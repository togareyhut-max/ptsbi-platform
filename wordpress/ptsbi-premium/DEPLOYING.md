# Deploying `ptsbi-premium` (local ZIP + deploy notes)

Dokumen ini membuat proses deploy **rapi, bisa diulang, dan selalu bisa balik ke baseline**.

## Konsep
- **Baseline**: titik aman yang jadi acuan rollback.
- **Pre-deploy**: bikin ZIP siap upload + catatan perubahan otomatis.
- **Artefak lokal**: ZIP + deploy notes disimpan di folder lokal yang **tidak masuk git**: `releases-local/`.
- **Catatan cepat**: lihat `NOTES.md` (index yang gampang dipanggil).

## 1) Set baseline (sekali untuk “titik sekarang”)
Jalankan dari repo root (atau dari folder plugin, bebas):

```powershell
git tag -a baseline-2026-06-02 -m "Baseline: titik awal workflow lokal"
```

Opsional (recommended): buat arsip ZIP baseline supaya ada backup file siap upload:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\wordpress\ptsbi-premium\scripts\predeploy.ps1" -Mode baseline
```

## 2) Pre-deploy (setiap mau upload manual ke WordPress)

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\wordpress\ptsbi-premium\scripts\predeploy.ps1" -Mode release
```

Output akan muncul di:
- `wordpress/ptsbi-premium/releases-local/*.zip`
- `wordpress/ptsbi-premium/releases-local/deploy-notes_*.md`

Lalu upload ZIP ke WordPress:
- WP Admin → Plugins → Add New → Upload Plugin → pilih ZIP → Activate.

## 3) Rollback ke baseline
Rollback source code ke baseline:

```powershell
git checkout baseline-2026-06-02
```

Lalu (kalau perlu) bikin ulang ZIP dari titik itu:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\wordpress\ptsbi-premium\scripts\predeploy.ps1" -Mode baseline
```

## Catatan penting
- `releases-local/` sudah di-ignore oleh git, jadi artefak zip dan notes tidak “mengotori” repo.
- Script ini mengambil versi plugin dari header `Version:` di `ptsbi-premium.php`.
- Jika working tree kamu sedang ada perubahan yang belum di-commit, deploy notes akan menuliskan statusnya (supaya kamu tidak kehilangan jejak).

