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
        add_action( 'init', [ __CLASS__, 'ensure_pages' ], 14 );
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

    private static function ensure_page( string $slug, string $title, string $content ): void {
        $existing = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $existing instanceof WP_Post ) {
            if ( strpos( (string) $existing->post_content, 'ptprm_bidang' ) === false ) {
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
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return '';
        }
        return sanitize_key( (string) get_user_meta( $user->ID, self::USER_META_BIDANG, true ) );
    }

    public static function is_bidang_user( $user = null ): bool {
        $slug = self::get_user_bidang_slug( $user );
        return $slug !== '' && isset( self::bidangs()[ $slug ] );
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
        return self::get_user_bidang_slug( $user ) === $bidang_slug;
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
