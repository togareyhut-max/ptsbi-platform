# Cara install PTSBI Premium v1.3.0

## File zip

Jalankan di PowerShell (folder backup website):

```powershell
cd "C:\Users\togar\Desktop\backup website ptsbi"
.\build-ptsbi-premium-zip.ps1
```

Hasil: `ptsbi-premium.zip`

## Upload ke WordPress

1. **Plugins → Add New → Upload Plugin** → pilih `ptsbi-premium.zip`
2. Jika plugin lama masih aktif: **Deactivate** dulu, lalu **Delete** (pengaturan tetap di database), atau **Replace** lewat upload (WordPress akan tawarkan ganti versi).
3. **Activate**
4. **Settings → PTSBI Premium**
   - URL aplikasi: `https://tarombo.ptsbi.org` (atau URL server baru setelah deploy)
   - Centang: **Tampilan premium** + **Kotak CTA**
   - **Simpan**
5. **Elementor → Tools → Regenerate CSS** + bersihkan cache hosting

## Yang diperbaiki di v1.3

| Fitur | Keterangan |
|-------|------------|
| Halaman `/tarombo/` | Teks tidak mepet layar — padding otomatis HP / iPad / monitor |
| CTA bawah halaman | Tombol ke **aplikasi** Tarombo (bukan halaman penjelasan) |
| Beranda | Tombol **Bergabung Sekarang** tetap ke aplikasi (`#gabung` → tarombo.ptsbi.org) |
| Pengaturan | Checkbox tidak mati sendiri setelah klik Simpan |

## Setelah aplikasi Flask siap di server

Ganti hanya **URL aplikasi Tarombo** di Settings — tidak perlu ubah tombol Elementor di beranda jika URL sama.
