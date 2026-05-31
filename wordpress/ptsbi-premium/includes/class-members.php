<?php
/**
 * Anggota module: registration, directory, CSV import, XLSX export.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Members {

    private const TABLE_MEMBERS  = 'ptprm_members';
    private const TABLE_ADDRESS  = 'ptprm_address_master';
    private const ROLE_MEMBER       = 'anggota';
    private const ROLE_MANAGER      = 'pengurus';
    private const ROLE_ORG_ADMIN    = 'admin_organisasi';
    private const QUERY_VAR_NONCE   = 'ptprm_members_nonce';
    private const SCHEMA_VERSION    = 4;
    private const PER_PAGE_DEFAULT  = 25;
    private const PER_PAGE_MAX      = 50;
    private const IMPORT_FLUSH_EVERY = 100;
    private const EXPORT_CHUNK      = 500;
    public const EXPORT_NONCE_ACTION = 'ptprm_members_export_xlsx';

    // Pending registration (approval) lives in user_meta until admin approves.
    public const META_PENDING_STATUS  = 'ptprm_member_reg_status';
    public const META_PENDING_NAME    = 'ptprm_member_reg_name';
    public const META_PENDING_WA      = 'ptprm_member_reg_wa';
    public const META_PENDING_CREATED = 'ptprm_member_reg_created_at';
    public const META_APPROVED_AT     = 'ptprm_member_reg_approved_at';
    public const PENDING_STATUS_VALUE = 'pending';

    public function __construct() {
        add_action( 'init', [ $this, 'maybe_upgrade' ], 5 );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_handle_export_download' ], 0 );
        add_action( 'init', [ $this, 'handle_front_import_csv' ], 20 );
        add_action( 'init', [ $this, 'handle_front_registration' ], 20 );
        add_shortcode( 'ptprm_member_registration', [ $this, 'shortcode_registration' ] );
        add_shortcode( 'ptprm_member_directory', [ $this, 'shortcode_directory' ] );
    }

    /**
     * Unduh XLSX dari panel admin (jalankan sebelum tema mengirim HTML).
     */
    public static function maybe_handle_export_download(): void {
        if ( empty( $_GET['ptprm_export_members'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        if ( ! is_user_logged_in() ) {
            status_header( 403 );
            wp_die( esc_html__( 'Silakan masuk terlebih dahulu.', 'ptsbi-premium' ), 403 );
        }

        if ( ! self::user_can_export() ) {
            status_header( 403 );
            wp_die( esc_html__( 'Anda tidak memiliki akses export data anggota.', 'ptsbi-premium' ), 403 );
        }

        $nonce = isset( $_GET['_wpnonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) )
            : '';
        if ( ! wp_verify_nonce( $nonce, self::EXPORT_NONCE_ACTION ) ) {
            status_header( 403 );
            wp_die( esc_html__( 'Tautan unduh tidak valid atau kedaluwarsa. Muat ulang halaman panel lalu coba lagi.', 'ptsbi-premium' ), 403 );
        }

        self::stream_export_xlsx();
    }

    public static function user_can_export(): bool {
        if ( ! class_exists( 'PTPRM_Access' ) ) {
            return current_user_can( 'manage_options' );
        }
        if ( PTPRM_Access::can_approve_members()
            || PTPRM_Access::can_manage_org_settings()
            || PTPRM_Access::can_manage() ) {
            return true;
        }
        return class_exists( 'PTPRM_Bidang_Registry' ) && PTPRM_Bidang_Registry::is_sekretariat_user();
    }

    public static function render_export_download_control(): void {
        if ( ! self::user_can_export() ) {
            return;
        }

        $action = class_exists( 'PTPRM_Admin_Portal' )
            ? PTPRM_Admin_Portal::portal_url( 'anggota' )
            : home_url( '/panel-pengurus/' );

        echo '<form method="get" action="' . esc_url( $action ) . '" class="ptprm-export-xlsx-form">';
        echo '<input type="hidden" name="ptprm_export_members" value="1">';
        wp_nonce_field( self::EXPORT_NONCE_ACTION, '_wpnonce', false );
        echo '<button type="submit" class="ptprm-cta ptprm-cta-outline ptprm-cta-size-medium">';
        echo '<span class="ptprm-cta-label">' . esc_html__( 'Unduh XLSX', 'ptsbi-premium' ) . '</span>';
        echo '</button></form>';
    }

    public static function export_download_url(): string {
        $base = class_exists( 'PTPRM_Admin_Portal' )
            ? PTPRM_Admin_Portal::portal_url( 'anggota' )
            : home_url( '/panel-pengurus/' );

        return wp_nonce_url(
            add_query_arg( 'ptprm_export_members', '1', $base ),
            self::EXPORT_NONCE_ACTION
        );
    }

    public function maybe_upgrade(): void {
        self::ensure_roles();
        $schema = (int) get_option( 'ptprm_members_schema', 0 );
        if ( $schema < self::SCHEMA_VERSION ) {
            self::install();
            self::ensure_indexes();
            if ( $schema > 0 && $schema < 4 ) {
                self::migrate_status_labels();
            }
            update_option( 'ptprm_members_schema', self::SCHEMA_VERSION, false );
        }
    }

    public static function per_page(): int {
        $o   = ptprm_options();
        $n   = (int) ( $o['members_per_page'] ?? self::PER_PAGE_DEFAULT );
        $n   = max( 10, min( self::PER_PAGE_MAX, $n ) );
        return $n;
    }

    public static function registration_enabled(): bool {
        $o = ptprm_options();
        return ! isset( $o['members_register_show'] ) || ! empty( $o['members_register_show'] );
    }

    public static function directory_enabled(): bool {
        $o = ptprm_options();
        return ! isset( $o['members_directory_show'] ) || ! empty( $o['members_directory_show'] );
    }

    public static function registration_auto_approve(): bool {
        $o = ptprm_options();
        return ! empty( $o['members_register_auto_approve'] );
    }

    public static function is_pending_registration_user( $user_or_id ): bool {
        $user = null;
        if ( $user_or_id instanceof WP_User ) {
            $user = $user_or_id;
        } elseif ( is_numeric( $user_or_id ) ) {
            $user = get_user_by( 'id', (int) $user_or_id );
        }
        if ( ! $user instanceof WP_User ) {
            return false;
        }

        // Pending hanya untuk pendaftar anggota baru, bukan admin/pengurus.
        if ( class_exists( 'PTPRM_Access' ) && ( PTPRM_Access::is_manager( $user ) || PTPRM_Access::is_site_admin( $user ) ) ) {
            return false;
        }

        $status = (string) get_user_meta( (int) $user->ID, self::META_PENDING_STATUS, true );
        return $status === self::PENDING_STATUS_VALUE;
    }

    /**
     * @return array<int, array{user_id:int,email:string,name:string,wa:string,created_at:string}>
     */
    public static function list_pending_registrations(): array {
        $q = new WP_User_Query(
            [
                'number'  => 50,
                'orderby' => 'registered',
                'order'   => 'DESC',
                'meta_query' => [
                    [
                        'key'     => self::META_PENDING_STATUS,
                        'value'   => self::PENDING_STATUS_VALUE,
                        'compare' => '=',
                    ],
                ],
            ]
        );

        $users = (array) ( $q->get_results() ?? [] );
        $out   = [];
        foreach ( $users as $u ) {
            if ( ! $u instanceof WP_User ) {
                continue;
            }
            $user_id = (int) $u->ID;
            $out[]   = [
                'user_id'    => $user_id,
                'email'      => (string) $u->user_email,
                'name'       => (string) get_user_meta( $user_id, self::META_PENDING_NAME, true ),
                'wa'         => (string) get_user_meta( $user_id, self::META_PENDING_WA, true ),
                'created_at' => (string) ( get_user_meta( $user_id, self::META_PENDING_CREATED, true ) ?: '' ),
            ];
        }
        return $out;
    }

    public static function approve_pending_registration( int $user_id ): bool {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User ) {
            return false;
        }
        if ( ! self::is_pending_registration_user( $user_id ) ) {
            return false;
        }

        $name = (string) get_user_meta( $user_id, self::META_PENDING_NAME, true );
        $wa   = (string) get_user_meta( $user_id, self::META_PENDING_WA, true );

        wp_update_user(
            [
                'ID'           => $user_id,
                'role'         => self::ROLE_MEMBER,
                'display_name' => $name,
            ]
        );

        global $wpdb;
        $table_members = $wpdb->prefix . self::TABLE_MEMBERS;
        $row_data      = [
            'kepala_keluarga' => $name,
            'phone'           => $wa,
            'country_name'    => 'Indonesia',
            'country_code'    => 'ID',
            'updated_at'      => current_time( 'mysql' ),
        ];
        $existing = self::get_record_by_user_id( $user_id );
        if ( $existing ) {
            $row_data['kepala_keluarga'] = $name !== '' ? $name : (string) ( $existing['kepala_keluarga'] ?? '' );
            $row_data['phone']           = $wa !== '' ? $wa : (string) ( $existing['phone'] ?? '' );
            $merge                        = array_merge( (array) $existing, $row_data );
            unset( $merge['id'], $merge['user_id'], $merge['created_at'], $merge['created_by'] );
            $merge['updated_at'] = current_time( 'mysql' );
            $wpdb->update( $table_members, $merge, [ 'user_id' => $user_id ] );
        } else {
            $wpdb->insert(
                $table_members,
                array_merge(
                    $row_data,
                    [
                        'user_id'    => $user_id,
                        'created_by' => $user_id,
                        'created_at' => current_time( 'mysql' ),
                    ]
                )
            );
        }

        delete_user_meta( $user_id, self::META_PENDING_STATUS );
        delete_user_meta( $user_id, self::META_PENDING_NAME );
        delete_user_meta( $user_id, self::META_PENDING_WA );
        delete_user_meta( $user_id, self::META_PENDING_CREATED );
        delete_user_meta( $user_id, 'ptprm_pending_profile' );
        update_user_meta( $user_id, self::META_APPROVED_AT, current_time( 'mysql' ) );

        do_action( 'ptprm_member_registered', $user_id );

        // Kunci akun demo anggota jika sudah ada anggota terdata.
        if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
            PTPRM_Default_Accounts::maybe_lock_demo_anggota();
        }

        self::notify_approved_registration( $user_id, $name, $wa, (string) $user->user_email );
        return true;
    }

    /**
     * Tolak pendaftaran pending dan hapus akun WordPress beserta data terkait.
     */
    public static function reject_and_delete_pending_registration( int $user_id ): bool {
        if ( ! self::is_pending_registration_user( $user_id ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        $wpdb->delete( $table, [ 'user_id' => $user_id ], [ '%d' ] );

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user( $user_id );
        return (bool) $deleted;
    }

    /**
     * @param array{nama?:string,status?:string,oppu?:string,provinsi?:string,kota?:string,kecamatan?:string,kelurahan?:string} $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function search_directory( array $filters, int $page, int $per_page, bool $full_columns ): array {
        return $this->search_members( $filters, $page, $per_page, $full_columns );
    }

    private static function notify_approved_registration( int $user_id, string $name, string $wa, string $email ): void {
        $o = ptprm_options();
        $notify_email = ! empty( $o['members_register_notify_email'] );
        $notify_wa    = ! empty( $o['members_register_notify_wa'] );

        if ( ! $notify_email && ! $notify_wa ) {
            return;
        }

        $login_url = class_exists( 'PTPRM_Login_Portal' )
            ? PTPRM_Login_Portal::login_url()
            : home_url( '/rumah-anggota/' );
        $org       = class_exists( 'PTPRM_Bootstrap' ) ? PTPRM_Bootstrap::org_name() : (string) get_bloginfo( 'name' );

        $body = sprintf(
            "Halo %s,\n\nPendaftaran Anda sebagai anggota organisasi %s telah disetujui.\n\nLogin di: %s\nUsername (email): %s\nPassword: (gunakan password yang Anda buat saat pendaftaran)\n",
            $name !== '' ? $name : $email,
            $org,
            $login_url,
            $email
        );

        if ( $notify_wa && $wa !== '' ) {
            $digits = ptprm_wa_digits( $wa );
            if ( $digits ) {
                $text = rawurlencode( sprintf( "Halo, pendaftaran Anda di %s sudah disetujui. Silakan login di %s.", $org, $login_url ) );
                $wa_link = 'https://wa.me/' . $digits . '?text=' . $text;
                $body   .= "\nLink WhatsApp (pesan siap dikirim):\n" . $wa_link . "\n";
            }
        }

        wp_mail( $email, __( 'Pendaftaran Anggota Disetujui', 'ptsbi-premium' ), $body );
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $members = $wpdb->prefix . self::TABLE_MEMBERS;
        $address = $wpdb->prefix . self::TABLE_ADDRESS;

        $sql_members = "CREATE TABLE {$members} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            family_no VARCHAR(50) NOT NULL DEFAULT '',
            kepala_keluarga VARCHAR(190) NOT NULL DEFAULT '',
            nama_istri VARCHAR(190) NOT NULL DEFAULT '',
            tarombo VARCHAR(190) NOT NULL DEFAULT '',
            oppu VARCHAR(190) NOT NULL DEFAULT '',
            nomor_sundut VARCHAR(50) NOT NULL DEFAULT '',
            hula_boru VARCHAR(50) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            country_code VARCHAR(10) NOT NULL DEFAULT 'ID',
            country_name VARCHAR(120) NOT NULL DEFAULT 'Indonesia',
            state_city VARCHAR(190) NOT NULL DEFAULT '',
            province VARCHAR(190) NOT NULL DEFAULT '',
            city VARCHAR(190) NOT NULL DEFAULT '',
            district VARCHAR(190) NOT NULL DEFAULT '',
            subdistrict VARCHAR(190) NOT NULL DEFAULT '',
            postal_code VARCHAR(20) NOT NULL DEFAULT '',
            rt VARCHAR(20) NOT NULL DEFAULT '',
            rw VARCHAR(20) NOT NULL DEFAULT '',
            address_detail TEXT NULL,
            street_name VARCHAR(190) NOT NULL DEFAULT '',
            house_number VARCHAR(50) NOT NULL DEFAULT '',
            is_overseas TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_kepala (kepala_keluarga),
            KEY idx_oppu (oppu),
            KEY idx_city (city),
            KEY idx_district (district),
            KEY idx_country (country_code)
        ) {$charset};";

        $sql_address = "CREATE TABLE {$address} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            country_code VARCHAR(10) NOT NULL DEFAULT 'ID',
            country_name VARCHAR(120) NOT NULL DEFAULT 'Indonesia',
            province VARCHAR(190) NOT NULL DEFAULT '',
            city VARCHAR(190) NOT NULL DEFAULT '',
            district VARCHAR(190) NOT NULL DEFAULT '',
            subdistrict VARCHAR(190) NOT NULL DEFAULT '',
            state_city VARCHAR(190) NOT NULL DEFAULT '',
            postal_code VARCHAR(20) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_city (city),
            KEY idx_district (district),
            KEY idx_subdistrict (subdistrict),
            KEY idx_country (country_code)
        ) {$charset};";

        dbDelta( $sql_members );
        dbDelta( $sql_address );
        self::ensure_indexes();

        self::ensure_roles();
    }

    /**
     * Index tambahan untuk filter (dbDelta tidak selalu menambah index baru).
     */
    public static function ensure_indexes(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;

        $wanted = [
            'idx_city_district' => 'city(80), district(80)',
            'idx_oppu_kepala'   => 'oppu(80), kepala_keluarga(80)',
        ];

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 );
        $existing = is_array( $existing ) ? array_unique( $existing ) : [];

        foreach ( $wanted as $name => $cols ) {
            if ( in_array( $name, $existing, true ) ) {
                continue;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE {$table} ADD INDEX {$name} ({$cols})" );
        }
    }

    /**
     * Pilihan Ompu resmi (Paroppuon).
     *
     * @return array<string, string>
     */
    public static function ompu_choices(): array {
        return [
            'Ruma Bolon'  => 'Ruma Bolon',
            'Ruma Surung' => 'Ruma Surung',
            'Ruma Sidari' => 'Ruma Sidari',
        ];
    }

    /**
     * Gabungkan jalan + nomor rumah ke kolom address_detail (kompatibel impor CSV).
     */
    public static function compose_address_detail( string $street, string $house, string $legacy = '' ): string {
        $street = trim( $street );
        $house  = trim( $house );
        if ( $street !== '' && $house !== '' ) {
            return $street . ', No. ' . $house;
        }
        if ( $street !== '' ) {
            return $street;
        }
        if ( $house !== '' ) {
            return 'No. ' . $house;
        }
        return trim( $legacy );
    }

    /**
     * @return array{street:string,house:string}
     */
    public static function split_address_detail( string $address_detail, string $street_name = '', string $house_number = '' ): array {
        if ( trim( $street_name ) !== '' || trim( $house_number ) !== '' ) {
            return [
                'street' => trim( $street_name ),
                'house'  => trim( $house_number ),
            ];
        }
        $address_detail = trim( $address_detail );
        if ( $address_detail === '' ) {
            return [ 'street' => '', 'house' => '' ];
        }
        if ( preg_match( '/^(.+?),\s*No\.?\s*(.+)$/iu', $address_detail, $m ) ) {
            return [
                'street' => trim( (string) $m[1] ),
                'house'  => trim( (string) $m[2] ),
            ];
        }
        return [ 'street' => $address_detail, 'house' => '' ];
    }

    public static function render_ompu_select( string $current, string $name = 'oppu' ): void {
        echo '<label><span>Ompu (Paroppuon)</span>';
        echo '<select name="' . esc_attr( $name ) . '">';
        echo '<option value="">' . esc_html__( '— Pilih Ompu —', 'ptsbi-premium' ) . '</option>';
        foreach ( self::ompu_choices() as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $current, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        if ( $current !== '' && ! isset( self::ompu_choices()[ $current ] ) ) {
            echo '<option value="' . esc_attr( $current ) . '" selected>' . esc_html( $current ) . '</option>';
        }
        echo '</select></label>';
    }

    /**
     * Normalisasi label status dari CSV lama (Hula/Boru) ke Anak/Boru/Bere/Ibebere.
     */
    public static function normalize_status_label( string $raw ): string {
        $k = strtolower( trim( $raw ) );
        if ( $k === '' || $k === '-' ) {
            return '';
        }
        if ( strpos( $k, 'hula' ) !== false || $k === 'anak' ) {
            return 'Anak';
        }
        if ( $k === 'boru' ) {
            return 'Boru';
        }
        if ( $k === 'bere' ) {
            return 'Bere';
        }
        if ( strpos( $k, 'ibebere' ) !== false || strpos( $k, 'ibe bere' ) !== false ) {
            return 'Ibebere';
        }
        return ucwords( $k );
    }

    /**
     * @return array<string, string>
     */
    public static function status_filter_choices(): array {
        return [
            ''        => __( 'Semua status', 'ptsbi-premium' ),
            'anak'    => 'Anak',
            'boru'    => 'Boru',
            'bere'    => 'Bere',
            'ibebere' => 'Ibebere',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_profile_choices(): array {
        return [
            ''        => __( '— Pilih status —', 'ptsbi-premium' ),
            'Anak'    => 'Anak',
            'Boru'    => 'Boru',
            'Bere'    => 'Bere',
            'Ibebere' => 'Ibebere',
        ];
    }

    public static function resolve_member_status( string $kepala, string $nama_istri, string $stored = '' ): string {
        $stored = self::normalize_status_label( $stored );
        if ( in_array( $stored, [ 'Anak', 'Boru', 'Bere', 'Ibebere' ], true ) ) {
            return $stored;
        }
        if ( self::kepala_is_samosir_anak( $kepala ) ) {
            return 'Anak';
        }
        if ( self::spouse_is_boru_samosir( $nama_istri ) ) {
            return 'Boru';
        }
        return '';
    }

    public static function render_status_select( string $current, string $kepala = '', string $istri = '' ): void {
        if ( $current === '' ) {
            $current = self::resolve_member_status( $kepala, $istri );
        } else {
            $current = self::normalize_status_label( $current );
        }
        echo '<label><span>' . esc_html__( 'Status', 'ptsbi-premium' ) . '</span>';
        echo '<select name="member_status">';
        foreach ( self::status_profile_choices() as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $current, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        $auto = self::resolve_member_status( $kepala, $istri, $current );
        if ( $auto !== '' && $auto !== $current ) {
            echo '<p class="ptprm-portal-help ptprm-member-full">' . esc_html__( 'Saran otomatis dari marga', 'ptsbi-premium' ) . ': <strong>' . esc_html( $auto ) . '</strong></p>';
        }
    }

    private static function migrate_status_labels(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "UPDATE {$table} SET hula_boru = 'Anak' WHERE hula_boru LIKE '%Hula%' OR hula_boru IN ('Hula-Hula','Hula Hula','hula-hula')" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "UPDATE {$table} SET hula_boru = 'Boru' WHERE LOWER(hula_boru) = 'boru'" );
    }

    public static function count_members(): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_record_by_user_id( int $user_id ): ?array {
        if ( $user_id <= 0 ) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC, id DESC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
        return is_array( $row ) ? $row : null;
    }

    /**
     * Data profil untuk form (gabung tabel anggota + meta pendaftaran).
     *
     * @return array<string,mixed>
     */
    public static function get_profile_record_for_user( int $user_id ): array {
        $defaults = [
            'kepala_keluarga' => '',
            'nama_istri'      => '',
            'tarombo'         => '',
            'oppu'            => '',
            'nomor_sundut'    => '',
            'phone'           => '',
            'country_name'    => 'Indonesia',
            'country_code'    => 'ID',
            'province'        => '',
            'city'            => '',
            'district'        => '',
            'subdistrict'     => '',
            'postal_code'     => '',
            'state_city'      => '',
            'address_detail'  => '',
            'street_name'     => '',
            'house_number'    => '',
            'rt'              => '',
            'rw'              => '',
            'is_overseas'     => 0,
        ];

        $record = self::get_record_by_user_id( $user_id );
        if ( is_array( $record ) ) {
            $merged = array_merge( $defaults, $record );
            $parts  = self::split_address_detail(
                (string) ( $merged['address_detail'] ?? '' ),
                (string) ( $merged['street_name'] ?? '' ),
                (string) ( $merged['house_number'] ?? '' )
            );
            $merged['street_name']  = $parts['street'];
            $merged['house_number'] = $parts['house'];
            return $merged;
        }

        if ( self::is_pending_registration_user( $user_id ) ) {
            $defaults['kepala_keluarga'] = (string) get_user_meta( $user_id, self::META_PENDING_NAME, true );
            $defaults['phone']           = (string) get_user_meta( $user_id, self::META_PENDING_WA, true );
            $draft                       = get_user_meta( $user_id, 'ptprm_pending_profile', true );
            if ( is_string( $draft ) && $draft !== '' ) {
                $decoded = json_decode( $draft, true );
                if ( is_array( $decoded ) ) {
                    $defaults = array_merge( $defaults, $decoded );
                }
            }
            return $defaults;
        }

        $user = get_user_by( 'id', $user_id );
        if ( $user instanceof WP_User ) {
            $defaults['kepala_keluarga'] = trim( (string) $user->display_name );
        }
        return $defaults;
    }

    /**
     * Sinkronkan nama/HP pendaftaran ke user WP + meta pending.
     *
     * @param array<string,mixed> $data
     */
    private static function sync_registration_identity( int $user_id, array $data ): void {
        $kepala = trim( (string) ( $data['kepala_keluarga'] ?? '' ) );
        $phone  = trim( (string) ( $data['phone'] ?? '' ) );

        if ( $kepala !== '' ) {
            wp_update_user(
                [
                    'ID'           => $user_id,
                    'display_name' => $kepala,
                ]
            );
        }

        if ( self::is_pending_registration_user( $user_id ) ) {
            if ( $kepala !== '' ) {
                update_user_meta( $user_id, self::META_PENDING_NAME, $kepala );
            }
            if ( $phone !== '' ) {
                update_user_meta( $user_id, self::META_PENDING_WA, $phone );
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function sanitize_member_row( array $data ): array {
        $allowed = [
            'kepala_keluarga', 'nama_istri', 'tarombo', 'oppu', 'nomor_sundut', 'phone',
            'country_name', 'country_code', 'province', 'city', 'district', 'subdistrict',
            'postal_code', 'state_city', 'address_detail', 'street_name', 'house_number',
            'rt', 'rw', 'is_overseas', 'hula_boru',
            'updated_at', 'user_id', 'created_by', 'created_at',
        ];
        $row = [];
        foreach ( $allowed as $key ) {
            if ( array_key_exists( $key, $data ) ) {
                $row[ $key ] = $data[ $key ];
            }
        }
        if ( isset( $row['is_overseas'] ) ) {
            $row['is_overseas'] = ! empty( $row['is_overseas'] ) ? 1 : 0;
        }
        return $row;
    }

    public static function save_profile_for_user( int $user_id, array $data ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }
        if ( empty( $data['is_overseas'] ) && class_exists( 'PTPRM_Address_Regions' ) ) {
            $norm = PTPRM_Address_Regions::normalize_address_fields(
                (string) ( $data['province'] ?? '' ),
                (string) ( $data['city'] ?? '' ),
                (string) ( $data['district'] ?? '' ),
                (string) ( $data['subdistrict'] ?? '' )
            );
            $data['province']    = $norm['province'];
            $data['city']        = $norm['city'];
            $data['district']    = $norm['district'];
            $data['subdistrict'] = $norm['subdistrict'];
        }
        if ( isset( $data['street_name'] ) || isset( $data['house_number'] ) ) {
            $data['address_detail'] = self::compose_address_detail(
                (string) ( $data['street_name'] ?? '' ),
                (string) ( $data['house_number'] ?? '' ),
                (string) ( $data['address_detail'] ?? '' )
            );
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;

        if ( function_exists( 'ptprm_members_table_exists' ) && ! ptprm_members_table_exists() ) {
            self::install();
        }

        $data  = self::sanitize_member_row( array_merge( $data, [ 'updated_at' => current_time( 'mysql' ) ] ) );

        self::sync_registration_identity( $user_id, $data );

        if ( self::is_pending_registration_user( $user_id ) ) {
            update_user_meta( $user_id, 'ptprm_pending_profile', wp_json_encode( $data ) );
        }

        // Hindari error "Unknown column" pada DB yang skemanya belum diupgrade penuh.
        $data = self::filter_to_existing_columns( $data );

        $existing = self::get_record_by_user_id( $user_id );
        if ( $existing ) {
            unset( $data['user_id'], $data['created_at'], $data['created_by'] );
            if ( $data === [] ) {
                return true;
            }
            $updated = $wpdb->update( $table, $data, [ 'user_id' => $user_id ], null, [ '%d' ] );
            return false !== $updated;
        }

        $data['user_id']    = $user_id;
        $data['created_by'] = $user_id;
        $data['created_at'] = current_time( 'mysql' );
        $inserted           = $wpdb->insert( $table, $data );
        return false !== $inserted;
    }

    /**
     * Daftar kolom yang benar-benar ada di tabel anggota (cache per-request).
     *
     * @return array<int,string>
     */
    private static function existing_member_columns(): array {
        static $cols = null;
        if ( is_array( $cols ) ) {
            return $cols;
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $found = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        $cols  = is_array( $found ) ? $found : [];
        return $cols;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function filter_to_existing_columns( array $data ): array {
        $cols = self::existing_member_columns();
        if ( $cols === [] ) {
            return $data;
        }
        return array_intersect_key( $data, array_flip( $cols ) );
    }

    /**
     * Alamat untuk direktori: anggota hanya sampai kecamatan (+ kota, provinsi).
     */
    public static function format_directory_address( array $row, bool $full_detail ): string {
        if ( $full_detail ) {
            return trim(
                implode(
                    ', ',
                    array_filter(
                        [
                            (string) ( $row['address_detail'] ?? '' ),
                            (string) ( $row['subdistrict'] ?? '' ),
                            (string) ( $row['district'] ?? '' ),
                            (string) ( $row['city'] ?? '' ),
                            (string) ( $row['province'] ?? '' ),
                            (string) ( $row['postal_code'] ?? '' ),
                            (string) ( $row['country_name'] ?? '' ),
                        ]
                    )
                )
            );
        }

        return trim(
            implode(
                ', ',
                array_filter(
                    [
                        (string) ( $row['district'] ?? '' ),
                        (string) ( $row['city'] ?? '' ),
                        (string) ( $row['province'] ?? '' ),
                        (string) ( $row['country_name'] ?? 'Indonesia' ),
                    ]
                )
            )
        );
    }

    public static function ensure_roles(): void {
        add_role(
            self::ROLE_MEMBER,
            __( 'Anggota', 'ptsbi-premium' ),
            [ 'read' => true ]
        );
        add_role(
            self::ROLE_MANAGER,
            __( 'Pengurus', 'ptsbi-premium' ),
            [ 'read' => true ]
        );
        add_role(
            self::ROLE_ORG_ADMIN,
            __( 'Admin Organisasi', 'ptsbi-premium' ),
            [ 'read' => true ]
        );
        if ( class_exists( 'PTPRM_Access' ) ) {
            PTPRM_Access::ensure_capabilities();
        }
    }

    public static function render_home_registration_section(): void {
        if ( ! self::registration_enabled() ) {
            return;
        }

        $logged_in = is_user_logged_in();

        echo '<section id="daftar-anggota" class="ptprm-section ptprm-member-section' . ( $logged_in ? ' ptprm-member-section-portal' : '' ) . '">';
        echo '<div class="ptprm-container">';
        echo '<div class="ptprm-text-center">';
        if ( $logged_in ) {
            echo '<span class="ptprm-eyebrow">' . esc_html__( 'Area Anggota', 'ptsbi-premium' ) . '</span>';
            echo '<h2 class="ptprm-h2">' . esc_html__( 'Panel Anggota', 'ptsbi-premium' ) . '</h2>';
        } else {
            echo '<span class="ptprm-eyebrow">' . esc_html__( 'Pendaftaran Anggota', 'ptsbi-premium' ) . '</span>';
            echo '<h2 class="ptprm-h2">' . esc_html__( 'Daftar Akun Anggota', 'ptsbi-premium' ) . '</h2>';
        }
        echo '</div>';

        if ( $logged_in && class_exists( 'PTPRM_Member_Portal' ) ) {
            echo do_shortcode( '[ptprm_member_portal]' );
        } else {
            echo do_shortcode( '[ptprm_member_registration]' );
        }

        echo '</div></section>';
    }

    public static function render_home_directory_section(): void {
        if ( ! self::directory_enabled() ) {
            return;
        }
        echo '<section id="cari-anggota" class="ptprm-section ptprm-member-section ptprm-member-section-directory">';
        echo '<div class="ptprm-container">';
        echo '<div class="ptprm-text-center">';
        echo '<span class="ptprm-eyebrow">' . esc_html__( 'Direktori Anggota', 'ptsbi-premium' ) . '</span>';
        echo '<h2 class="ptprm-h2">' . esc_html__( 'Cari Data Keluarga', 'ptsbi-premium' ) . '</h2>';
        echo '</div>';
        echo do_shortcode( '[ptprm_member_directory]' );
        echo '</div></section>';
    }

    public function handle_front_import_csv(): void {
        if ( empty( $_POST['ptprm_members_import_csv'] ) ) {
            return;
        }
        if ( ! PTPRM_Access::can_approve_members() ) {
            wp_die( esc_html__( 'Akses ditolak.', 'ptsbi-premium' ) );
        }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'ptprm_members_import_csv' ) ) {
            wp_die( esc_html__( 'Sesi tidak valid.', 'ptsbi-premium' ) );
        }

        if ( empty( $_FILES['ptprm_members_csv']['tmp_name'] ) ) {
            $this->redirect_admin_error( __( 'File CSV tidak ditemukan.', 'ptsbi-premium' ) );
        }

        $tmp = (string) $_FILES['ptprm_members_csv']['tmp_name'];
        $fh  = fopen( $tmp, 'r' );
        if ( ! $fh ) {
            $this->redirect_admin_error( __( 'Gagal membaca file CSV.', 'ptsbi-premium' ) );
        }

        global $wpdb;
        $table_members = $wpdb->prefix . self::TABLE_MEMBERS;
        $table_address = $wpdb->prefix . self::TABLE_ADDRESS;

        if ( ! empty( $_POST['replace_all'] ) ) {
            $wpdb->query( "TRUNCATE TABLE {$table_members}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        wp_suspend_cache_addition( true );
        $csv_format   = 'legacy';
        $header_seen  = false;
        $row_no       = 0;
        $imported     = 0;
        $address_seen = [];

        while ( ( $row = fgetcsv( $fh, 0, ';' ) ) !== false ) {
            $row_no++;
            if ( $this->is_empty_csv_row( $row ) ) {
                continue;
            }

            if ( ! $header_seen && $this->row_is_header( $row ) ) {
                $csv_format  = $this->detect_csv_format( $row );
                $header_seen = true;
                continue;
            }

            $member = $this->parse_import_row( $row, $csv_format );
            if ( ! $member ) {
                continue;
            }

            $wpdb->insert( $table_members, $member );
            $imported++;

            $city     = (string) ( $member['city'] ?? '' );
            $district = (string) ( $member['district'] ?? '' );
            $subd     = (string) ( $member['subdistrict'] ?? '' );
            $province = (string) ( $member['province'] ?? '' );
            $country  = (string) ( $member['country_name'] ?? 'Indonesia' );
            $country_code = (string) ( $member['country_code'] ?? 'ID' );

            if ( $subd !== '' || $district !== '' || $city !== '' ) {
                $addr_key = strtolower( $country_code . '|' . $city . '|' . $district . '|' . $subd );
                if ( ! isset( $address_seen[ $addr_key ] ) ) {
                    $address_seen[ $addr_key ] = true;
                    $wpdb->insert(
                        $table_address,
                        [
                            'country_code'  => $country_code,
                            'country_name'  => $country,
                            'province'      => $province,
                            'city'          => $city,
                            'district'      => $district,
                            'subdistrict'   => $subd,
                            'state_city'    => (string) ( $member['state_city'] ?? '' ),
                            'postal_code'   => (string) ( $member['postal_code'] ?? '' ),
                            'created_at'    => current_time( 'mysql' ),
                            'updated_at'    => current_time( 'mysql' ),
                        ]
                    );
                }
            }

            if ( $imported > 0 && $imported % self::IMPORT_FLUSH_EVERY === 0 ) {
                wp_cache_flush();
            }
        }

        fclose( $fh );
        wp_suspend_cache_addition( false );
        $this->redirect_admin_notice(
            sprintf(
                /* translators: %d: total imported rows */
                __( 'Import selesai. %d data keluarga diproses.', 'ptsbi-premium' ),
                (int) $imported
            )
        );
    }

    public static function stream_export_xlsx(): void {
        global $wpdb;

        if ( ! function_exists( 'ptprm_members_table_exists' ) || ! ptprm_members_table_exists() ) {
            self::redirect_export_error( __( 'Tabel data anggota belum tersedia. Nonaktifkan lalu aktifkan kembali plugin Premium Organization.', 'ptsbi-premium' ) );
        }

        $table_members = $wpdb->prefix . self::TABLE_MEMBERS;

        $headers = [
            'No', 'Nama Kepala Keluarga', 'Nama Istri', 'Tarombo', 'Ompu (Paroppuon)', 'Nomor Sundut',
            'Alamat', 'RT', 'RW', 'Kecamatan', 'Kelurahan', 'Wilayah / Kota', 'Provinsi', 'Kode Pos',
            'Negara', 'State / Kota (Luar Negeri)', 'Status', 'No HP',
        ];

        $dataset = [];
        $no      = 1;
        $offset  = 0;

        while ( true ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $chunk = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_members} ORDER BY id ASC LIMIT %d OFFSET %d",
                    self::EXPORT_CHUNK,
                    $offset
                ),
                ARRAY_A
            );

            if ( $wpdb->last_error ) {
                self::redirect_export_error(
                    __( 'Gagal membaca data anggota dari database.', 'ptsbi-premium' ) . ' ' . $wpdb->last_error
                );
            }

            if ( ! is_array( $chunk ) || $chunk === [] ) {
                break;
            }

            foreach ( $chunk as $r ) {
                $dataset[] = [
                    $no++,
                    (string) ( $r['kepala_keluarga'] ?? '' ),
                    (string) ( $r['nama_istri'] ?? '' ),
                    (string) ( $r['tarombo'] ?? '' ),
                    (string) ( $r['oppu'] ?? '' ),
                    (string) ( $r['nomor_sundut'] ?? '' ),
                    (string) ( $r['address_detail'] ?? '' ),
                    (string) ( $r['rt'] ?? '' ),
                    (string) ( $r['rw'] ?? '' ),
                    (string) ( $r['district'] ?? '' ),
                    (string) ( $r['subdistrict'] ?? '' ),
                    (string) ( $r['city'] ?? '' ),
                    (string) ( $r['province'] ?? '' ),
                    (string) ( $r['postal_code'] ?? '' ),
                    (string) ( $r['country_name'] ?? '' ),
                    (string) ( $r['state_city'] ?? '' ),
                    self::compute_marga_status(
                        (string) ( $r['kepala_keluarga'] ?? '' ),
                        (string) ( $r['nama_istri'] ?? '' ),
                        (string) ( $r['hula_boru'] ?? '' )
                    ),
                    (string) ( $r['phone'] ?? '' ),
                ];
            }

            if ( count( $chunk ) < self::EXPORT_CHUNK ) {
                break;
            }
            $offset += self::EXPORT_CHUNK;
        }

        $xlsx = self::build_xlsx_binary( $headers, $dataset );
        if ( $xlsx === '' ) {
            self::stream_export_csv_fallback( $headers, $dataset );
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="data-anggota-' . gmdate( 'Y-m-d' ) . '.xlsx"' );
        header( 'Content-Length: ' . (string) strlen( $xlsx ) );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        echo $xlsx; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * @param array<int,string>        $headers
     * @param array<int,array<int,mixed>> $rows
     */
    private static function stream_export_csv_fallback( array $headers, array $rows ): void {
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="data-anggota-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) {
            wp_die( esc_html__( 'Gagal membuat file export.', 'ptsbi-premium' ), 500 );
        }
        fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
        fputcsv( $out, $headers );
        foreach ( $rows as $row ) {
            fputcsv( $out, $row );
        }
        fclose( $out );
        exit;
    }

    public function handle_front_registration(): void {
        if ( ! isset( $_POST['ptprm_member_register'] ) ) {
            return;
        }
        if ( ! isset( $_POST[ self::QUERY_VAR_NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::QUERY_VAR_NONCE ] ) ), 'ptprm_member_register' ) ) {
            return;
        }

        $redirect = wp_get_referer() ?: home_url( '/' );

        // Field baru (di Rumah Anggota): name, email, wa, password.
        // Field lama (masih dibackward kompatibel): username, kepala_keluarga, phone, dll.
        $name     = sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? $_POST['kepala_keluarga'] ?? '' ) ) );
        $email    = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? $_POST['username'] ?? '' ) ) );
        $phone    = sanitize_text_field( wp_unslash( (string) ( $_POST['phone'] ?? $_POST['wa'] ?? '' ) ) );
        $password = (string) ( $_POST['password'] ?? '' );

        if ( $name === '' || $email === '' || $phone === '' || $password === '' ) {
            wp_safe_redirect( add_query_arg( 'ptprm_register_error', rawurlencode( __( 'Nama, Email, No HP, dan password wajib diisi.', 'ptsbi-premium' ) ), $redirect ) );
            exit;
        }
        if ( ! is_email( $email ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_register_error', rawurlencode( __( 'Email tidak valid.', 'ptsbi-premium' ) ), $redirect ) );
            exit;
        }

        $username = sanitize_user( $email );

        if ( username_exists( $username ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_register_error', rawurlencode( __( 'Email sudah dipakai.', 'ptsbi-premium' ) ), $redirect ) );
            exit;
        }

        if ( class_exists( 'PTPRM_Default_Accounts' ) && PTPRM_Default_Accounts::is_reserved_login( $username ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_register_error', rawurlencode( __( 'Email ini tidak dapat digunakan.', 'ptsbi-premium' ) ), $redirect ) );
            exit;
        }

        if ( strlen( $password ) < 6 ) {
            wp_safe_redirect( add_query_arg( 'ptprm_register_error', rawurlencode( __( 'Password minimal 6 karakter.', 'ptsbi-premium' ) ), $redirect ) );
            exit;
        }

        $auto = self::registration_auto_approve();

        // Buat WP user dulu, supaya admin bisa meng-approve (role/panel) tanpa manipulasi credential.
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_register_error', rawurlencode( $user_id->get_error_message() ), $redirect ) );
            exit;
        }

        if ( $auto ) {
            wp_update_user(
                [
                    'ID'           => $user_id,
                    'role'         => self::ROLE_MEMBER,
                    'display_name' => $name,
                ]
            );

            global $wpdb;
            $table_members = $wpdb->prefix . self::TABLE_MEMBERS;
            $wpdb->insert(
                $table_members,
                [
                    'user_id'          => $user_id,
                    'kepala_keluarga' => $name,
                    'phone'            => $phone,
                    'country_name'     => 'Indonesia',
                    'country_code'     => 'ID',
                    'created_by'       => $user_id,
                    'created_at'       => current_time( 'mysql' ),
                    'updated_at'       => current_time( 'mysql' ),
                ]
            );

            do_action( 'ptprm_member_registered', $user_id );
            if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
                PTPRM_Default_Accounts::maybe_lock_demo_anggota();
            }

            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id, true, is_ssl() );

            if ( class_exists( 'PTPRM_Member_Portal' ) ) {
                wp_safe_redirect( PTPRM_Member_Portal::portal_url() );
            } else {
                wp_safe_redirect( add_query_arg( 'ptprm_register_ok', '1', $redirect ) );
            }
            exit;
        }

        // Manual approve: user dibuat pending (tanpa role anggota) dan menunggu approve admin.
        wp_update_user(
            [
                'ID'           => $user_id,
                'role'         => 'subscriber',
                'display_name' => $name,
            ]
        );
        update_user_meta( $user_id, self::META_PENDING_STATUS, self::PENDING_STATUS_VALUE );
        update_user_meta( $user_id, self::META_PENDING_NAME, $name );
        update_user_meta( $user_id, self::META_PENDING_WA, $phone );
        update_user_meta( $user_id, self::META_PENDING_CREATED, current_time( 'mysql' ) );

        $login_portal = class_exists( 'PTPRM_Login_Portal' ) ? PTPRM_Login_Portal::login_url() : home_url( '/rumah-anggota/' );

        // Opsional: kirim ke API setelah user lokal dibuat (tidak memblokir pendaftaran).
        if ( class_exists( 'PTPRM_Membership_Api_Client' ) && PTPRM_Membership_Api_Client::enabled() ) {
            PTPRM_Membership_Api_Client::post(
                '/registrations',
                [
                    'full_name' => $name,
                    'email'     => $email,
                    'phone_wa'  => $phone,
                    'password'  => $password,
                ]
            );
        }

        wp_safe_redirect( add_query_arg( 'ptprm_register_pending', '1', $login_portal ) );
        exit;
    }

    public function shortcode_registration(): string {
        ob_start();
        $is_logged = is_user_logged_in();
        echo '<div class="ptprm-member-card">';
        if ( isset( $_GET['ptprm_register_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $msg = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_register_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $msg !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $msg ) . '</p>';
            }
        } elseif ( isset( $_GET['ptprm_register_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Pendaftaran berhasil. Akun ini bisa dipakai untuk login anggota.', 'ptsbi-premium' ) . '</p>';
        } elseif ( isset( $_GET['ptprm_register_pending'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Pendaftaran Anda diterima dan menunggu persetujuan admin.', 'ptsbi-premium' ) . '</p>';
        }

        if ( $is_logged ) {
            echo '</div>';
            return (string) ob_get_clean();
        }

        echo '<form method="post" class="ptprm-member-form">';
        wp_nonce_field( 'ptprm_member_register', self::QUERY_VAR_NONCE );
        echo '<input type="hidden" name="ptprm_member_register" value="1">';
        echo '<div class="ptprm-member-grid">';
        $auto = self::registration_auto_approve();
        echo '<p class="ptprm-portal-help">';
        echo $auto
            ? esc_html__( 'Pendaftaran akan langsung disetujui (auto approve).', 'ptsbi-premium' )
            : esc_html__( 'Pendaftaran menunggu persetujuan admin. Detail bisa dilengkapi setelah login.', 'ptsbi-premium' );
        echo '</p>';

        echo '<label><span>' . esc_html__( 'Nama', 'ptsbi-premium' ) . ' *</span><input type="text" name="name" required></label>';
        echo '<label><span>' . esc_html__( 'Email (sebagai username)', 'ptsbi-premium' ) . ' *</span><input type="email" name="email" autocomplete="username" required></label>';
        echo '<label><span>' . esc_html__( 'No HP', 'ptsbi-premium' ) . ' *</span><input type="tel" name="phone" autocomplete="tel" required></label>';
        echo '<label><span>' . esc_html__( 'Password', 'ptsbi-premium' ) . ' *</span><input type="password" name="password" minlength="6" required></label>';
        echo '</div>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Daftar Anggota', 'ptsbi-premium' ) . '</span></button>';
        echo '</form>';
        if ( class_exists( 'PTPRM_Member_Portal' ) ) {
            echo '<p class="ptprm-portal-foot">';
            echo esc_html__( 'Sudah punya akun?', 'ptsbi-premium' ) . ' ';
            echo '<a href="' . esc_url( class_exists( 'PTPRM_Login_Portal' ) ? PTPRM_Login_Portal::login_url() : PTPRM_Member_Portal::login_url() ) . '">' . esc_html__( 'Masuk di sini', 'ptsbi-premium' ) . '</a>';
            echo '</p>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    /**
     * Status keluarga: Anak / Boru / Bere / Ibebere (kolom hula_boru).
     */
    public static function compute_marga_status( string $kepala, string $nama_istri, string $stored = '' ): string {
        return self::resolve_member_status( $kepala, $nama_istri, $stored );
    }

    private static function kepala_is_samosir_anak( string $kepala ): bool {
        $kepala = trim( $kepala );
        if ( $kepala === '' || $kepala === '-' ) {
            return false;
        }
        return (bool) preg_match( '/(?:\.\s*|\s)Samosir\s*$/iu', $kepala )
            || preg_match( '/^Samosir$/iu', $kepala );
    }

    private static function spouse_is_boru_samosir( string $istri ): bool {
        $istri = trim( $istri );
        if ( $istri === '' || $istri === '-' ) {
            return false;
        }
        if ( preg_match( '/\bBr\.?\b/iu', $istri ) ) {
            return true;
        }
        if ( preg_match( '/\bBoru\b/iu', $istri ) ) {
            return true;
        }
        return false;
    }

    /**
     * SQL fragment: kepala keluarga marga Samosir.
     */
    private static function sql_kepala_samosir(): string {
        return "(kepala_keluarga LIKE '% Samosir' OR kepala_keluarga LIKE '%. Samosir' OR kepala_keluarga = 'Samosir')";
    }

    /**
     * SQL fragment: pasangan boru (Br. / Boru), bukan kepala Samosir.
     */
    private static function sql_boru_line(): string {
        $boru = "(nama_istri <> '' AND nama_istri <> '-' AND (nama_istri LIKE '% Br.%' OR nama_istri LIKE '% Br %' OR nama_istri LIKE '%Boru%'))";
        return '(' . $boru . ' AND NOT ' . self::sql_kepala_samosir() . ')';
    }

    /**
     * @return list<string>
     */
    public static function get_distinct_ompu_values(): array {
        $choices = array_values( self::ompu_choices() );
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_col( "SELECT DISTINCT oppu FROM {$table} WHERE oppu <> '' ORDER BY oppu ASC" );
        if ( ! is_array( $rows ) ) {
            return $choices;
        }
        foreach ( $rows as $ompu ) {
            $ompu = (string) $ompu;
            if ( $ompu !== '' && ! in_array( $ompu, $choices, true ) ) {
                $choices[] = $ompu;
            }
        }
        return $choices;
    }

    private function enqueue_directory_filter_assets(): void {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;
        if ( ! class_exists( 'PTPRM_Address_Regions' ) ) {
            return;
        }
        PTPRM_Address_Regions::enqueue_scripts(
            'ptprm-member-directory-filter',
            PTPRM_URL . 'assets/js/member-directory-filter.js'
        );
    }

    /**
     * @param array{nama?:string,status?:string,oppu?:string,provinsi?:string,kota?:string,kecamatan?:string,tab?:string} $filters
     */
    private function render_directory_filters( array $filters ): void {
        $this->enqueue_directory_filter_assets();
        $ompu_list = self::get_distinct_ompu_values();
        $statuses  = self::status_filter_choices();

        echo '<form method="get" class="ptprm-member-filter" data-ptprm-directory-filter>';
        if ( class_exists( 'PTPRM_Member_Portal' ) && ( PTPRM_Member_Portal::is_member_page() || PTPRM_Member_Portal::is_embedded_on_home() ) ) {
            echo '<input type="hidden" name="tab" value="cari">';
        }
        if ( class_exists( 'PTPRM_Admin_Portal' ) && PTPRM_Admin_Portal::is_admin_page() ) {
            echo '<input type="hidden" name="tab" value="anggota">';
        }

        echo '<label><span>' . esc_html__( 'Nama', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" name="nama" value="' . esc_attr( $filters['nama'] ?? '' ) . '"></label>';

        echo '<label><span>' . esc_html__( 'Status', 'ptsbi-premium' ) . '</span>';
        echo '<select name="status">';
        foreach ( $statuses as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( (string) ( $filters['status'] ?? '' ), $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';

        echo '<label><span>Ompu</span>';
        echo '<select name="oppu">';
        echo '<option value="">' . esc_html__( 'Semua Ompu', 'ptsbi-premium' ) . '</option>';
        foreach ( $ompu_list as $ompu ) {
            echo '<option value="' . esc_attr( $ompu ) . '"' . selected( (string) ( $filters['oppu'] ?? '' ), $ompu, false ) . '>' . esc_html( $ompu ) . '</option>';
        }
        echo '</select></label>';

        $prov = (string) ( $filters['provinsi'] ?? '' );
        $kota = (string) ( $filters['kota'] ?? '' );
        $kec  = (string) ( $filters['kecamatan'] ?? '' );

        echo '<label><span>' . esc_html__( 'Wilayah', 'ptsbi-premium' ) . '</span>';
        echo '<select name="wilayah" data-ptprm-wilayah data-current="jabodetabek">';
        echo '<option value="jabodetabek"' . selected( (string) ( $filters['wilayah'] ?? 'jabodetabek' ), 'jabodetabek', false ) . '>' . esc_html__( 'Jabodetabek', 'ptsbi-premium' ) . '</option>';
        echo '<option value="all"' . selected( (string) ( $filters['wilayah'] ?? '' ), 'all', false ) . '>' . esc_html__( 'Seluruh Indonesia', 'ptsbi-premium' ) . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__( 'Provinsi', 'ptsbi-premium' ) . '</span>';
        echo '<select name="provinsi" data-current="' . esc_attr( $prov ) . '"><option value="">' . esc_html__( 'Semua provinsi', 'ptsbi-premium' ) . '</option></select></label>';

        echo '<label><span>' . esc_html__( 'Kota / Kabupaten', 'ptsbi-premium' ) . '</span>';
        echo '<select name="kota" data-current="' . esc_attr( $kota ) . '"><option value="">' . esc_html__( 'Semua kota', 'ptsbi-premium' ) . '</option></select></label>';

        echo '<label><span>' . esc_html__( 'Kecamatan', 'ptsbi-premium' ) . '</span>';
        echo '<select name="kecamatan" data-current="' . esc_attr( $kec ) . '"><option value="">' . esc_html__( 'Semua kecamatan', 'ptsbi-premium' ) . '</option></select></label>';

        $kel = (string) ( $filters['kelurahan'] ?? '' );
        echo '<label><span>' . esc_html__( 'Kelurahan', 'ptsbi-premium' ) . '</span>';
        echo '<select name="kelurahan" data-current="' . esc_attr( $kel ) . '"><option value="">' . esc_html__( 'Semua kelurahan', 'ptsbi-premium' ) . '</option></select></label>';

        echo '<div class="ptprm-member-filter-actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Cari', 'ptsbi-premium' ) . '</button>';
        $reset_args = [];
        if ( class_exists( 'PTPRM_Member_Portal' ) && ( PTPRM_Member_Portal::is_member_page() || PTPRM_Member_Portal::is_embedded_on_home() ) ) {
            $reset_args['tab'] = 'cari';
        } elseif ( class_exists( 'PTPRM_Admin_Portal' ) && PTPRM_Admin_Portal::is_admin_page() ) {
            $reset_args['tab'] = 'anggota';
        }
        echo ' <a class="button" href="' . esc_url( add_query_arg( $reset_args, remove_query_arg( [ 'nama', 'status', 'oppu', 'provinsi', 'kota', 'kecamatan', 'kelurahan', 'ptprm_mp' ] ) ) ) . '">' . esc_html__( 'Reset', 'ptsbi-premium' ) . '</a>';
        echo '</div>';
        echo '</form>';
    }

    public function shortcode_directory(): string {
        ob_start();
        if ( ! is_user_logged_in() ) {
            $redirect  = home_url( add_query_arg( [], $_SERVER['REQUEST_URI'] ?? '/' ) );
            $login_url = class_exists( 'PTPRM_Member_Portal' )
                ? PTPRM_Member_Portal::login_url( $redirect )
                : wp_login_url( $redirect );
            echo '<div class="ptprm-member-card">';
            echo '<p>' . esc_html__( 'Direktori anggota hanya tersedia setelah login.', 'ptsbi-premium' ) . '</p>';
            echo '<a class="ptprm-cta ptprm-cta-2 ptprm-cta-size-medium" href="' . esc_url( $login_url ) . '"><span class="ptprm-cta-label">' . esc_html__( 'Rumah Anggota', 'ptsbi-premium' ) . '</span></a>';
            echo '</div>';
            return (string) ob_get_clean();
        }

        $user = wp_get_current_user();
        if ( class_exists( 'PTPRM_Member_Portal' ) && ! PTPRM_Member_Portal::is_anggota( $user ) && ! self::can_view_sensitive_fields() ) {
            echo '<div class="ptprm-member-card">';
            if ( self::is_pending_registration_user( (int) $user->ID ) ) {
                echo '<p>' . esc_html__( 'Data direktori akan tersedia setelah pendaftaran Anda disetujui admin.', 'ptsbi-premium' ) . '</p>';
            } else {
                echo '<p>' . esc_html__( 'Direktori anggota hanya untuk akun anggota.', 'ptsbi-premium' ) . '</p>';
            }
            echo '</div>';
            return (string) ob_get_clean();
        }

        $filters = [
            'nama'      => sanitize_text_field( wp_unslash( (string) ( $_GET['nama'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'status'    => sanitize_key( (string) ( $_GET['status'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'oppu'      => sanitize_text_field( wp_unslash( (string) ( $_GET['oppu'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'provinsi'  => sanitize_text_field( wp_unslash( (string) ( $_GET['provinsi'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'kota'      => sanitize_text_field( wp_unslash( (string) ( $_GET['kota'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'kecamatan' => sanitize_text_field( wp_unslash( (string) ( $_GET['kecamatan'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'kelurahan' => sanitize_text_field( wp_unslash( (string) ( $_GET['kelurahan'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'tab'       => sanitize_key( (string) ( $_GET['tab'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ];
        if ( ! in_array( $filters['status'], [ '', 'anak', 'boru', 'bere', 'ibebere' ], true ) ) {
            $filters['status'] = '';
        }
        $page     = max( 1, (int) ( $_GET['ptprm_mp'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $per_page = self::per_page();
        $can_full = self::can_view_sensitive_fields();
        $result   = $this->search_members( $filters, $page, $per_page, $can_full );
        $rows     = $result['rows'];
        $total    = (int) $result['total'];
        $pages    = max( 1, (int) ceil( $total / $per_page ) );

        echo '<div class="ptprm-member-card">';
        $this->render_directory_filters( $filters );

        if ( $total > 0 ) {
            echo '<p class="ptprm-member-meta">';
            echo esc_html(
                sprintf(
                    /* translators: 1: from row, 2: to row, 3: total */
                    __( 'Menampilkan %1$d–%2$d dari %3$d keluarga.', 'ptsbi-premium' ),
                    ( ( $page - 1 ) * $per_page ) + 1,
                    min( $page * $per_page, $total ),
                    $total
                )
            );
            echo '</p>';
        }

        if ( ! $can_full ) {
            echo '<p class="ptprm-member-note">' . esc_html__( 'Alamat ditampilkan sampai kecamatan. Untuk detail lengkap, hubungi sekretariat.', 'ptsbi-premium' ) . '</p>';
        }

        echo '<div class="ptprm-member-table-wrap"><table class="ptprm-member-table"><thead><tr>';
        echo '<th>' . esc_html__( 'Nama Kepala Keluarga', 'ptsbi-premium' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'ptsbi-premium' ) . '</th>';
        echo '<th>Ompu</th><th>' . esc_html__( 'Nomor Sundut', 'ptsbi-premium' ) . '</th><th>' . esc_html__( 'Alamat', 'ptsbi-premium' ) . '</th>';
        if ( $can_full ) {
            echo '<th>' . esc_html__( 'No HP', 'ptsbi-premium' ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( ! $rows ) {
            echo '<tr><td colspan="' . ( $can_full ? '6' : '5' ) . '">' . esc_html__( 'Data tidak ditemukan.', 'ptsbi-premium' ) . '</td></tr>';
        } else {
            foreach ( $rows as $row ) {
                $status_label = self::compute_marga_status(
                    (string) ( $row['kepala_keluarga'] ?? '' ),
                    (string) ( $row['nama_istri'] ?? '' ),
                    (string) ( $row['hula_boru'] ?? '' )
                );

                echo '<tr>';
                echo '<td>' . esc_html( (string) ( $row['kepala_keluarga'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $status_label !== '' ? $status_label : '—' ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['oppu'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['nomor_sundut'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( self::format_directory_address( $row, $can_full ) ) . '</td>';
                if ( $can_full ) {
                    echo '<td>' . esc_html( (string) ( $row['phone'] ?? '' ) ) . '</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';

        if ( $pages > 1 ) {
            $this->render_pagination( $page, $pages, $filters );
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    /**
     * @param array{nama?:string,status?:string,oppu?:string,provinsi?:string,kota?:string,kecamatan?:string,tab?:string} $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    private function search_members( array $filters, int $page, int $per_page, bool $full_columns ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        $where = [];
        $args  = [];

        if ( ! empty( $filters['nama'] ) ) {
            $where[] = 'kepala_keluarga LIKE %s';
            $args[]  = '%' . $wpdb->esc_like( $filters['nama'] ) . '%';
        }
        if ( ! empty( $filters['status'] ) ) {
            $status_key = $filters['status'];
            if ( 'anak' === $status_key ) {
                $where[] = "(hula_boru = 'Anak' OR (" . self::sql_kepala_samosir() . " AND (hula_boru = '' OR hula_boru IS NULL)))";
            } elseif ( 'boru' === $status_key ) {
                $where[] = "(hula_boru = 'Boru' OR (" . self::sql_boru_line() . " AND (hula_boru = '' OR hula_boru = 'Boru')))";
            } elseif ( 'bere' === $status_key ) {
                $where[] = "hula_boru = 'Bere'";
            } elseif ( 'ibebere' === $status_key ) {
                $where[] = "hula_boru = 'Ibebere'";
            }
        }
        if ( ! empty( $filters['oppu'] ) ) {
            $where[] = 'oppu = %s';
            $args[]  = $filters['oppu'];
        }
        if ( ! empty( $filters['provinsi'] ) ) {
            $where[] = 'province = %s';
            $args[]  = $filters['provinsi'];
        }
        if ( ! empty( $filters['kota'] ) ) {
            $where[] = 'city = %s';
            $args[]  = $filters['kota'];
        }
        if ( ! empty( $filters['kecamatan'] ) ) {
            $where[] = 'district = %s';
            $args[]  = $filters['kecamatan'];
        }
        if ( ! empty( $filters['kelurahan'] ) ) {
            $where[] = 'subdistrict = %s';
            $args[]  = $filters['kelurahan'];
        }

        $where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';
        $cols      = 'id, kepala_keluarga, nama_istri, oppu, nomor_sundut, hula_boru, district, city, province, country_name';
        if ( $full_columns ) {
            $cols .= ', subdistrict, address_detail, postal_code, phone, state_city';
        }

        $count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}";
        if ( $args ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) );
        } else {
            $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $offset = ( $page - 1 ) * $per_page;
        $list_sql = "SELECT {$cols} FROM {$table}{$where_sql} ORDER BY kepala_keluarga ASC LIMIT %d OFFSET %d";
        $list_args = array_merge( $args, [ $per_page, $offset ] );
        $rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_args ), ARRAY_A );

        return [
            'rows'  => is_array( $rows ) ? $rows : [],
            'total' => $total,
        ];
    }

    /**
     * @param array{nama?:string,status?:string,oppu?:string,provinsi?:string,kota?:string,kecamatan?:string,tab?:string} $filters
     */
    private function render_pagination( int $page, int $pages, array $filters ): void {
        $base = [
            'nama'      => $filters['nama'] ?? '',
            'status'    => $filters['status'] ?? '',
            'oppu'      => $filters['oppu'] ?? '',
            'provinsi'  => $filters['provinsi'] ?? '',
            'kota'      => $filters['kota'] ?? '',
            'kecamatan' => $filters['kecamatan'] ?? '',
            'kelurahan' => $filters['kelurahan'] ?? '',
            'tab'       => $filters['tab'] ?? '',
        ];
        $base = array_filter(
            $base,
            static function ( $v ) {
                return $v !== '' && $v !== null;
            }
        );

        echo '<nav class="ptprm-member-pagination" aria-label="' . esc_attr__( 'Halaman direktori', 'ptsbi-premium' ) . '">';

        if ( $page > 1 ) {
            $prev = add_query_arg( array_merge( $base, [ 'ptprm_mp' => $page - 1 ] ) );
            echo '<a class="ptprm-member-page-link" href="' . esc_url( $prev ) . '">&larr; ' . esc_html__( 'Sebelumnya', 'ptsbi-premium' ) . '</a>';
        }

        echo '<span class="ptprm-member-page-status">' . esc_html( sprintf( '%d / %d', $page, $pages ) ) . '</span>';

        if ( $page < $pages ) {
            $next = add_query_arg( array_merge( $base, [ 'ptprm_mp' => $page + 1 ] ) );
            echo '<a class="ptprm-member-page-link" href="' . esc_url( $next ) . '">' . esc_html__( 'Berikutnya', 'ptsbi-premium' ) . ' &rarr;</a>';
        }

        echo '</nav>';
    }

    public static function can_view_sensitive_fields(): bool {
        return PTPRM_Access::can_manage();
    }

    private function is_empty_csv_row( array $row ): bool {
        foreach ( $row as $cell ) {
            if ( trim( (string) $cell ) !== '' ) {
                return false;
            }
        }
        return true;
    }

    private function row_is_header( array $row ): bool {
        $joined = strtolower( implode( ' ', array_map( 'strval', $row ) ) );
        return strpos( $joined, 'nama kepala' ) !== false
            || strpos( $joined, 'kepala keluarga' ) !== false
            || strpos( $joined, 'nama ayah' ) !== false;
    }

    /**
     * @param array<int, string> $header
     */
    private function detect_csv_format( array $header ): string {
        $joined = strtolower( implode( ' ', array_map( 'strval', $header ) ) );
        if ( strpos( $joined, 'nama ayah' ) !== false || strpos( $joined, 'hula' ) !== false ) {
            return 'dami2024';
        }
        return 'legacy';
    }

    /**
     * @param array<int, string> $row
     * @return array<string, mixed>|null
     */
    private function parse_import_row( array $row, string $format ): ?array {
        if ( 'dami2024' === $format ) {
            $kepala = $this->normalize_text( $this->cell( $row, 1 ) );
            if ( $kepala === '' ) {
                return null;
            }
            $istri    = $this->normalize_text( $this->cell( $row, 2 ) );
            $city_raw = $this->normalize_text( $this->cell( $row, 13 ) );
            $district = $this->normalize_text( $this->cell( $row, 11 ) );
            $subd     = $this->normalize_text( $this->cell( $row, 12 ) );
            $province = $this->guess_province_from_city( $city_raw );
            if ( class_exists( 'PTPRM_Address_Regions' ) ) {
                $norm     = PTPRM_Address_Regions::normalize_address_fields( $province, $city_raw, $district, $subd );
                $province = (string) $norm['province'];
                $city_raw = (string) $norm['city'];
                $district = (string) $norm['district'];
                $subd     = (string) $norm['subdistrict'];
            }
            $status_raw = $this->normalize_text( $this->cell( $row, 14 ) );
            $status     = self::resolve_member_status( $kepala, $istri, $status_raw );

            return [
                'family_no'       => $this->normalize_text( $this->cell( $row, 0 ) ),
                'kepala_keluarga' => $kepala,
                'nama_istri'      => $istri,
                'tarombo'         => $this->normalize_text( $this->cell( $row, 5 ) ),
                'oppu'            => $this->normalize_text( $this->cell( $row, 6 ) ),
                'nomor_sundut'    => $this->normalize_text( $this->cell( $row, 7 ) ),
                'address_detail'  => $this->normalize_text( $this->cell( $row, 8 ) ),
                'rt'              => $this->normalize_text( $this->cell( $row, 9 ) ),
                'rw'              => $this->normalize_text( $this->cell( $row, 10 ) ),
                'district'        => $district,
                'subdistrict'     => $subd,
                'city'            => $city_raw,
                'province'        => $province,
                'postal_code'     => '',
                'country_name'    => 'Indonesia',
                'country_code'    => 'ID',
                'state_city'      => '',
                'hula_boru'       => $status,
                'is_overseas'     => 0,
                'created_by'      => get_current_user_id(),
                'created_at'      => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ];
        }

        $kepala = $this->normalize_text( $this->cell( $row, 1 ) );
        if ( $kepala === '' || strtolower( $kepala ) === 'nama kepala keluarga' ) {
            return null;
        }
        $istri    = $this->normalize_text( $this->cell( $row, 2 ) );
        $city     = $this->normalize_text( $this->cell( $row, 11 ) );
        $district = $this->normalize_text( $this->cell( $row, 9 ) );
        $subd     = $this->normalize_text( $this->cell( $row, 10 ) );
        $province = $this->guess_province_from_city( $city );
        if ( class_exists( 'PTPRM_Address_Regions' ) ) {
            $norm     = PTPRM_Address_Regions::normalize_address_fields( $province, $city, $district, $subd );
            $province = (string) $norm['province'];
            $city     = (string) $norm['city'];
            $district = (string) $norm['district'];
            $subd     = (string) $norm['subdistrict'];
        }
        $status_raw = $this->normalize_text( $this->cell( $row, 12 ) );

        return [
            'family_no'       => $this->normalize_text( $this->cell( $row, 0 ) ),
            'kepala_keluarga' => $kepala,
            'nama_istri'      => $istri,
            'tarombo'         => $this->normalize_text( $this->cell( $row, 3 ) ),
            'oppu'            => $this->normalize_text( $this->cell( $row, 4 ) ),
            'nomor_sundut'    => $this->normalize_text( $this->cell( $row, 5 ) ),
            'address_detail'  => $this->normalize_text( $this->cell( $row, 6 ) ),
            'rt'              => $this->normalize_text( $this->cell( $row, 7 ) ),
            'rw'              => $this->normalize_text( $this->cell( $row, 8 ) ),
            'district'        => $district,
            'subdistrict'     => $subd,
            'city'            => $city,
            'province'        => $province,
            'postal_code'     => '',
            'country_name'    => 'Indonesia',
            'country_code'    => 'ID',
            'state_city'      => '',
            'hula_boru'       => self::resolve_member_status( $kepala, $istri, $status_raw ),
            'is_overseas'     => 0,
            'created_by'      => get_current_user_id(),
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        ];
    }

    private function cell( array $row, int $index ): string {
        return isset( $row[ $index ] ) ? (string) $row[ $index ] : '';
    }

    private function normalize_text( string $text ): string {
        $text = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
        return $text;
    }

    private function guess_province_from_city( string $city ): string {
        if ( class_exists( 'PTPRM_Address_Regions' ) ) {
            $g = PTPRM_Address_Regions::guess_province( $city );
            if ( $g !== '' ) {
                return $g;
            }
        }
        $lc = strtolower( $city );
        if ( strpos( $lc, 'jakarta' ) !== false ) {
            return 'DKI Jakarta';
        }
        if ( strpos( $lc, 'bogor' ) !== false ) {
            return 'Jawa Barat';
        }
        if ( strpos( $lc, 'bekasi' ) !== false || strpos( $lc, 'cikarang' ) !== false || strpos( $lc, 'cibitung' ) !== false ) {
            return 'Jawa Barat';
        }
        if ( strpos( $lc, 'tangerang' ) !== false || strpos( $lc, 'tangsel' ) !== false ) {
            return 'Banten';
        }
        if ( strpos( $lc, 'depok' ) !== false ) {
            return 'Jawa Barat';
        }
        return '';
    }

    private function redirect_admin_notice( string $message ): void {
        $base = class_exists( 'PTPRM_Admin_Portal' )
            ? PTPRM_Admin_Portal::portal_url( 'anggota' )
            : home_url( '/panel-pengurus/?tab=anggota' );
        wp_safe_redirect( add_query_arg( 'ptprm_members_notice', rawurlencode( $message ), $base ) );
        exit;
    }

    private function redirect_admin_error( string $message ): void {
        $base = class_exists( 'PTPRM_Admin_Portal' )
            ? PTPRM_Admin_Portal::portal_url( 'anggota' )
            : home_url( '/panel-pengurus/?tab=anggota' );
        wp_safe_redirect( add_query_arg( 'ptprm_members_error', rawurlencode( $message ), $base ) );
        exit;
    }

    private static function redirect_export_error( string $message ): void {
        if ( class_exists( 'PTPRM_Admin_Portal' ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_members_error', rawurlencode( $message ), PTPRM_Admin_Portal::portal_url( 'anggota' ) ) );
            exit;
        }
        wp_die( esc_html( $message ) );
    }

    private static function build_xlsx_binary( array $headers, array $rows ): string {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return '';
        }

        $all_rows = array_merge( [ $headers ], $rows );
        $shared_strings = [];
        $shared_map     = [];
        $sheet_rows_xml = [];

        foreach ( $all_rows as $r_idx => $row ) {
            $row_index = $r_idx + 1;
            $cells_xml = [];
            foreach ( $row as $c_idx => $val ) {
                $cell_ref = self::xlsx_col_name( $c_idx + 1 ) . $row_index;
                $value    = (string) $val;
                if ( is_numeric( $value ) && $value !== '' && ! preg_match( '/^0\d+$/', $value ) ) {
                    $cells_xml[] = '<c r="' . $cell_ref . '"><v>' . esc_html( $value ) . '</v></c>';
                } else {
                    if ( ! isset( $shared_map[ $value ] ) ) {
                        $shared_map[ $value ] = count( $shared_strings );
                        $shared_strings[] = $value;
                    }
                    $cells_xml[] = '<c r="' . $cell_ref . '" t="s"><v>' . (int) $shared_map[ $value ] . '</v></c>';
                }
            }
            $sheet_rows_xml[] = '<row r="' . $row_index . '">' . implode( '', $cells_xml ) . '</row>';
        }

        $sheet_data = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode( '', $sheet_rows_xml ) . '</sheetData></worksheet>';

        $shared_xml_items = '';
        foreach ( $shared_strings as $str ) {
            $shared_xml_items .= '<si><t>' . self::xml_escape( (string) $str ) . '</t></si>';
        }
        $shared_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $shared_strings ) . '" uniqueCount="' . count( $shared_strings ) . '">'
            . $shared_xml_items
            . '</sst>';

        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Data Anggota" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';

        $tmp_file = wp_tempnam( 'ptprm-members-export' );
        if ( ! $tmp_file ) {
            return '';
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $tmp_file, ZipArchive::OVERWRITE ) ) {
            return '';
        }

        $zip->addFromString( '[Content_Types].xml', $content_types );
        $zip->addFromString( '_rels/.rels', $rels );
        $zip->addFromString( 'xl/workbook.xml', $workbook );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_data );
        $zip->addFromString( 'xl/sharedStrings.xml', $shared_xml );
        $zip->addFromString( 'xl/styles.xml', $styles );
        $zip->close();

        $bin = (string) file_get_contents( $tmp_file );
        @unlink( $tmp_file );
        return $bin;
    }

    private static function xlsx_col_name( int $num ): string {
        $name = '';
        while ( $num > 0 ) {
            $num--;
            $name = chr( 65 + ( $num % 26 ) ) . $name;
            $num = (int) floor( $num / 26 );
        }
        return $name;
    }

    private static function xml_escape( string $text ): string {
        return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }
}
