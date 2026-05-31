# Premium Organization (ptsbi-premium) — universal

Plugin di folder `Premium Plugins/ptsbi-premium/` (versi **1.4.0**).

## Bukan terkunci ptsbi.org

- Pengaturan disimpan di `wp_options` → key **`ptprm_options`** **per website** (database masing-masing container).
- Tidak ada cek domain / license ke ptsbi.org.
- Default teks PTSBI/Tarombo dihapus; instalasi baru memakai nama dari **Pengaturan → Umum WordPress**.

## Pasang di website lain

1. Zip folder `ptsbi-premium` → upload di **Plugins → Add New → Upload**.
2. Aktifkan.
3. Menu **Premium Plugin** → **Buat halaman standar** → **Isi contoh copywriting** → sesuaikan teks/logo di tab **Header** → **Simpan Perubahan**.
4. Atau **Sesuaikan dengan situs ini** jika DB hasil copy dari situs lain.

## Cadangan JSON (pindah pengaturan antar situs)

1. Di situs **sumber** (mis. ptsbi.org): **Premium Plugin** → **Unduh cadangan JSON** → simpan file di komputer (mis. `ptprm-backup-2026-05-25.json`).
2. Di situs **baru**: pasang plugin **v1.3.1+** → **Premium Plugin** → **Choose File** → pilih file itu → **Pulihkan dari JSON**.
3. File **tidak** diletakkan di folder plugin, `wp-content`, atau server via FTP — hanya diunggah lewat tombol itu.
4. Setelah pulih: **Sesuaikan dengan situs ini** (nama organisasi) → cek gambar (ID media dari situs lama mungkin kosong; unggah ulang di tab Hero/Galeri/Pengurus) → **Simpan Perubahan**.

## Jika copy database dari ptsbi.org

Setelah restore DB ke site baru, buka admin plugin:

- Klik **Sesuaikan dengan situs ini** — mengganti nama organisasi, hero, teks WA, dll. sesuai `home_url()` situs baru.
- Atau **Pulihkan dari JSON** jika punya cadangan dari situs lain (jangan pakai Reset universal di versi lama).

## Fitur Tarombo (opsional)

Bukan wajib. Di tab **Umum**:

- Kosongkan **URL aplikasi** jika tidak ada layanan eksternal.
- Matikan **Tampilkan blok CTA** jika tidak perlu.

## Upload ke server (WinSCP)

```bash
docker cp /home/togaa/ptsbi-premium wordpress-NAMASITE:/var/www/html/wp-content/plugins/ptsbi-premium
docker exec wordpress-NAMASITE chown -R www-data:www-data /var/www/html/wp-content/plugins/ptsbi-premium
```

Ganti `wordpress-NAMASITE` (mis. `wordpress-project-coba`, `wordpress-kotakita`).

## Simpan gagal di site lain (tapi ptsbi.org bisa)

**Tidak ada kunci domain di kode** — penyebab umum:

| Penyebab | Solusi |
|----------|--------|
| Versi lama tanpa AJAX/base64 | Upload **v1.2.2** ke semua container |
| Security / WAF memblokir `admin-ajax.php` | Whitelist `admin-ajax.php` untuk user login; v1.2.2 otomatis fallback ke `admin-post.php` |
| Cache admin | Purge LiteSpeed / cache plugin, hard refresh (Ctrl+F5) |
| URL admin ≠ siteurl (http/https, www) | **Pengaturan → Umum** — samakan Alamat WordPress & Alamat situs dengan URL yang Anda buka |
| DB hasil copy ptsbi | Bukan penyebab simpan gagal; klik **Sesuaikan dengan situs ini** untuk teks |

**Cek versi:** menu Premium Plugin → pojok kanan harus **v1.4.0**.

**Situs baru simpan gagal:** upload v1.3.1, buka halaman Premium Plugin sekali (perbaikan DB), pulihkan JSON jika punya cadangan, **Ctrl+F5**, lalu Simpan.

**Isi contoh:** tombol biru **Isi contoh copywriting** langsung menulis ke database (tanpa perlu Simpan dulu).

**Cek simpan (F12 → Network):** klik Simpan → request `admin-ajax.php?action=ptprm_save_settings` → response JSON `success: true` atau pesan error di `data.message`.
