<?php
/**
 * Helpers & default options for Section Studio.
 * Schema designed to be reusable across organization websites, not PTSBI-specific.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Available section types that Section Studio understands.
 * Each section has a slug + label + default settings.
 */
function ptsbi_pe_section_types() {
    return [
        'hero'    => __( 'Hero', 'ptsbi-premium-enhancer' ),
        'about'   => __( 'Tentang', 'ptsbi-premium-enhancer' ),
        'values'  => __( 'Nilai-Nilai', 'ptsbi-premium-enhancer' ),
        'news'    => __( 'Berita / Kegiatan', 'ptsbi-premium-enhancer' ),
        'stats'   => __( 'Statistik', 'ptsbi-premium-enhancer' ),
        'contact' => __( 'Kontak / Kunjungi', 'ptsbi-premium-enhancer' ),
        'footer'  => __( 'Footer', 'ptsbi-premium-enhancer' ),
    ];
}

/**
 * Font family list (label => CSS stack).
 */
function ptsbi_pe_font_choices() {
    return [
        'theme-default'      => __( '— Pakai font tema —', 'ptsbi-premium-enhancer' ),
        'Playfair Display'   => 'Playfair Display, serif',
        'Cormorant Garamond' => 'Cormorant Garamond, serif',
        'Lora'               => 'Lora, serif',
        'Merriweather'       => 'Merriweather, serif',
        'Inter'              => 'Inter, sans-serif',
        'Plus Jakarta Sans'  => 'Plus Jakarta Sans, sans-serif',
        'Poppins'            => 'Poppins, sans-serif',
        'Montserrat'         => 'Montserrat, sans-serif',
        'Open Sans'          => 'Open Sans, sans-serif',
        'Roboto'             => 'Roboto, sans-serif',
        'Nunito'             => 'Nunito, sans-serif',
        'Manrope'            => 'Manrope, sans-serif',
        'Source Sans 3'      => 'Source Sans 3, sans-serif',
    ];
}

/**
 * Default per-section setting block.
 */
function ptsbi_pe_section_defaults() {
    return [
        'enabled'        => 1,
        'bg_color'       => '',
        'pad_top'        => '',   // empty = use defaults
        'pad_bottom'     => '',
        'pad_top_mobile'    => '',
        'pad_bottom_mobile' => '',
        'heading_font'   => 'theme-default',
        'heading_color'  => '',
        'heading_size'   => '',   // px desktop, empty = default
        'heading_size_mobile' => '',
        'body_font'      => 'theme-default',
        'body_color'     => '',
        'body_size'      => '',
        'body_size_mobile' => '',
    ];
}

/**
 * Master default option blob.
 */
function ptsbi_pe_defaults() {
    $defaults = [
        // GLOBAL
        'enabled'           => 1,
        'layout_mode'       => 'elementor', // elementor | auto (legacy JS inject)
        'plugin_label'      => 'Section Studio',
        'address'           => 'Ruko Harapan Mulya Regency Blok BG 1-09 Jl. Harapan Mulya Regency RT. 000 RW. 000, Setia Mulya, Tarumajaya, Kab. Bekasi, Jawa Barat.',
        'color_primary'     => '#0A1F3D',
        'color_accent'      => '#C9A44C',
        'color_text'        => '#1C1C1C',
        'color_text_soft'   => '#4A4A4A',
        'font_heading'      => 'Playfair Display',
        'font_body'         => 'Plus Jakarta Sans',
        'container_max'     => 1200,

        // GLOBAL CONTACT / SOCIAL
        'whatsapp_number'   => '',
        'fb_url'            => '',
        'ig_url'            => '',
        'yt_url'            => '',
        'tt_url'            => '',

        // HERO CTAs
        'cta1_show'         => 1,
        'cta1_label'        => 'Bergabung Sekarang',
        'cta1_url'          => '',
        'cta1_style'        => 'filled',      // filled | outline | ghost
        'cta1_color_bg'     => '#C9A44C',
        'cta1_color_text'   => '#0A1F3D',

        'cta2_show'         => 1,
        'cta2_label'        => 'Kirim Pertanyaan',
        'cta2_url'          => '',            // auto-fallback to wa.me if empty
        'cta2_use_whatsapp' => 1,
        'cta2_style'        => 'ghost-light', // filled | outline | ghost-light | ghost-dark
        'cta2_icon'         => 'whatsapp',
        'cta2_color_bg'     => '',
        'cta2_color_text'   => '#ffffff',
        'cta2_mode'         => 'inquiry', // link | inquiry
        'cta2_inquiry_title'   => 'Kirim Pertanyaan',
        'cta2_inquiry_hint'    => 'Tulis pertanyaan Anda — akan dikirim lewat WhatsApp.',
        'cta2_inquiry_default' => 'Horas. Saya minta informasi lebih lanjut tentang PTSBI',
        'cta2_inquiry_submit'  => 'Kirim via WhatsApp',

        // FIXES (default ON)
        'fix_hero_contrast' => 1,
        'fix_hero_overlay'  => 1,             // overlay only on text side
        'fix_sticky_header' => 1,
        'fix_address_label' => 1,             // Address: -> Alamat:
        'fix_hours_label'   => 1,             // Mon-Fri -> Senin-Jumat
        'fix_dup_sections'  => 1,
        'fix_double_footer' => 1,
        'fix_news_card'     => 1,             // small image + caption below
        'news_image_height' => 200,
        'news_overlay_opacity' => 0,          // 0 = no overlay

        // STATS values (mirror existing or override)
        'stat_a_num'   => '10+',
        'stat_a_label' => 'Cabang Wilayah',
        'stat_b_num'   => '500+',
        'stat_b_label' => 'Kepala Keluarga',
        'stat_c_num'   => '1000+',
        'stat_c_label' => 'Anggota',
        'stat_d_num'   => '20+',
        'stat_d_label' => 'Tahun Kebersamaan',
        'stats_animate' => 1,

        // BACK TO TOP
        'show_back_to_top' => 1,
    ];

    // Add per-section default block.
    foreach ( array_keys( ptsbi_pe_section_types() ) as $slug ) {
        foreach ( ptsbi_pe_section_defaults() as $k => $v ) {
            $defaults[ 'sec_' . $slug . '_' . $k ] = $v;
        }
    }

    return $defaults;
}

function ptsbi_pe_get_options() {
    $saved = get_option( PTSBI_PE_OPTION, [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }
    return array_merge( ptsbi_pe_defaults(), $saved );
}

function ptsbi_pe_get( $key, $default = '' ) {
    $opts = ptsbi_pe_get_options();
    return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
}

function ptsbi_pe_is_enabled() {
    return (int) ptsbi_pe_get( 'enabled', 1 ) === 1;
}

/**
 * elementor = widget drag-drop di Elementor (disarankan)
 * auto      = plugin menyisipkan section lewat JavaScript (legacy)
 */
function ptsbi_pe_layout_mode() {
    $mode = ptsbi_pe_get( 'layout_mode', 'elementor' );
    return in_array( $mode, [ 'elementor', 'auto' ], true ) ? $mode : 'elementor';
}

function ptsbi_pe_uses_elementor_layout() {
    return ptsbi_pe_layout_mode() === 'elementor';
}

function ptsbi_pe_resolve_font( $key ) {
    if ( ! $key || $key === 'theme-default' ) {
        return '';
    }
    $choices = ptsbi_pe_font_choices();
    if ( isset( $choices[ $key ] ) && $key !== 'theme-default' ) {
        return $choices[ $key ];
    }
    return '';
}

function ptsbi_pe_safe_color( $hex ) {
    $hex = sanitize_hex_color( $hex );
    return $hex ? $hex : '';
}

function ptsbi_pe_safe_int( $v, $min = 0, $max = 1000 ) {
    if ( $v === '' || $v === null ) return '';
    $v = (int) $v;
    return max( $min, min( $max, $v ) );
}

function ptsbi_pe_safe_url( $u ) {
    return $u ? esc_url_raw( trim( $u ) ) : '';
}

function ptsbi_pe_safe_text( $t ) {
    return sanitize_text_field( (string) $t );
}

function ptsbi_pe_wa_link( $number, $text = '' ) {
    $digits = preg_replace( '/\D/', '', (string) $number );
    if ( ! $digits ) {
        return '';
    }
    if ( strpos( $digits, '62' ) !== 0 && strpos( $digits, '0' ) === 0 ) {
        $digits = '62' . substr( $digits, 1 );
    }
    $url = 'https://wa.me/' . $digits;
    if ( $text !== '' && $text !== null ) {
        $url .= '?text=' . rawurlencode( (string) $text );
    }
    return $url;
}
