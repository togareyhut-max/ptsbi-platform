<?php
/**
 * Akses situs: anggota, pengurus, admin organisasi → panel frontend saja.
 * Hanya administrator WordPress (bukan akun organisasi) yang boleh wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Access {

    public const CAP_MANAGE          = 'ptprm_manage';
    public const CAP_APPROVE_MEMBERS = 'ptprm_approve_members';
    public const CAP_ORG_SETTINGS    = 'ptprm_manage_org_settings';
    public const ROLE_ORG_ADMIN      = 'admin_organisasi';

    /** @var list<string> */
    private const ORG_FRONTEND_ROLES = [ 'admin_organisasi', 'pengurus', 'anggota' ];

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'handle_logout_request' ], 1 );
        add_action( 'init', [ __CLASS__, 'handle_portal_logout_post' ], 2 );
        add_action( 'init', [ __CLASS__, 'block_wp_admin_early' ], 1 );
        add_action( 'init', [ __CLASS__, 'ensure_capabilities' ], 4 );
        add_action( 'init', [ __CLASS__, 'reconcile_organization_roles' ], 5 );
        add_action( 'admin_init', [ __CLASS__, 'block_wp_admin' ], 1 );
        add_action( 'login_init', [ __CLASS__, 'redirect_logged_in_from_wp_login' ] );
        add_action( 'wp_logout', [ __CLASS__, 'clear_auth_cookies' ] );
        add_filter( 'show_admin_bar', [ __CLASS__, 'hide_admin_bar_on_front' ] );
        add_filter( 'the_content', [ __CLASS__, 'inject_portal_shortcode_if_empty' ], 5 );
        add_filter( 'login_redirect', [ __CLASS__, 'filter_login_redirect' ], 99, 3 );
    }

    /**
     * Perbaiki role akun organisasi yang salah jadi administrator WP.
     */
    public static function reconcile_organization_roles(): void {
        if ( get_option( 'ptprm_access_reconcile_v3' ) ) {
            return;
        }

        if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
            PTPRM_Default_Accounts::ensure_all();

            $map = [
                'admin'    => self::ROLE_ORG_ADMIN,
                'pengurus' => 'pengurus',
                'anggota'  => 'anggota',
            ];

            foreach ( $map as $type => $role ) {
                $login = PTPRM_Default_Accounts::login_for( $type );
                $user  = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
                if ( ! $user instanceof WP_User ) {
                    continue;
                }
                if ( in_array( 'administrator', (array) $user->roles, true ) ) {
                    $user->set_role( $role );
                }
                update_user_meta( $user->ID, PTPRM_Default_Accounts::META_TYPE, $type );
            }
        }

        $users = get_users(
            [
                'meta_key'   => 'ptprm_default_account',
                'meta_compare' => 'EXISTS',
                'number'     => 200,
            ]
        );
        foreach ( $users as $user ) {
            if ( ! $user instanceof WP_User ) {
                continue;
            }
            $type = (string) get_user_meta( $user->ID, 'ptprm_default_account', true );
            if ( $type === 'developer' ) {
                continue;
            }
            $role = self::ROLE_ORG_ADMIN;
            if ( $type === 'pengurus' || strpos( $type, 'bidang_' ) === 0 ) {
                $role = 'pengurus';
            } elseif ( $type === 'anggota' ) {
                $role = 'anggota';
            }
            if ( in_array( 'administrator', (array) $user->roles, true ) ) {
                $user->set_role( $role );
            }
        }

        update_option( 'ptprm_access_reconcile_v3', 1, false );
    }

    public static function is_wp_admin_url( string $url ): bool {
        $url = untrailingslashit( $url );
        $admin = untrailingslashit( admin_url() );
        return $url === $admin || strpos( $url, $admin . '/' ) === 0;
    }

    /**
     * Akun organisasi (admin@, pengurus@, anggota@, dll.) — tidak boleh wp-admin.
     */
    public static function is_organization_user( $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return false;
        }

        $roles = (array) $user->roles;
        foreach ( self::ORG_FRONTEND_ROLES as $role ) {
            if ( in_array( $role, $roles, true ) ) {
                return true;
            }
        }

        if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
            $type = (string) get_user_meta( $user->ID, PTPRM_Default_Accounts::META_TYPE, true );
            if ( $type !== '' && $type !== 'developer' ) {
                return true;
            }
            $email = strtolower( (string) $user->user_email );
            foreach ( PTPRM_Default_Accounts::reserved_logins() as $login ) {
                if ( strtolower( $login ) === $email ) {
                    return true;
                }
            }
            foreach ( PTPRM_Default_Accounts::get_stored_logins() as $stored ) {
                if ( strtolower( (string) $stored ) === $email ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Pemilik situs WordPress — bukan akun organisasi frontend. */
    public static function is_site_admin( $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return false;
        }
        if ( self::is_organization_user( $user ) ) {
            return false;
        }
        return in_array( 'administrator', (array) $user->roles, true );
    }

    /**
     * @param WP_User $user
     */
    public static function sanitize_redirect_for_user( WP_User $user, string $url ): string {
        $url = wp_validate_redirect( $url, '' );
        if ( $url === '' ) {
            return self::portal_url_for_user( $user );
        }
        if ( self::is_wp_admin_url( $url ) && ! self::is_site_admin( $user ) ) {
            return self::portal_url_for_user( $user );
        }
        return $url;
    }

    /**
     * @param string           $redirect_to
     * @param string           $requested
     * @param WP_User|WP_Error $user
     */
    public static function filter_login_redirect( $redirect_to, $requested, $user ) {
        if ( ! $user instanceof WP_User ) {
            return $redirect_to;
        }
        $target = $requested !== '' ? $requested : $redirect_to;
        if ( $target === '' || self::is_wp_admin_url( $target ) ) {
            return self::portal_url_for_user( $user );
        }
        return self::sanitize_redirect_for_user( $user, $target );
    }

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
        if ( strpos( $req, 'admin-ajax.php' ) !== false || strpos( $req, 'async-upload.php' ) !== false ) {
            return;
        }

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
        $manager_caps = [
            'read'         => true,
            'upload_files' => true,
            self::CAP_MANAGE => true,
        ];
        $org_admin_caps = array_merge(
            $manager_caps,
            [
                self::CAP_APPROVE_MEMBERS => true,
                self::CAP_ORG_SETTINGS    => true,
            ]
        );

        $pengurus = get_role( 'pengurus' );
        if ( $pengurus ) {
            foreach ( $manager_caps as $cap => $grant ) {
                if ( $grant ) {
                    $pengurus->add_cap( $cap );
                }
            }
            $pengurus->remove_cap( 'manage_options' );
        }

        add_role(
            self::ROLE_ORG_ADMIN,
            __( 'Admin Organisasi', 'ptsbi-premium' ),
            [ 'read' => true ]
        );
        $org_admin = get_role( self::ROLE_ORG_ADMIN );
        if ( $org_admin ) {
            foreach ( $org_admin_caps as $cap => $grant ) {
                if ( $grant ) {
                    $org_admin->add_cap( $cap );
                }
            }
            $org_admin->remove_cap( 'manage_options' );
        }

        $member = get_role( 'anggota' );
        if ( $member ) {
            $member->add_cap( 'read' );
            $member->remove_cap( 'manage_options' );
        }

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( self::CAP_MANAGE );
            $admin->add_cap( self::CAP_APPROVE_MEMBERS );
            $admin->add_cap( self::CAP_ORG_SETTINGS );
        }
    }

    public static function can_manage(): bool {
        return current_user_can( self::CAP_MANAGE );
    }

    public static function can_approve_members(): bool {
        return current_user_can( self::CAP_APPROVE_MEMBERS );
    }

    public static function can_manage_org_settings(): bool {
        return current_user_can( self::CAP_ORG_SETTINGS ) || current_user_can( 'manage_options' );
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

    public static function is_pengurus_only( $user = null ): bool {
        return self::is_manager( $user ) && ! self::is_site_admin( $user ) && ! self::is_org_admin( $user );
    }

    public static function portal_url_for_user( $user = null ): string {
        $user = $user ?: wp_get_current_user();

        if ( class_exists( 'PTPRM_Members' ) && PTPRM_Members::is_pending_registration_user( $user ) ) {
            return class_exists( 'PTPRM_Login_Portal' ) ? PTPRM_Login_Portal::login_url() : home_url( '/rumah-anggota/' );
        }

        if ( class_exists( 'PTPRM_Member_Portal' ) && PTPRM_Member_Portal::is_anggota( $user ) ) {
            return PTPRM_Member_Portal::portal_url();
        }

        if ( self::is_organization_user( $user ) || self::is_org_admin( $user ) || self::is_manager( $user ) ) {
            if ( class_exists( 'PTPRM_Admin_Portal' ) ) {
                return PTPRM_Admin_Portal::portal_url();
            }
        }

        if ( self::is_site_admin( $user ) ) {
            return admin_url();
        }

        return home_url( '/' );
    }

    public static function login_url_for_user( $user = null ): string {
        unset( $user );
        return class_exists( 'PTPRM_Login_Portal' )
            ? PTPRM_Login_Portal::login_url()
            : home_url( '/' );
    }

    public static function block_wp_admin(): void {
        if ( wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }

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
