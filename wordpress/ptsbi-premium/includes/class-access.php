<?php
/**
 * Akses situs: anggota & pengurus ke portal depan; pemilik situs (manage_options) tetap wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Access {

    public const CAP_MANAGE          = 'ptprm_manage';
    public const CAP_APPROVE_MEMBERS = 'ptprm_approve_members';
    public const CAP_ORG_SETTINGS    = 'ptprm_manage_org_settings';
    public const CAP_PURGE_CACHE     = 'ptprm_purge_cache';
    public const ROLE_ORG_ADMIN      = 'admin_organisasi';

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'handle_logout_request' ], 1 );
        add_action( 'init', [ __CLASS__, 'handle_portal_logout_post' ], 2 );
        add_action( 'init', [ __CLASS__, 'block_wp_admin_early' ], 1 );
        add_action( 'init', [ __CLASS__, 'ensure_capabilities' ], 4 );
        add_action( 'template_redirect', [ __CLASS__, 'portal_nocache_headers' ], 0 );
        add_action( 'admin_init', [ __CLASS__, 'block_wp_admin' ], 1 );
        add_action( 'login_init', [ __CLASS__, 'redirect_logged_in_from_wp_login' ] );
        add_action( 'wp_logout', [ __CLASS__, 'clear_auth_cookies' ] );
        add_filter( 'show_admin_bar', [ __CLASS__, 'hide_admin_bar_on_front' ] );
        add_filter( 'the_content', [ __CLASS__, 'inject_portal_shortcode_if_empty' ], 5 );
    }

    /** Portal berisi nonce form — jangan di-cache (penyebab utama "sesi kedaluwarsa"). */
    public static function portal_nocache_headers(): void {
        if ( ! function_exists( 'ptprm_is_portal_page_request' ) || ! ptprm_is_portal_page_request() ) {
            return;
        }
        if ( function_exists( 'ptprm_portal_nocache_headers' ) ) {
            ptprm_portal_nocache_headers();
        }
    }

    /**
     * Logout lewat URL frontend (tidak bergantung wp-login.php).
     */
    public static function logout_url( string $redirect = '' ): string {
        $base = class_exists( 'PTPRM_Login_Portal' )
            ? PTPRM_Login_Portal::login_url()
            : home_url( '/' );
        $args = [
            'ptprm_action' => 'logout',
            '_wpnonce'     => wp_create_nonce( 'ptprm-logout' ),
        ];
        if ( $redirect !== '' ) {
            $args['redirect_to'] = rawurlencode( $redirect );
        }
        return add_query_arg( $args, $base );
    }

    public static function handle_logout_request(): void {
        if ( ! isset( $_GET['ptprm_action'] ) || sanitize_key( wp_unslash( (string) $_GET['ptprm_action'] ) ) !== 'logout' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'ptprm-logout' ) ) {
            wp_die( esc_html__( 'Sesi keluar tidak valid. Muat ulang halaman lalu coba lagi.', 'ptsbi-premium' ), 403 );
        }

        $redirect = self::logout_redirect_url();
        if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $maybe = wp_validate_redirect( wp_unslash( (string) $_GET['redirect_to'] ), '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $maybe ) {
                $redirect = $maybe;
            }
        }
        self::perform_logout( $redirect );
    }

    /**
     * Jika halaman portal kosong (Elementor / migrasi), sisipkan shortcode otomatis.
     *
     * @param string $content
     */
    public static function inject_portal_shortcode_if_empty( $content ): string {
        if ( ! is_page() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        if ( trim( wp_strip_all_tags( (string) $content ) ) !== '' ) {
            return $content;
        }

        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        if ( class_exists( 'PTPRM_Login_Portal' ) && $slug === PTPRM_Login_Portal::login_slug() ) {
            return '[ptprm_login_portal]';
        }
        if ( class_exists( 'PTPRM_Member_Portal' ) && $slug === PTPRM_Member_Portal::portal_slug() ) {
            return '[ptprm_member_portal]';
        }
        if ( class_exists( 'PTPRM_Admin_Portal' ) && $slug === PTPRM_Admin_Portal::portal_slug() ) {
            return '[ptprm_admin_portal]';
        }
        return $content;
    }

    public static function clear_auth_cookies(): void {
        $path   = COOKIEPATH ?: '/';
        $domain = COOKIE_DOMAIN ?: '';
        $secure = is_ssl();
        foreach ( [ 'ptprm_api_access_token' => true, 'ptprm_api_role' => false ] as $name => $http_only ) {
            if ( isset( $_COOKIE[ $name ] ) ) {
                setcookie( $name, '', time() - YEAR_IN_SECONDS, $path, $domain, $secure, $http_only );
                unset( $_COOKIE[ $name ] );
            }
        }
    }

    public static function perform_logout( string $redirect = '' ): void {
        wp_logout();
        self::clear_auth_cookies();
        wp_safe_redirect( $redirect !== '' ? $redirect : self::logout_redirect_url() );
        exit;
    }

    public static function logout_redirect_url(): string {
        return class_exists( 'PTPRM_Login_Portal' )
            ? add_query_arg( 'ptprm_logged_out', '1', PTPRM_Login_Portal::login_url() )
            : home_url( '/' );
    }

    /**
     * Fallback POST logout (form lama) — diproses sangat awal agar tidak tertahan redirect/cache.
     */
    public static function handle_portal_logout_post(): void {
        $checks = [
            'ptprm_portal_logout' => 'ptprm_portal_logout',
            'ptprm_member_logout' => 'ptprm_member_logout',
            'ptprm_admin_logout'  => 'ptprm_admin_logout',
        ];
        foreach ( $checks as $field => $action ) {
            if ( empty( $_POST[ $field ] ) ) {
                continue;
            }
            if ( ! isset( $_POST[ $action ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ $action ] ) ), $action ) ) {
                return;
            }
            self::perform_logout( self::logout_redirect_url() );
        }
    }

    public static function render_logout_link( string $extra_class = '' ): void {
        $class = trim( 'ptprm-cta ptprm-cta-2 ptprm-cta-size-medium ptprm-portal-logout-link ' . $extra_class );
        echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( self::logout_url() ) . '">';
        echo '<span class="ptprm-cta-label">' . esc_html__( 'Keluar', 'ptsbi-premium' ) . '</span></a>';
    }

    /**
     * Hard block paling awal untuk URL /wp-admin.
     * Semua akun organisasi tetap user WordPress, tetapi tidak boleh masuk area wp-admin.
     */
    public static function block_wp_admin_early(): void {
        if ( wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }

        $req = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if ( $req === '' || strpos( $req, '/wp-admin' ) === false ) {
            return;
        }
        if ( strpos( $req, 'admin-ajax.php' ) !== false ) {
            return;
        }

        // Administrator situs saja yang boleh wp-admin.
        if ( is_user_logged_in() && self::is_site_admin() ) {
            return;
        }

        if ( is_user_logged_in() ) {
            wp_safe_redirect( self::portal_url_for_user() );
            exit;
        }

        if ( class_exists( 'PTPRM_Login_Portal' ) ) {
            wp_safe_redirect( PTPRM_Login_Portal::login_url() );
            exit;
        }
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    public static function ensure_capabilities(): void {
        $base_caps = [
            'read'                   => true,
            'edit_posts'             => true,
            'publish_posts'          => true,
            'upload_files'           => true,
            'edit_published_posts'   => true,
            'delete_posts'           => true,
            'delete_published_posts' => true,
            self::CAP_MANAGE         => true,
        ];
        $admin_only_caps = [
            self::CAP_APPROVE_MEMBERS => true,
            self::CAP_ORG_SETTINGS    => true,
        ];

        $pengurus = get_role( 'pengurus' );
        if ( $pengurus ) {
            foreach ( $base_caps as $cap => $grant ) {
                if ( $grant ) {
                    $pengurus->add_cap( $cap );
                }
            }
            $pengurus->add_cap( self::CAP_PURGE_CACHE );
        }

        add_role(
            self::ROLE_ORG_ADMIN,
            __( 'Admin Organisasi', 'ptsbi-premium' ),
            [ 'read' => true ]
        );
        $org_admin = get_role( self::ROLE_ORG_ADMIN );
        if ( $org_admin ) {
            foreach ( array_merge( $base_caps, $admin_only_caps ) as $cap => $grant ) {
                if ( $grant ) {
                    $org_admin->add_cap( $cap );
                }
            }
            $org_admin->add_cap( self::CAP_PURGE_CACHE );
        }

        $anggota = get_role( 'anggota' );
        if ( $anggota ) {
            $anggota->add_cap( self::CAP_PURGE_CACHE );
        }

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( self::CAP_MANAGE );
            $admin->add_cap( self::CAP_APPROVE_MEMBERS );
            $admin->add_cap( self::CAP_ORG_SETTINGS );
            $admin->add_cap( self::CAP_PURGE_CACHE );
        }
    }

    public static function can_manage(): bool {
        return current_user_can( self::CAP_MANAGE );
    }

    public static function can_approve_members(): bool {
        return current_user_can( self::CAP_APPROVE_MEMBERS );
    }

    public static function can_manage_org_settings(): bool {
        return current_user_can( self::CAP_ORG_SETTINGS );
    }

    public static function is_manager( $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return false;
        }
        return user_can( $user, self::CAP_MANAGE );
    }

    public static function is_org_admin( $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return false;
        }
        return user_can( $user, self::CAP_ORG_SETTINGS );
    }

    /** Pemilik situs / pengelola plugin Premium (akses wp-admin penuh). */
    public static function is_site_admin( $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return false;
        }
        return user_can( $user, 'manage_options' );
    }

    /** Role pengurus organisasi (panel depan), bukan administrator WordPress. */
    public static function is_pengurus_only( $user = null ): bool {
        return self::is_manager( $user ) && ! self::is_site_admin( $user ) && ! self::is_org_admin( $user );
    }

    public static function portal_url_for_user( $user = null ): string {
        $user = $user ?: wp_get_current_user();
        if ( class_exists( 'PTPRM_Member_Portal' ) && PTPRM_Member_Portal::is_anggota( $user ) ) {
            return PTPRM_Member_Portal::portal_url();
        }
        if ( class_exists( 'PTPRM_Members' ) && PTPRM_Members::is_pending_registration_user( $user ) ) {
            return class_exists( 'PTPRM_Login_Portal' ) ? PTPRM_Login_Portal::login_url() : home_url( '/rumah-anggota/' );
        }
        if ( class_exists( 'PTPRM_Bidang_Registry' ) && PTPRM_Bidang_Registry::is_bidang_user( $user ) ) {
            return PTPRM_Bidang_Registry::panel_url( PTPRM_Bidang_Registry::get_user_bidang_slug( $user ) );
        }
        if ( self::is_site_admin( $user ) ) {
            return admin_url();
        }
        if ( class_exists( 'PTPRM_Admin_Portal' ) && self::is_manager( $user ) ) {
            return PTPRM_Admin_Portal::portal_url();
        }
        return home_url( '/' );
    }

    public static function login_url_for_user( $user = null ): string {
        unset( $user );
        if ( class_exists( 'PTPRM_Login_Portal' ) ) {
            return PTPRM_Login_Portal::login_url();
        }
        return home_url( '/' );
    }

    public static function block_wp_admin(): void {
        if ( wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }

        // Administrator WordPress: kelola Premium Plugin & wp-admin seperti biasa.
        if ( is_user_logged_in() && self::is_site_admin() ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            if ( class_exists( 'PTPRM_Login_Portal' ) ) {
                wp_safe_redirect( PTPRM_Login_Portal::login_url() );
                exit;
            }
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        wp_safe_redirect( self::portal_url_for_user() );
        exit;
    }

    public static function redirect_logged_in_from_wp_login(): void {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( in_array( $action, [ 'logout', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'postpass' ], true ) ) {
            return;
        }

        if ( is_user_logged_in() ) {
            wp_safe_redirect( self::portal_url_for_user() );
            exit;
        }
    }

    public static function hide_admin_bar_on_front( $show ) {
        if ( is_admin() ) {
            return $show;
        }
        if ( self::is_site_admin() ) {
            return $show;
        }
        if ( is_user_logged_in() ) {
            return false;
        }
        return $show;
    }
}
