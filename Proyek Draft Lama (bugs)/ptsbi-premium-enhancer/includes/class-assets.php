<?php
/**
 * Enqueue frontend assets and inject dynamic CSS (built from settings).
 * Priority 999 ensures we come AFTER Astra & Elementor so our base stylesheet
 * can be safely overridden by Elementor inline styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Assets {

    public function __construct() {
        add_action( 'init', [ $this, 'register_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 999 );
        add_action( 'wp_head', [ $this, 'print_asset_vars' ], 5 );
    }

    public function register_assets() {
        if ( ! ptsbi_pe_is_enabled() ) {
            return;
        }

        wp_register_style(
            'studio-frontend',
            PTSBI_PE_URL . 'assets/css/ptsbi-frontend.css',
            [],
            PTSBI_PE_VERSION
        );

        wp_register_script(
            'studio-frontend',
            PTSBI_PE_URL . 'assets/js/ptsbi-frontend.js',
            [],
            PTSBI_PE_VERSION,
            true
        );
    }

    public function print_asset_vars() {
        if ( ! ptsbi_pe_is_enabled() ) {
            return;
        }
        $gorga = PTSBI_PE_URL . 'assets/img/gorga-pattern.svg';
        $divider = PTSBI_PE_URL . 'assets/img/ornament-divider.svg';
        echo '<style id="studio-pe-vars">:root{--studio-gorga-pattern:url("' . esc_url( $gorga ) . '");--studio-ornament-divider:url("' . esc_url( $divider ) . '");}</style>';
    }

    public function enqueue() {
        if ( ! ptsbi_pe_is_enabled() ) {
            return;
        }

        $opts = ptsbi_pe_get_options();

        // Load Google Fonts only for fonts that are actually selected.
        $font_keys = array_unique( array_filter( [
            $opts['font_heading'],
            $opts['font_body'],
        ], function ( $k ) { return $k && $k !== 'theme-default'; } ) );

        // Add per-section fonts too.
        foreach ( array_keys( ptsbi_pe_section_types() ) as $slug ) {
            $hf = $opts[ "sec_{$slug}_heading_font" ] ?? '';
            $bf = $opts[ "sec_{$slug}_body_font" ] ?? '';
            if ( $hf && $hf !== 'theme-default' ) $font_keys[] = $hf;
            if ( $bf && $bf !== 'theme-default' ) $font_keys[] = $bf;
        }
        $font_keys = array_unique( $font_keys );

        if ( $font_keys ) {
            $parts = [];
            foreach ( $font_keys as $f ) {
                $parts[] = str_replace( ' ', '+', $f ) . ':wght@300;400;500;600;700;800';
            }
            $url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $parts ) . '&display=swap';
            wp_enqueue_style( 'studio-fonts', $url, [], null );
        }

        $deps = $font_keys ? [ 'studio-fonts' ] : [];
        wp_enqueue_style( 'studio-frontend' );
        if ( $deps && wp_styles()->registered['studio-frontend'] ) {
            wp_styles()->registered['studio-frontend']->deps = $deps;
        }

        // Inline CSS built from settings.
        $builder = new PTSBI_PE_CSS_Builder();
        $inline = $builder->build();
        if ( $inline ) {
            wp_add_inline_style( 'studio-frontend', $inline );
        }

        wp_enqueue_script( 'studio-frontend' );

        $cta1_url = $opts['cta1_url'];
        $cta2_url = $opts['cta2_url'];
        if ( ! $cta2_url && (int) $opts['cta2_use_whatsapp'] === 1 && $opts['whatsapp_number'] ) {
            $cta2_url = ptsbi_pe_wa_link( $opts['whatsapp_number'] );
        }

        $replacer = new PTSBI_PE_Text_Replacer();

        $addr = $opts['address'] ?? '';

        wp_localize_script( 'studio-frontend', 'STUDIO_PE', [
            'home'        => home_url( '/' ),
            'layoutMode'  => ptsbi_pe_layout_mode(),
            'pluginUrl'   => PTSBI_PE_URL,
            'fixes'       => [
                'heroContrast'  => (int) $opts['fix_hero_contrast'],
                'heroOverlay'   => (int) $opts['fix_hero_overlay'],
                'dupSections'   => (int) $opts['fix_dup_sections'],
                'doubleFooter'  => (int) $opts['fix_double_footer'],
                'newsCard'      => (int) $opts['fix_news_card'],
            ],
            'cta1'        => [
                'show'   => (int) $opts['cta1_show'],
                'label'  => $opts['cta1_label'],
                'url'    => $cta1_url,
                'style'  => $opts['cta1_style'],
                'bg'     => $opts['cta1_color_bg'],
                'fg'     => $opts['cta1_color_text'],
            ],
            'cta2'        => [
                'show'   => (int) $opts['cta2_show'],
                'label'  => $opts['cta2_label'],
                'url'    => $cta2_url,
                'style'  => $opts['cta2_style'],
                'icon'   => $opts['cta2_icon'],
                'bg'     => $opts['cta2_color_bg'],
                'fg'     => $opts['cta2_color_text'],
                'mode'   => $opts['cta2_mode'] ?? 'inquiry',
            ],
            'inquiry'     => [
                'title'          => $opts['cta2_inquiry_title'] ?? '',
                'hint'           => $opts['cta2_inquiry_hint'] ?? '',
                'defaultMessage' => $opts['cta2_inquiry_default'] ?? '',
                'submitLabel'    => $opts['cta2_inquiry_submit'] ?? 'Kirim via WhatsApp',
                'whatsappNumber' => $opts['whatsapp_number'] ?? '',
            ],
            'stickyHeader' => (int) ( $opts['fix_sticky_header'] ?? 1 ),
            'news'        => [
                'imgHeight'      => (int) $opts['news_image_height'],
                'overlayOpacity' => (int) $opts['news_overlay_opacity'],
            ],
            'stats'       => [
                'animate' => (int) $opts['stats_animate'],
            ],
            'showBackToTop' => (int) $opts['show_back_to_top'],
            'textRules'   => $replacer->rules(),
            'social'      => [
                'facebook'  => $opts['fb_url'],
                'instagram' => $opts['ig_url'],
                'youtube'   => $opts['yt_url'],
                'tiktok'    => $opts['tt_url'],
            ],
            'address'     => $addr,
        ] );
    }
}
