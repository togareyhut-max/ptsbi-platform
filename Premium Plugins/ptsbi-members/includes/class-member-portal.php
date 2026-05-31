<?php
/**
 * Portal anggota di frontend — tanpa wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'PTPRM_Member_Portal', false ) ) {
    return;
}

class PTPRM_Member_Portal {

    public const SLUG_LOGIN  = 'masuk';
    public const SLUG_PORTAL = 'area-anggota';

    private const NONCE_PROFILE  = 'ptprm_member_profile';
    private const NONCE_LOGOUT   = 'ptprm_member_logout';

    public function __construct() {
        add_action( 'init', [ __CLASS__, 'maybe_ensure_pages' ], 15 );
        add_action( 'init', [ $this, 'handle_profile_save' ], 20 );
        add_shortcode( 'ptprm_member_portal', [ $this, 'shortcode_portal' ] );
        add_filter( 'ptprm_subpage_hero_skip', [ $this, 'skip_subpage_hero' ] );
    }

    public static function login_slug(): string {
        $o = ptsmb_options();
        $s = sanitize_title( (string) ( $o['members_login_slug'] ?? self::SLUG_LOGIN ) );
        return $s !== '' ? $s : self::SLUG_LOGIN;
    }

    public static function portal_slug(): string {
        $o = ptsmb_options();
        $s = sanitize_title( (string) ( $o['members_portal_slug'] ?? self::SLUG_PORTAL ) );
        return $s !== '' ? $s : self::SLUG_PORTAL;
    }

    public static function login_url( string $redirect = '' ): string {
        if ( class_exists( 'PTPRM_Login_Portal' ) ) {
            return PTPRM_Login_Portal::login_url( $redirect );
        }
        $url = home_url( '/' . self::login_slug() . '/' );
        if ( $redirect !== '' ) {
            $url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
        }
        return $url;
    }

    public static function portal_url(): string {
        return home_url( '/' . self::portal_slug() . '/' );
    }

    /**
     * Panel anggota disematkan di beranda (#daftar-anggota) untuk pengguna yang sudah login.
     */
    public static function is_embedded_on_home(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( ! ( is_front_page() || is_home() ) ) {
            return false;
        }
        return class_exists( 'PTPRM_Members' ) && PTPRM_Members::registration_enabled();
    }

    /**
     * URL navigasi portal (beranda atau halaman area anggota).
     *
     * @param array<string, string|int> $args
     */
    public static function navigation_url( array $args = [] ): string {
        $embed = self::is_embedded_on_home();
        $base  = $embed ? home_url( '/' ) : self::portal_url();
        $url   = $args ? add_query_arg( $args, $base ) : $base;
        if ( $embed ) {
            $url .= '#daftar-anggota';
        }
        return $url;
    }

    public static function should_enqueue_portal_assets(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        return self::is_member_page() || self::is_embedded_on_home();
    }

    public static function is_anggota( $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return false;
        }
        return in_array( 'anggota', (array) $user->roles, true );
    }

    public static function maybe_ensure_pages(): void {
        $needs_flush = false;
        if ( ! get_option( 'ptprm_member_pages_v1' ) ) {
            self::ensure_pages();
            update_option( 'ptprm_member_pages_v1', 1, false );
            $needs_flush = true;
        }
        self::repair_portal_page_content();
        if ( $needs_flush ) {
            flush_rewrite_rules( false );
        }
    }

    public static function repair_portal_page_content(): void {
        $page = get_page_by_path( self::portal_slug(), OBJECT, 'page' );
        if ( ! $page instanceof WP_Post ) {
            return;
        }
        if ( strpos( (string) $page->post_content, 'ptprm_member_portal' ) !== false ) {
            return;
        }
        wp_update_post(
            [
                'ID'           => (int) $page->ID,
                'post_content' => '[ptprm_member_portal]',
            ]
        );
    }

    public static function is_member_page(): bool {
        if ( ! is_page() ) {
            return false;
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        return $slug === self::portal_slug();
    }

    public function skip_subpage_hero( bool $skip ): bool {
        return $skip || self::is_member_page();
    }

    public function handle_profile_save(): void {
        if ( empty( $_POST['ptprm_member_profile'] ) || ! is_user_logged_in() ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_PROFILE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_PROFILE ] ) ), 'ptprm_member_profile' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! self::is_anggota() && ! current_user_can( 'manage_options' ) && ! PTPRM_Members::is_pending_registration_user( $user_id ) ) {
            return;
        }

        $is_overseas = ! empty( $_POST['is_overseas'] ) || ( isset( $_POST['address_wilayah'] ) && sanitize_key( wp_unslash( (string) $_POST['address_wilayah'] ) ) === 'overseas' );
        $city        = sanitize_text_field( wp_unslash( (string) ( $_POST['city'] ?? '' ) ) );
        $province    = sanitize_text_field( wp_unslash( (string) ( $_POST['province'] ?? '' ) ) );
        $district    = sanitize_text_field( wp_unslash( (string) ( $_POST['district'] ?? '' ) ) );
        $subdistrict = sanitize_text_field( wp_unslash( (string) ( $_POST['subdistrict'] ?? '' ) ) );
        if ( $is_overseas ) {
            $province    = '';
            $city        = '';
            $district    = '';
            $subdistrict = '';
        }
        if ( ! $is_overseas && $province === '' && $city !== '' && class_exists( 'PTPRM_Address_Regions' ) ) {
            $province = PTPRM_Address_Regions::guess_province( $city );
        }
        if ( ! $is_overseas && class_exists( 'PTPRM_Address_Regions' ) ) {
            $norm = PTPRM_Address_Regions::normalize_address_fields( $province, $city, $district, $subdistrict );
            $province    = (string) $norm['province'];
            $city        = (string) $norm['city'];
            $district    = (string) $norm['district'];
            $subdistrict = (string) $norm['subdistrict'];
        }

        $data = [
            'kepala_keluarga' => sanitize_text_field( wp_unslash( (string) ( $_POST['kepala_keluarga'] ?? '' ) ) ),
            'nama_istri'      => sanitize_text_field( wp_unslash( (string) ( $_POST['nama_istri'] ?? '' ) ) ),
            'tarombo'         => sanitize_text_field( wp_unslash( (string) ( $_POST['tarombo'] ?? '' ) ) ),
            'oppu'            => sanitize_text_field( wp_unslash( (string) ( $_POST['oppu'] ?? '' ) ) ),
            'hula_boru'       => PTPRM_Members::normalize_status_label( sanitize_text_field( wp_unslash( (string) ( $_POST['member_status'] ?? $_POST['hula_boru'] ?? '' ) ) ) ),
            'nomor_sundut'    => sanitize_text_field( wp_unslash( (string) ( $_POST['nomor_sundut'] ?? '' ) ) ),
            'phone'           => sanitize_text_field( wp_unslash( (string) ( $_POST['phone'] ?? '' ) ) ),
            'street_name'     => sanitize_text_field( wp_unslash( (string) ( $_POST['street_name'] ?? '' ) ) ),
            'house_number'    => sanitize_text_field( wp_unslash( (string) ( $_POST['house_number'] ?? '' ) ) ),
            'country_name'    => $is_overseas
                ? sanitize_text_field( wp_unslash( (string) ( $_POST['country_name_intl'] ?? $_POST['country_name'] ?? '' ) ) )
                : sanitize_text_field( wp_unslash( (string) ( $_POST['country_name'] ?? 'Indonesia' ) ) ),
            'province'        => $province,
            'city'            => $city,
            'district'        => $district,
            'subdistrict'     => $subdistrict,
            'postal_code'     => sanitize_text_field( wp_unslash( (string) ( $_POST['postal_code'] ?? '' ) ) ),
            'state_city'      => sanitize_text_field( wp_unslash( (string) ( $_POST['state_city'] ?? '' ) ) ),
            'address_detail'  => $is_overseas
                ? sanitize_textarea_field( wp_unslash( (string) ( $_POST['address_detail_intl'] ?? $_POST['address_detail'] ?? '' ) ) )
                : sanitize_textarea_field( wp_unslash( (string) ( $_POST['address_detail'] ?? '' ) ) ),
            'rt'              => sanitize_text_field( wp_unslash( (string) ( $_POST['rt'] ?? '' ) ) ),
            'rw'              => sanitize_text_field( wp_unslash( (string) ( $_POST['rw'] ?? '' ) ) ),
            'is_overseas'     => $is_overseas ? 1 : 0,
            'country_code'    => $is_overseas ? 'INTL' : 'ID',
        ];
        if ( $data['hula_boru'] === '' ) {
            $data['hula_boru'] = PTPRM_Members::resolve_member_status(
                (string) $data['kepala_keluarga'],
                (string) $data['nama_istri']
            );
        }

        if ( empty( $_POST['ptprm_password_only'] ) ) {
            $saved = PTPRM_Members::save_profile_for_user( $user_id, $data );
            if ( ! $saved ) {
                wp_safe_redirect( add_query_arg( 'ptprm_profile_error', rawurlencode( __( 'Profil tidak tersimpan. Periksa data alamat lalu coba lagi.', 'ptsbi-premium' ) ), self::navigation_url( [ 'tab' => 'profil' ] ) ) );
                exit;
            }
        }

        $pass1 = (string) ( $_POST['pass1'] ?? '' );
        $pass2 = (string) ( $_POST['pass2'] ?? '' );
        if ( $pass1 !== '' ) {
            if ( $pass1 !== $pass2 ) {
                wp_safe_redirect( add_query_arg( 'ptprm_profile_error', rawurlencode( __( 'Konfirmasi password tidak sama.', 'ptsbi-premium' ) ), self::navigation_url( [ 'tab' => 'akun' ] ) ) );
                exit;
            }
            if ( strlen( $pass1 ) < 6 ) {
                wp_safe_redirect( add_query_arg( 'ptprm_profile_error', rawurlencode( __( 'Password minimal 6 karakter.', 'ptsbi-premium' ) ), self::navigation_url( [ 'tab' => 'akun' ] ) ) );
                exit;
            }
            wp_set_password( $pass1, $user_id );
            wp_set_auth_cookie( $user_id, true );
        }

        wp_safe_redirect( add_query_arg( 'ptprm_profile_ok', '1', self::navigation_url( [ 'tab' => 'profil' ] ) ) );
        exit;
    }

    public function shortcode_portal(): string {
        ob_start();

        if ( ! is_user_logged_in() ) {
            echo '<div class="ptprm-member-card ptprm-portal-card">';
            echo '<p>' . esc_html__( 'Silakan masuk untuk mengakses area anggota.', 'ptsbi-premium' ) . '</p>';
            echo '<a class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium" href="' . esc_url( self::login_url( self::portal_url() ) ) . '">';
            echo '<span class="ptprm-cta-label">' . esc_html__( 'Rumah Anggota', 'ptsbi-premium' ) . '</span></a>';
            echo '</div>';
            return (string) ob_get_clean();
        }

        if ( ! self::is_anggota() && ! PTPRM_Members::can_view_sensitive_fields() && ! PTPRM_Members::is_pending_registration_user( get_current_user_id() ) ) {
            echo '<div class="ptprm-member-card ptprm-portal-card">';
            echo '<p>' . esc_html__( 'Area ini untuk akun dengan role Anggota.', 'ptsbi-premium' ) . '</p>';
            echo '</div>';
            return (string) ob_get_clean();
        }

        $user         = wp_get_current_user();
        $is_pending   = PTPRM_Members::is_pending_registration_user( (int) $user->ID );
        $record       = PTPRM_Members::get_profile_record_for_user( (int) $user->ID );
        $tab          = sanitize_key( (string) ( $_GET['tab'] ?? 'profil' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $allowed      = $is_pending ? [ 'profil', 'akun' ] : [ 'profil', 'cari', 'akun' ];
        if ( ! in_array( $tab, $allowed, true ) ) {
            $tab = 'profil';
        }

        if ( $is_pending && $tab === 'profil' ) {
            echo '<div class="ptprm-member-alert ptprm-member-alert-ok ptprm-portal-pending-note">';
            echo esc_html__( 'Pendaftaran menunggu persetujuan admin. Anda tetap bisa melengkapi profil; perubahan nama dan HP ikut tersimpan di data pendaftaran.', 'ptsbi-premium' );
            echo '</div>';
        }

        $embedded = self::is_embedded_on_home();
        echo '<div class="ptprm-portal-wrap' . ( $embedded ? ' ptprm-portal-wrap--embedded' : '' ) . '">';
        echo '<header class="ptprm-portal-head">';
        if ( ! $embedded ) {
            echo '<h2 class="ptprm-portal-title">' . esc_html__( 'Area Anggota', 'ptsbi-premium' ) . '</h2>';
        }
        echo '<p class="ptprm-portal-greet">' . esc_html( sprintf(
            /* translators: %s: display name */
            __( 'Halo, %s', 'ptsbi-premium' ),
            $user->display_name ?: $user->user_login
        ) ) . '</p>';
        echo '</header>';

        echo '<nav class="ptprm-portal-tabs" aria-label="' . esc_attr__( 'Menu area anggota', 'ptsbi-premium' ) . '">';
        foreach (
            [
                'profil' => __( 'Profil Keluarga', 'ptsbi-premium' ),
                'cari'   => __( 'Pencarian', 'ptsbi-premium' ),
                'akun'   => __( 'Akun', 'ptsbi-premium' ),
            ] as $key => $label
        ) {
            $active = $tab === $key ? ' is-active' : '';
            $url    = self::navigation_url( [ 'tab' => $key ] );
            echo '<a class="ptprm-portal-tab' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        echo '<div class="ptprm-portal-panel">';
        if ( $tab === 'cari' ) {
            echo do_shortcode( '[ptprm_member_directory]' );
        } elseif ( $tab === 'akun' ) {
            $this->render_account_tab( $user );
        } else {
            $this->render_profile_tab( $record );
        }
        echo '</div>';

        echo '<footer class="ptprm-portal-footbar">';
        PTPRM_Access::render_logout_link();
        echo '</footer>';

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed>|null $record
     */
    private function render_profile_tab( ?array $record ): void {
        $r = is_array( $record ) ? $record : [];

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        if ( isset( $_GET['ptprm_profile_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Profil berhasil disimpan.', 'ptsbi-premium' ) . '</p>';
        }
        if ( isset( $_GET['ptprm_profile_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $err = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_profile_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $err !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $err ) . '</p>';
            }
        }

        echo '<p class="ptprm-portal-help">' . esc_html__( 'Kelola data keluarga Anda di sini. Perubahan nama kepala keluarga dan No HP ikut memperbarui data pendaftaran.', 'ptsbi-premium' ) . '</p>';

        $user = wp_get_current_user();
        if ( $user instanceof WP_User && $user->exists() ) {
            echo '<p class="ptprm-portal-help ptprm-portal-reg-summary">';
            echo esc_html__( 'Email (username)', 'ptsbi-premium' ) . ': <strong>' . esc_html( (string) $user->user_email ) . '</strong>';
            echo '</p>';
        }

        echo '<form method="post" class="ptprm-member-form" data-ptprm-address-form>';
        wp_nonce_field( 'ptprm_member_profile', self::NONCE_PROFILE );
        echo '<input type="hidden" name="ptprm_member_profile" value="1">';
        echo '<div class="ptprm-member-grid">';

        $text_fields = [
            'kepala_keluarga' => __( 'Nama Kepala Keluarga', 'ptsbi-premium' ),
            'nama_istri'      => __( 'Nama Istri', 'ptsbi-premium' ),
            'tarombo'         => __( 'Tarombo', 'ptsbi-premium' ),
            'nomor_sundut'    => __( 'Nomor Sundut', 'ptsbi-premium' ),
            'phone'           => __( 'No HP', 'ptsbi-premium' ),
            'rt'              => 'RT',
            'rw'              => 'RW',
        ];
        foreach ( $text_fields as $key => $label ) {
            $val = (string) ( $r[ $key ] ?? '' );
            echo '<label><span>' . esc_html( $label ) . '</span>';
            echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '"></label>';
        }

        PTPRM_Members::render_ompu_select( (string) ( $r['oppu'] ?? '' ) );
        PTPRM_Members::render_status_select(
            (string) ( $r['hula_boru'] ?? '' ),
            (string) ( $r['kepala_keluarga'] ?? '' ),
            (string) ( $r['nama_istri'] ?? '' )
        );

        $status = PTPRM_Members::compute_marga_status(
            (string) ( $r['kepala_keluarga'] ?? '' ),
            (string) ( $r['nama_istri'] ?? '' ),
            (string) ( $r['hula_boru'] ?? '' )
        );
        if ( $status !== '' ) {
            echo '<p class="ptprm-member-full ptprm-portal-help">';
            echo esc_html__( 'Status marga (otomatis jika kosong)', 'ptsbi-members' ) . ': <strong>' . esc_html( $status ) . '</strong>';
            echo '</p>';
        }

        $overseas = ! empty( $r['is_overseas'] );
        $wilayah_current = $overseas ? 'overseas' : 'jabodetabek';
        echo '<input type="hidden" name="is_overseas" value="' . ( $overseas ? '1' : '0' ) . '" data-ptprm-overseas-flag>';
        $country = (string) ( $r['country_name'] ?? 'Indonesia' );
        $province = (string) ( $r['province'] ?? '' );
        $city     = (string) ( $r['city'] ?? '' );
        if ( $province === '' && $city !== '' && class_exists( 'PTPRM_Address_Regions' ) ) {
            $province = PTPRM_Address_Regions::guess_province( $city );
        }

        echo '<div class="ptprm-member-full ptprm-address-region-block" data-ptprm-address-id>';
        echo '<div class="ptprm-member-grid ptprm-address-region-grid">';
        echo '<label><span>' . esc_html__( 'Negara', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" name="country_name" value="' . esc_attr( $country ) . '"></label>';

        if ( class_exists( 'PTPRM_Address_Regions' ) ) {
            PTPRM_Address_Regions::render_wilayah_select( $wilayah_current );
            PTPRM_Address_Regions::render_select( 'province', __( 'Provinsi', 'ptsbi-premium' ), $province, [ 'data-current' => $province, 'required' => 'required' ] );
            PTPRM_Address_Regions::render_select( 'city', __( 'Kabupaten / Kota', 'ptsbi-premium' ), $city, [ 'data-current' => $city, 'required' => 'required' ] );
            PTPRM_Address_Regions::render_select( 'district', __( 'Kecamatan', 'ptsbi-premium' ), (string) ( $r['district'] ?? '' ), [ 'data-current' => (string) ( $r['district'] ?? '' ), 'required' => 'required' ] );
            PTPRM_Address_Regions::render_select( 'subdistrict', __( 'Kelurahan', 'ptsbi-premium' ), (string) ( $r['subdistrict'] ?? '' ), [ 'data-current' => (string) ( $r['subdistrict'] ?? '' ) ] );
        }

        $postal = (string) ( $r['postal_code'] ?? '' );
        echo '<label><span>' . esc_html__( 'Kode Pos', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" name="postal_code" value="' . esc_attr( $postal ) . '" maxlength="10" autocomplete="postal-code"></label>';
        echo '</div></div>';

        echo '<div class="ptprm-member-full ptprm-address-region-block" data-ptprm-address-intl' . ( $overseas ? '' : ' style="display:none;"' ) . '>';
        echo '<div class="ptprm-member-grid">';
        echo '<label><span>' . esc_html__( 'Negara (luar negeri)', 'ptsbi-members' ) . '</span>';
        echo '<input type="text" name="country_name_intl" value="' . esc_attr( $overseas ? $country : '' ) . '" data-ptprm-country-intl></label>';
        echo '<label><span>' . esc_html__( 'State / Kota', 'ptsbi-members' ) . '</span>';
        echo '<input type="text" name="state_city" value="' . esc_attr( (string) ( $r['state_city'] ?? '' ) ) . '"></label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Alamat lengkap (manual)', 'ptsbi-members' ) . '</span>';
        echo '<textarea name="address_detail_intl" rows="3" data-ptprm-address-intl-text>' . esc_textarea( $overseas ? (string) ( $r['address_detail'] ?? '' ) : '' ) . '</textarea></label>';
        echo '</div></div>';

        $addr_parts = PTPRM_Members::split_address_detail(
            (string) ( $r['address_detail'] ?? '' ),
            (string) ( $r['street_name'] ?? '' ),
            (string) ( $r['house_number'] ?? '' )
        );
        echo '<label><span>' . esc_html__( 'Nama Jalan', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" name="street_name" value="' . esc_attr( $addr_parts['street'] ) . '"></label>';
        echo '<label><span>' . esc_html__( 'Nomor Rumah', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" name="house_number" value="' . esc_attr( $addr_parts['house'] ) . '"></label>';

        echo '</div>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Profil', 'ptsbi-premium' ) . '</span></button>';
        echo '</form></div>';
    }

    private function render_account_tab( WP_User $user ): void {
        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p><strong>' . esc_html__( 'Username', 'ptsbi-premium' ) . ':</strong> ' . esc_html( $user->user_login ) . '</p>';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Ganti password di bawah jika perlu. Kosongkan jika tidak ingin mengubah.', 'ptsbi-premium' ) . '</p>';
        echo '<form method="post" class="ptprm-member-form">';
        wp_nonce_field( 'ptprm_member_profile', self::NONCE_PROFILE );
        echo '<input type="hidden" name="ptprm_member_profile" value="1">';
        echo '<input type="hidden" name="ptprm_password_only" value="1">';
        echo '<label><span>' . esc_html__( 'Password baru', 'ptsbi-premium' ) . '</span>';
        echo '<input type="password" name="pass1" autocomplete="new-password"></label>';
        echo '<label><span>' . esc_html__( 'Ulangi password baru', 'ptsbi-premium' ) . '</span>';
        echo '<input type="password" name="pass2" autocomplete="new-password"></label>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Password', 'ptsbi-premium' ) . '</span></button>';
        echo '</form></div>';
    }

    /**
     * Buat halaman masuk & area anggota jika belum ada.
     *
     * @return array{login:int,portal:int}
     */
    public static function ensure_pages(): array {
        $ids = [ 'portal' => 0 ];

        $pages = [
            'portal' => [
                'slug'    => self::portal_slug(),
                'title'   => __( 'Area Anggota', 'ptsbi-premium' ),
                'content' => '[ptprm_member_portal]',
            ],
        ];

        foreach ( $pages as $key => $bp ) {
            $existing = get_page_by_path( $bp['slug'], OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                $ids[ $key ] = (int) $existing->ID;
                continue;
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
                $ids[ $key ] = (int) $id;
            }
        }

        return $ids;
    }
}
