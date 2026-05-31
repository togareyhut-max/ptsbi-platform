# KontenKit Core v2.0.0 — suite premium (GPL, tanpa license)

Plugin **baru** (bukan modifikasi / nulled Bloggingpro). Slug: `kontenkit-core`.

## Fitur v2

### Admin (KontenKit → menu kiri)

| Tab | Isi |
|-----|-----|
| **Umum** | Warna brand & aksen, CSS kustom, mode maintenance |
| **Artikel** | Meta bar, waktu baca, views, TOC, breadcrumb, share, sticky share, author box, related (list/grid), newsletter |
| **Konten & CPT** | Portfolio (`kk_portfolio`), Testimonial (`kk_testimonial`) |
| **SEO** | Open Graph, Schema.org Article (JSON-LD) |
| **Shortcode** | Referensi semua shortcode |

### Meta box artikel

- Subjudul, label badge (BREAKING, dll.), URL video YouTube (embed di atas konten)

### Shortcode

| Shortcode | Contoh |
|-----------|--------|
| `[kk_button]` | `[kk_button url="/kontak" text="Hubungi" style="accent"]` |
| `[kk_alert]` | `[kk_alert type="success" title="Info"]Teks[/kk_alert]` |
| `[kk_columns]` | `[kk_columns cols="3"]...[/kk_columns]` |
| `[kk_icon_box]` | `[kk_icon_box icon="★" title="Cepat"]Deskripsi[/kk_icon_box]` |
| `[kk_accordion]` | `[kk_accordion title="FAQ" open="yes"]Jawaban[/kk_accordion]` |
| `[kk_quote]` | `[kk_quote author="Nama" role="CEO"]Kutipan[/kk_quote]` |
| `[kk_youtube]` | `[kk_youtube id="VIDEO_ID"]` atau `url="..."` |
| `[kk_pricing]` | `[kk_pricing name="Pro" price="99000" period="/bln" features="A\|B\|C" highlighted="yes"]` |
| `[kk_testimonials]` | `[kk_testimonials count="6" columns="3"]` |
| `[kk_portfolio]` | `[kk_portfolio count="8" columns="4"]` |
| `[kk_cta]` | `[kk_cta title="Mulai" text="..." btn="Daftar" url="#"]` |
| `[kk_spacer]` | `[kk_spacer height="3rem"]` |
| `[kk_divider]` | `[kk_divider style="gradient"]` |

Style tombol: `accent`, `outline`, `ghost`.

## Buat ZIP

**Di PC** (PowerShell, dari folder plugins):

```powershell
cd "C:\Users\togar\.cursor\projects\empty-window\deploy\wordpress\plugins"
Compress-Archive -Path kontenkit-core -DestinationPath kontenkit-core.zip -Force
```

**Di server** (setelah upload folder ke `/home/togaa/`):

```bash
cd /home/togaa
zip -r kontenkit-core.zip kontenkit-core
```

Struktur zip harus: `kontenkit-core/kontenkit-core.php` (bukan file PHP di root zip).

## Pasang di WordPress

1. **Plugins → Add New → Upload Plugin** → `kontenkit-core.zip`
2. **Activate**
3. Menu **KontenKit** — atur tab, **Simpan**
4. Jika CPT baru / archive 404: **Settings → Permalinks → Save** (sekali)

## Upload ke site (WinSCP)

1. Upload folder `kontenkit-core` ke `/home/togaa/kontenkit-core`
2. Salin ke container (ganti nama container sesuai site):

```bash
docker cp /home/togaa/kontenkit-core wordpress-kotakita:/var/www/html/wp-content/plugins/kontenkit-core
docker exec wordpress-kotakita chown -R www-data:www-data /var/www/html/wp-content/plugins/kontenkit-core
```

3. Aktifkan di wp-admin → **KontenKit**

## Perbandingan dengan Bloggingpro

| | Bloggingpro Core | KontenKit Core |
|--|------------------|----------------|
| License | Key komersial | Tidak ada (GPL) |
| CPT / shortcode | Proprietary | Terbuka, bisa dikembangkan |
| SEO / OG | Tergantung versi | Built-in v2 |

## Catatan legal

- Jangan gunakan untuk bypass lisensi plugin berbayar lain.
- Newsletter box UI saja — sambungkan ke Mailchimp / form plugin untuk kirim email.
