<?php
/**
 * Default option schema & helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return array{0:int,1:int,2:int}
 */
function ptprm_hex_to_rgb( string $hex ): array {
    $hex = ltrim( (string) $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
        return [ 10, 31, 61 ];
    }
    return [
        hexdec( substr( $hex, 0, 2 ) ),
        hexdec( substr( $hex, 2, 2 ) ),
        hexdec( substr( $hex, 4, 2 ) ),
    ];
}

/**
 * Pilihan corak ornamen latar (beranda, statistik, sub-halaman, placeholder).
 *
 * @return array<string, string>
 */
function ptprm_pattern_choices() {
    return [
        'none'      => __( 'Tanpa corak (kosong)', 'ptsbi-premium' ),
        'gorga'     => __( 'Gorga Batak', 'ptsbi-premium' ),
        'geometric' => __( 'Geometris', 'ptsbi-premium' ),
        'dots'      => __( 'Titik halus', 'ptsbi-premium' ),
        'chevron'   => __( 'Chevron', 'ptsbi-premium' ),
        'lattice'   => __( 'Anyaman garis', 'ptsbi-premium' ),
        'waves'     => __( 'Gelombang', 'ptsbi-premium' ),
    ];
}

/**
 * Slug corak aktif (default: gorga untuk kompatibilitas situs lama).
 */
function ptprm_pattern_style( ?array $o = null ): string {
    $o    = $o ?? ptprm_options();
    $slug = sanitize_key( (string) ( $o['pattern_style'] ?? 'gorga' ) );
    $all  = ptprm_pattern_choices();
    return isset( $all[ $slug ] ) ? $slug : 'gorga';
}

function ptprm_pattern_enabled( ?array $o = null ): bool {
    return ptprm_pattern_style( $o ) !== 'none';
}

/**
 * URL file SVG corak.
 */
function ptprm_pattern_asset_url( string $slug ): string {
    $files = [
        'gorga'     => 'gorga-pattern.svg',
        'geometric' => 'pattern-geometric.svg',
        'dots'      => 'pattern-dots.svg',
        'chevron'   => 'pattern-chevron.svg',
        'lattice'   => 'pattern-lattice.svg',
        'waves'     => 'pattern-waves.svg',
    ];
    if ( ! isset( $files[ $slug ] ) ) {
        return '';
    }
    return PTPRM_URL . 'assets/img/' . $files[ $slug ];
}

/**
 * Nilai CSS untuk custom property --ptprm-pattern-img.
 */
function ptprm_pattern_css_var( ?array $o = null ): string {
    $slug = ptprm_pattern_style( $o );
    if ( $slug === 'none' ) {
        return 'none';
    }
    $url = ptprm_pattern_asset_url( $slug );
    return $url !== '' ? 'url("' . esc_url( $url ) . '")' : 'none';
}

function ptprm_shade_hex( string $hex, int $percent ): string {
    $hex = ltrim( $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
        return '#000000';
    }
    $r = hexdec( substr( $hex, 0, 2 ) );
    $g = hexdec( substr( $hex, 2, 2 ) );
    $b = hexdec( substr( $hex, 4, 2 ) );
    $adj = static function ( $c ) use ( $percent ) {
        $c = $c + ( $c * ( $percent / 100 ) );
        return max( 0, min( 255, (int) round( $c ) ) );
    };
    return sprintf( '#%02X%02X%02X', $adj( $r ), $adj( $g ), $adj( $b ) );
}

/**
 * Satu baris alamat untuk geocoding / peta (baris baru → koma).
 */
function ptprm_normalize_address( string $address ): string {
    $address = trim( preg_replace( '/[\r\n]+/', ', ', $address ) );
    return trim( preg_replace( '/\s+/', ' ', $address ) );
}

/**
 * URL embed Google Maps dari alamat (tab Umum). Manual iframe/URL diutamakan.
 *
 * @param array<string, mixed>|null $o Options.
 */
function ptprm_visit_map_embed_url( ?array $o = null ): string {
    $o      = $o ?? ptprm_options();
    $stored = trim( (string) ( $o['visit_map_url'] ?? '' ) );

    if ( stripos( $stored, '<iframe' ) !== false ) {
        return ptprm_extract_map_src( $stored );
    }

    $manual = ptprm_extract_map_src( $stored );
    if ( $manual !== '' ) {
        return $manual;
    }

    $address = ptprm_normalize_address( (string) ( $o['address'] ?? '' ) );
    if ( $address === '' ) {
        return '';
    }

    return 'https://www.google.com/maps?q=' . rawurlencode( $address ) . '&hl=id&z=16&output=embed';
}

/**
 * HTML iframe peta yang aman (dari paste admin).
 */
function ptprm_sanitize_map_iframe_html( string $html ): string {
    return wp_kses(
        $html,
        [
            'iframe' => [
                'src'             => true,
                'width'           => true,
                'height'          => true,
                'style'           => true,
                'class'           => true,
                'allow'           => true,
                'allowfullscreen' => true,
                'loading'         => true,
                'referrerpolicy'  => true,
                'title'           => true,
                'frameborder'     => true,
                'aria-hidden'     => true,
                'tabindex'        => true,
            ],
        ]
    );
}

/**
 * Markup peta untuk section Kunjungi: embed HTML manual, URL manual, atau otomatis dari alamat.
 *
 * @param array<string, mixed>|null $o Options.
 */
function ptprm_visit_map_markup( ?array $o = null ): string {
    $o      = $o ?? ptprm_options();
    $stored = trim( (string) ( $o['visit_map_url'] ?? '' ) );

    if ( $stored !== '' && stripos( $stored, '<iframe' ) !== false ) {
        $html = ptprm_sanitize_map_iframe_html( $stored );
        if ( $html !== '' ) {
            return $html;
        }
    }

    $url = ptprm_visit_map_embed_url( $o );
    if ( $url === '' ) {
        return '';
    }

    return sprintf(
        '<iframe src="%1$s" width="100%%" height="100%%" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="%2$s"></iframe>',
        esc_url( $url ),
        esc_attr__( 'Lokasi di peta', 'ptsbi-premium' )
    );
}

/**
 * Tautan buka Google Maps (tab baru) dari alamat Umum.
 */
function ptprm_visit_map_external_url( ?array $o = null ): string {
    $address = ptprm_normalize_address( (string) ( ( $o ?? ptprm_options() )['address'] ?? '' ) );
    if ( $address === '' ) {
        return '';
    }
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
}

/**
 * Warna turunan agar section (hero, footer, CTA, overlay) ikut palet primer/aksen.
 *
 * @param array<string, int> $overlay_ops Optional overlay opacity overrides.
 * @return array<string, string|int>
 */
function ptprm_derive_brand_colors( string $primary, string $accent, array $overlay_ops = [] ): array {
    $primary = sanitize_hex_color( $primary ) ?: '#0A1F3D';
    $accent  = sanitize_hex_color( $accent ) ?: '#C9A44C';

    $hero_op  = isset( $overlay_ops['hero_overlay_opacity'] ) ? (int) $overlay_ops['hero_overlay_opacity'] : 55;
    $sub_op   = isset( $overlay_ops['subpage_overlay'] ) ? (int) $overlay_ops['subpage_overlay'] : 70;
    $ban_op   = isset( $overlay_ops['banner_overlay'] ) ? (int) $overlay_ops['banner_overlay'] : 68;
    $pop_op   = isset( $overlay_ops['popup_overlay_opacity'] ) ? (int) $overlay_ops['popup_overlay_opacity'] : 70;

    return [
        'hero_overlay_color'     => $primary,
        'hero_overlay_opacity'   => max( 35, min( 85, $hero_op ) ),
        'subpage_overlay'        => max( 40, min( 90, $sub_op ) ),
        'banner_overlay'         => max( 40, min( 90, $ban_op ) ),
        'popup_overlay_opacity'  => max( 45, min( 90, $pop_op ) ),
        'header_bg_color'        => '',
        'header_text_color'      => '#FFFFFF',
        'cta1_bg'                => $accent,
        'cta1_color'             => $primary,
        'cta1_bg_hover'          => ptprm_shade_hex( $accent, 12 ),
        'cta1_color_hover'       => $primary,
        'cta2_bg'                => 'transparent',
        'cta2_color'             => '#FFFFFF',
        'cta2_bg_hover'          => '#FFFFFF',
        'cta2_color_hover'       => $primary,
    ];
}

/**
 * Palet warna premium — gelap & terang, dengan overlay optimal per palet.
 *
 * @return array<string, array<string, mixed>>
 */
function ptprm_color_theme_presets() {
    return [
        'navy_gold' => [
            'label'     => 'Navy & Emas',
            'tone'      => 'dark',
            'primary'   => '#0A1F3D',
            'accent'    => '#C9A44C',
            'text'      => '#1C1C1C',
            'text_soft' => '#5C5C5C',
            'cream'     => '#FAF6EE',
            'overlays'  => [ 'hero_overlay_opacity' => 55, 'subpage_overlay' => 72, 'banner_overlay' => 70, 'popup_overlay_opacity' => 72 ],
        ],
        'charcoal_gold' => [
            'label'     => 'Charcoal & Emas',
            'tone'      => 'dark',
            'primary'   => '#1E1E1E',
            'accent'    => '#C9A44C',
            'text'      => '#222222',
            'text_soft' => '#666666',
            'cream'     => '#F9F7F2',
            'overlays'  => [ 'hero_overlay_opacity' => 58, 'subpage_overlay' => 74, 'banner_overlay' => 72, 'popup_overlay_opacity' => 74 ],
        ],
        'forest_gold' => [
            'label'     => 'Forest & Emas',
            'tone'      => 'dark',
            'primary'   => '#0E3B2E',
            'accent'    => '#D4AF37',
            'text'      => '#1A2420',
            'text_soft' => '#4F5E58',
            'cream'     => '#F7F5EF',
            'overlays'  => [ 'hero_overlay_opacity' => 54, 'subpage_overlay' => 70, 'banner_overlay' => 68, 'popup_overlay_opacity' => 70 ],
        ],
        'burgundy_rose' => [
            'label'     => 'Burgundy & Rose',
            'tone'      => 'dark',
            'primary'   => '#4A1C2B',
            'accent'    => '#D4A574',
            'text'      => '#2A1A1F',
            'text_soft' => '#6B5560',
            'cream'     => '#FBF7F4',
            'overlays'  => [ 'hero_overlay_opacity' => 56, 'subpage_overlay' => 72, 'banner_overlay' => 70, 'popup_overlay_opacity' => 72 ],
        ],
        'stone_bronze' => [
            'label'     => 'Stone & Perunggu',
            'tone'      => 'dark',
            'primary'   => '#3D3835',
            'accent'    => '#C4A77D',
            'text'      => '#2C2826',
            'text_soft' => '#6F6762',
            'cream'     => '#FAF7F2',
            'overlays'  => [ 'hero_overlay_opacity' => 55, 'subpage_overlay' => 71, 'banner_overlay' => 69, 'popup_overlay_opacity' => 71 ],
        ],
        'slate_teal' => [
            'label'     => 'Slate & Teal',
            'tone'      => 'dark',
            'primary'   => '#243447',
            'accent'    => '#5BB5A2',
            'text'      => '#1E2A33',
            'text_soft' => '#5A6B78',
            'cream'     => '#F4F7F8',
            'overlays'  => [ 'hero_overlay_opacity' => 52, 'subpage_overlay' => 68, 'banner_overlay' => 66, 'popup_overlay_opacity' => 68 ],
        ],
        'pearl_gold' => [
            'label'     => 'Pearl & Gold (terang)',
            'tone'      => 'light',
            'primary'   => '#3A342E',
            'accent'    => '#B8923A',
            'text'      => '#2A2622',
            'text_soft' => '#6B645C',
            'cream'     => '#FFFCF7',
            'overlays'  => [ 'hero_overlay_opacity' => 46, 'subpage_overlay' => 62, 'banner_overlay' => 58, 'popup_overlay_opacity' => 62 ],
        ],
        'sage_ivory' => [
            'label'     => 'Sage & Ivory (terang)',
            'tone'      => 'light',
            'primary'   => '#2F4A3E',
            'accent'    => '#7FAF8E',
            'text'      => '#1F2E26',
            'text_soft' => '#5A6F62',
            'cream'     => '#F6FAF7',
            'overlays'  => [ 'hero_overlay_opacity' => 44, 'subpage_overlay' => 60, 'banner_overlay' => 56, 'popup_overlay_opacity' => 60 ],
        ],
        'sand_copper' => [
            'label'     => 'Sand & Copper (terang)',
            'tone'      => 'light',
            'primary'   => '#4A3F35',
            'accent'    => '#C4885C',
            'text'      => '#332A24',
            'text_soft' => '#7A6B60',
            'cream'     => '#FBF7F0',
            'overlays'  => [ 'hero_overlay_opacity' => 45, 'subpage_overlay' => 61, 'banner_overlay' => 57, 'popup_overlay_opacity' => 61 ],
        ],
        'mist_blue' => [
            'label'     => 'Mist & Blue (terang)',
            'tone'      => 'light',
            'primary'   => '#2A3D52',
            'accent'    => '#5B8DEF',
            'text'      => '#1E2A36',
            'text_soft' => '#5A6B7A',
            'cream'     => '#F5F9FD',
            'overlays'  => [ 'hero_overlay_opacity' => 43, 'subpage_overlay' => 58, 'banner_overlay' => 55, 'popup_overlay_opacity' => 58 ],
        ],
        'linen_plum' => [
            'label'     => 'Linen & Plum (terang)',
            'tone'      => 'light',
            'primary'   => '#4A3348',
            'accent'    => '#C97B84',
            'text'      => '#2E222C',
            'text_soft' => '#6E5E68',
            'cream'     => '#FDF8F6',
            'overlays'  => [ 'hero_overlay_opacity' => 44, 'subpage_overlay' => 60, 'banner_overlay' => 56, 'popup_overlay_opacity' => 60 ],
        ],
    ];
}

/**
 * Semua field opsi yang diisi saat memilih palet (untuk admin + simpan).
 *
 * @return array<string, string|int>|null
 */
function ptprm_color_preset_bundle( string $slug ): ?array {
    $presets = ptprm_color_theme_presets();
    if ( ! isset( $presets[ $slug ] ) ) {
        return null;
    }
    $p = $presets[ $slug ];
    return array_merge(
        [
            'color_primary'    => $p['primary'],
            'color_accent'     => $p['accent'],
            'color_text'       => $p['text'],
            'color_text_soft'  => $p['text_soft'],
            'color_cream'      => $p['cream'],
        ],
        ptprm_derive_brand_colors( $p['primary'], $p['accent'], $p['overlays'] ?? [] )
    );
}

function ptprm_font_choices() {
    return [
        'global'             => __( '— pakai font global —', 'ptsbi-premium' ),
        'Playfair Display'   => 'Playfair Display, serif',
        'Cormorant Garamond' => 'Cormorant Garamond, serif',
        'Lora'               => 'Lora, serif',
        'Merriweather'       => 'Merriweather, serif',
        'Inter'              => 'Inter, sans-serif',
        'Plus Jakarta Sans'  => 'Plus Jakarta Sans, sans-serif',
        'Poppins'            => 'Poppins, sans-serif',
        'Montserrat'         => 'Montserrat, sans-serif',
        'Manrope'            => 'Manrope, sans-serif',
        'Open Sans'          => 'Open Sans, sans-serif',
    ];
}

/**
 * Pilihan object-position untuk gambar hero (nilai = nilai CSS).
 */
function ptprm_hero_img_position_choices(): array {
    return [
        'center 35%'    => __( 'Tengah-atas (rekomendasi wajah)', 'ptsbi-premium' ),
        'center center' => __( 'Tengah', 'ptsbi-premium' ),
        'center top'    => __( 'Atas', 'ptsbi-premium' ),
        'center bottom' => __( 'Bawah', 'ptsbi-premium' ),
        'left center'   => __( 'Kiri', 'ptsbi-premium' ),
        'right center'  => __( 'Kanan', 'ptsbi-premium' ),
    ];
}

function ptprm_sanitize_hero_img_position( $value ): string {
    $value   = trim( (string) $value );
    $allowed = array_keys( ptprm_hero_img_position_choices() );
    return in_array( $value, $allowed, true ) ? $value : 'center 35%';
}

function ptprm_sanitize_hero_height_mode( $value ): string {
    $value   = sanitize_key( (string) $value );
    $allowed = [ 'small', 'standard', 'tall', 'full', 'custom' ];
    return in_array( $value, $allowed, true ) ? $value : 'standard';
}

function ptprm_icon_choices() {
    return [
        'none'     => __( '— tanpa ikon —', 'ptsbi-premium' ),
        'whatsapp' => 'WhatsApp',
        'arrow'    => 'Panah',
        'phone'    => 'Telepon',
        'mail'     => 'Email',
        'users'    => 'Anggota',
        'heart'    => 'Hati',
        'feather'  => 'Bulu',
        'hands'    => 'Tangan',
        'pin'      => 'Pin lokasi',
        'clock'    => 'Jam',
    ];
}

function ptprm_defaults() {
    return [
        'enabled'           => 1,

        // BRAND
        'org_name'          => 'Organisasi Premium',
        'pattern_style'     => 'gorga',
        'color_primary'     => '#0A1F3D',
        'color_accent'      => '#C9A44C',
        'color_text'        => '#1C1C1C',
        'color_text_soft'   => '#4A4A4A',
        'color_cream'       => '#FAF6EE',
        'font_heading'      => 'Playfair Display',
        'font_body'         => 'Plus Jakarta Sans',
        'container_max'     => 1200,

        /* -------- TIPOGRAFI GLOBAL (Beranda + Sub-halaman) -------- */
        // Beranda (section umum)
        'type_h2_size'        => 36,
        'type_h2_size_m'      => 28,
        'type_h2_lh'          => 118,   // x100, so 1.18
        'type_lead_size'      => 17,
        'type_lead_size_m'    => 15,
        'type_lead_lh'        => 170,
        'type_eyebrow_size'   => 13,
        'type_eyebrow_size_m' => 12,
        'type_body_size'      => 16,
        'type_body_size_m'    => 15,
        'type_body_lh'        => 175,
        'type_li_size'        => 16,
        'type_li_size_m'      => 15,

        // Sub-halaman (konten the_content)
        'type_sub_h2_size'    => 30,
        'type_sub_h2_size_m'  => 24,
        'type_sub_h3_size'    => 22,
        'type_sub_h3_size_m'  => 20,
        'type_sub_body_size'  => 16,
        'type_sub_body_size_m'=> 15,
        'type_sub_body_lh'    => 178,
        'type_sub_li_size'    => 16,
        'type_sub_li_size_m'  => 15,

        // CONTACT
        'whatsapp'          => '',
        'address'           => '',
        'hours'             => 'Senin–Jumat, 09.00–17.00 WIB',
        'fb_url'            => '',
        'ig_url'            => '',
        'yt_url'            => '',
        'tt_url'            => '',

        /* -------- HERO -------- */
        'hero_show'                  => 1,
        'hero_img_desktop'           => '',
        'hero_img_tablet'            => '',
        'hero_img_mobile'            => '',
        'hero_slide_1'               => '',
        'hero_slide_2'               => '',
        'hero_slide_3'               => '',
        'hero_slide_4'               => '',
        'hero_slide_5'               => '',
        'hero_slideshow'             => 1,
        'hero_slideshow_interval'    => 5000,
        'hero_slideshow_pause'       => 1,
        'hero_slideshow_dots'        => 1,
        'hero_img_position'          => 'center 35%',
        'hero_overlay_opacity'       => 55,
        'hero_overlay_color'         => '#0A1F3D',
        'hero_overlay_gradient'      => 1,
        'hero_height_mode'           => 'full', // small | standard | tall | full | custom
        'hero_height_desktop'        => 720,
        'hero_height_mobile'         => 560,
        'hero_text_align_h'          => 'left',
        'hero_text_align_v'          => 'top',
        'hero_text_max_width'        => 620,
        'hero_text_gap'              => 18,
        'hero_pad_top'               => '',
        'hero_pad_bottom'            => '',
        'hero_animation'             => 'fade-up',

        'hero_eyebrow_show'          => 1,
        'hero_eyebrow_text'          => 'SELAMAT DATANG',
        'hero_eyebrow_font'          => 'global',
        'hero_eyebrow_size'          => 14,
        'hero_eyebrow_size_mobile'   => 12,
        'hero_eyebrow_weight'        => '600',
        'hero_eyebrow_color'         => '#C9A44C',
        'hero_eyebrow_letter_spacing'=> 24,
        'hero_eyebrow_style'         => 'uppercase',
        'hero_eyebrow_shadow'        => 0,

        'hero_title_show'            => 1,
        'hero_title_text'            => 'Wadah Kebersamaan Keluarga Besar yang Elegan',
        'hero_title_font'            => 'global',
        'hero_title_size'            => 64,
        'hero_title_size_mobile'     => 36,
        'hero_title_weight'          => '700',
        'hero_title_color'           => '#FFFFFF',
        'hero_title_letter_spacing'  => 0,
        'hero_title_style'           => 'normal',
        'hero_title_shadow'          => 1,
        'hero_title_line_height'     => 112, // x100, so 1.12

        'hero_sub_show'              => 1,
        'hero_sub_text'              => 'Mempererat persaudaraan, melestarikan adat istiadat, dan saling mendukung antar keluarga besar di seluruh wilayah Indonesia.',
        'hero_sub_font'              => 'global',
        'hero_sub_size'              => 18,
        'hero_sub_size_mobile'       => 15,
        'hero_sub_weight'            => '400',
        'hero_sub_color'             => '#E5E7EB',
        'hero_sub_letter_spacing'    => 0,
        'hero_sub_style'             => 'normal',
        'hero_sub_shadow'            => 1,
        'hero_sub_line_height'       => 170,

        // CTA 1
        'cta1_show'          => 1,
        'cta1_label'         => 'Bergabung Sekarang',
        'cta1_url'           => '#daftar-anggota',
        'cta1_target'        => '_self',
        'cta1_icon'          => 'arrow',
        'cta1_style'         => 'solid',
        'cta1_bg'            => '#C9A44C',
        'cta1_color'         => '#0A1F3D',
        'cta1_bg_hover'      => '#E0BC65',
        'cta1_color_hover'   => '#0A1F3D',
        'cta1_radius'        => 10,
        'cta1_size'          => 'medium',

        // CTA 2
        'cta2_show'          => 1,
        'cta2_label'         => 'Kirim Pertanyaan',
        'cta2_url'           => '',
        'cta2_target'        => '_blank',
        'cta2_icon'          => 'whatsapp',
        'cta2_style'         => 'ghost',
        'cta2_bg'            => 'transparent',
        'cta2_color'         => '#FFFFFF',
        'cta2_bg_hover'      => '#FFFFFF',
        'cta2_color_hover'   => '#0A1F3D',
        'cta2_radius'        => 10,
        'cta2_size'          => 'medium',
        'cta2_mode'          => 'inquiry', // link | inquiry
        'cta2_inquiry_title'   => 'Kirim Pertanyaan',
        'cta2_inquiry_hint'    => 'Tulis pertanyaan Anda — akan dikirim lewat WhatsApp.',
        'cta2_inquiry_default' => 'Halo, saya ingin informasi lebih lanjut tentang organisasi ini.',
        'cta2_inquiry_submit'  => 'Kirim via WhatsApp',

        /* -------- HEADER (logo & menu premium) -------- */
        'header_use_plugin'          => 1,
        'header_logo'                => '',
        'header_logo_max_height'     => 48,
        'header_menu_font_size'      => 15,
        'header_layout'              => 'split',     // classic | centered | split | minimal
        'header_nav_style'           => 'pill',      // pill | underline | plain
        'header_menu_source'         => 'custom',    // custom | wp
        'header_wp_menu'             => 0,
        'header_menu_items'          => '',
        'header_submenu_max'         => 15,
        'header_show_cta'            => 1,
        'header_cta_label'           => 'Rumah Anggota',
        'header_cta_url'             => '/rumah-anggota/',
        'header_cta_style'           => 'accent',    // accent | outline | ghost
        'header_bg_color'            => '',
        'header_text_color'          => '#FFFFFF',
        'header_transparent_home'    => 0,
        'header_hide_theme'          => 1,

        /* -------- SITUS (header sticky, CTA halaman khusus — opsional) -------- */
        'sticky_header'              => 1,
        'tarombo_app_url'            => '',
        'tarombo_cta_title'          => 'Aplikasi / layanan terkait',
        'tarombo_cta_lead'           => 'Kunjungi aplikasi atau layanan digital organisasi kami.',
        'enable_tarombo_page_button' => 0,
        'tarombo_page_button_label'  => 'Buka aplikasi',
        'tarombo_page_slug'          => 'layanan',
        'tarombo_digital_show'       => 1,
        'tarombo_digital_eyebrow'    => 'Fitur Unggulan',
        'tarombo_digital_title'      => 'Kebanggaan Keluarga Besar Kami',
        'tarombo_digital_subtitle'   => 'Tarombo Digital',
        'tarombo_digital_preview_url'=> 'https://tarombo.ptsbi.org',
        'tarombo_digital_iframe_height' => 560,
        'tarombo_digital_new_tab_name' => 'Tarombo',
        '_site_fingerprint'          => '',

        /* -------- ABOUT -------- */
        'about_show'      => 1,
        'about_eyebrow'   => 'TENTANG KAMI',
        'about_title'     => 'Mengenal Organisasi Kami',
        'about_body'      => "Kami adalah wadah kebersamaan keluarga besar yang berkomitmen mempererat persaudaraan, melestarikan adat istiadat Batak, dan saling mendukung dalam setiap kegiatan keluarga.\n\nMelalui kepengurusan dan kegiatan rutin, kami menjaga warisan budaya dan menyatukan generasi.",
        'about_image'     => '',
        'about_layout'    => 'image-left',
        'about_cta_label' => 'Pelajari Selengkapnya',
        'about_cta_url'   => '/tentang-kami/',

        /* -------- VALUES -------- */
        'values_show'      => 1,
        'values_eyebrow'   => 'NILAI-NILAI KAMI',
        'values_title'     => 'Fondasi yang Kami Pegang Bersama',
        'values_subtitle'  => 'Nilai-nilai yang menjadi pedoman organisasi kami.',
        'values_hover_hint' => 'Arahkan kursor untuk penjelasan',
        'values_items'     => '', // Diisi otomatis dari migrate jika kosong

        /* -------- ANGGOTA (setelah Hero) -------- */
        'members_register_show'  => 1,
        'members_directory_show' => 1,
        'members_per_page'       => 25,
        'portal_login_slug'      => 'rumah-anggota',
        'members_login_slug'     => 'rumah-anggota',
        'members_portal_slug'    => 'area-anggota',
        'admin_login_slug'       => 'masuk-pengurus',
        'admin_portal_slug'      => 'panel-pengurus',

        /* -------- PENDAFTARAN ANGGOTA (Approval) -------- */
        // 1 = pendaftaran langsung disetujui (tidak perlu admin menekan approve).
        'members_register_auto_approve'   => 0,
        // Notifikasi hasil approval: email (wp_mail) dan/atau tautan WhatsApp (wa.me).
        'members_register_notify_email'  => 1,
        'members_register_notify_wa'     => 1,

        /* -------- Membership API (service terpisah) -------- */
        'membership_api_enabled'         => 0,
        'membership_api_base_url'        => '',
        'membership_api_integration_key' => '',
        'membership_api_timeout_sec'     => 15,

        /* -------- HOME ORDER -------- */
        // JSON array of slugs: about, values, team, activities, gallery, stats, banner, tarombo_digital, visit, faq
        'home_sections_order' => '',

        /* -------- ACTIVITIES -------- */
        'activities_show'        => 1,
        'activities_eyebrow'     => 'KEGIATAN KAMI',
        'activities_title'       => 'Kabar dan Kegiatan Terbaru',
        'activities_subtitle'    => 'Ikuti pembaruan kegiatan, agenda, dan informasi terkini dari keluarga besar.',
        'activities_count'       => 3,
        'activities_category'    => 0,
        'activities_more_label'  => 'Lihat Semua Kegiatan',
        'activities_more_url'    => '',

        /* -------- STATS -------- */
        'stats_show'        => 1,
        'stats_eyebrow'     => 'STATISTIK',
        'stats_title'       => 'Organisasi Kami dalam Angka',
        'stats_a_num'       => '10+',
        'stats_a_label'     => 'Cabang Wilayah',
        'stats_b_num'       => '500+',
        'stats_b_label'     => 'Kepala Keluarga',
        'stats_c_num'       => '1000+',
        'stats_c_label'     => 'Anggota Aktif',
        'stats_d_num'       => '20+',
        'stats_d_label'     => 'Tahun Kebersamaan',
        'stats_animate'     => 1,

        /* -------- VISIT -------- */
        'visit_show'         => 1,
        'visit_eyebrow'      => 'KUNJUNGI KAMI',
        'visit_title'        => 'Mari Terhubung & Bertemu Bersama Keluarga Besar',
        'visit_intro'        => 'Kami terbuka untuk silaturahmi, koordinasi kegiatan, dan masukan dari seluruh anggota.',
        'visit_show_address' => 1,
        'visit_show_hours'   => 1,
        'visit_show_wa'      => 1,
        'visit_map_url'      => '',

        /* -------- FOOTER -------- */
        'footer_show'        => 1,
        'footer_tagline'     => 'Wadah kebersamaan keluarga besar untuk mempererat persaudaraan, melestarikan budaya, dan saling mendukung.',
        'footer_col1_label'  => 'Tautan Cepat',
        'footer_col1_links'  => "Beranda|/\nTentang Kami|/tentang-kami/\nProgram|/program/\nKegiatan|/kegiatan/",
        'footer_col2_label'  => 'Lainnya',
        'footer_col2_links'  => "Kontak|/kontak/\nGaleri|/galeri/",
        'footer_show_social' => 1,
        'footer_copyright'   => '© {year} {org}. Seluruh hak cipta dilindungi.',

        /* -------- TEAM (Pengurus) -------- */
        'team_show'      => 1,
        'team_eyebrow'   => 'PENGURUS',
        'team_title'     => 'Tim Kepengurusan',
        'team_subtitle'  => 'Wajah-wajah yang menggerakkan organisasi kami.',
        'team_columns'   => 4,
        'team_layout'    => 'grid', // grid | grouped
        'team_items'     => '',
        'team_more_label' => 'Lihat Struktur Lengkap',
        'team_more_url'   => '/struktur-organisasi/',
        'team_photo_style' => 'compact', // compact | card

        /* -------- PENGURUS WILAYAH (box link) -------- */
        'wilayah_show'    => 1,
        'wilayah_title'   => 'Pengurus Wilayah',
        'wilayah_intro'   => 'Perwakilan pengurus di berbagai wilayah.',
        'wilayah_items'   => '',

        /* -------- GALLERY -------- */
        'gallery_show'      => 0,
        'gallery_eyebrow'   => 'GALERI',
        'gallery_title'     => 'Momen Kebersamaan',
        'gallery_subtitle'  => 'Dokumentasi kegiatan dan pertemuan keluarga besar.',
        'gallery_ids'       => '', // comma-separated attachment IDs
        'gallery_columns'   => 4,
        'gallery_lightbox'  => 1,
        'gallery_layout'    => 'grid',   // grid | slider | marquee
        'gallery_autoplay'  => 4500,     // ms (slider)
        'gallery_arrows'    => 1,        // slider arrow nav
        'gallery_dots'      => 1,        // slider dots
        'gallery_speed'     => 35,       // s (marquee duration)
        'gallery_pause'     => 1,        // pause on hover (both modes)

        /* -------- BANNER CTA -------- */
        'banner_show'      => 0,
        'banner_image'     => '',
        'banner_overlay'   => 70,
        'banner_eyebrow'   => 'AYO BERGABUNG',
        'banner_title'     => 'Jadilah Bagian dari Keluarga Besar Kami',
        'banner_subtitle'  => 'Daftarkan diri Anda dan ikut serta dalam kegiatan, silaturahmi, dan pelestarian budaya.',
        'banner_cta_label' => 'Bergabung Sekarang',
        'banner_cta_url'   => '',
        'banner_cta2_show' => 0,
        'banner_cta2_label'=> 'Hubungi WA',
        'banner_cta2_url'  => '',

        /* -------- FAQ -------- */
        'faq_show'      => 1,
        'faq_eyebrow'   => 'PERTANYAAN UMUM',
        'faq_title'     => 'Hal yang Sering Ditanyakan',
        'faq_subtitle'  => 'Belum menemukan jawaban? Hubungi kami via WhatsApp.',
        'faq_1_q' => 'Bagaimana cara bergabung?', 'faq_1_a' => 'Hubungi sekretariat via WhatsApp atau formulir Kontak. Tim kami akan menjelaskan persyaratan dan proses pendaftaran.',
        'faq_2_q' => 'Apakah ada iuran anggota?', 'faq_2_a' => 'Iuran disesuaikan program organisasi. Rincian diberikan saat orientasi anggota baru.',
        'faq_3_q' => 'Kapan kegiatan rutin diadakan?', 'faq_3_a' => 'Agenda diumumkan di halaman Kegiatan dan kanal komunikasi resmi organisasi.',
        'faq_4_q' => 'Bisakah mitra eksternal berkolaborasi?', 'faq_4_a' => 'Ya. Kirim proposal kerja sama ke pengurus untuk ditinjau.',
        'faq_5_q' => 'Di mana melihat struktur pengurus?', 'faq_5_a' => 'Lihat section Pengurus di beranda atau halaman Struktur Organisasi.',
        'faq_6_q' => '', 'faq_6_a' => '',
        'faq_7_q' => '', 'faq_7_a' => '',
        'faq_8_q' => '', 'faq_8_a' => '',
        'faq_9_q' => '', 'faq_9_a' => '',
        'faq_10_q' => '', 'faq_10_a' => '',

        /* -------- POP-UP IKLAN -------- */
        'popup_enabled'        => 0,
        'popup_show_home'      => 1,
        'popup_show_subpage'   => 0,
        'popup_delay'          => 3000,      // ms
        'popup_frequency'      => 'session', // session | day | always
        'popup_orientation'    => 'portrait',// portrait | landscape
        'popup_image'          => '',        // legacy / image #1
        'popup_image_2'        => '',
        'popup_image_3'        => '',
        'popup_link_1'         => '',
        'popup_link_2'         => '',
        'popup_link_3'         => '',
        'popup_slide_interval' => 4500,      // ms, rotasi otomatis antar foto
        'popup_overlay_opacity'=> 70,
        'popup_eyebrow'        => 'PENGUMUMAN',
        'popup_title'          => 'Bergabunglah dengan Kami',
        'popup_body'           => 'Daftarkan diri Anda menjadi anggota aktif organisasi kami dan ikut serta dalam kegiatan-kegiatan terbaru.',
        'popup_cta_label'      => 'Selengkapnya',
        'popup_cta_url'        => '',
        'popup_cta_target'     => '_self',
        'popup_show_close'     => 1,
        'popup_close_text'     => 'Tidak sekarang',

        /* -------- SUB-PAGE -------- */
        'subpage_hero_show'     => 1,
        'subpage_breadcrumb'    => 1,
        'subpage_ornament'      => 1,
        'subpage_gorga'         => 1,
        'subpage_overlay'       => 70,
        'subpage_eyebrow_auto'  => 1,
        'subpage_default_intro' => '',
    ];
}

/**
 * Slug section beranda yang valid (urutan default di ptprm_default_home_sections_order).
 *
 * @return list<string>
 */
/**
 * Section beranda yang tidak lagi ditawarkan di admin (tetap diabaikan di frontend).
 *
 * @return list<string>
 */
function ptprm_retired_home_section_slugs(): array {
    return [ 'banner', 'faq' ];
}

/**
 * Section beranda aktif (admin + frontend).
 *
 * @return list<string>
 */
function ptprm_home_section_slugs(): array {
    return [ 'about', 'values', 'team', 'activities', 'gallery', 'stats', 'tarombo_digital', 'visit' ];
}

/**
 * Label admin untuk slug section beranda.
 *
 * @return array<string, string>
 */
function ptprm_home_section_labels(): array {
    return [
        'about'           => 'Tentang',
        'values'          => 'Nilai-Nilai',
        'team'            => 'Pengurus',
        'activities'      => 'Kegiatan',
        'gallery'         => 'Galeri',
        'stats'           => 'Statistik',
        'tarombo_digital' => 'Tarombo Digital',
        'visit'           => 'Kunjungi',
    ];
}

/**
 * Default urutan section beranda (setelah Hero).
 *
 * @return list<string>
 */
function ptprm_default_home_sections_order(): array {
    return [ 'about', 'values', 'team', 'activities', 'gallery', 'stats', 'tarombo_digital', 'visit' ];
}

/**
 * Ambil urutan section beranda dari opsi (fallback ke default).
 *
 * @param array<string, mixed>|null $o Options.
 * @return list<string>
 */
function ptprm_get_home_sections_order( ?array $o = null ): array {
    $o   = $o ?? ptprm_options();
    $raw = $o['home_sections_order'] ?? '';

    $arr = null;
    if ( is_array( $raw ) ) {
        $arr = $raw;
    } elseif ( is_string( $raw ) && trim( $raw ) !== '' ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            $arr = $decoded;
        }
    }

    $valid = ptprm_home_section_slugs();
    $out   = [];
    if ( is_array( $arr ) ) {
        foreach ( $arr as $v ) {
            $slug = sanitize_key( (string) $v );
            if ( in_array( $slug, $valid, true ) && ! in_array( $slug, $out, true ) ) {
                $out[] = $slug;
            }
        }
    }

    // Tambahkan yang hilang (upgrade aman)
    foreach ( ptprm_default_home_sections_order() as $slug ) {
        if ( in_array( $slug, $valid, true ) && ! in_array( $slug, $out, true ) ) {
            $out[] = $slug;
        }
    }

    return $out;
}

/**
 * Bentuk data yang disimpan di wp_options — sama di semua situs.
 * ptsbi.org saat simpan masih jalan: values_items & team_items berisi JSON string (bukan '').
 * Reset universal versi lama menulis '' → form admin rusak / simpan gagal.
 *
 * @param array<string, mixed> $o Raw option row.
 * @return array<string, mixed>
 */
/**
 * Apakah array hasil decode JSON mirip opsi plugin (bukan file acak).
 *
 * @param mixed $data Decoded JSON.
 */
function ptprm_is_importable_option_array( $data ): bool {
    if ( ! is_array( $data ) ) {
        return false;
    }
    $keys = array_keys( $data );
    if ( in_array( 'org_name', $keys, true ) || in_array( 'values_items', $keys, true ) || in_array( 'hero_title_text', $keys, true ) ) {
        return true;
    }
    return count( $keys ) >= 12;
}

/**
 * Gabungkan cadangan JSON ke format penyimpanan situs saat ini.
 *
 * @param array<string, mixed> $imported Raw backup row.
 * @return array<string, mixed>
 */
function ptprm_import_option_from_array( array $imported ): array {
    $imported['_site_fingerprint'] = class_exists( 'PTPRM_Bootstrap' ) ? PTPRM_Bootstrap::site_fingerprint() : '';
    return ptprm_normalize_option_for_storage( array_merge( ptprm_defaults(), $imported ) );
}

function ptprm_normalize_option_for_storage( array $o ): array {
    $o = array_merge( ptprm_defaults(), $o );

    $values = [];
    if ( ! empty( $o['values_items'] ) && is_string( $o['values_items'] ) ) {
        $decoded = json_decode( $o['values_items'], true );
        if ( is_array( $decoded ) ) {
            $values = ptprm_sanitize_values_items( $decoded );
        }
    } elseif ( ! empty( $o['values_items'] ) && is_array( $o['values_items'] ) ) {
        $values = ptprm_sanitize_values_items( $o['values_items'] );
    }
    if ( ! $values ) {
        $values = ptprm_migrate_values_from_legacy( $o );
    }
    $o['values_items'] = wp_json_encode( $values, JSON_UNESCAPED_UNICODE );

    // Pengurus — simpan data jika ada; tampilkan sesuai team_show.
    $team_raw = isset( $o['team_items'] ) ? trim( (string) $o['team_items'] ) : '';
    if ( $team_raw !== '' && $team_raw !== '[]' ) {
        if ( is_string( $o['team_items'] ) ) {
            $decoded = json_decode( $o['team_items'], true );
            $team    = is_array( $decoded ) ? ptprm_sanitize_team_items( $decoded ) : [];
        } else {
            $team = ptprm_sanitize_team_items( $o['team_items'] );
        }
        $o['team_items'] = wp_json_encode( $team ?: [], JSON_UNESCAPED_UNICODE );
    } else {
        $o['team_items'] = '[]';
    }

    $wilayah_raw = isset( $o['wilayah_items'] ) ? trim( (string) $o['wilayah_items'] ) : '';
    if ( $wilayah_raw !== '' && $wilayah_raw !== '[]' ) {
        $decoded = is_string( $o['wilayah_items'] ) ? json_decode( $o['wilayah_items'], true ) : $o['wilayah_items'];
        $wilayah = is_array( $decoded ) ? ptprm_sanitize_wilayah_items( $decoded ) : [];
        $o['wilayah_items'] = wp_json_encode( $wilayah ?: [], JSON_UNESCAPED_UNICODE );
    } else {
        $o['wilayah_items'] = '[]';
    }

    $o['banner_show'] = 0;
    $o['faq_show']    = 0;

    $sub_max = ptprm_header_submenu_max( $o );
    $menu    = [];
    if ( ! empty( $o['header_menu_items'] ) && is_string( $o['header_menu_items'] ) ) {
        $decoded = json_decode( $o['header_menu_items'], true );
        if ( is_array( $decoded ) ) {
            $menu = ptprm_sanitize_header_menu_items( $decoded, 0, $sub_max );
        }
    } elseif ( ! empty( $o['header_menu_items'] ) && is_array( $o['header_menu_items'] ) ) {
        $menu = ptprm_sanitize_header_menu_items( $o['header_menu_items'], 0, $sub_max );
    }
    if ( ! $menu && class_exists( 'PTPRM_Pages' ) ) {
        $menu = PTPRM_Pages::default_menu_items_for_options();
    }
    $o['header_menu_items'] = wp_json_encode( $menu, JSON_UNESCAPED_UNICODE );

    if ( empty( $o['_site_fingerprint'] ) && class_exists( 'PTPRM_Bootstrap' ) ) {
        $o['_site_fingerprint'] = PTPRM_Bootstrap::site_fingerprint();
    }

    return $o;
}

function ptprm_options() {
    $saved = get_option( PTPRM_OPTION, [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }
    return ptprm_normalize_option_for_storage( $saved );
}

/**
 * Perbaiki DB sekali jika masih format lama (repeater kosong) — tanpa hapus teks kustom lain.
 */
function ptprm_maybe_repair_stored_option(): void {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( get_transient( 'ptprm_storage_repaired_' . PTPRM_VERSION ) ) {
        return;
    }
    $raw = get_option( PTPRM_OPTION, null );
    if ( ! is_array( $raw ) ) {
        set_transient( 'ptprm_storage_repaired_' . PTPRM_VERSION, 1, WEEK_IN_SECONDS );
        return;
    }
    $needs = ( empty( $raw['values_items'] ) || empty( $raw['header_menu_items'] ) );
    if ( $needs ) {
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( $raw ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );
        wp_cache_delete( 'alloptions', 'options' );
    }
    set_transient( 'ptprm_storage_repaired_' . PTPRM_VERSION, 1, WEEK_IN_SECONDS );
}

/**
 * @return list<array{icon:string,title:string,desc:string}>
 */
function ptprm_migrate_values_from_legacy( array $o ): array {
    $items = [];
    for ( $i = 1; $i <= 4; $i++ ) {
        $title = trim( (string) ( $o[ "values_{$i}_title" ] ?? '' ) );
        if ( $title === '' ) {
            continue;
        }
        $items[] = [
            'icon'  => (string) ( $o[ "values_{$i}_icon" ] ?? 'users' ),
            'title' => $title,
            'desc'  => (string) ( $o[ "values_{$i}_desc" ] ?? '' ),
        ];
    }
    if ( ! $items ) {
        $items = [
            [
                'icon'  => 'users',
                'title' => 'Kebersamaan',
                'desc'  => 'Menjaga silaturahmi dan dukungan antar anggota dalam setiap kegiatan.',
            ],
            [
                'icon'  => 'heart',
                'title' => 'Kepedulian',
                'desc'  => 'Saling membantu saat ada kebutuhan mendesak atau program sosial bersama.',
            ],
            [
                'icon'  => 'feather',
                'title' => 'Pelestarian Budaya',
                'desc'  => 'Melestarikan adat dan tradisi agar tetap relevan bagi generasi muda.',
            ],
            [
                'icon'  => 'hands',
                'title' => 'Kolaborasi',
                'desc'  => 'Bekerja sama dengan cabang dan mitra untuk program yang berdampak.',
            ],
        ];
    }
    return $items;
}

/**
 * @return list<array{image:string,name:string,role:string,url:string,group:string}>
 */
function ptprm_migrate_team_from_legacy( array $o ): array {
    $items = [];
    for ( $i = 1; $i <= 8; $i++ ) {
        $name = trim( (string) ( $o[ "team_{$i}_name" ] ?? '' ) );
        if ( $name === '' ) {
            continue;
        }
        $items[] = [
            'image' => (string) ( $o[ "team_{$i}_image" ] ?? '' ),
            'name'  => $name,
            'role'  => (string) ( $o[ "team_{$i}_role" ] ?? '' ),
            'url'   => (string) ( $o[ "team_{$i}_url" ] ?? '' ),
            'group' => '',
        ];
    }
    if ( ! $items ) {
        $items = [
            [ 'image' => '', 'name' => 'Ketua Umum', 'role' => 'Ketua Umum', 'url' => '', 'group' => 'Pengurus Inti' ],
            [ 'image' => '', 'name' => 'Sekretaris', 'role' => 'Sekretaris', 'url' => '', 'group' => 'Pengurus Inti' ],
            [ 'image' => '', 'name' => 'Bendahara', 'role' => 'Bendahara', 'url' => '', 'group' => 'Pengurus Inti' ],
            [ 'image' => '', 'name' => 'Koordinator Program', 'role' => 'Koordinator', 'url' => '', 'group' => 'Bidang Program' ],
        ];
    }
    return $items;
}

function ptprm_get_values_items( ?array $o = null ): array {
    $o = $o ?? ptprm_options();
    if ( ! empty( $o['values_items'] ) ) {
        $decoded = json_decode( (string) $o['values_items'], true );
        if ( is_array( $decoded ) && $decoded ) {
            return ptprm_sanitize_values_items( $decoded );
        }
    }
    return ptprm_migrate_values_from_legacy( $o );
}

function ptprm_get_team_items( ?array $o = null ): array {
    $o = $o ?? ptprm_options();
    if ( ! empty( $o['team_items'] ) ) {
        $decoded = json_decode( (string) $o['team_items'], true );
        if ( is_array( $decoded ) ) {
            return ptprm_sanitize_team_items( $decoded );
        }
    }
    return ptprm_migrate_team_from_legacy( $o );
}

function ptprm_sanitize_values_items( $raw ): array {
    if ( is_string( $raw ) ) {
        $decoded = json_decode( wp_unslash( $raw ), true );
        $raw     = is_array( $decoded ) ? $decoded : [];
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    $icons = array_keys( ptprm_icon_choices() );
    $out   = [];
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $title = sanitize_text_field( $row['title'] ?? '' );
        if ( $title === '' ) {
            continue;
        }
        $icon = sanitize_text_field( $row['icon'] ?? 'users' );
        if ( ! in_array( $icon, $icons, true ) ) {
            $icon = 'users';
        }
        $out[] = [
            'icon'  => $icon,
            'title' => $title,
            'desc'  => sanitize_textarea_field( $row['desc'] ?? '' ),
        ];
        if ( count( $out ) >= 24 ) {
            break;
        }
    }
    return $out;
}

/**
 * Parse baris submenu: "Label | /url" (satu baris = satu item).
 *
 * @return list<array{label:string,url:string,target:string,highlight:int,children:array}>
 */
function ptprm_parse_menu_submenu_lines( string $text, int $max = 15 ): array {
    $max = max( 1, min( 20, $max ) );
    $out = [];
    foreach ( preg_split( '/\R/', $text ) ?: [] as $line ) {
        $line = trim( (string) $line );
        if ( $line === '' || strpos( $line, '|' ) === false ) {
            continue;
        }
        $parts = array_map( 'trim', explode( '|', $line, 2 ) );
        if ( $parts[0] === '' || ! isset( $parts[1] ) || $parts[1] === '' ) {
            continue;
        }
        $out[] = [
            'label'     => sanitize_text_field( $parts[0] ),
            'url'       => sanitize_text_field( $parts[1] ),
            'target'    => '_self',
            'highlight' => 0,
            'children'  => [],
        ];
        if ( count( $out ) >= $max ) {
            break;
        }
    }
    return $out;
}

/**
 * @param list<array<string, mixed>> $children Child rows.
 */
function ptprm_menu_children_to_lines( array $children ): string {
    $lines = [];
    foreach ( $children as $c ) {
        if ( ! is_array( $c ) ) {
            continue;
        }
        $label = trim( (string) ( $c['label'] ?? '' ) );
        if ( $label === '' ) {
            continue;
        }
        $url     = trim( (string) ( $c['url'] ?? '/' ) );
        $lines[] = $label . ' | ' . $url;
    }
    return implode( "\n", $lines );
}

/**
 * Sanitize satu tingkat submenu (tanpa rekursi).
 *
 * @return list<array{label:string,url:string,target:string,highlight:int,children:array}>
 */
function ptprm_sanitize_menu_child_items( $raw, int $max ): array {
    if ( is_string( $raw ) ) {
        $decoded = json_decode( wp_unslash( $raw ), true );
        $raw     = is_array( $decoded ) ? $decoded : ptprm_parse_menu_submenu_lines( $raw, $max );
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    $out = [];
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $label = sanitize_text_field( $row['label'] ?? '' );
        if ( $label === '' ) {
            continue;
        }
        $target = ( $row['target'] ?? '_self' ) === '_blank' ? '_blank' : '_self';
        $out[]  = [
            'label'     => $label,
            'url'       => sanitize_text_field( $row['url'] ?? '/' ),
            'target'    => $target,
            'highlight' => 0,
            'children'  => [],
        ];
        if ( count( $out ) >= $max ) {
            break;
        }
    }
    return $out;
}

/**
 * Batas item submenu per menu utama (tanpa memanggil ptprm_options — hindari rekursi).
 *
 * @param array<string, mixed>|null $o Options row.
 */
function ptprm_header_submenu_max( ?array $o = null ): int {
    if ( is_array( $o ) && array_key_exists( 'header_submenu_max', $o ) ) {
        return max( 1, min( 15, (int) $o['header_submenu_max'] ) );
    }
    return 15;
}

/**
 * @return list<array{label:string,url:string,target:string,highlight:int,children:array}>
 */
function ptprm_sanitize_header_menu_items( $raw, int $depth = 0, ?int $max_children = null ): array {
    if ( is_string( $raw ) ) {
        $decoded = json_decode( wp_unslash( $raw ), true );
        $raw     = is_array( $decoded ) ? $decoded : [];
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    if ( $max_children === null ) {
        $max_children = 15;
    }
    $out = [];
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $label = sanitize_text_field( $row['label'] ?? '' );
        if ( $label === '' ) {
            continue;
        }
        $children = [];
        if ( $depth === 0 ) {
            if ( ! empty( $row['children'] ) && is_array( $row['children'] ) ) {
                $children = ptprm_sanitize_menu_child_items( $row['children'], $max_children );
            } elseif ( isset( $row['submenu_lines'] ) && is_string( $row['submenu_lines'] ) ) {
                $children = ptprm_parse_menu_submenu_lines( $row['submenu_lines'], $max_children );
            }
        }
        $target = ( $row['target'] ?? '_self' ) === '_blank' ? '_blank' : '_self';
        $out[]  = [
            'label'     => $label,
            'url'       => sanitize_text_field( $row['url'] ?? '/' ),
            'target'    => $target,
            'highlight' => ( $depth === 0 && ! empty( $row['highlight'] ) ) ? 1 : 0,
            'children'  => $children,
        ];
        if ( $depth === 0 && count( $out ) >= 12 ) {
            break;
        }
        if ( $depth === 1 && count( $out ) >= $max_children ) {
            break;
        }
    }
    return $out;
}

function ptprm_get_header_menu_items( ?array $o = null ): array {
    $o = $o ?? ptprm_options();
    $sub_max = ptprm_header_submenu_max( $o );
    if ( ! empty( $o['header_menu_items'] ) ) {
        $decoded = json_decode( (string) $o['header_menu_items'], true );
        if ( is_array( $decoded ) && $decoded ) {
            return ptprm_sanitize_header_menu_items( $decoded, 0, $sub_max );
        }
    }
    if ( class_exists( 'PTPRM_Pages' ) ) {
        return PTPRM_Pages::default_menu_items_for_options();
    }
    return [];
}

function ptprm_sanitize_team_items( $raw ): array {
    if ( is_string( $raw ) ) {
        $decoded = json_decode( wp_unslash( $raw ), true );
        $raw     = is_array( $decoded ) ? $decoded : [];
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    $out = [];
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $name = sanitize_text_field( $row['name'] ?? '' );
        if ( $name === '' ) {
            continue;
        }
        $image = $row['image'] ?? '';
        if ( is_numeric( $image ) ) {
            $image = (string) (int) $image;
        } else {
            $image = esc_url_raw( (string) $image );
        }
        $out[] = [
            'image' => $image,
            'name'  => $name,
            'role'  => sanitize_text_field( $row['role'] ?? '' ),
            'url'   => esc_url_raw( $row['url'] ?? '' ),
            'group' => sanitize_text_field( $row['group'] ?? '' ),
        ];
        if ( count( $out ) >= 5 ) {
            break;
        }
    }
    return $out;
}

/**
 * @return list<array{label:string,url:string}>
 */
function ptprm_sanitize_wilayah_items( $raw ): array {
    if ( is_string( $raw ) ) {
        $decoded = json_decode( wp_unslash( $raw ), true );
        $raw     = is_array( $decoded ) ? $decoded : [];
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    $out = [];
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $label = sanitize_text_field( $row['label'] ?? '' );
        if ( $label === '' ) {
            continue;
        }
        $url = sanitize_text_field( (string) ( $row['url'] ?? '' ) );
        $out[] = [
            'label' => $label,
            'url'   => $url,
        ];
        if ( count( $out ) >= 20 ) {
            break;
        }
    }
    return $out;
}

/**
 * @return list<array{label:string,url:string}>
 */
function ptprm_get_wilayah_items( ?array $o = null ): array {
    $o = $o ?? ptprm_options();
    if ( ! empty( $o['wilayah_items'] ) ) {
        $decoded = json_decode( (string) $o['wilayah_items'], true );
        if ( is_array( $decoded ) ) {
            return ptprm_sanitize_wilayah_items( $decoded );
        }
    }
    return [];
}

function ptprm_get( $key, $default = '' ) {
    $o = ptprm_options();
    return $o[ $key ] ?? $default;
}

/**
 * Judul brand di footer — mengikuti Pengaturan → Umum (Judul situs).
 */
function ptprm_footer_brand_name(): string {
    $site = trim( (string) get_bloginfo( 'name' ) );
    if ( $site !== '' ) {
        return $site;
    }
    return class_exists( 'PTPRM_Bootstrap' ) ? PTPRM_Bootstrap::org_name() : '';
}

/**
 * Deskripsi brand footer: custom (footer_tagline) atau Slogan situs WordPress.
 *
 * @param array<string, mixed>|null $o Options row.
 */
function ptprm_footer_brand_description( ?array $o = null ): string {
    $o      = $o ?? ptprm_options();
    $custom = trim( (string) ( $o['footer_tagline'] ?? '' ) );
    if ( $custom !== '' ) {
        return $custom;
    }
    return trim( (string) get_bloginfo( 'description' ) );
}

function ptprm_resolve_font( $key, $fallback = '' ) {
    if ( ! $key || $key === 'global' ) return $fallback;
    $list = ptprm_font_choices();
    if ( isset( $list[ $key ] ) && $key !== 'global' ) {
        return $list[ $key ];
    }
    return $fallback;
}

function ptprm_wa_link( $number ) {
    $d = ptprm_wa_digits( $number );
    return $d ? 'https://wa.me/' . $d : '';
}

function ptprm_wa_digits( $number ) {
    $d = preg_replace( '/\D/', '', (string) $number );
    if ( ! $d ) {
        return '';
    }
    if ( strpos( $d, '62' ) !== 0 && strpos( $d, '0' ) === 0 ) {
        $d = '62' . substr( $d, 1 );
    }
    return $d;
}

/**
 * Extract Google Maps embed URL from either:
 *   - a raw iframe HTML (the snippet from Google Maps → Share → Embed)
 *   - a plain "src" URL
 * Returns a safe URL or empty string if nothing valid.
 */
function ptprm_extract_map_src( $value ) {
    $value = trim( (string) $value );
    if ( $value === '' ) {
        return '';
    }

    $value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

    if ( stripos( $value, '<iframe' ) !== false ) {
        if ( preg_match( '#\bsrc\s*=\s*["\']([^"\']+)["\']#i', $value, $m ) ) {
            $value = trim( $m[1] );
        } else {
            return '';
        }
    }

    if ( ! preg_match( '#^https?://#i', $value ) ) {
        return '';
    }

    if ( ! preg_match( '#^https?://([a-z0-9-]+\.)*google\.[a-z.]+/maps#i', $value )
        && ! preg_match( '#^https?://maps\.google\.#i', $value ) ) {
        return '';
    }

    return esc_url_raw( $value );
}

function ptprm_rumah_anggota_entry_url( string $redirect = '' ): string {
    if ( class_exists( 'PTPRM_Login_Portal' ) ) {
        return PTPRM_Login_Portal::entry_url( $redirect );
    }
    return home_url( '/rumah-anggota/' );
}

function ptprm_is_rumah_anggota_path( string $path ): bool {
    $path = trim( $path, '/' );
    if ( $path === '' ) {
        return false;
    }
    $slugs = [ 'rumah-anggota', 'masuk', 'masuk-pengurus' ];
    if ( class_exists( 'PTPRM_Login_Portal' ) ) {
        $slugs[] = PTPRM_Login_Portal::login_slug();
        $slugs   = array_merge( $slugs, PTPRM_Login_Portal::legacy_login_slugs() );
    }
    return in_array( $path, array_unique( $slugs ), true );
}

function ptprm_resolve_url( $url ) {
    if ( ! $url ) {
        return '';
    }
    $url = trim( $url );
    if ( $url === '#' ) {
        return $url;
    }
    if ( strpos( $url, '#' ) === 0 ) {
        return home_url( '/' ) . $url;
    }
    if ( strpos( $url, '//' ) === 0 ) {
        return $url;
    }

    $path = $url;
    if ( $url[0] === '/' ) {
        $resolved = home_url( $url );
        $path     = trim( (string) wp_parse_url( $resolved, PHP_URL_PATH ), '/' );
    } elseif ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
        $path = trim( $url, '/' );
    }

    if ( $path !== '' && ptprm_is_rumah_anggota_path( $path ) ) {
        return ptprm_rumah_anggota_entry_url();
    }

    if ( $url[0] === '/' ) {
        return home_url( $url );
    }
    if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
        return $url;
    }
    return home_url( '/' . ltrim( $url, '/' ) );
}

/**
 * Daftar URL gambar hero untuk slideshow (slot 1–5). Fallback ke gambar desktop lama.
 *
 * @param array<string, mixed>|null $o Options.
 * @return list<string>
 */
function ptprm_get_hero_slides( ?array $o = null ): array {
    $o      = $o ?? ptprm_options();
    $slides = [];
    for ( $i = 1; $i <= 5; $i++ ) {
        $url = ptprm_image_url( $o[ 'hero_slide_' . $i ] ?? '' );
        if ( $url !== '' ) {
            $slides[] = $url;
        }
    }
    if ( $slides === [] ) {
        $legacy = ptprm_image_url( $o['hero_img_desktop'] ?? '' );
        if ( $legacy !== '' ) {
            $slides[] = $legacy;
        }
    }
    return $slides;
}

function ptprm_image_url( $value ) {
    if ( ! $value ) return '';
    if ( is_numeric( $value ) ) {
        $u = wp_get_attachment_image_url( (int) $value, 'full' );
        return $u ? $u : '';
    }
    return esc_url_raw( $value );
}

function ptprm_icon_svg( $name ) {
    $icons = [
        'whatsapp' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.554-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.45L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.89 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.979-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.149-.174.198-.298.297-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.521.074-.793.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>',
        'arrow'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
        'phone'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
        'mail'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
        'users'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'heart'    => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
        'feather'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>',
        'hands'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11V6a2 2 0 1 1 4 0v5"/><path d="M13 11V4a2 2 0 1 1 4 0v8"/><path d="M17 12v-2a2 2 0 1 1 4 0v5a7 7 0 0 1-7 7H8a7 7 0 0 1-7-7v-2a2 2 0 1 1 4 0v2"/></svg>',
        'pin'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'clock'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22.675 0H1.325C.593 0 0 .593 0 1.325v21.351C0 23.407.593 24 1.325 24H12.82V14.706h-3.131v-3.622h3.131V8.413c0-3.1 1.894-4.788 4.659-4.788 1.325 0 2.464.099 2.795.143v3.24h-1.918c-1.504 0-1.796.715-1.796 1.762v2.31h3.587l-.467 3.622h-3.12V24h6.116c.731 0 1.324-.593 1.324-1.324V1.325C24 .593 23.407 0 22.675 0z"/></svg>',
        'instagram'=> '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.366.062 2.633.336 3.608 1.311.975.975 1.249 2.242 1.311 3.608.058 1.266.07 1.646.07 4.85s-.012 3.584-.07 4.85c-.062 1.366-.336 2.633-1.311 3.608-.975.975-2.242 1.249-3.608 1.311-1.266.058-1.646.07-4.85.07s-3.584-.012-4.85-.07c-1.366-.062-2.633-.336-3.608-1.311-.975-.975-1.249-2.242-1.311-3.608-.058-1.266-.07-1.646-.07-4.85s.012-3.584.07-4.85c.062-1.366.336-2.633 1.311-3.608.975-.975 2.242-1.249 3.608-1.311 1.266-.058 1.646-.07 4.85-.07zM12 0C8.741 0 8.332.014 7.052.072 5.775.13 4.602.405 3.635 1.372 2.668 2.339 2.393 3.512 2.335 4.789 2.277 6.069 2.263 6.478 2.263 9.737c0 3.259.014 3.668.072 4.948.058 1.277.333 2.45 1.3 3.417.967.967 2.14 1.242 3.417 1.3 1.28.058 1.689.072 4.948.072s3.668-.014 4.948-.072c1.277-.058 2.45-.333 3.417-1.3.967-.967 1.242-2.14 1.3-3.417.058-1.28.072-1.689.072-4.948s-.014-3.668-.072-4.948c-.058-1.277-.333-2.45-1.3-3.417C19.398.405 18.225.13 16.948.072 15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>',
        'youtube'  => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
        'tiktok'   => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V8.62a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.84 4.84 0 0 1-1.84-.05z"/></svg>',
    ];
    return $icons[ $name ] ?? '';
}

function ptprm_ornament_svg() {
    return '<svg viewBox="0 0 200 24" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" aria-hidden="true">' .
        '<line x1="0" y1="12" x2="80" y2="12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>' .
        '<line x1="120" y1="12" x2="200" y2="12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>' .
        '<path d="M85 12 L100 4 L115 12 L100 20 Z" fill="none" stroke="currentColor" stroke-width="1.6"/>' .
        '<circle cx="100" cy="12" r="2.6" fill="currentColor"/>' .
        '<circle cx="74" cy="12" r="1.6" fill="currentColor"/>' .
        '<circle cx="126" cy="12" r="1.6" fill="currentColor"/>' .
        '</svg>';
}

function ptprm_parse_links( $text ) {
    $out   = [];
    $lines = preg_split( "/\r\n|\r|\n/", (string) $text );
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( ! $line ) continue;
        $parts = array_map( 'trim', explode( '|', $line, 2 ) );
        if ( count( $parts ) >= 2 && $parts[0] !== '' ) {
            $out[] = [ 'label' => $parts[0], 'url' => $parts[1] ];
        }
    }
    return $out;
}
