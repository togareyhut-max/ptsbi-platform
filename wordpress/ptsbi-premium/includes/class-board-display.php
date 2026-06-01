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
        add_filter( 'the_content', [ $this, 'inject_board_shortcode' ], 8 );
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function shortcode( $atts ): string {
        $atts   = shortcode_atts( [ 'region' => 'pusat' ], is_array( $atts ) ? $atts : [], 'ptprm_board' );
        $region = sanitize_key( (string) $atts['region'] );
        return self::render( $region );
    }

    public function inject_board_shortcode( string $content ): string {
        if ( ! is_page() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        if ( trim( wp_strip_all_tags( $content ) ) !== '' ) {
            return $content;
        }
        if ( ! class_exists( 'PTPRM_Board_Registry' ) ) {
            return $content;
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        foreach ( PTPRM_Board_Registry::regions() as $meta ) {
            if ( (string) $meta['page_slug'] === $slug ) {
                return '[ptprm_board region="' . esc_attr( (string) $meta['slug'] ) . '"]';
            }
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
        echo '<h2 class="ptprm-board-title">' . esc_html( (string) $meta['title'] ) . '</h2>';

        if ( ! empty( $meta['has_featured_photos'] ) ) {
            self::render_featured_row( $region, $items );
        }

        self::render_list( $region, $items, ! empty( $meta['has_featured_photos'] ) );
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
            $url = wp_get_attachment_image_url( (int) $image, 'medium' );
            return $url ? (string) $url : '';
        }
        $url = esc_url_raw( $image );
        return $url !== '' ? $url : '';
    }
}
