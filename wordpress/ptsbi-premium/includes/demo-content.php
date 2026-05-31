<?php
/**
 * Contoh copywriting universal — bisa diterapkan ke situs mana pun.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Opsi lengkap dengan teks contoh (nama organisasi dari blog WordPress).
 *
 * @return array<string, mixed>
 */
function ptprm_demo_options(): array {
    $blog = (string) get_bloginfo( 'name' );
    if ( $blog === '' ) {
        $blog = 'Organisasi Anda';
    }
    $values = [
        [
            'icon'  => 'users',
            'title' => 'Kebersamaan',
            'desc'  => 'Menjaga silaturahmi antar anggota dan keluarga besar, dalam suka maupun duka.',
        ],
        [
            'icon'  => 'heart',
            'title' => 'Kepedulian',
            'desc'  => 'Saling membantu saat ada kebutuhan mendesak, musibah, atau program sosial bersama.',
        ],
        [
            'icon'  => 'feather',
            'title' => 'Pelestarian Budaya',
            'desc'  => 'Melestarikan adat, bahasa, dan tradisi leluhur agar tetap hidup di generasi muda.',
        ],
        [
            'icon'  => 'hands',
            'title' => 'Kolaborasi',
            'desc'  => 'Bekerja sama dengan pengurus, cabang wilayah, dan mitra untuk program yang berdampak.',
        ],
    ];

    $team = [
        [
            'image' => '',
            'name'  => 'Bapak Ketua Umum',
            'role'  => 'Ketua Umum',
            'url'   => '',
            'group' => 'Pengurus Inti',
        ],
        [
            'image' => '',
            'name'  => 'Ibu Sekretaris',
            'role'  => 'Sekretaris',
            'url'   => '',
            'group' => 'Pengurus Inti',
        ],
        [
            'image' => '',
            'name'  => 'Bapak Bendahara',
            'role'  => 'Bendahara',
            'url'   => '',
            'group' => 'Pengurus Inti',
        ],
        [
            'image' => '',
            'name'  => 'Koordinator Program',
            'role'  => 'Koordinator Kegiatan',
            'url'   => '',
            'group' => 'Bidang Program',
        ],
    ];

    $d = array_merge(
        ptprm_defaults(),
        [
            'enabled'           => 1,
            'org_name'          => $blog,
            '_site_fingerprint' => PTPRM_Bootstrap::site_fingerprint(),

            'whatsapp' => '6281234567890',
            'address'  => "Gedung Serbaguna {$blog}\nJl. Contoh No. 123, Kota Anda\nProvinsi, Kode Pos 12345",
            'hours'    => 'Senin–Jumat 09.00–17.00 WIB · Sabtu 09.00–12.00 (janji temu)',
            'fb_url'   => 'https://facebook.com/',
            'ig_url'   => 'https://instagram.com/',
            'yt_url'   => 'https://youtube.com/',
            'tt_url'   => '',

            'hero_eyebrow_text' => 'SELAMAT DATANG',
            'hero_title_text'   => sprintf( 'Website Resmi %s', $blog ),
            'hero_sub_text'     => 'Informasi kegiatan, struktur organisasi, dan cara bergabung — semua ada di satu tempat. Silakan jelajahi dan hubungi kami jika ada pertanyaan.',

            'cta1_label' => 'Lihat Program Kami',
            'cta1_url'   => '/program/',
            'cta2_label' => 'Hubungi via WhatsApp',
            'cta2_mode'  => 'inquiry',
            'cta2_inquiry_default' => sprintf( 'Halo, saya ingin informasi lebih lanjut tentang %s.', $blog ),

            'about_title' => sprintf( 'Mengenal %s', $blog ),
            'about_body'  => "{$blog} adalah wadah kebersamaan yang menghubungkan anggota, keluarga besar, dan komunitas.\n\nKami mengadakan kegiatan rutin, program sosial, dan dokumentasi sejarah organisasi. Setiap anggota dipersilakan berpartisipasi sesuai kemampuan dan minat.\n\nVisi kami: organisasi yang solid, inklusif, dan relevan untuk generasi sekarang dan mendatang.",

            'values_title'    => 'Nilai yang Kami Junjung',
            'values_subtitle' => 'Pedoman bersama dalam setiap kegiatan dan keputusan organisasi.',
            'values_hover_hint' => 'Arahkan kursor ke kartu untuk penjelasan',
            'values_items'    => wp_json_encode( $values, JSON_UNESCAPED_UNICODE ),

            'activities_title'    => 'Berita & Kegiatan Terbaru',
            'activities_subtitle' => 'Agenda terkini, liputan acara, dan pengumuman penting.',
            'activities_more_url' => '/kegiatan/',

            'stats_a_num' => '12+', 'stats_a_label' => 'Cabang / Wilayah',
            'stats_b_num' => '800+', 'stats_b_label' => 'Anggota Terdaftar',
            'stats_c_num' => '50+', 'stats_c_label' => 'Kegiatan per Tahun',
            'stats_d_num' => '25+', 'stats_d_label' => 'Tahun Berdiri',

            'visit_title' => 'Kunjungi atau Hubungi Kami',
            'visit_intro' => 'Kantor sekretariat terbuka untuk koordinasi, pendaftaran, dan konsultasi program. Silakan buat janji terlebih dahulu via WhatsApp.',

            'footer_tagline'    => sprintf( '%s — mempererat persaudaraan, melestarikan budaya, dan berkontribusi untuk masyarakat.', $blog ),
            'footer_col1_links' => "Beranda|/\nTentang|/tentang-kami/\nProgram|/program/\nKegiatan|/kegiatan/",
            'footer_col2_links' => "Kontak|/kontak/\nStruktur|/struktur-organisasi/\nGaleri|/galeri/",

            'team_show'     => 1,
            'team_title'    => 'Pengurus & Penggerak',
            'team_subtitle' => 'Tim yang mengkoordinasikan program dan komunikasi organisasi.',
            'team_items'    => wp_json_encode( $team, JSON_UNESCAPED_UNICODE ),

            'gallery_show'     => 0,
            'gallery_subtitle' => 'Cuplikan dokumentasi kegiatan — unggah foto di tab Section Tambahan.',

            'banner_show'     => 1,
            'banner_title'    => 'Ingin Ikut Berkontribusi?',
            'banner_subtitle' => 'Daftarkan diri Anda atau ajukan kerja sama program. Tim kami akan menghubungi dalam 1–3 hari kerja.',
            'banner_cta_url'  => '/kontak/',

            'faq_show'     => 1,
            'faq_subtitle' => 'Jawaban singkat sebelum Anda menghubungi sekretariat.',
            'faq_1_q'      => 'Bagaimana cara bergabung?',
            'faq_1_a'      => 'Isi formulir di halaman Kontak atau kirim pesan WhatsApp. Pengurus akan memandu proses pendaftaran dan persyaratan anggota.',
            'faq_2_q'      => 'Apakah ada iuran anggota?',
            'faq_2_a'      => 'Besaran iuran disesuaikan program dan keputusan rapat anggota. Rincian diinformasikan saat pendaftaran.',
            'faq_3_q'      => 'Kapan kegiatan rutin diadakan?',
            'faq_3_a'      => 'Agenda bulanan diumumkan di halaman Kegiatan dan grup WhatsApp resmi organisasi.',
            'faq_4_q'      => 'Bisakah organisasi luar berkolaborasi?',
            'faq_4_a'      => 'Ya. Kirim proposal kerja sama ke sekretariat untuk ditinjau pengurus.',
            'faq_5_q'      => 'Di mana saya bisa melihat struktur pengurus?',
            'faq_5_a'      => 'Buka halaman Struktur Organisasi atau section Pengurus di beranda.',

            'popup_title' => sprintf( 'Selamat datang di %s', $blog ),
            'popup_body'  => 'Jelajahi program terbaru dan cara bergabung. Tutup pop-up ini kapan saja — tidak akan mengganggu navigasi.',

            'subpage_default_intro' => 'Halaman ini bagian dari website resmi organisasi. Gunakan menu utama untuk kembali ke beranda atau hubungi kami.',

            'tarombo_cta_title' => 'Layanan digital (opsional)',
            'tarombo_cta_lead'  => 'Tautkan aplikasi, direktori anggota, atau sistem internal organisasi Anda di sini.',
            'tarombo_app_url'   => '',
            'enable_tarombo_page_button' => 0,

            'header_use_plugin'      => 1,
            'header_layout'          => 'split',
            'header_nav_style'       => 'pill',
            'header_menu_source'     => 'custom',
            'header_show_cta'        => 1,
            'header_cta_label'       => __( 'Rumah Anggota', 'ptsbi-premium' ),
            'header_cta_url'         => '/rumah-anggota/',
            'header_cta_style'       => 'accent',
            'header_menu_items'      => wp_json_encode(
                PTPRM_Pages::default_menu_items_for_options(),
                JSON_UNESCAPED_UNICODE
            ),
        ]
    );

    return ptprm_normalize_option_for_storage( $d );
}

/**
 * Isi semua field contoh; gambar/logo yang sudah diunggah tetap dipertahankan.
 *
 * @return array<string, mixed>
 */
function ptprm_apply_demo_copywriting(): array {
    $existing = (array) get_option( PTPRM_OPTION, [] );
    $demo     = ptprm_demo_options();

    $preserve = [
        'header_logo',
        'hero_img_desktop',
        'hero_img_tablet',
        'hero_img_mobile',
        'about_image',
        'banner_image',
        'popup_image',
        'popup_image_2',
        'popup_image_3',
        'gallery_ids',
    ];

    foreach ( $preserve as $key ) {
        if ( ! empty( $existing[ $key ] ) ) {
            $demo[ $key ] = $existing[ $key ];
        }
    }

    if ( ! empty( $existing['team_items'] ) ) {
        $has_photo = false;
        $decoded   = json_decode( (string) $existing['team_items'], true );
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $row ) {
                if ( ! empty( $row['image'] ) ) {
                    $has_photo = true;
                    break;
                }
            }
        }
        if ( $has_photo ) {
            $demo['team_items'] = $existing['team_items'];
        }
    }

    return ptprm_normalize_option_for_storage( array_merge( $existing, $demo ) );
}
