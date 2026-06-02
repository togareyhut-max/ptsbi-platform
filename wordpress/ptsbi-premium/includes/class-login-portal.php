<?php
/**
 * Portal masuk tunggal (Rumah Anggota) — semua peran login di sini, diarahkan ke panel masing-masing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Login_Portal {

    public const SLUG_LOGIN = 'rumah-anggota';

    private const POST_LOGIN  = 'ptprm_portal_login';
    private const NONCE_LOGIN = 'ptprm_portal_login_nonce';
    private const NONCE_LOGOUT = 'ptprm_portal_logout';

    public function __construct() {
        add_action( 'init', [ __CLASS__, 'maybe_ensure_pages' ], 13 );
        add_action( 'template_redirect', [ __CLASS__, 'redirect_legacy_login_pages' ], 5 );
        add_action( 'template_redirect', [ __CLASS__, 'redirect_logged_in_from_login_page' ], 6 );
        add_action( 'init', [ $this, 'handle_login' ], 20 );
        add_filter( 'login_redirect', [ $this, 'login_redirect' ], 10, 3 );
        add_shortcode( 'ptprm_login_portal', [ $this, 'shortcode_login' ] );
        add_shortcode( 'ptprm_member_login', [ $this, 'shortcode_login' ] );
        add_shortcode( 'ptprm_admin_login', [ $this, 'shortcode_login' ] );
        add_filter( 'ptprm_subpage_hero_skip', [ $this, 'skip_subpage_hero' ] );
    }

    public static function login_slug(): string {
        $o = ptprm_options();
        $s = sanitize_title( (string) ( $o['portal_login_slug'] ?? '' ) );
        if ( $s === '' ) {
            $s = sanitize_title( (string) ( $o['members_login_slug'] ?? self::SLUG_LOGIN ) );
        }
        return $s !== '' ? $s : self::SLUG_LOGIN;
    }

    public static function login_url( string $redirect = '' ): string {
        $url = home_url( '/' . self::login_slug() . '/' );
        if ( $redirect !== '' ) {
            $url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
        }
        return $url;
    }

    /** URL masuk jika belum login; panel jika sudah login. */
    public static function entry_url( string $redirect = '' ): string {
        if ( is_user_logged_in() ) {
            return PTPRM_Access::portal_url_for_user();
        }
        return self::login_url( $redirect );
    }

    public static function legacy_login_slugs(): array {
        $o      = ptprm_options();
        $legacy = [
            'masuk',
            'masuk-pengurus',
            self::SLUG_LOGIN,
            sanitize_title( (string) ( $o['members_login_slug'] ?? 'masuk' ) ),
            sanitize_title( (string) ( $o['admin_login_slug'] ?? 'masuk-pengurus' ) ),
        ];
        $unified = self::login_slug();
        $out     = [];
        foreach ( $legacy as $slug ) {
            if ( $slug !== '' && $slug !== $unified ) {
                $out[] = $slug;
            }
        }
        return array_values( array_unique( $out ) );
    }

    public static function maybe_ensure_pages(): void {
        $needs_flush = false;
        if ( ! get_option( 'ptprm_unified_login_v1' ) ) {
            self::ensure_pages();
            update_option( 'ptprm_unified_login_v1', 1, false );
            $needs_flush = true;
        }
        self::repair_login_page_content();
        if ( $needs_flush ) {
            flush_rewrite_rules( false );
        }
    }

    /** Pastikan halaman Rumah Anggota punya shortcode portal. */
    public static function repair_login_page_content(): void {
        $page = get_page_by_path( self::login_slug(), OBJECT, 'page' );
        if ( ! $page instanceof WP_Post ) {
            return;
        }
        $content = (string) $page->post_content;
        if ( strpos( $content, 'ptprm_login_portal' ) !== false || strpos( $content, 'ptprm_member_login' ) !== false ) {
            return;
        }
        wp_update_post(
            [
                'ID'           => (int) $page->ID,
                'post_content' => '[ptprm_login_portal]',
            ]
        );
    }

    public static function is_login_page(): bool {
        if ( ! is_page() ) {
            return false;
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        return $slug === self::login_slug();
    }

    public function skip_subpage_hero( bool $skip ): bool {
        return $skip || self::is_login_page();
    }

    public static function redirect_legacy_login_pages(): void {
        if ( ! is_page() || self::is_login_page() ) {
            return;
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        if ( in_array( $slug, self::legacy_login_slugs(), true ) ) {
            $target = self::login_url();
            if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $maybe = wp_validate_redirect( wp_unslash( (string) $_GET['redirect_to'] ), '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $maybe ) {
                    $target = self::login_url( $maybe );
                }
            }
            wp_safe_redirect( $target, 301 );
            exit;
        }
    }

    /**
     * Jika sesi sudah valid dan user membuka Rumah Anggota, langsung arahkan ke panel.
     * Ini mencegah kasus "login sukses tapi tetap diam di halaman login".
     */
    public static function redirect_logged_in_from_login_page(): void {
        if ( ! is_user_logged_in() || ! self::is_login_page() ) {
            return;
        }

        // Jangan redirect saat baru logout / ada pesan login di URL.
        foreach ( [ 'ptprm_logged_out', 'ptprm_login_error', 'ptprm_login_pending', 'ptprm_register_pending' ] as $key ) {
            if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return;
            }
        }
        if ( isset( $_GET['ptprm_action'] ) && sanitize_key( wp_unslash( (string) $_GET['ptprm_action'] ) ) === 'logout' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return;
        }

        $target = PTPRM_Access::portal_url_for_user( $user );
        if ( $target === '' ) {
            return;
        }
        $current = home_url( add_query_arg( [], $_SERVER['REQUEST_URI'] ?? '/' ) );
        if ( untrailingslashit( $current ) === untrailingslashit( $target ) ) {
            return;
        }

        wp_safe_redirect( $target );
        exit;
    }

    /**
     * @param WP_User|WP_Error $user
     */
    public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! $user instanceof WP_User ) {
            return $redirect_to;
        }
        return self::redirect_after_login( $user, (string) $requested_redirect_to );
    }

    public static function redirect_after_login( WP_User $user, string $requested = '' ): string {
        if ( class_exists( 'PTPRM_Members' ) && PTPRM_Members::is_pending_registration_user( $user ) ) {
            return add_query_arg( 'ptprm_login_pending', '1', self::login_url() );
        }

        if ( $requested !== '' ) {
            $valid = wp_validate_redirect( $requested, false );
            if ( $valid ) {
                return $valid;
            }
        }
        return PTPRM_Access::portal_url_for_user( $user );
    }

    public static function panel_label_for_user( ?WP_User $user = null ): string {
        $user = $user ?: wp_get_current_user();
        if ( class_exists( 'PTPRM_Member_Portal' ) && PTPRM_Member_Portal::is_anggota( $user ) ) {
            return __( 'Buka Area Anggota', 'ptsbi-premium' );
        }
        if ( class_exists( 'PTPRM_Bidang_Registry' ) ) {
            $bidang = PTPRM_Bidang_Registry::resolve_bidang_slug_for_user( $user );
            if ( $bidang !== '' && isset( PTPRM_Bidang_Registry::bidangs()[ $bidang ] ) ) {
                return sprintf(
                    /* translators: %s: bidang name */
                    __( 'Buka Panel %s', 'ptsbi-premium' ),
                    PTPRM_Bidang_Registry::bidangs()[ $bidang ]['title']
                );
            }
        }
        if ( PTPRM_Access::is_site_admin( $user ) ) {
            return __( 'Buka Dashboard WordPress', 'ptsbi-premium' );
        }
        if ( PTPRM_Access::is_org_admin( $user ) ) {
            return __( 'Buka Panel Admin Organisasi', 'ptsbi-premium' );
        }
        if ( PTPRM_Access::is_manager( $user ) ) {
            return __( 'Buka Panel Pengurus', 'ptsbi-premium' );
        }
        return __( 'Buka Beranda', 'ptsbi-premium' );
    }

    public function handle_login(): void {
        if ( empty( $_POST[ self::POST_LOGIN ] ) ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_LOGIN ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_LOGIN ] ) ), 'ptprm_portal_login' ) ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_login_error',
                    rawurlencode( __( 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba masuk lagi.', 'ptsbi-premium' ) ),
                    self::login_url()
                )
            );
            exit;
        }

        $requested = '';
        if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $maybe = wp_validate_redirect( wp_unslash( (string) $_GET['redirect_to'] ), '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $maybe ) {
                $requested = $maybe;
            }
        }

        $email_input = trim( sanitize_text_field( wp_unslash( (string) ( $_POST['log'] ?? '' ) ) ) );
        $login_input = $email_input;
        if ( strpos( $email_input, '@' ) !== false && is_email( $email_input ) ) {
            $u = get_user_by( 'email', $email_input );
            if ( $u instanceof WP_User ) {
                $login_input = (string) $u->user_login;
            }
        }

        $password = (string) ( $_POST['pwd'] ?? '' );
        $creds    = [
            'user_login'    => $login_input,
            'user_password' => $password,
            'remember'      => ! empty( $_POST['rememberme'] ),
        ];

        // Login utama: WordPress lokal (tidak menunggu API eksternal).
        $user = wp_signon( $creds, is_ssl() );

        // Fallback: verifikasi via email + password (user_login bisa berbeda dari email).
        if ( is_wp_error( $user ) && strpos( $email_input, '@' ) !== false && is_email( $email_input ) ) {
            $by_email = wp_authenticate_email_password( null, $email_input, $password );
            if ( $by_email instanceof WP_User ) {
                $user = $by_email;
                wp_set_current_user( (int) $user->ID );
                wp_set_auth_cookie( (int) $user->ID, ! empty( $_POST['rememberme'] ), is_ssl() );
            }
        }

        // Fallback: sinkron akun default lalu retry sekali.
        if ( is_wp_error( $user ) && class_exists( 'PTPRM_Default_Accounts' ) ) {
            $normalized = strtolower( $email_input );
            $known      = [
                strtolower( (string) PTPRM_Default_Accounts::login_for( 'admin' ) ),
                strtolower( (string) PTPRM_Default_Accounts::login_for( 'pengurus' ) ),
                strtolower( (string) PTPRM_Default_Accounts::login_for( 'anggota' ) ),
                strtolower( (string) PTPRM_Default_Accounts::LOGIN_ADMIN ),
                strtolower( (string) PTPRM_Default_Accounts::LOGIN_PENGURUS ),
                strtolower( (string) PTPRM_Default_Accounts::LOGIN_ANGGOTA ),
            ];
            if ( in_array( $normalized, array_unique( $known ), true ) ) {
                PTPRM_Default_Accounts::ensure_all();

                if ( strpos( $email_input, '@' ) !== false && is_email( $email_input ) ) {
                    $u = get_user_by( 'email', $email_input );
                    if ( $u instanceof WP_User ) {
                        $creds['user_login'] = (string) $u->user_login;
                    }
                }

                $user = wp_signon( $creds, is_ssl() );
            }
        }

        if ( is_wp_error( $user ) ) {
            wp_safe_redirect(
                add_query_arg( 'ptprm_login_error', rawurlencode( $user->get_error_message() ), self::login_url() )
            );
            exit;
        }

        // Pastikan sesi benar-benar aktif sebelum redirect.
        if ( ! is_user_logged_in() ) {
            wp_set_current_user( (int) $user->ID );
            wp_set_auth_cookie( (int) $user->ID, ! empty( $_POST['rememberme'] ), is_ssl() );
        }

        if ( class_exists( 'PTPRM_Default_Accounts' )
            && PTPRM_Default_Accounts::is_demo_anggota_locked()
            && PTPRM_Default_Accounts::is_demo_anggota_user( $user )
        ) {
            wp_logout();
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_login_error',
                    rawurlencode( __( 'Akun demo anggota sudah tidak aktif. Silakan daftar akun sendiri di beranda.', 'ptsbi-premium' ) ),
                    self::login_url()
                )
            );
            exit;
        }

        // Opsional: sinkron token API setelah login lokal sukses (tidak memblokir redirect).
        if ( class_exists( 'PTPRM_Membership_Api_Client' ) && PTPRM_Membership_Api_Client::enabled() ) {
            $api = PTPRM_Membership_Api_Client::post(
                '/auth/login',
                [
                    'email'    => is_email( $email_input ) ? $email_input : (string) $user->user_email,
                    'password' => $password,
                ]
            );
            if ( ! is_wp_error( $api ) ) {
                $token = (string) ( $api['access_token'] ?? '' );
                $role  = (string) ( $api['user']['role'] ?? $api['role'] ?? '' );
                $ttl   = time() + DAY_IN_SECONDS;
                if ( $token !== '' ) {
                    setcookie( 'ptprm_api_access_token', $token, $ttl, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true );
                }
                if ( $role !== '' ) {
                    setcookie( 'ptprm_api_role', $role, $ttl, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), false );
                }
            }
        }

        wp_safe_redirect( self::redirect_after_login( $user, $requested ) );
        exit;
    }

    public function shortcode_login(): string {
        if ( ! is_user_logged_in() ) {
            nocache_headers();
        }
        ob_start();
        echo '<div class="ptprm-portal-login-wrap">';

        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( class_exists( 'PTPRM_Members' ) && PTPRM_Members::is_pending_registration_user( $user ) ) {
                echo '<div class="ptprm-member-card ptprm-portal-card">';
                echo '<h2 class="ptprm-portal-login-title">' . esc_html__( 'Rumah Anggota', 'ptsbi-premium' ) . '</h2>';
                echo '<p>' . esc_html__( 'Pendaftaran Anda masih menunggu persetujuan admin. Setelah disetujui, Anda bisa melengkapi profil.', 'ptsbi-premium' ) . '</p>';
                echo '<div class="ptprm-portal-logout-form" style="margin-top:1rem;">';
                PTPRM_Access::render_logout_link( 'ptprm-cta--ghost' );
                echo '</div>';
                echo '</div></div>';
                return (string) ob_get_clean();
            }

            echo '<div class="ptprm-member-card ptprm-portal-card">';
            echo '<h2 class="ptprm-portal-login-title">' . esc_html__( 'Rumah Anggota', 'ptsbi-premium' ) . '</h2>';
            echo '<p>' . esc_html__( 'Anda sudah masuk.', 'ptsbi-premium' ) . '</p>';
            echo '<a class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium" href="' . esc_url( PTPRM_Access::portal_url_for_user( $user ) ) . '">';
            echo '<span class="ptprm-cta-label">' . esc_html( self::panel_label_for_user( $user ) ) . '</span></a>';
            echo '<div class="ptprm-portal-logout-form" style="margin-top:1rem;">';
            PTPRM_Access::render_logout_link( 'ptprm-cta--ghost' );
            echo '</div>';
            echo '</div></div>';
            return (string) ob_get_clean();
        }

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<h2 class="ptprm-portal-login-title">' . esc_html__( 'Rumah Anggota', 'ptsbi-premium' ) . '</h2>';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Masuk dengan akun Anda. Setelah login, Anda diarahkan ke panel sesuai peran (anggota, pengurus, atau administrator situs).', 'ptsbi-premium' ) . '</p>';

        if ( isset( $_GET['ptprm_login_pending'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Pendaftaran Anda sedang menunggu persetujuan admin.', 'ptsbi-premium' ) . '</p>';
        }

        if ( isset( $_GET['ptprm_login_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $err = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_login_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $err !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $err ) . '</p>';
            }
        }
        if ( isset( $_GET['ptprm_logged_out'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Anda telah keluar.', 'ptsbi-premium' ) . '</p>';
        }

        echo '<form method="post" class="ptprm-member-form ptprm-login-form" action="' . esc_url( self::login_url() ) . '">';
        wp_nonce_field( 'ptprm_portal_login', self::NONCE_LOGIN );
        echo '<input type="hidden" name="' . esc_attr( self::POST_LOGIN ) . '" value="1">';
        echo '<label><span>' . esc_html__( 'Email (username)', 'ptsbi-premium' ) . '</span>';
        echo '<input type="email" name="log" autocomplete="username" required></label>';
        echo '<label><span>' . esc_html__( 'Password', 'ptsbi-premium' ) . '</span>';
        echo '<input type="password" name="pwd" autocomplete="current-password" required></label>';
        echo '<label class="ptprm-member-remember"><input type="checkbox" name="rememberme" value="1"> ' . esc_html__( 'Ingat saya', 'ptsbi-premium' ) . '</label>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Masuk', 'ptsbi-premium' ) . '</span></button>';
        echo '</form>';

        echo '<div style="margin-top:1rem;">';
        if ( class_exists( 'PTPRM_Members' ) && PTPRM_Members::registration_enabled() ) {
            echo do_shortcode( '[ptprm_member_registration]' );
        }
        echo '</div>';
        echo '</div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @return array{login:int}
     */
    public static function ensure_pages(): array {
        $ids = [ 'login' => 0 ];
        $bp  = [
            'slug'    => self::login_slug(),
            'title'   => __( 'Rumah Anggota', 'ptsbi-premium' ),
            'content' => '[ptprm_login_portal]',
        ];

        $existing = get_page_by_path( $bp['slug'], OBJECT, 'page' );
        if ( $existing instanceof WP_Post ) {
            $ids['login'] = (int) $existing->ID;
            if ( strpos( (string) $existing->post_content, 'ptprm_login_portal' ) === false ) {
                wp_update_post(
                    [
                        'ID'           => $existing->ID,
                        'post_content' => $bp['content'],
                    ]
                );
            }
            return $ids;
        }

        $id = wp_insert_post(
            [
                'post_title'   => $bp['title'],
                'post_name'    => $bp['slug'],
                'post_content' => $bp['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ],
            true
        );
        if ( ! is_wp_error( $id ) ) {
            $ids['login'] = (int) $id;
        }

        return $ids;
    }
}
