<?php
/**
 * Enqueue frontend assets + dynamic CSS variables from settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Assets {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 999 );
    }

    public function enqueue() {
        $o = ptprm_options();

        $fonts = array_unique( array_filter( [
            $o['font_heading'],
            $o['font_body'],
            ( $o['hero_eyebrow_font'] !== 'global' ? $o['hero_eyebrow_font'] : '' ),
            ( $o['hero_title_font']   !== 'global' ? $o['hero_title_font']   : '' ),
            ( $o['hero_sub_font']     !== 'global' ? $o['hero_sub_font']     : '' ),
        ] ) );

        if ( $fonts ) {
            $parts = [];
            foreach ( $fonts as $f ) {
                $parts[] = str_replace( ' ', '+', $f ) . ':wght@300;400;500;600;700;800';
            }
            $url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $parts ) . '&display=swap';
            wp_enqueue_style( 'ptprm-fonts', $url, [], null );
        }

        wp_enqueue_style(
            'ptprm-frontend',
            PTPRM_URL . 'assets/css/frontend.css',
            $fonts ? [ 'ptprm-fonts' ] : [],
            PTPRM_VERSION
        );

        $inline = $this->build_inline_css( $o );
        if ( $inline !== '' ) {
            wp_add_inline_style( 'ptprm-frontend', $inline );
        }

        wp_enqueue_script(
            'ptprm-frontend',
            PTPRM_URL . 'assets/js/frontend.js',
            [],
            PTPRM_VERSION,
            true
        );

        if ( class_exists( 'PTPRM_Member_Portal' ) && PTPRM_Member_Portal::should_enqueue_portal_assets() && class_exists( 'PTPRM_Address_Regions' ) ) {
            PTPRM_Address_Regions::enqueue_scripts(
                'ptprm-member-address',
                PTPRM_URL . 'assets/js/member-portal-address.js'
            );
        }

        wp_localize_script( 'ptprm-frontend', 'PTPRM', [
            'animateStats'   => ! empty( $o['stats_animate'] ),
            'galleryAuto'    => (int) $o['gallery_autoplay'],
            'galleryPause'   => ! empty( $o['gallery_pause'] ),
            'popupEnabled'   => $this->should_render_popup( $o ),
            'popupDelay'     => (int) $o['popup_delay'],
            'popupFrequency' => $o['popup_frequency'],
            'whatsapp'       => ptprm_wa_digits( $o['whatsapp'] ?? '' ),
            'cta2Mode'       => $o['cta2_mode'] ?? 'inquiry',
            'stickyHeader'   => ! empty( $o['sticky_header'] ),
            'inquiry'        => [
                'title'          => $o['cta2_inquiry_title'] ?? 'Kirim Pertanyaan',
                'hint'           => $o['cta2_inquiry_hint'] ?? '',
                'defaultMessage' => $o['cta2_inquiry_default'] ?? '',
                'submitLabel'    => $o['cta2_inquiry_submit'] ?? 'Kirim via WhatsApp',
            ],
        ] );
    }

    public function should_render_popup( $o ) {
        if ( empty( $o['popup_enabled'] ) ) return false;
        if ( ( is_front_page() || is_home() ) && ! empty( $o['popup_show_home'] ) )   return true;
        if ( is_page() && ! is_front_page() && ! empty( $o['popup_show_subpage'] ) )  return true;
        return false;
    }

    /**
     * CSS variabel brand — dipasang SETELAH frontend.css agar tidak ditimpa :root default.
     */
    public function build_inline_css( $o ) {
        $primary = (string) ( $o['color_primary'] ?? '#0A1F3D' );
        $rgb     = ptprm_hex_to_rgb( $primary );

        $vars = [];
        $vars['--ptprm-primary']        = $primary;
        $vars['--ptprm-primary-soft']   = $this->shade( $primary, -16 );
        $vars['--ptprm-primary-deep']   = $this->shade( $primary, -28 );
        $vars['--ptprm-primary-rgb']    = $rgb[0] . ',' . $rgb[1] . ',' . $rgb[2];
        $vars['--ptprm-accent']         = $o['color_accent'];
        $vars['--ptprm-accent-soft']    = $this->shade( $o['color_accent'], 16 );
        $vars['--ptprm-text']           = $o['color_text'];
        $vars['--ptprm-text-soft']      = $o['color_text_soft'];
        $vars['--ptprm-cream']          = $o['color_cream'];
        $vars['--ptprm-max']            = (int) $o['container_max'] . 'px';
        $vars['--ptprm-shadow-sm']      = '0 4px 14px rgba(' . $vars['--ptprm-primary-rgb'] . ',.08)';
        $vars['--ptprm-shadow-md']      = '0 10px 30px rgba(' . $vars['--ptprm-primary-rgb'] . ',.12)';

        $header_bg = $o['header_bg_color'] ?? '';
        if ( ! $header_bg ) {
            $header_bg = $primary;
        }
        $vars['--ptprm-header-bg']        = $header_bg;
        $vars['--ptprm-header-text']      = $o['header_text_color'] ?? '#ffffff';
        $vars['--ptprm-header-logo-max']  = max( 28, min( 120, (int) ( $o['header_logo_max_height'] ?? 48 ) ) ) . 'px';
        $vars['--ptprm-header-menu-size'] = max( 12, min( 20, (int) ( $o['header_menu_font_size'] ?? 15 ) ) ) . 'px';

        $heading = ptprm_resolve_font( $o['font_heading'], $o['font_heading'] . ', serif' );
        $body    = ptprm_resolve_font( $o['font_body'],    $o['font_body'] . ', sans-serif' );
        $vars['--ptprm-font-heading'] = $heading;
        $vars['--ptprm-font-body']    = $body;

        $vars['--ptprm-hero-text-max']  = (int) $o['hero_text_max_width'] . 'px';
        $vars['--ptprm-hero-gap']       = (int) $o['hero_text_gap'] . 'px';
        $vars['--ptprm-hero-h-d']       = max( 320, min( 1400, (int) $o['hero_height_desktop'] ) ) . 'px';
        $vars['--ptprm-hero-h-m']       = max( 320, min( 1200, (int) $o['hero_height_mobile'] ) ) . 'px';
        $img_pos                        = ptprm_sanitize_hero_img_position( $o['hero_img_position'] ?? '' );
        $vars['--ptprm-hero-img-pos']   = $img_pos;
        $vars['--ptprm-hero-overlay']   = $primary;
        $vars['--ptprm-hero-overlay-a'] = max( 0, min( 100, (int) $o['hero_overlay_opacity'] ) ) / 100;
        $pop_a                          = max( 0, min( 100, (int) $o['popup_overlay_opacity'] ) ) / 100;
        $vars['--ptprm-popup-overlay-rgb'] = $vars['--ptprm-primary-rgb'];
        $vars['--ptprm-popup-overlay-a']   = $pop_a;

        $eyebrow_font = ptprm_resolve_font( $o['hero_eyebrow_font'], 'inherit' );
        $title_font   = ptprm_resolve_font( $o['hero_title_font'],   'inherit' );
        $sub_font     = ptprm_resolve_font( $o['hero_sub_font'],     'inherit' );

        // Eyebrow
        $vars['--ptprm-eyebrow-font']    = $eyebrow_font;
        $vars['--ptprm-eyebrow-size']    = (int) $o['hero_eyebrow_size'] . 'px';
        $vars['--ptprm-eyebrow-size-m']  = (int) $o['hero_eyebrow_size_mobile'] . 'px';
        $vars['--ptprm-eyebrow-weight']  = $o['hero_eyebrow_weight'];
        $vars['--ptprm-eyebrow-color']   = $o['hero_eyebrow_color'];
        $vars['--ptprm-eyebrow-ls']      = ( (int) $o['hero_eyebrow_letter_spacing'] / 100 ) . 'em';

        // Title
        $vars['--ptprm-title-font']    = $title_font;
        $vars['--ptprm-title-size']    = (int) $o['hero_title_size'] . 'px';
        $vars['--ptprm-title-size-m']  = (int) $o['hero_title_size_mobile'] . 'px';
        $vars['--ptprm-title-weight']  = $o['hero_title_weight'];
        $vars['--ptprm-title-color']   = $o['hero_title_color'];
        $vars['--ptprm-title-ls']      = ( (int) $o['hero_title_letter_spacing'] / 100 ) . 'em';
        $vars['--ptprm-title-lh']      = ( (int) $o['hero_title_line_height'] / 100 );

        // Subtitle
        $vars['--ptprm-sub-font']    = $sub_font;
        $vars['--ptprm-sub-size']    = (int) $o['hero_sub_size'] . 'px';
        $vars['--ptprm-sub-size-m']  = (int) $o['hero_sub_size_mobile'] . 'px';
        $vars['--ptprm-sub-weight']  = $o['hero_sub_weight'];
        $vars['--ptprm-sub-color']   = $o['hero_sub_color'];
        $vars['--ptprm-sub-ls']      = ( (int) $o['hero_sub_letter_spacing'] / 100 ) . 'em';
        $vars['--ptprm-sub-lh']      = ( (int) $o['hero_sub_line_height'] / 100 );

        // CTA1
        $vars['--ptprm-cta1-bg']        = $o['cta1_bg'];
        $vars['--ptprm-cta1-color']     = $o['cta1_color'];
        $vars['--ptprm-cta1-bg-h']      = $o['cta1_bg_hover'] ?: $o['cta1_bg'];
        $vars['--ptprm-cta1-color-h']   = $o['cta1_color_hover'] ?: $o['cta1_color'];
        $vars['--ptprm-cta1-radius']    = (int) $o['cta1_radius'] . 'px';

        // CTA2
        $vars['--ptprm-cta2-bg']        = $o['cta2_bg'];
        $vars['--ptprm-cta2-color']     = $o['cta2_color'];
        $vars['--ptprm-cta2-bg-h']      = $o['cta2_bg_hover'] ?: $o['cta2_bg'];
        $vars['--ptprm-cta2-color-h']   = $o['cta2_color_hover'] ?: $o['cta2_color'];
        $vars['--ptprm-cta2-radius']    = (int) $o['cta2_radius'] . 'px';

        // Subpage
        $vars['--ptprm-sub-overlay-a']  = max( 0, min( 100, (int) $o['subpage_overlay'] ) ) / 100;
        $pattern_css                 = ptprm_pattern_css_var( $o );
        $vars['--ptprm-pattern-img'] = $pattern_css;
        $vars['--ptprm-gorga-img']   = $pattern_css;

        // Tipografi global — Beranda
        $vars['--ptprm-fs-h2']        = (int) $o['type_h2_size'] . 'px';
        $vars['--ptprm-fs-h2-m']      = (int) $o['type_h2_size_m'] . 'px';
        $vars['--ptprm-lh-h2']        = ( (int) $o['type_h2_lh'] / 100 );
        $vars['--ptprm-fs-lead']      = (int) $o['type_lead_size'] . 'px';
        $vars['--ptprm-fs-lead-m']    = (int) $o['type_lead_size_m'] . 'px';
        $vars['--ptprm-lh-lead']      = ( (int) $o['type_lead_lh'] / 100 );
        $vars['--ptprm-fs-eyebrow']   = (int) $o['type_eyebrow_size'] . 'px';
        $vars['--ptprm-fs-eyebrow-m'] = (int) $o['type_eyebrow_size_m'] . 'px';
        $vars['--ptprm-fs-body']      = (int) $o['type_body_size'] . 'px';
        $vars['--ptprm-fs-body-m']    = (int) $o['type_body_size_m'] . 'px';
        $vars['--ptprm-lh-body']      = ( (int) $o['type_body_lh'] / 100 );
        $vars['--ptprm-fs-li']        = (int) $o['type_li_size'] . 'px';
        $vars['--ptprm-fs-li-m']      = (int) $o['type_li_size_m'] . 'px';

        // Tipografi — Sub-halaman (the_content)
        $vars['--ptprm-fs-sub-h2']    = (int) $o['type_sub_h2_size'] . 'px';
        $vars['--ptprm-fs-sub-h2-m']  = (int) $o['type_sub_h2_size_m'] . 'px';
        $vars['--ptprm-fs-sub-h3']    = (int) $o['type_sub_h3_size'] . 'px';
        $vars['--ptprm-fs-sub-h3-m']  = (int) $o['type_sub_h3_size_m'] . 'px';
        $vars['--ptprm-fs-sub-body']  = (int) $o['type_sub_body_size'] . 'px';
        $vars['--ptprm-fs-sub-body-m']= (int) $o['type_sub_body_size_m'] . 'px';
        $vars['--ptprm-lh-sub-body']  = ( (int) $o['type_sub_body_lh'] / 100 );
        $vars['--ptprm-fs-sub-li']    = (int) $o['type_sub_li_size'] . 'px';
        $vars['--ptprm-fs-sub-li-m']  = (int) $o['type_sub_li_size_m'] . 'px';

        // Gallery carousel
        $vars['--ptprm-gallery-speed']     = (int) $o['gallery_speed'] . 's';

        // Tipografi style flags
        $title_style_css = $this->style_to_css( $o['hero_title_style'] );
        $sub_style_css   = $this->style_to_css( $o['hero_sub_style'] );
        $eyebrow_style_css = $this->style_to_css( $o['hero_eyebrow_style'] );

        $css = ':root{';
        foreach ( $vars as $k => $v ) {
            $css .= $k . ': ' . $v . ';';
        }
        $css .= '}';
        $css .= '.ptprm-hero-eyebrow{' . $eyebrow_style_css . '}';
        $css .= '.ptprm-hero-title{' . $title_style_css . '}';
        $css .= '.ptprm-hero-sub{' . $sub_style_css . '}';
        $css .= '.ptprm-hero-media img,.ptprm-hero-slide img,.ptprm-hero-single-img{object-position:var(--ptprm-hero-img-pos);}';
        if ( ! empty( $o['hero_title_shadow'] ) ) {
            $css .= '.ptprm-hero-title{text-shadow:0 2px 18px rgba(0,0,0,.45);}';
        }
        if ( ! empty( $o['hero_sub_shadow'] ) ) {
            $css .= '.ptprm-hero-sub{text-shadow:0 1px 10px rgba(0,0,0,.4);}';
        }
        if ( ! empty( $o['hero_eyebrow_shadow'] ) ) {
            $css .= '.ptprm-hero-eyebrow{text-shadow:0 1px 6px rgba(0,0,0,.45);}';
        }

        return $css;
    }

    private function style_to_css( $style ) {
        switch ( $style ) {
            case 'italic':     return 'font-style:italic;text-transform:none;';
            case 'uppercase':  return 'text-transform:uppercase;font-style:normal;';
            case 'capitalize': return 'text-transform:capitalize;font-style:normal;';
            default:           return 'text-transform:none;font-style:normal;';
        }
    }

    private function shade( $hex, $percent ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) !== 6 ) return '#' . $hex;
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        $adj = function ( $c ) use ( $percent ) {
            $c = $c + ( $c * ( $percent / 100 ) );
            return max( 0, min( 255, (int) round( $c ) ) );
        };
        return sprintf( '#%02X%02X%02X', $adj( $r ), $adj( $g ), $adj( $b ) );
    }
}
