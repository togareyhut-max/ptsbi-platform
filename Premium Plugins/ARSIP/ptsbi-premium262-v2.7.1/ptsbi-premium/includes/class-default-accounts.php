<?php
/**
 * Akun demo: anggota (terkunci setelah ada anggota nyata), admin & pengurus (password sementara tetap).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Default_Accounts {

    public const TEMP_PASSWORD     = '12345678';
    public const LOGIN_ANGGOTA     = 'anggota';
    public const LOGIN_ADMIN       = 'admin@ptsbi.org';
    public const LOGIN_PENGURUS    = 'pengurus@ptsbi.org';
    public const META_TYPE         = 'ptprm_default_account';
    public const META_TEMP_PASS    = 'ptprm_default_temp_password';
    public const OPTION_LOCK       = 'ptprm_demo_anggota_locked';
    public const OPTION_VERSION    = 'ptprm_default_accounts_version';
    public const OPTION_LOGINS     = 'ptprm_default_account_logins';
    public const ACCOUNTS_VERSION  = 3;

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'maybe_ensure' ], 6 );
        add_filter( 'authenticate', [ __CLASS__, 'block_locked_demo_login' ], 30, 3 );
        add_action( 'ptprm_member_registered', [ __CLASS__, 'maybe_lock_demo_anggota' ] );
        add_action( 'profile_update', [ __CLASS__, 'clear_temp_password_flag_on_change' ], 10, 2 );
    }

    public static function reserved_logins(): array {
        return [ self::LOGIN_ANGGOTA, self::LOGIN_ADMIN, self::LOGIN_PENGURUS ];
    }

    public static function is_reserved_login( string $login ): bool {
        $login = strtolower( $login );
        if ( in_array( $login, self::reserved_logins(), true ) ) {
            return true;
        }
        foreach ( self::get_stored_logins() as $stored ) {
            if ( strtolower( (string) $stored ) === $login ) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, string> */
    public static function get_stored_logins(): array {
        $stored = get_option( self::OPTION_LOGINS, [] );
        return is_array( $stored ) ? $stored : [];
    }

    public static function login_for( string $type ): string {
        $stored = self::get_stored_logins();
        if ( ! empty( $stored[ $type ] ) ) {
            return (string) $stored[ $type ];
        }
        switch ( $type ) {
            case 'admin':
                $preferred = self::LOGIN_ADMIN;
                break;
            case 'pengurus':
                $preferred = self::LOGIN_PENGURUS;
                break;
            case 'anggota':
                $preferred = self::LOGIN_ANGGOTA;
                break;
            default:
                $preferred = $type;
        }
        return $preferred;
    }

    private static function store_login( string $type, string $login ): void {
        $stored         = self::get_stored_logins();
        $stored[ $type ] = $login;
        update_option( self::OPTION_LOGINS, $stored, false );
    }

    private static function resolve_login( string $preferred, string $type ): string {
        $user = get_user_by( 'login', $preferred );
        if ( ! $user instanceof WP_User ) {
            self::store_login( $type, $preferred );
            return $preferred;
        }

        $marked = (string) get_user_meta( $user->ID, self::META_TYPE, true );
        if ( $marked === $type ) {
            self::store_login( $type, $preferred );
            return $preferred;
        }
        if ( $marked !== '' ) {
            return self::login_for( $type );
        }

        // Username sudah dipakai akun lain (mis. administrator WordPress).
        $fallback = 'org-' . $preferred;
        if ( username_exists( $fallback ) ) {
            $fallback = 'ptprm-' . $preferred;
        }
        self::store_login( $type, $fallback );
        return $fallback;
    }

    public static function maybe_ensure(): void {
        if ( (int) get_option( self::OPTION_VERSION, 0 ) >= self::ACCOUNTS_VERSION ) {
            self::maybe_lock_demo_anggota();
            return;
        }
        self::ensure_all();
        update_option( self::OPTION_VERSION, self::ACCOUNTS_VERSION, false );
        self::maybe_lock_demo_anggota();
    }

    public static function ensure_all(): void {
        PTPRM_Members::ensure_roles();
        PTPRM_Access::ensure_capabilities();

        self::ensure_user(
            self::resolve_login( self::LOGIN_ADMIN, 'admin' ),
            'admin_organisasi',
            'admin',
            true
        );
        self::ensure_user(
            self::resolve_login( self::LOGIN_PENGURUS, 'pengurus' ),
            'pengurus',
            'pengurus',
            true
        );
        self::ensure_user(
            self::resolve_login( self::LOGIN_ANGGOTA, 'anggota' ),
            'anggota',
            'anggota',
            false
        );
        self::seed_demo_member_row();
    }

    /**
     * @return int User ID atau 0 jika gagal / bentrok.
     */
    private static function ensure_user( string $login, string $role, string $type, bool $keep_temp_password ): int {
        $email = sanitize_email( $login );

        // Prioritaskan lookup by email untuk pola login berbasis email.
        if ( is_email( $email ) ) {
            $by_email = get_user_by( 'email', $email );
            if ( $by_email instanceof WP_User ) {
                $marked = get_user_meta( $by_email->ID, self::META_TYPE, true );
                if ( $marked === '' || $marked === $type ) {
                    update_user_meta( $by_email->ID, self::META_TYPE, $type );
                    wp_update_user( [ 'ID' => $by_email->ID, 'role' => $role ] );
                    if ( $keep_temp_password || get_user_meta( $by_email->ID, self::META_TEMP_PASS, true ) ) {
                        wp_set_password( self::TEMP_PASSWORD, $by_email->ID );
                        update_user_meta( $by_email->ID, self::META_TEMP_PASS, '1' );
                    }
                    self::store_login( $type, $login );
                    return (int) $by_email->ID;
                }
            }
        }

        $existing = get_user_by( 'login', $login );
        if ( $existing instanceof WP_User ) {
            $marked = get_user_meta( $existing->ID, self::META_TYPE, true );
            if ( $marked !== $type && $marked !== '' ) {
                return 0;
            }
            if ( $marked === '' ) {
                update_user_meta( $existing->ID, self::META_TYPE, $type );
            }
            wp_update_user( [ 'ID' => $existing->ID, 'role' => $role ] );
            if ( $keep_temp_password || get_user_meta( $existing->ID, self::META_TEMP_PASS, true ) ) {
                wp_set_password( self::TEMP_PASSWORD, $existing->ID );
                update_user_meta( $existing->ID, self::META_TEMP_PASS, '1' );
            }
            if ( is_email( $email ) && $existing->user_email !== $email ) {
                wp_update_user(
                    [
                        'ID'         => $existing->ID,
                        'user_email' => $email,
                    ]
                );
            }
            return (int) $existing->ID;
        }

        if ( ! is_email( $email ) ) {
            $email = sanitize_email( $login . '@example.local' );
        }

        $user_id = wp_create_user( $login, self::TEMP_PASSWORD, $email );
        if ( is_wp_error( $user_id ) ) {
            return 0;
        }

        $user = get_user_by( 'id', $user_id );
        if ( $user instanceof WP_User ) {
            $user->set_role( $role );
        }

        update_user_meta( $user_id, self::META_TYPE, $type );
        if ( $keep_temp_password ) {
            update_user_meta( $user_id, self::META_TEMP_PASS, '1' );
        }

        return (int) $user_id;
    }

    private static function seed_demo_member_row(): void {
        $user_id = self::demo_anggota_user_id();
        if ( ! $user_id ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ptprm_members';
        $has   = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id )
        );
        if ( $has > 0 ) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'user_id'          => $user_id,
                'kepala_keluarga'  => __( 'Contoh Kepala Keluarga', 'ptsbi-premium' ),
                'nama_istri'       => __( 'Contoh Istri', 'ptsbi-premium' ),
                'oppu'             => 'Contoh Ompu',
                'city'             => 'Jakarta',
                'district'         => 'Contoh Kecamatan',
                'country_name'     => 'Indonesia',
                'country_code'     => 'ID',
                'created_by'       => $user_id,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ]
        );
    }

    public static function get_user_id( string $login ): int {
        $user = get_user_by( 'login', $login );
        return ( $user instanceof WP_User ) ? (int) $user->ID : 0;
    }

    public static function demo_anggota_user_id(): int {
        return self::get_user_id( self::login_for( 'anggota' ) );
    }

    public static function is_demo_anggota_user( $user ): bool {
        if ( is_numeric( $user ) ) {
            $user = get_user_by( 'id', (int) $user );
        }
        if ( ! $user instanceof WP_User ) {
            return false;
        }
        return get_user_meta( $user->ID, self::META_TYPE, true ) === 'anggota';
    }

    public static function is_demo_anggota_locked(): bool {
        if ( get_option( self::OPTION_LOCK ) ) {
            return true;
        }

        $demo_id = self::demo_anggota_user_id();
        if ( ! $demo_id ) {
            return false;
        }

        $others = get_users(
            [
                'role'    => 'anggota',
                'exclude' => [ $demo_id ],
                'number'  => 1,
                'fields'  => 'ID',
            ]
        );
        if ( ! empty( $others ) ) {
            self::lock_demo_anggota();
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ptprm_members';
        $linked = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id > 0 AND user_id != %d",
                $demo_id
            )
        );
        if ( $linked > 0 ) {
            self::lock_demo_anggota();
            return true;
        }

        return false;
    }

    public static function lock_demo_anggota(): void {
        update_option( self::OPTION_LOCK, '1', false );
    }

    public static function maybe_lock_demo_anggota(): void {
        if ( self::is_demo_anggota_locked() ) {
            return;
        }
        $demo_id = self::demo_anggota_user_id();
        if ( ! $demo_id ) {
            return;
        }

        $others = get_users(
            [
                'role'    => 'anggota',
                'exclude' => [ $demo_id ],
                'number'  => 1,
                'fields'  => 'ID',
            ]
        );
        if ( ! empty( $others ) ) {
            self::lock_demo_anggota();
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ptprm_members';
        $linked = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id > 0 AND user_id != %d",
                $demo_id
            )
        );
        if ( $linked > 0 ) {
            self::lock_demo_anggota();
        }
    }

    /**
     * @param WP_User|WP_Error|null $user
     * @return WP_User|WP_Error|null
     */
    public static function block_locked_demo_login( $user, $username, $password ) {
        unset( $password );
        if ( ! self::is_demo_anggota_locked() ) {
            return $user;
        }
        if ( strtolower( (string) $username ) !== strtolower( self::login_for( 'anggota' ) ) ) {
            return $user;
        }
        return new WP_Error(
            'ptprm_demo_locked',
            __( 'Akun demo anggota sudah tidak aktif karena sudah ada anggota terdaftar. Silakan daftar akun sendiri atau gunakan akun Anda.', 'ptsbi-premium' )
        );
    }

    public static function clear_temp_password_flag_on_change( $user_id, $old_user_data ): void {
        unset( $old_user_data );
        if ( ! isset( $_POST['pass1'] ) || (string) $_POST['pass1'] === '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }
        delete_user_meta( (int) $user_id, self::META_TEMP_PASS );
    }

    /**
     * Info untuk panel wp-admin (hanya manage_options).
     *
     * @return array<int, array{label: string, login: string, password: string, url: string, note: string}>
     */
    public static function credentials_for_admin(): array {
        $member_login = class_exists( 'PTPRM_Login_Portal' )
            ? PTPRM_Login_Portal::login_url()
            : ( class_exists( 'PTPRM_Member_Portal' ) ? PTPRM_Member_Portal::login_url() : home_url( '/rumah-anggota/' ) );
        $panel_login  = $member_login;

        $anggota_note = self::is_demo_anggota_locked()
            ? __( 'Terkunci — sudah ada anggota terdaftar.', 'ptsbi-premium' )
            : __( 'Hanya untuk uji coba. Ganti password setelah ada anggota nyata.', 'ptsbi-premium' );

        return [
            [
                'label'    => __( 'Anggota (demo)', 'ptsbi-premium' ),
                'login'    => self::login_for( 'anggota' ),
                'password' => self::is_demo_anggota_locked() ? '—' : self::TEMP_PASSWORD,
                'url'      => $member_login,
                'note'     => $anggota_note,
            ],
            [
                'label'    => __( 'Admin organisasi (panel depan)', 'ptsbi-premium' ),
                'login'    => self::login_for( 'admin' ),
                'password' => self::TEMP_PASSWORD,
                'url'      => $panel_login,
                'note'     => __( 'Password sementara — ganti setelah produksi. Bukan akun wp-admin WordPress.', 'ptsbi-premium' ),
            ],
            [
                'label'    => __( 'Pengurus (panel depan)', 'ptsbi-premium' ),
                'login'    => self::login_for( 'pengurus' ),
                'password' => self::TEMP_PASSWORD,
                'url'      => $panel_login,
                'note'     => __( 'Password sementara — ganti setelah produksi.', 'ptsbi-premium' ),
            ],
        ];
    }
}
