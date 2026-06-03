<?php
/**
 * Tampilan pengurus di halaman wilayah / pusat.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Board_Display {

    public function __construct() {
        add_shortcode( 'ptprm_board', [ $this, 'shortcode' ] );
        add_filter( 'the_content', [ $this, 'append_board_on_region_pages' ], 25 );
        add_action( 'wp_footer', [ __CLASS__, 'render_region_board_in_footer' ], 8 );
    }

    /** Cadangan jika template/konten tidak memproses shortcode (cache / kutip melengkung). */
    public static function render_region_board_in_footer(): void {
        if ( ! is_page() || ! class_exists( 'PTPRM_Board_Registry' ) ) {
            return;
        }
        static $done = false;
        if ( $done ) {
            return;
        }
        global $post;
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        $page_slug = (string) $post->post_name;
        foreach ( PTPRM_Board_Registry::regions() as $meta ) {
            if ( (string) $meta['page_slug'] !== $page_slug ) {
                continue;
            }
            $html = self::render( (string) $meta['slug'] );
            if ( $html !== '' ) {
                echo '<div class="ptprm-board-footer-fallback">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                $done = true;
            }
            break;
        }
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function shortcode( $atts ): string {
        $atts   = shortcode_atts( [ 'region' => 'pusat' ], is_array( $atts ) ? $atts : [], 'ptprm_board' );
        $region = sanitize_key( (string) $atts['region'] );
        return self::render( $region );
    }

    /**
     * Selalu tampilkan daftar pengurus di halaman wilayah meski konten halaman sudah berisi judul/blok lain.
     */
    public function append_board_on_region_pages( string $content ): string {
        if ( ! is_singular( 'page' ) ) {
            return $content;
        }
        if ( ! class_exists( 'PTPRM_Board_Registry' ) ) {
            return $content;
        }

        $page_slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        foreach ( PTPRM_Board_Registry::regions() as $meta ) {
            if ( (string) $meta['page_slug'] !== $page_slug ) {
                continue;
            }

            if ( strpos( $content, 'ptprm-board' ) !== false ) {
                return $content;
            }

            $working = $content;
            if ( strpos( $working, '[ptprm_board' ) !== false ) {
                $normalized = str_replace(
                    [ "\u{201C}", "\u{201D}", "\u{201E}", "\u{00AB}", "\u{00BB}", '&#8220;', '&#8221;', '&#8243;' ],
                    '"',
                    $working
                );
                $working = do_shortcode( $normalized );
            }

            if ( strpos( $working, 'ptprm-board' ) !== false ) {
                return $working;
            }

            $html = self::render( (string) $meta['slug'] );
            if ( $html === '' ) {
                return $working;
            }

            $working = preg_replace( '/<p>\s*\[ptprm_board[^\]]*\]\s*<\/p>/iu', '', $working ) ?? $working;

            return $working . $html;
        }

        return $content;
    }

    public static function render( string $region ): string {
        if ( ! class_exists( 'PTPRM_Board_Registry' ) ) {
            return '';
        }
        $regions = PTPRM_Board_Registry::regions();
        $region  = sanitize_key( $region );
        if ( ! isset( $regions[ $region ] ) ) {
            return '';
        }
        $meta  = $regions[ $region ];
        $items = PTPRM_Board_Registry::get_items( $region );

        ob_start();
        echo '<div class="ptprm-board ptprm-board--' . esc_attr( $region ) . '">';

        if ( ! empty( $meta['has_featured_photos'] ) ) {
            self::render_featured_row( $region, $items );
        }

        self::render_list( $region, $items, ! empty( $meta['has_featured_photos'] ) );

        if ( ! $items ) {
            echo '<p class="ptprm-portal-help">' . esc_html__( 'Daftar pengurus belum diisi. Admin dapat mengelolanya di Panel Pengurus → Pengurus Wilayah.', 'ptsbi-premium' ) . '</p>';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private static function render_featured_row( string $region, array $items ): void {
        $featured = [];
        foreach ( $items as $item ) {
            if ( PTPRM_Board_Registry::is_featured_item( $region, $item ) ) {
                $featured[] = $item;
            }
        }
        if ( ! $featured ) {
            return;
        }
        echo '<div class="ptprm-board-featured">';
        foreach ( $featured as $item ) {
            $img = self::image_url( (string) ( $item['image'] ?? '' ) );
            echo '<article class="ptprm-board-card">';
            if ( $img !== '' ) {
                echo '<div class="ptprm-board-card-photo"><img src="' . esc_url( $img ) . '" alt="" loading="lazy"></div>';
            } else {
                echo '<div class="ptprm-board-card-photo ptprm-board-card-photo--placeholder" aria-hidden="true"></div>';
            }
            echo '<h3 class="ptprm-board-card-name">' . esc_html( (string) ( $item['name'] ?? '' ) ) . '</h3>';
            echo '<p class="ptprm-board-card-role">' . esc_html( (string) ( $item['role'] ?? '' ) ) . '</p>';
            echo '</article>';
        }
        echo '</div>';
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private static function render_list( string $region, array $items, bool $skip_featured ): void {
        $current_group = '';
        echo '<div class="ptprm-board-list">';
        foreach ( $items as $item ) {
            if ( $skip_featured && PTPRM_Board_Registry::is_featured_item( $region, $item ) ) {
                continue;
            }
            $group = trim( (string) ( $item['group'] ?? '' ) );
            if ( $group !== '' && $group !== $current_group ) {
                $current_group = $group;
                echo '<h3 class="ptprm-board-group">' . esc_html( $group ) . '</h3>';
            }
            $role = trim( (string) ( $item['role'] ?? '' ) );
            $name = trim( (string) ( $item['name'] ?? '' ) );
            if ( $name === '' ) {
                continue;
            }
            echo '<p class="ptprm-board-line">';
            if ( $role !== '' ) {
                echo '<strong>' . esc_html( $role ) . '</strong>: ';
            }
            echo esc_html( $name );
            echo '</p>';
        }
        echo '</div>';
    }

    private static function image_url( string $image ): string {
        if ( $image === '' ) {
            return '';
        }
        if ( is_numeric( $image ) ) {
            $url = wp_get_attachment_image_url( (int) $image, 'medium_large' );
            return $url ? (string) $url : '';
        }
        $url = esc_url_raw( $image );
        return $url !== '' ? $url : '';
    }
}
