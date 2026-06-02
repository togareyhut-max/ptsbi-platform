<?php
/**
 * Registry bidang kerja PTSBI + akun panel masing-masing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Bidang_Registry {

    public const USER_META_BIDANG = 'ptprm_bidang_slug';

    /**
     * @return array<string, array{slug:string,title:string,page_slug:string,panel_slug:string,is_sekretariat:bool}>
     */
    public static function bidangs(): array {
        return [
            'sekretariat' => [
                'slug'           => 'sekretariat',
                'title'          => __( 'Sekretariat Pusat', 'ptsbi-premium' ),
                'page_slug'      => 'sekretariat-pusat',
                'panel_slug'     => 'panel-sekretariat',
                'is_sekretariat' => true,
            ],
            'adat-budaya' => [
                'slug'           => 'adat-budaya',
                'title'          => __( 'Bidang Adat dan Budaya', 'ptsbi-premium' ),
                'page_slug'      => 'bidang-adat-budaya',
                'panel_slug'     => 'panel-adat-budaya',
                'is_sekretariat' => false,
            ],
            'usaha-dana'  => [
                'slug'           => 'usaha-dana',
                'title'          => __( 'Bidang Usaha dan Dana', 'ptsbi-premium' ),
                'page_slug'      => 'bidang-usaha-dana',
                'panel_slug'     => 'panel-usaha-dana',
                'is_sekretariat' => false,
            ],
            'sos-dik-mud' => [
                'slug'           => 'sos-dik-mud',
                'title'          => __( 'Bidang Sosial, Pendidikan dan Kepemudaan', 'ptsbi-premium' ),
                'page_slug'      => 'bidang-sosial-pendidikan',
                'panel_slug'     => 'panel-sosial-pendidikan',
                'is_sekretariat' => false,
            ],
            'hukum'       => [
                'slug'           => 'hukum',
                'title'          => __( 'Bidang Hukum', 'ptsbi-premium' ),
                'page_slug'      => 'bidang-hukum',
                'panel_slug'     => 'panel-hukum',
                'is_sekretariat' => false,
            ],
        ];
    }

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'ensure_categories' ], 11 );
        add_action( 'init', [ __CLASS__, 'sync_bidang_user_meta' ], 12 );
        add_action( 'init', [ __CLASS__, 'ensure_pages' ], 14 );
        add_action( 'init', [ __CLASS__, 'repair_panel_pages' ], 15 );
        add_action( 'init', [ __CLASS__, 'maybe_restore_missing_pages' ], 16 );
        add_action( 'template_redirect', [ __CLASS__, 'redirect_bidang_users_from_org_panel' ], 8 );
    }

    /**
     * Email login resmi tiap panel bidang.
     *
     * @return array<string, string> slug => email
     */
    public static function bidang_account_emails(): array {
        return [
            'sekretariat' => 'sekretariat@ptsbi.org',
            'adat-budaya' => 'adat@ptsbi.org',
            'usaha-dana'  => 'usaha@ptsbi.org',
            'sos-dik-mud' => 'sos@ptsbi.org',
            'hukum'       => 'hukum@ptsbi.org',
        ];
    }

    public static function slug_for_email( string $email ): string {
        $email = strtolower( trim( $email ) );
        foreach ( self::bidang_account_emails() as $slug => $mapped ) {
            if ( strtolower( $mapped ) === $email ) {
                return (string) $slug;
            }
        }
        return '';
    }

    /**
     * Tentukan bidang user (meta, email, atau tipe akun demo) dan simpan meta jika perlu.
     */
    public static function resolve_bidang_slug_for_user( $user = null, bool $persist = true ): string {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return '';
        }
        if ( class_exists( 'PTPRM_Access' ) && PTPRM_Access::is_site_admin( $user ) ) {
            return '';
        }

        $slug = sanitize_key( (string) get_user_meta( $user->ID, self::USER_META_BIDANG, true ) );
        if ( $slug !== '' && isset( self::bidangs()[ $slug ] ) ) {
            return $slug;
        }

        $slug = self::slug_for_email( (string) $user->user_email );
        if ( $slug === '' ) {
            $slug = self::slug_for_email( (string) $user->user_login );
        }
        if ( $slug === '' && class_exists( 'PTPRM_Default_Accounts' ) ) {
            $marked = (string) get_user_meta( $user->ID, PTPRM_Default_Accounts::META_TYPE, true );
            if ( strpos( $marked, 'bidang_' ) === 0 ) {
                $slug = str_replace( '_', '-', substr( $marked, 7 ) );
            }
        }

        $slug = sanitize_key( $slug );
        if ( $slug !== '' && isset( self::bidangs()[ $slug ] ) && $persist ) {
            self::set_user_bidang_slug( (int) $user->ID, $slug );
        }
        return ( isset( self::bidangs()[ $slug ] ) ? $slug : '' );
    }

    /**
     * Sinkronkan meta bidang untuk akun email resmi (hindari salah masuk panel admin).
     */
    public static function sync_bidang_user_meta(): void {
        foreach ( self::bidang_account_emails() as $slug => $email ) {
            $user = get_user_by( 'email', $email );
            if ( ! $user instanceof WP_User ) {
                $user = get_user_by( 'login', $email );
            }
            if ( ! $user instanceof WP_User ) {
                continue;
            }
            if ( class_exists( 'PTPRM_Access' ) && PTPRM_Access::is_site_admin( $user ) ) {
                continue;
            }
            self::set_user_bidang_slug( (int) $user->ID, $slug );
            if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
                $type = 'bidang_' . str_replace( '-', '_', $slug );
                update_user_meta( $user->ID, PTPRM_Default_Accounts::META_TYPE, $type );
            }
            if ( in_array( 'admin_organisasi', (array) $user->roles, true ) ) {
                $user->set_role( 'pengurus' );
            }
        }
    }

    /**
     * Akun bidang tidak boleh memakai panel pengurus/admin organisasi.
     */
    public static function redirect_bidang_users_from_org_panel(): void {
        if ( ! is_user_logged_in() || ! class_exists( 'PTPRM_Admin_Portal' ) ) {
            return;
        }
        if ( ! PTPRM_Admin_Portal::is_admin_page() ) {
            return;
        }
        $slug = self::resolve_bidang_slug_for_user();
        if ( $slug === '' ) {
            return;
        }
        wp_safe_redirect( self::panel_url( $slug ) );
        exit;
    }

    /**
     * Cari halaman berdasarkan slug (termasuk draft / trash).
     */
    public static function locate_page( string $slug ): ?WP_Post {
        $slug = sanitize_title( $slug );
        if ( $slug === '' ) {
            return null;
        }
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $page instanceof WP_Post ) {
            return $page;
        }
        $posts = get_posts(
            [
                'name'           => $slug,
                'post_type'      => 'page',
                'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'trash' ],
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ]
        );
        return ( ! empty( $posts[0] ) && $posts[0] instanceof WP_Post ) ? $posts[0] : null;
    }

    /**
     * Buat ulang halaman bidang & panel yang hilang atau ada di Trash.
     */
    public static function restore_all_pages(): void {
        self::ensure_categories();
        foreach ( self::bidangs() as $meta ) {
            self::ensure_page(
                (string) $meta['page_slug'],
                (string) $meta['title'],
                '[ptprm_bidang slug="' . esc_attr( $meta['slug'] ) . '"]'
            );
            self::ensure_page(
                (string) $meta['panel_slug'],
                sprintf(
                    /* translators: %s: bidang name */
                    __( 'Panel %s', 'ptsbi-premium' ),
                    $meta['title']
                ),
                '[ptprm_bidang_panel slug="' . esc_attr( $meta['slug'] ) . '"]'
            );
        }
        self::repair_panel_pages();
        update_option( 'ptprm_bidang_pages_v1', 1, false );
        flush_rewrite_rules( false );
    }

    public static function maybe_restore_missing_pages(): void {
        foreach ( self::bidangs() as $meta ) {
            $page = self::locate_page( (string) $meta['panel_slug'] );
            if ( ! $page instanceof WP_Post || $page->post_status !== 'publish' ) {
                self::restore_all_pages();
                return;
            }
        }
    }

    public static function content_option_key( string $slug ): string {
        return 'bidang_' . sanitize_key( $slug );
    }

    /**
     * @return array{visi:string,misi:string,program:string}
     */
    public static function get_content( string $slug ): array {
        $slug = sanitize_key( $slug );
        $o    = ptprm_options();
        $key  = self::content_option_key( $slug );
        $raw  = isset( $o[ $key ] ) ? trim( (string) $o[ $key ] ) : '';
        $base = [ 'visi' => '', 'misi' => '', 'program' => '' ];
        if ( $raw === '' ) {
            return $base;
        }
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return $base;
        }
        return [
            'visi'    => sanitize_textarea_field( (string) ( $decoded['visi'] ?? '' ) ),
            'misi'    => sanitize_textarea_field( (string) ( $decoded['misi'] ?? '' ) ),
            'program' => sanitize_textarea_field( (string) ( $decoded['program'] ?? '' ) ),
        ];
    }

    /**
     * @param array{visi?:string,misi?:string,program?:string} $content
     */
    public static function save_content( string $slug, array $content ): void {
        $slug = sanitize_key( $slug );
        if ( ! isset( self::bidangs()[ $slug ] ) ) {
            return;
        }
        $payload = [
            'visi'    => sanitize_textarea_field( (string) ( $content['visi'] ?? '' ) ),
            'misi'    => sanitize_textarea_field( (string) ( $content['misi'] ?? '' ) ),
            'program' => sanitize_textarea_field( (string) ( $content['program'] ?? '' ) ),
        ];
        $opts         = (array) get_option( PTPRM_OPTION, [] );
        $opts[ self::content_option_key( $slug ) ] = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE );
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( $opts ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );
    }

    public static function category_slug( string $bidang_slug, string $type ): string {
        return 'ptsbi-' . sanitize_key( $bidang_slug ) . '-' . sanitize_key( $type );
    }

    public static function ensure_categories(): void {
        if ( get_option( 'ptprm_bidang_cats_v1' ) ) {
            return;
        }
        foreach ( self::bidangs() as $meta ) {
            foreach ( [ 'kegiatan', 'pengumuman' ] as $type ) {
                $slug = self::category_slug( $meta['slug'], $type );
                if ( ! term_exists( $slug, 'category' ) ) {
                    wp_insert_term(
                        $meta['title'] . ' — ' . ( $type === 'kegiatan' ? __( 'Kegiatan', 'ptsbi-premium' ) : __( 'Pengumuman', 'ptsbi-premium' ) ),
                        'category',
                        [ 'slug' => $slug ]
                    );
                }
            }
        }
        update_option( 'ptprm_bidang_cats_v1', 1, false );
    }

    public static function ensure_pages(): void {
        if ( get_option( 'ptprm_bidang_pages_v1' ) ) {
            return;
        }
        foreach ( self::bidangs() as $meta ) {
            self::ensure_page( (string) $meta['page_slug'], (string) $meta['title'], '[ptprm_bidang slug="' . esc_attr( $meta['slug'] ) . '"]' );
            self::ensure_page(
                (string) $meta['panel_slug'],
                sprintf(
                    /* translators: %s: bidang name */
                    __( 'Panel %s', 'ptsbi-premium' ),
                    $meta['title']
                ),
                '[ptprm_bidang_panel slug="' . esc_attr( $meta['slug'] ) . '"]'
            );
        }
        update_option( 'ptprm_bidang_pages_v1', 1, false );
        flush_rewrite_rules( false );
    }

    /**
     * Perbaiki shortcode panel bidang (mis. [ptprm_bidang_portal] salah ketik).
     */
    public static function repair_panel_pages(): void {
        foreach ( self::bidangs() as $meta ) {
            $panel_slug = (string) $meta['panel_slug'];
            $correct    = '[ptprm_bidang_panel slug="' . esc_attr( (string) $meta['slug'] ) . '"]';
            $page       = self::locate_page( $panel_slug );
            if ( ! $page instanceof WP_Post ) {
                self::ensure_page(
                    $panel_slug,
                    sprintf(
                        /* translators: %s: bidang name */
                        __( 'Panel %s', 'ptsbi-premium' ),
                        $meta['title']
                    ),
                    $correct
                );
                continue;
            }
            $content = (string) $page->post_content;
            $needs   = strpos( $content, 'ptprm_bidang_panel' ) === false
                || strpos( $content, 'ptprm_bidang_portal' ) !== false;
            if ( $needs ) {
                wp_update_post(
                    [
                        'ID'           => (int) $page->ID,
                        'post_content' => $correct,
                    ]
                );
            }
        }
    }

    private static function ensure_page( string $slug, string $title, string $content ): void {
        $existing = self::locate_page( $slug );
        if ( $existing instanceof WP_Post ) {
            if ( $existing->post_status === 'trash' ) {
                wp_untrash_post( (int) $existing->ID );
                $existing = get_post( (int) $existing->ID );
            }
            if ( $existing instanceof WP_Post && $existing->post_status !== 'publish' ) {
                wp_update_post(
                    [
                        'ID'          => (int) $existing->ID,
                        'post_status' => 'publish',
                    ]
                );
            }
            $current = (string) $existing->post_content;
            if ( strpos( $current, 'ptprm_bidang_panel' ) === false || strpos( $current, 'ptprm_bidang_portal' ) !== false ) {
                wp_update_post(
                    [
                        'ID'           => (int) $existing->ID,
                        'post_content' => $content,
                    ]
                );
            }
            return;
        }
        wp_insert_post(
            [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]
        );
    }

    public static function set_user_bidang_slug( int $user_id, string $slug ): void {
        update_user_meta( $user_id, self::USER_META_BIDANG, sanitize_key( $slug ) );
    }

    public static function get_user_bidang_slug( $user = null ): string {
        return self::resolve_bidang_slug_for_user( $user, false );
    }

    public static function is_bidang_user( $user = null ): bool {
        return self::resolve_bidang_slug_for_user( $user ) !== '';
    }

    public static function is_sekretariat_user( $user = null ): bool {
        return self::get_user_bidang_slug( $user ) === 'sekretariat';
    }

    public static function user_can_manage_bidang( string $bidang_slug, $user = null ): bool {
        $bidang_slug = sanitize_key( $bidang_slug );
        $user        = $user ?: wp_get_current_user();
        if ( class_exists( 'PTPRM_Access' ) && PTPRM_Access::is_site_admin( $user ) ) {
            return true;
        }
        if ( class_exists( 'PTPRM_Access' ) && $user instanceof WP_User && user_can( $user, PTPRM_Access::CAP_ORG_SETTINGS ) ) {
            return true;
        }
        return self::resolve_bidang_slug_for_user( $user, false ) === $bidang_slug;
    }

    public static function panel_url( string $slug, string $tab = '', array $args = [] ): string {
        $slug = sanitize_key( $slug );
        if ( ! isset( self::bidangs()[ $slug ] ) ) {
            return home_url( '/' );
        }
        $url = home_url( '/' . self::bidangs()[ $slug ]['panel_slug'] . '/' );
        if ( $tab !== '' ) {
            $args['tab'] = $tab;
        }
        return $args ? add_query_arg( $args, $url ) : $url;
    }

    public static function is_bidang_panel_page(): bool {
        if ( ! is_page() ) {
            return false;
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        foreach ( self::bidangs() as $meta ) {
            if ( (string) $meta['panel_slug'] === $slug ) {
                return true;
            }
        }
        return false;
    }

    public static function bidang_slug_from_panel_page(): string {
        if ( ! is_page() ) {
            return '';
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        foreach ( self::bidangs() as $meta ) {
            if ( (string) $meta['panel_slug'] === $slug ) {
                return (string) $meta['slug'];
            }
        }
        return '';
    }
}
