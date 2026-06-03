<?php
/**
 * Sesi form portal: cegah cache LiteSpeed + refresh nonce + fallback verifikasi.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Portal_Session {

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'nocache_early' ], 0 );
        add_action( 'template_redirect', [ __CLASS__, 'nocache_headers' ], 0 );
        add_filter( 'litespeed_cache_is_cacheable', [ __CLASS__, 'litespeed_disable_cache' ], 10, 1 );
        add_filter( 'litespeed_control_cacheable', [ __CLASS__, 'litespeed_disable_cache' ], 10, 1 );
        add_action( 'wp_ajax_ptprm_refresh_portal_nonces', [ __CLASS__, 'ajax_refresh_nonces' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_script' ], 1000 );
    }

    public static function is_request_early(): bool {
        if ( is_admin() ) {
            return false;
        }
        if ( ! function_exists( 'ptprm_portal_page_slugs' ) ) {
            return false;
        }
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
        if ( $path === '' ) {
            return false;
        }
        foreach ( explode( '/', $path ) as $segment ) {
            if ( in_array( $segment, ptprm_portal_page_slugs(), true ) ) {
                return true;
            }
        }
        return false;
    }

    public static function nocache_early(): void {
        if ( ! self::is_request_early() ) {
            return;
        }
        self::send_nocache_headers();
    }

    public static function nocache_headers(): void {
        if ( ! function_exists( 'ptprm_is_portal_page_request' ) || ! ptprm_is_portal_page_request() ) {
            return;
        }
        self::send_nocache_headers();
    }

    public static function send_nocache_headers(): void {
        if ( function_exists( 'ptprm_portal_nocache_headers' ) ) {
            ptprm_portal_nocache_headers();
            return;
        }
        if ( headers_sent() ) {
            return;
        }
        nocache_headers();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'Pragma: no-cache', true );
        if ( is_user_logged_in() ) {
            header( 'Vary: Cookie', false );
        }
        if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
            define( 'LSCACHE_NO_CACHE', true );
        }
        if ( has_action( 'litespeed_control_set_nocache' ) ) {
            do_action( 'litespeed_control_set_nocache', 'ptprm-portal' );
        }
    }

    /**
     * @param mixed $cacheable
     * @return mixed
     */
    public static function litespeed_disable_cache( $cacheable ) {
        if ( self::is_request_early() ) {
            return false;
        }
        if ( function_exists( 'ptprm_is_portal_page_request' ) && ptprm_is_portal_page_request() ) {
            return false;
        }
        return $cacheable;
    }

    /**
     * @return list<string>
     */
    public static function nonce_actions(): array {
        return array_values(
            array_unique(
                apply_filters(
                    'ptprm_portal_nonce_actions',
                    [
                        'ptprm_admin_settings',
                        'ptprm_admin_post',
                        'ptprm_admin_member_approve',
                        'ptprm_admin_member_reject',
                        'ptprm_members_import_csv',
                        'ptprm_admin_team_photos',
                        'ptprm_board_save',
                        'ptprm_bidang_content_save',
                        'ptprm_bidang_post_save',
                        'ptprm_member_profile',
                        'ptprm_portal_pdf_save',
                        'ptprm_popup_portal_save',
                        'ptprm_purge_cache',
                        'ptprm_portal_login',
                        'ptprm_member_register',
                    ]
                )
            )
        );
    }

    public static function capability_for_action( string $action ): string {
        $map = [
            'ptprm_admin_settings'       => 'ptprm_manage_org_settings',
            'ptprm_admin_post'           => 'ptprm_manage',
            'ptprm_admin_member_approve' => 'ptprm_approve_members',
            'ptprm_admin_member_reject'  => 'ptprm_approve_members',
            'ptprm_members_import_csv'   => 'ptprm_approve_members',
            'ptprm_admin_team_photos'    => 'ptprm_manage_org_settings',
            'ptprm_board_save'           => 'ptprm_manage_org_settings',
            'ptprm_bidang_content_save'  => 'ptprm_manage',
            'ptprm_bidang_post_save'     => 'ptprm_manage',
            'ptprm_member_profile'       => 'read',
            'ptprm_portal_pdf_save'      => 'ptprm_manage_org_settings',
            'ptprm_popup_portal_save'    => 'ptprm_manage_org_settings',
            'ptprm_purge_cache'          => 'ptprm_purge_cache',
            'ptprm_portal_login'         => 'read',
            'ptprm_member_register'      => 'read',
        ];
        return (string) apply_filters( 'ptprm_portal_nonce_capability', $map[ $action ] ?? '', $action );
    }

    public static function is_portal_referer(): bool {
        $ref = wp_get_referer();
        if ( ! $ref || ! function_exists( 'ptprm_portal_page_slugs' ) ) {
            return false;
        }
        $home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        $ref_host  = wp_parse_url( $ref, PHP_URL_HOST );
        if ( ! $home_host || $ref_host !== $home_host ) {
            return false;
        }
        $path = trim( (string) wp_parse_url( $ref, PHP_URL_PATH ), '/' );
        if ( $path === '' ) {
            return false;
        }
        foreach ( explode( '/', $path ) as $segment ) {
            if ( in_array( $segment, ptprm_portal_page_slugs(), true ) ) {
                return true;
            }
        }
        return false;
    }

    public static function verify_fallback( string $action ): bool {
        if ( ! is_user_logged_in() || ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
            return false;
        }
        if ( ! self::is_portal_referer() ) {
            return false;
        }
        $cap = self::capability_for_action( $action );
        if ( $cap === '' ) {
            return false;
        }
        if ( $cap === 'read' ) {
            return true;
        }
        if ( $cap === 'ptprm_manage_org_settings' && current_user_can( 'manage_options' ) ) {
            return true;
        }
        return current_user_can( $cap );
    }

    public static function verify_form_nonce( string $action ): bool {
        if ( ! isset( $_POST['_wpnonce'] ) ) {
            return self::verify_fallback( $action );
        }
        $ok = (bool) wp_verify_nonce(
            sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ),
            $action
        );
        if ( $ok ) {
            return true;
        }
        return self::verify_fallback( $action );
    }

    public static function ajax_refresh_nonces(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( null, 403 );
        }
        $nonces = [];
        foreach ( self::nonce_actions() as $action ) {
            $cap = self::capability_for_action( $action );
            if ( $cap === '' ) {
                continue;
            }
            if ( $cap !== 'read' && ! current_user_can( $cap ) && ! current_user_can( 'manage_options' ) ) {
                continue;
            }
            $nonces[ $action ] = wp_create_nonce( $action );
        }
        wp_send_json_success( [ 'nonces' => $nonces ] );
    }

    public static function enqueue_script(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        $on_portal = function_exists( 'ptprm_is_portal_page_request' ) && ptprm_is_portal_page_request();
        if ( ! $on_portal && ! self::is_request_early() ) {
            return;
        }
        wp_enqueue_script(
            'ptprm-portal-session',
            PTPRM_URL . 'assets/js/portal-session.js',
            [],
            PTPRM_VERSION,
            true
        );
        wp_localize_script(
            'ptprm-portal-session',
            'ptprmPortalSession',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'action'  => 'ptprm_refresh_portal_nonces',
            ]
        );
    }
}
