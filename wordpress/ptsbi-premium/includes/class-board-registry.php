<?php
/**
 * Pengurus pusat & wilayah — penyimpanan per region.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once PTPRM_DIR . 'includes/board-default-data.php';

class PTPRM_Board_Registry {

    public const OPTION_SEEDED = 'ptprm_boards_seeded_v1';

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

    public static function option_key( string $region ): string {
        return 'board_' . sanitize_key( $region );
    }

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'maybe_seed_defaults' ], 12 );
        add_action( 'init', [ __CLASS__, 'ensure_region_pages' ], 13 );
    }

    public static function maybe_seed_defaults(): void {
        if ( get_option( self::OPTION_SEEDED ) ) {
            return;
        }
        $catalog = ptprm_board_default_catalog();
        $opts    = (array) get_option( PTPRM_OPTION, [] );
        foreach ( self::regions() as $slug => $meta ) {
            $key = self::option_key( $slug );
            if ( empty( $opts[ $key ] ) ) {
                $items = $catalog[ $slug ] ?? [];
                $opts[ $key ] = wp_json_encode( ptprm_sanitize_board_items( $items ), JSON_UNESCAPED_UNICODE );
            }
        }
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( $opts ), true );
        update_option( self::OPTION_SEEDED, 1, false );
    }

    public static function ensure_region_pages(): void {
        if ( get_option( 'ptprm_board_pages_v3' ) ) {
            return;
        }
        foreach ( self::regions() as $meta ) {
            $slug      = (string) $meta['page_slug'];
            $canonical = '[ptprm_board region="' . $meta['slug'] . '"]';
            $existing  = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                if ( ! self::content_is_canonical_board( (string) $existing->post_content, (string) $meta['slug'] ) ) {
                    wp_update_post(
                        [
                            'ID'           => (int) $existing->ID,
                            'post_content' => $canonical,
                        ]
                    );
                }
                continue;
            }
            wp_insert_post(
                [
                    'post_title'   => (string) $meta['title'],
                    'post_name'    => $slug,
                    'post_content' => $canonical,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]
            );
        }
        update_option( 'ptprm_board_pages_v3', 1, false );
        delete_option( 'ptprm_board_pages_v1' );
        flush_rewrite_rules( false );
    }

    /** Konten sudah memakai shortcode lurus yang benar (bukan kutip melengkung / nama lama). */
    private static function content_is_canonical_board( string $content, string $region ): bool {
        $needle = '[ptprm_board region="' . $region . '"]';
        return strpos( $content, $needle ) !== false;
    }

    /**
     * @return list<array{name:string,role:string,group:string,image:string,featured:int}>
     */
    public static function get_items( string $region ): array {
        $region = sanitize_key( $region );
        if ( ! isset( self::regions()[ $region ] ) ) {
            return [];
        }
        $opts = get_option( PTPRM_OPTION, [] );
        if ( ! is_array( $opts ) ) {
            $opts = [];
        }
        $key = self::option_key( $region );
        $raw = isset( $opts[ $key ] ) ? trim( (string) $opts[ $key ] ) : '';
        if ( $raw === '' || $raw === '[]' ) {
            $catalog = ptprm_board_default_catalog();
            return ptprm_sanitize_board_items( $catalog[ $region ] ?? [] );
        }
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? ptprm_sanitize_board_items( $decoded ) : [];
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public static function save_items( string $region, array $items ): void {
        $region = sanitize_key( $region );
        if ( ! isset( self::regions()[ $region ] ) ) {
            return;
        }
        $items = ptprm_sanitize_board_items( $items );
        if ( 'pusat' === $region ) {
            foreach ( $items as $i => $item ) {
                if ( self::is_featured_item( 'pusat', $item ) ) {
                    $items[ $i ]['featured'] = 1;
                }
            }
        }
        $opts = (array) get_option( PTPRM_OPTION, [] );
        if ( ! is_array( $opts ) ) {
            $opts = [];
        }
        $opts[ self::option_key( $region ) ] = wp_json_encode( $items, JSON_UNESCAPED_UNICODE );
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( $opts ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );
        wp_cache_delete( 'alloptions', 'options' );
    }

    public static function featured_roles_for_region( string $region ): array {
        if ( 'pusat' !== sanitize_key( $region ) ) {
            return [];
        }
        return [ 'ketua umum', 'sekretaris umum', 'bendahara umum' ];
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
