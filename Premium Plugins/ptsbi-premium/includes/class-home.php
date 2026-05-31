<?php
/**
 * Take over the homepage rendering via template_include.
 * Astra header/menu masih dipanggil (get_header), tetapi seluruh konten Beranda
 * di-render oleh plugin. Elementor di Beranda diabaikan dengan aman.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Home {

    public function __construct() {
        add_filter( 'template_include', [ $this, 'use_home_template' ], 999 );
        add_filter( 'body_class',       [ $this, 'body_class' ] );
        add_action( 'wp_footer',        [ $this, 'render_premium_footer' ], 9 );
        add_action( 'wp_footer',        [ $this, 'render_popup' ], 11 );
    }

    public function use_home_template( $template ) {
        if ( ! ( is_front_page() || is_home() ) ) {
            return $template;
        }
        $custom = PTPRM_DIR . 'templates/home.php';
        return file_exists( $custom ) ? $custom : $template;
    }

    public function body_class( $classes ) {
        if ( is_front_page() || is_home() ) {
            $classes[] = 'ptprm-is-home';
        } elseif ( is_page() ) {
            $classes[] = 'ptprm-is-subpage';
        }
        if ( is_front_page() || is_home() || is_page() ) {
            $style = ptprm_pattern_style();
            $classes[] = 'ptprm-pattern-' . sanitize_html_class( $style );
            if ( ! ptprm_pattern_enabled() ) {
                $classes[] = 'ptprm-pattern-off';
            }
        }
        return $classes;
    }

    public function render_premium_footer() {
        $o = ptprm_options();
        if ( empty( $o['footer_show'] ) ) return;
        PTPRM_Renderer::footer();
    }

    public function render_popup() {
        $o = ptprm_options();
        if ( empty( $o['popup_enabled'] ) ) return;
        $is_home    = ( is_front_page() || is_home() );
        $is_subpage = ( is_page() && ! is_front_page() );
        if ( $is_home && empty( $o['popup_show_home'] ) ) return;
        if ( $is_subpage && empty( $o['popup_show_subpage'] ) ) return;
        if ( ! $is_home && ! $is_subpage ) return;
        PTPRM_Renderer::popup( $o );
    }
}
