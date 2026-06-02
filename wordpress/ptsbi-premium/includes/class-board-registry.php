<?php
/**
 * Pengurus pusat & wilayah — penyimpanan per region.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once PTPRM_DIR . 'includes/board-default-data.php';

class PTPRM_Board_Registry {

    public const OPTION_SEEDED          = 'ptprm_boards_seeded_v1';
    public const OPTION_CATALOG_VERSION = 'ptprm_board_catalog_version';
    public const CATALOG_VERSION        = '3.1.9';

    /**
     * Halaman WP tambahan yang menampilkan pengurus pusat.
     *
     * @return array<string, string> page_slug => region
     */
    public static function extra_page_slugs(): array {
        return [
            'struktur-organisasi' => 'pusat',
            'pengurus-pusat'      => 'pusat',
        ];
    }

    public static function region_for_page_slug( string $page_slug ): ?string {
        $page_slug = sanitize_title( $page_slug );
        foreach ( self::regions() as $slug => $meta ) {
            if ( (string) $meta['page_slug'] === $page_slug ) {
                return $slug;
            }
        }
        $extra = self::extra_page_slugs();
        return isset( $extra[ $page_slug ] ) ? (string) $extra[ $page_slug ] : null;
    }

    /**
     * @return array<string, array{slug:string,title:string,page_slug:string,has_featured_photos:bool,subtitle:string}>
     */
    public static function regions(): array {
        return [
            'pusat'     => [
                'slug'                => 'pusat',
                'title'               => __( 'Struktur Pengurus Pusat', 'ptsbi-premium' ),
                'page_slug'           => 'ptsbi-pusat',
                'has_featured_photos' => true,
                'subtitle'            => '',
            ],
            'samosir'   => [
                'slug'                => 'samosir',
                'title'               => __( 'Pengurus PTSBI Kab. Samosir', 'ptsbi-premium' ),
                'page_slug'           => 'ptsbi-samosir',
                'has_featured_photos' => false,
                'subtitle'            => '',
            ],
            'medan'     => [
                'slug'                => 'medan',
                'title'               => __( 'Pengurus PTSBI Kota Medan', 'ptsbi-premium' ),
                'page_slug'           => 'ptsbi-medan',
                'has_featured_photos' => false,
                'subtitle'            => '',
            ],
            'pekanbaru' => [
                'slug'                => 'pekanbaru',
                'title'               => __( 'Pengurus PTSBI Pekanbaru', 'ptsbi-premium' ),
                'page_slug'           => 'ptsbi-pekanbaru',
                'has_featured_photos' => false,
                'subtitle'            => '',
            ],
        ];
    }

    /** Key di ptprm_options (legacy). */
    public static function option_key( string $region ): string {
        return 'board_' . sanitize_key( $region );
    }

    /** Opsi WP terpisah — tidak ikut tertimpa saat simpan pengaturan plugin. */
    public static function standalone_option_key( string $region ): string {
        return 'ptprm_board_data_' . sanitize_key( $region );
    }

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'maybe_seed_defaults' ], 12 );
        add_action( 'init', [ __CLASS__, 'ensure_region_pages' ], 13 );
        add_action( 'init', [ __CLASS__, 'maybe_apply_catalog_version' ], 14 );
        add_action( 'init', [ __CLASS__, 'maybe_migrate_standalone_storage' ], 15 );
        add_action( 'init', [ __CLASS__, 'maybe_repair_empty_board_storage' ], 16 );
        add_action( 'init', [ __CLASS__, 'ensure_extra_board_pages' ], 17 );
    }

    public static function maybe_seed_defaults(): void {
        if ( get_option( self::OPTION_SEEDED ) ) {
            return;
        }
        $catalog = ptprm_board_default_catalog();
        foreach ( self::regions() as $slug => $meta ) {
            unset( $meta );
            if ( self::get_items( $slug ) === [] ) {
                self::save_items( $slug, $catalog[ $slug ] ?? [], false );
            }
        }
        update_option( self::OPTION_SEEDED, 1, false );
    }

    public static function maybe_migrate_standalone_storage(): void {
        if ( get_option( 'ptprm_board_standalone_v1' ) ) {
            return;
        }
        foreach ( self::regions() as $slug => $meta ) {
            unset( $meta );
            $legacy = self::read_legacy_raw( $slug );
            if ( $legacy === '' || $legacy === '[]' ) {
                continue;
            }
            $existing = get_option( self::standalone_option_key( $slug ), '' );
            if ( ! is_string( $existing ) || $existing === '' || $existing === '[]' ) {
                update_option( self::standalone_option_key( $slug ), $legacy, false );
            }
        }
        update_option( 'ptprm_board_standalone_v1', 1, false );
    }

    public static function maybe_apply_catalog_version(): void {
        if ( get_option( self::OPTION_CATALOG_VERSION ) === self::CATALOG_VERSION ) {
            return;
        }

        $catalog = ptprm_board_default_catalog();
        foreach ( self::regions() as $slug => $meta ) {
            unset( $meta );
            $existing = self::get_items( $slug );
            $items    = function_exists( 'ptprm_merge_board_catalog' )
                ? ptprm_merge_board_catalog( $existing, $catalog[ $slug ] ?? [] )
                : ptprm_sanitize_board_items( $catalog[ $slug ] ?? [] );
            self::save_items( $slug, $items, false );
        }

        self::sync_region_page_titles();
        update_option( self::OPTION_CATALOG_VERSION, self::CATALOG_VERSION, false );
    }

    public static function sync_region_page_titles(): void {
        foreach ( self::regions() as $meta ) {
            $slug  = (string) $meta['page_slug'];
            $title = (string) $meta['title'];
            $page  = get_page_by_path( $slug, OBJECT, 'page' );
            if ( ! $page instanceof WP_Post ) {
                continue;
            }
            $current = (string) $page->post_title;
            $clean   = preg_replace( '/\s*20\d{2}\s*[-–]\s*20\d{2}\s*/u', ' ', $current );
            $clean   = is_string( $clean ) ? trim( preg_replace( '/\s+/u', ' ', $clean ) ) : $current;
            $next    = $title;
            if ( $clean !== '' && $clean !== $current && stripos( $current, '2025' ) !== false ) {
                $next = $clean;
            }
            if ( $next !== $current ) {
                wp_update_post(
                    [
                        'ID'         => (int) $page->ID,
                        'post_title' => $next,
                    ]
                );
            }
        }
    }

    public static function ensure_region_pages(): void {
        if ( get_option( 'ptprm_board_pages_v2' ) ) {
            return;
        }
        foreach ( self::regions() as $meta ) {
            $slug     = (string) $meta['page_slug'];
            $existing = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                if ( strpos( (string) $existing->post_content, '[ptprm_board' ) === false ) {
                    wp_update_post(
                        [
                            'ID'           => (int) $existing->ID,
                            'post_content' => '[ptprm_board region="' . esc_attr( $meta['slug'] ) . '"]',
                        ]
                    );
                }
                continue;
            }
            wp_insert_post(
                [
                    'post_title'   => (string) $meta['title'],
                    'post_name'    => $slug,
                    'post_content' => '[ptprm_board region="' . esc_attr( $meta['slug'] ) . '"]',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]
            );
        }
        update_option( 'ptprm_board_pages_v2', 1, false );
        flush_rewrite_rules( false );
    }

    /**
     * Halaman alias (pengurus-pusat, struktur-organisasi) dengan shortcode pengurus pusat.
     */
    public static function ensure_extra_board_pages(): void {
        if ( get_option( 'ptprm_board_extra_pages_v1' ) ) {
            return;
        }
        $regions = self::regions();
        foreach ( self::extra_page_slugs() as $page_slug => $region ) {
            $region = sanitize_key( $region );
            if ( ! isset( $regions[ $region ] ) ) {
                continue;
            }
            $page_slug = sanitize_title( $page_slug );
            $existing  = get_page_by_path( $page_slug, OBJECT, 'page' );
            $shortcode = '[ptprm_board region="' . esc_attr( $region ) . '"]';
            $title     = (string) ( $regions[ $region ]['title'] ?? __( 'Pengurus Pusat', 'ptsbi-premium' ) );
            if ( $existing instanceof WP_Post ) {
                if ( strpos( (string) $existing->post_content, '[ptprm_board' ) === false ) {
                    wp_update_post(
                        [
                            'ID'           => (int) $existing->ID,
                            'post_content' => $shortcode,
                        ]
                    );
                }
                continue;
            }
            wp_insert_post(
                [
                    'post_title'   => $title,
                    'post_name'    => $page_slug,
                    'post_content' => $shortcode,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]
            );
        }
        update_option( 'ptprm_board_extra_pages_v1', 1, false );
        flush_rewrite_rules( false );
    }

    /**
     * Opsi kosong "[]" membuat daftar pengurus tidak tampil di versi lama — hapus agar fallback katalog jalan.
     */
    public static function maybe_repair_empty_board_storage(): void {
        $version = (string) get_option( 'ptprm_board_empty_repair_v1', '' );
        if ( $version === '2' ) {
            return;
        }
        foreach ( self::regions() as $slug => $meta ) {
            unset( $meta );
            $key = self::standalone_option_key( $slug );
            $raw = get_option( $key, '' );
            if ( is_string( $raw ) && self::raw_has_no_displayable_names( $raw ) ) {
                delete_option( $key );
            }
            $legacy = self::read_legacy_raw( $slug );
            if ( self::raw_has_no_displayable_names( $legacy ) ) {
                $opts = (array) get_option( PTPRM_OPTION, [] );
                if ( is_array( $opts ) ) {
                    unset( $opts[ self::option_key( $slug ) ] );
                    update_option( PTPRM_OPTION, $opts, true );
                }
            }
        }
        update_option( 'ptprm_board_empty_repair_v1', '2', false );
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public static function items_have_displayable_names( array $items ): bool {
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            if ( trim( (string) ( $item['name'] ?? '' ) ) !== '' ) {
                return true;
            }
        }
        return false;
    }

    private static function raw_has_no_displayable_names( string $raw ): bool {
        $raw = trim( $raw );
        if ( $raw === '' || $raw === '[]' ) {
            return true;
        }
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return true;
        }
        return ! self::items_have_displayable_names( ptprm_sanitize_board_items( $decoded ) );
    }

    private static function read_legacy_raw( string $region ): string {
        $opts = get_option( PTPRM_OPTION, [] );
        if ( ! is_array( $opts ) ) {
            return '';
        }
        $key = self::option_key( $region );
        return isset( $opts[ $key ] ) ? trim( (string) $opts[ $key ] ) : '';
    }

    private static function catalog_items( string $region ): array {
        $catalog = ptprm_board_default_catalog();
        return ptprm_sanitize_board_items( $catalog[ $region ] ?? [] );
    }

    private static function decode_items( string $raw, string $region ): array {
        if ( $raw === '' || $raw === '[]' ) {
            return [];
        }
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }
        return ptprm_sanitize_board_items( $decoded );
    }

    /**
     * @return list<array{name:string,role:string,group:string,image:string,featured:int}>
     */
    public static function get_items( string $region ): array {
        $region = sanitize_key( $region );
        if ( ! isset( self::regions()[ $region ] ) ) {
            return [];
        }

        $raw = get_option( self::standalone_option_key( $region ), '' );
        if ( ! is_string( $raw ) || $raw === '' ) {
            $raw = self::read_legacy_raw( $region );
        }

        $items = self::decode_items( is_string( $raw ) ? $raw : '', $region );
        if ( $items !== [] && self::items_have_displayable_names( $items ) ) {
            return $items;
        }

        return self::catalog_items( $region );
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public static function save_items( string $region, array $items, bool $purge_cache = true ): void {
        $region = sanitize_key( $region );
        if ( ! isset( self::regions()[ $region ] ) ) {
            return;
        }
        $items = ptprm_sanitize_board_items( $items );
        if ( $items === [] ) {
            return;
        }
        if ( 'pusat' === $region ) {
            foreach ( $items as $i => $item ) {
                if ( self::is_featured_item( 'pusat', $item ) ) {
                    $items[ $i ]['featured'] = 1;
                }
            }
        }

        $json = wp_json_encode( $items, JSON_UNESCAPED_UNICODE );
        update_option( self::standalone_option_key( $region ), $json, false );

        $opts = (array) get_option( PTPRM_OPTION, [] );
        if ( ! is_array( $opts ) ) {
            $opts = [];
        }
        $opts[ self::option_key( $region ) ] = $json;
        update_option( PTPRM_OPTION, ptprm_preserve_board_keys_for_storage( $opts ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( self::standalone_option_key( $region ), 'options' );

        if ( $purge_cache && class_exists( 'PTPRM_Cache_Purge' ) ) {
            PTPRM_Cache_Purge::purge_all();
        }
    }

    public static function featured_roles_for_region( string $region ): array {
        if ( 'pusat' !== sanitize_key( $region ) ) {
            return [];
        }
        return [ 'ketua umum', 'sekretaris umum', 'bendahara umum' ];
    }

    /** Jabatan inti yang wajib punya kolom URL foto di panel admin pusat. */
    public static function is_core_photo_role( string $role ): bool {
        $role = strtolower( trim( $role ) );
        if ( $role === '' ) {
            return false;
        }
        foreach ( self::featured_roles_for_region( 'pusat' ) as $needle ) {
            if ( $role === $needle || strpos( $role, $needle ) !== false ) {
                return true;
            }
        }
        return false;
    }

    public static function is_featured_item( string $region, array $item ): bool {
        if ( ! empty( $item['featured'] ) ) {
            return true;
        }
        if ( 'pusat' !== sanitize_key( $region ) ) {
            return false;
        }
        $role = strtolower( (string) ( $item['role'] ?? '' ) );
        foreach ( self::featured_roles_for_region( 'pusat' ) as $needle ) {
            if ( $role === $needle || strpos( $role, $needle ) !== false ) {
                return true;
            }
        }
        return false;
    }
}
