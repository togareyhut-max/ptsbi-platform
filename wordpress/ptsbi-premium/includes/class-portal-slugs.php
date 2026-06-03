<?php
/**
 * Migrasi slug panel + redirect URL lama (panel-pengurus, panel-sekretariat, dll.).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Portal_Slugs {

    private const MIGRATE_OPTION = 'ptprm_portal_slugs_v3';

    /**
     * @return array<string, string> slug lama => slug baru
     */
    public static function legacy_redirect_map(): array {
        $map = [
            'panel-pengurus'          => 'panel-admin',
            'panel-sekretariat'       => 'sekretariat',
            'panel-adat-budaya'       => 'adat-budaya',
            'panel-usaha-dana'        => 'usaha-dana',
            'panel-sosial-pendidikan' => 'sos-dik-mud',
            'panel-hukum'             => 'hukum',
            'sekretariat-pusat'       => 'sekretariat',
        ];
        if ( class_exists( 'PTPRM_Bidang_Registry' ) ) {
            foreach ( PTPRM_Bidang_Registry::bidangs() as $meta ) {
                $canonical = (string) ( $meta['panel_slug'] ?? '' );
                $legacy    = (string) ( $meta['legacy_panel_slug'] ?? '' );
                if ( $legacy !== '' && $canonical !== '' && $legacy !== $canonical ) {
                    $map[ $legacy ] = $canonical;
                }
            }
        }
        return $map;
    }

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'maybe_migrate' ], 12 );
        add_action( 'template_redirect', [ __CLASS__, 'redirect_legacy_slugs' ], 4 );
    }

    public static function maybe_migrate(): void {
        if ( get_option( self::MIGRATE_OPTION ) ) {
            return;
        }

        $opts = (array) get_option( PTPRM_OPTION, [] );
        if ( ( $opts['admin_portal_slug'] ?? '' ) === 'panel-pengurus' || empty( $opts['admin_portal_slug'] ) ) {
            $opts['admin_portal_slug'] = 'panel-admin';
            update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( $opts ), true );
            wp_cache_delete( PTPRM_OPTION, 'options' );
        }

        if ( class_exists( 'PTPRM_Admin_Portal' ) ) {
            PTPRM_Admin_Portal::migrate_portal_page_slug();
        }
        if ( class_exists( 'PTPRM_Bidang_Registry' ) ) {
            PTPRM_Bidang_Registry::migrate_panel_pages();
        }

        update_option( self::MIGRATE_OPTION, 1, false );
        flush_rewrite_rules( false );
    }

    public static function redirect_legacy_slugs(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        $map = self::legacy_redirect_map();
        if ( ! $map ) {
            return;
        }

        $slug = '';
        if ( is_page() ) {
            $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        } else {
            $path = trim( (string) wp_parse_url( home_url( add_query_arg( [] ) ), PHP_URL_PATH ), '/' );
            $slug = sanitize_title( basename( $path ) );
        }

        if ( $slug === '' || ! isset( $map[ $slug ] ) ) {
            return;
        }

        $target = home_url( '/' . sanitize_title( $map[ $slug ] ) . '/' );
        $query  = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $query !== '' ) {
            $target = $target . ( strpos( $target, '?' ) === false ? '?' : '&' ) . $query;
        }
        wp_safe_redirect( $target, 301 );
        exit;
    }
}
