=== Premium Organization Plugin ===
Contributors:      premiumplugins
Tags:              homepage, hero, premium, organization, sections, responsive
Requires at least: 5.8
Tested up to:      6.7
Requires PHP:      7.4
Stable tag:        3.0.1
License:           GPLv2 or later

Satu plugin lengkap: beranda organisasi + modul anggota (pendaftaran, profil, import CSV DAMI, export XLSX, panel pengurus). Plugin ptsbi-members terpisah tidak dipakai lagi sejak v3.0.1.

== Description ==

Plugin ini mengambil alih konten Beranda dan membungkus sub-halaman dengan template premium yang seragam. Tidak memerlukan Elementor.

**Beranda — urutan tetap:**
Hero → Tentang → Nilai-Nilai → Kegiatan → Statistik → Kunjungi → Footer.
Setiap section dapat dimatikan/dihidupkan dari panel.

**Hero — kontrol penuh:**
- Gambar terpisah untuk Desktop, Tablet, Mobile (responsif otomatis)
- Posisi fokus gambar, overlay, gradient
- Tinggi: kecil / standar / tinggi / penuh layar / custom px
- 3 elemen teks (eyebrow, judul, subjudul) — masing-masing: font, ukuran desktop & mobile, bobot, warna, letter-spacing, line-height, style (italic / uppercase / capitalize), bayangan
- 2 tombol CTA dengan style solid/outline/ghost, ikon, warna hover, radius, ukuran
- Posisi teks horizontal & vertikal
- Animasi muncul

**Live preview** di admin update otomatis saat Anda mengetik.

**Sub-halaman:**
Hero navy dengan corak Gorga Batak + ornamen emas + breadcrumb. Konten WP (entry-content) dibungkus container premium dengan tipografi serif untuk heading.

**Universal:**
Tidak hardcode untuk PTSBI — nama organisasi, warna, font, teks, ikon, tautan semua dapat diedit.

== Installation ==

1. Zip folder `ptsbi-premium`.
2. WP Admin → Plugins → Add New → Upload Plugin → pilih ZIP → Activate.
3. Buka menu **Premium Plugin** di sidebar admin.
4. Tab **Umum** — isi nama organisasi, warna, font, WA, alamat, sosial media.
5. Tab **Hero** — upload gambar (desktop/tablet/mobile), isi teks, atur warna tombol, lihat preview.
6. Tab **Beranda** — edit isi Tentang, Nilai, Kegiatan, Statistik, Kunjungi.
7. Tab **Sub-halaman** — atur header sub-halaman (Gorga, ornamen, breadcrumb).
8. Tab **Footer** — isi tagline & tautan.
9. Simpan.

== Changelog ==

= 3.1.10 =
* Panel Pengurus Wilayah (pusat): kolom **URL foto** tampil saat centang foto; tombol pilih media mengisi URL.
* Tab **Foto Pengurus Pusat** (`struktur`) kembali terdaftar di panel admin.

= 3.1.9 =
* **[SELESAI — terverifikasi live Jun 2026]** Halaman pengurus pusat: `/ptsbi-pusat/`, `/pengurus-pusat/`, `/struktur-organisasi/` menampilkan daftar + kartu foto inti.
* Shortcode `[ptprm_board]`: sumber data `ptprm_board_data_{region}` / `board_{region}` di wp_options, fallback katalog `board-default-data.php`.
* Perbaiki data tersimpan tanpa nama + deteksi cangkang HTML kosong agar filter `the_content` mengisi ulang daftar.
* Deploy otomatis saat push ke `main` (folder plugin) + verifikasi versi di ptsbi.org.

= 3.1.8 =
* Deploy: salin plugin ke volume Docker (bukan hanya docker cp) + verifikasi versi + rollback otomatis jika gagal.
* Pengurus pusat: perbaiki opsi kosong [], buat halaman /pengurus-pusat/, judul section di halaman wilayah.

= 3.1.6 =
* Pengurus Pusat: perbaikan tampilan di halaman depan setelah simpan panel (shortcode kosong tidak lagi menghalangi daftar).
* Halaman Struktur Organisasi ikut menampilkan pengurus pusat.
* Kartu foto: jabatan lalu nama; ukuran foto sedang.

= 1.4.3 =
* Footer: judul brand = Judul situs (Pengaturan → Umum). Deskripsi custom atau otomatis dari Slogan situs jika field kosong.

= 1.4.2 =
* Nilai: penjelasan kustom tampil langsung di kartu, rata kiri (bukan overlay tengah).

= 1.4.1 =
* Header: ukuran font menu (px); tata letak Classic/Split/Centered/Minimal benar-benar berbeda; hover logo membesar halus.
* Umum: 8 palet warna premium modern (satu klik isi primer+aksen+teks+krem).
* Kunjungi: peta sedikit lebih kecil.
* Pop-up: proporsi gambar/teks otomatis (sedikit teks = gambar lebih besar; banyak teks = gambar tetap dominan).

= 1.4.0 =
* Tombol **Isi contoh copywriting** — mengisi semua field teks (gambar/logo tetap).
* Tab **Header**: logo, layout modern (split/centered/classic/minimal), gaya menu (pill/underline/plain), menu kustom atau WordPress, CTA.
* Tombol **Buat halaman standar** — Tentang, Program, Kegiatan, Struktur, Galeri, Kontak + menu WP.

= 1.3.1 =
* Tombol **Pulihkan dari JSON** — impor file cadangan (tidak perlu menaruh file di folder plugin/server).
* Simpan: nilai & pengurus tetap dipertahankan meski DB masih kosong (situs baru).

= 1.3.0 =
* Akar masalah reset: values_items / team_items disimpan kosong ('') di database; ptsbi yang bisa simpan punya JSON lengkap. Normalisasi otomatis + perbaikan sekali di admin.
* Tombol Reset universal dihapus (merusak data + memicu gagal simpan). Ganti: Unduh cadangan JSON.
* Simpan: tidak menghapus nilai/pengurus jika form repeater kosong karena salah kirim.

= 1.2.5 =
* Reset universal: isi contoh lengkap (sama seperti demo), anti-cache admin, peringatan purge cache.
* Repeater nilai/pengurus: data awal via base64 (tidak rusak di atribut HTML).
* Simpan: cegah action ganda; pesan jelas jika form admin dari cache.

= 1.2.4 =
* Fix kritis simpan: hapus `settings_fields()` yang menimpa `action=ptprm_save_settings` dengan `action=update` (halaman WordPress › Error kosong).

= 1.2.3 =
* Simpan: pakai admin-post.php (bukan AJAX) — lebih andal di semua website.
* Tombol **Isi contoh copywriting** — mengisi semua field teks contoh.
* Default: FAQ, nilai, pengurus, dan teks section lebih lengkap.

= 1.2.2 =
* Fix universal: simpan pengaturan di semua website (bukan hanya ptsbi.org).
* Payload base64 + fallback admin-post.php jika AJAX diblokir WAF/security.
* Checkbox tidak lagi ter-reset saat data form terpotong.
* Pesan error lebih jelas (sesi habis, data kosong, data tidak lengkap).

= 1.7.2 =
* Nilai-nilai: saat hover/tap, full box hanya tampilkan penjelasan (tanpa judul & ikon), teks rata tengah.
* Overlay penjelasan memakai warna preset aktif tanpa ornamen agar lebih jelas dibaca.

= 1.7.1 =
* Nilai-nilai: hover/tap menampilkan penjelasan full box (centered), background ikut preset; ikon berganti dengan penjelasan.
* Admin: preview hero bisa switch Desktop/Tablet/HP.

= 1.7.0 =
* Nilai-nilai: deskripsi kembali muncul saat hover (tooltip), tetap bisa tambah/ubah/hapus item.
* Beranda: urutan section setelah Hero bisa diubah (drag & drop) dari panel admin.

= 1.6.2 =
* Fix menu HP: drawer dipindah di luar header fixed (hilang strip biru di tengah); sembunyikan menu mobile Astra saat pakai header plugin.

= 1.6.1 =
* Menu burger HP: drawer premium slide-in, backdrop blur, tombol bulat animasi X, CTA di bawah.
* Hero beranda di HP: fullscreen (100svh) dengan teks tidak tertutup header.

= 1.6.0 =
* Hero: banner berganti otomatis — 5 slot gambar, interval & titik indikator dapat diatur.

= 1.5.2 =
* Corak ornamen latar bisa dipilih: Gorga, geometris, titik, chevron, anyaman, gelombang, atau tanpa corak (kosong).
* Sub-halaman & section lain mengikuti pilihan corak global di tab Umum.

= 1.5.1 =
* Fix kritis: variabel warna preset tidak berlaku di statistik/footer karena ditimpa CSS default (sekarang diinjeksi setelah stylesheet).
* Placeholder tanpa foto (tentang, kegiatan, pengurus, peta) ikut warna dominan preset; sub-halaman ikut variabel primer.
* Peta: panel embed HTML iframe; simpan iframe utuh; alamat multiline didukung; URL embed www.google.com.

= 1.5.0 =
* Google Maps di Kunjungi Kami mengikuti alamat tab Umum (embed otomatis); field iframe tetap opsional untuk override.
* Palet warna premium memperbarui seluruh section (bukan hanya primer/aksen): overlay hero, sub-halaman, banner, pop-up, CTA, header.
* Tambah palet terang (Pearl, Sage, Sand, Mist, Linen) + overlay optimal per palet.
* Frontend CSS: gradien navy hardcoded diganti variabel `--ptprm-primary-rgb` agar ikut warna dominan.

= 1.4.3 =
* Footer: judul = judul situs WordPress; deskripsi = field custom atau slogan situs.

= 1.2.0 =
* Nilai-nilai: tambah/hapus tanpa batas (repeater), penjelasan tampil di kartu, grid auto-fit.
* Pengurus: tanpa batas 8 orang, grup per jabatan/bidang, layout grid atau grouped.
* Menu mobile (Astra): drawer dengan latar primer & teks putih terbaca.

= 1.1.0 =
* Universal: pengaturan per situs (fingerprint URL), tanpa default ptsbi.org / tarombo.ptsbi.org.
* Tombol admin: Sesuaikan dengan situs ini, Reset universal.
* Blok aplikasi eksternal (dulu Tarombo) sepenuhnya bisa diedit atau dimatikan.
* Nama plugin: Premium Organization (bukan PTSBI di judul).

= 1.0.2 =
* Fix: judul Statistik (teks navy di atas latar navy) sekarang putih.
* Fix: jarak antar section (Kegiatan / Statistik / Kunjungi) sekarang lega.
* Tentang Kami: gambar placeholder elegan saat belum upload + tombol default ke /tentang-kami/.
* Kunjungi Kami: layout baru — judul di tengah, kartu alamat di kiri, box Google Maps di kanan (dengan placeholder jika belum diisi).
* Tambah helper resolve URL relatif (/tentang-kami/) → URL absolut.

= 1.0.1 =
* Fix: Beranda kosong saat halaman home diatur dengan Elementor. Sekarang plugin pakai template_include sehingga selalu menang atas page builder lain.
* Fix: BUILD-ZIP.bat memakai .NET ZipFile.CreateFromDirectory supaya subfolder pasti ikut.

= 1.0.0 =
* Rilis pertama. Beranda diambil alih plugin (tanpa Elementor), Hero responsif penuh, sub-halaman seragam dengan ornamen Gorga.
