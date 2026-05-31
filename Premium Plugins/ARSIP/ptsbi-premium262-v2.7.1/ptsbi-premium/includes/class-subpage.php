<?php
/**
 * Take over sub-page rendering via template_include. Astra header tetap.
 * Konten asli halaman tetap dirender lewat the_content() — kompatibel dengan
 * editor klasik, Gutenberg, dan Elementor (Elementor mengisi the_content saat aktif).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Subpage {

    public function __construct() {
        add_filter( 'template_include', [ $this, 'use_subpage_template' ], 998 );
    }

    public function use_subpage_template( $template ) {
        if ( is_front_page() || is_home() ) return $template;
        if ( ! is_page() ) return $template;
        if ( empty( ptprm_get( 'subpage_hero_show', 1 ) ) ) return $template;

        $custom = PTPRM_DIR . 'templates/subpage.php';
        return file_exists( $custom ) ? $custom : $template;
    }
}
