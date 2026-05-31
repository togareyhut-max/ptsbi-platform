=== Section Studio — Elementor Companion ===
Contributors:      sectionstudio
Tags:              elementor, sections, premium, responsive, widget
Requires at least: 5.8
Tested up to:      6.7
Requires PHP:      7.4
Stable tag:        2.1.2
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Companion plugin untuk Elementor: widget drag & drop (Nilai-Nilai, Kunjungi Kami, Footer Premium) plus panel Section Studio untuk warna, CTA Hero, dan perbaikan otomatis beranda.

== Description ==

Section Studio menambahkan:

* **Widget Elementor** (kategori *Section Studio*): Nilai-Nilai Kami, Kunjungi Kami, Footer Premium — bisa **drag & drop** di halaman Beranda seperti section Elementor biasa.
* Menu **Section Studio** di WP Admin: warna brand, font, 2 CTA Hero, alamat default, mode tata letak (Elementor vs Otomatis), perbaikan kartu berita, duplikasi, footer.

**Mode Elementor (disarankan):** Anda susun urutan section di Elementor (mis. Nilai-Nilai antara Tentang dan Kegiatan). Plugin tidak menyisipkan HTML lewat JavaScript.

**Mode Otomatis (legacy):** Plugin menyisipkan section Nilai/Kunjungi/Footer sendiri — urutan bisa menempel ke blok lain (mis. dekat alamat).

Plugin **non-destructive**: tipografi widget Elementor tetap menang. Setting Section Studio menambah lapisan styling lewat CSS variables.

== Installation ==

1. Login ke WP Admin.
2. Plugins → Add New → Upload Plugin → pilih ZIP `ptsbi-premium-enhancer.zip` → Install Now → Activate.
3. Pastikan **Elementor** aktif.
4. **Section Studio** → tab Global: pilih **Elementor**, isi WhatsApp & alamat → Simpan.
5. **Pages** → Beranda → **Edit with Elementor**.
6. Panel widget kiri → cari kategori **Section Studio** → tarik **Nilai-Nilai Kami** ke antara Tentang dan Kegiatan; tambah **Kunjungi Kami** di atas peta; **Footer Premium** di bawah.
7. Sembunyikan/hapus section Elementor lama yang duplikat (Nilai-Nilai / Kunjungi / Footer lama).
8. Update → Ctrl+F5 di beranda.

== Elementor widgets ==

* **Nilai-Nilai Kami** — 4 kartu + tooltip hover; konten bisa diedit di panel widget.
* **Kunjungi Kami** — alamat, jam, WhatsApp (default dari Global).
* **Footer Premium** — tautan, sosial media, copyright.

== Changelog ==

= 2.1.2 =
* Perbaikan halaman putih (WSOD): muat widget Elementor hanya setelah Elementor siap, kompatibilitas register widget lama/baru, default repeater aman.
* URL corak Gorga & ornamen memakai path absolut agar sub-halaman selalu tampil.

= 2.1.1 =
* Corak ornamen Gorga Batak di header sub-halaman (pola SVG + divider emas di hero).
* Template sub-halaman premium seragam: hero navy+emas, tipografi, kartu bernomor, spacing konten.

= 2.1.0 =
* Widget Elementor drag & drop (Nilai, Kunjungi, Footer).
* Mode tata letak: Elementor vs Otomatis (legacy inject JS).
* Alamat default di pengaturan Global.

= 2.0.0 =
* Rewrite total: arsitektur non-destructive, panel setting per-section, 2 CTA Hero.

= 1.0.0 =
* Rilis pertama.
