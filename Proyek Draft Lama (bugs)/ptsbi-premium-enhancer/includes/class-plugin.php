<?php
/**
 * Bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Plugin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        new PTSBI_PE_Settings();
        new PTSBI_PE_Assets();
        new PTSBI_PE_Frontend();
        new PTSBI_PE_Elementor();
    }

    public static function on_activate() {
        if ( false === get_option( PTSBI_PE_OPTION ) ) {
            add_option( PTSBI_PE_OPTION, ptsbi_pe_defaults() );
        } else {
            // Merge defaults so new keys become available without overwriting user data.
            $saved  = (array) get_option( PTSBI_PE_OPTION, [] );
            $merged = array_merge( ptsbi_pe_defaults(), $saved );
            // Situs yang sudah jalan: tetap mode auto sampai user pilih Elementor di pengaturan.
            if ( ! isset( $saved['layout_mode'] ) ) {
                $merged['layout_mode'] = 'auto';
            }
            update_option( PTSBI_PE_OPTION, $merged );
        }
    }

    public static function on_deactivate() {
        // Keep options so users do not lose configuration.
    }
}
