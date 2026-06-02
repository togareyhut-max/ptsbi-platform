<?php
/**
 * Tampilan pengurus di halaman wilayah / pusat.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Board_Display {

    /** @var array<string, bool> */
    private static $rendered = [];

    public function __construct() {
        add_shortcode( 'ptprm_board', [ $this, 'shortcode' ] );
        add_filter( 'the_content', [ $this, 'append_board_on_region_pages' ], 99 );
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function shortcode( $atts ): string {
        $atts   = shortcode_atts( [ 'region' => 'pusat' ], is_array( $atts ) ? $atts : [], 'ptprm_board' );
        $region = sanitize_key( (string) $atts['region'] );
        return self::render( $region );
    }

    public function append_board_on_region_pages( string $content ): string {
        if ( ! is_page() || ! class_exists( 'PTPRM_Board_Registry' ) ) {
            return $content;
        }

        $page_id = get_queried_object_id();
        if ( ! $page_id ) {
            return $content;
        }

        $region = PTPRM_Board_Registry::region_for_page_slug( (string) get_post_field( 'post_name', $page_id ) );
        if ( $region === null ) {
            return $content;
        }

        if ( self::content_has_board_entries( $content ) ) {
            return $content;
        }

        $content = self::strip_board_markup( $content );
        $html    = self::render( $region );
        if ( $html === '' ) {
            return $content;
        }

        return $content . $html;
    }

    /**
     * Dipanggil dari template sub-halaman — selalu coba tampilkan jika belum ada entri.
     */
    public static function render_for_current_page(): string {
        if ( ! is_page() || ! class_exists( 'PTPRM_Board_Registry' ) ) {
            return '';
        }
        $page_id = get_queried_object_id();
        if ( ! $page_id ) {
            return '';
        }
        $region = PTPRM_Board_Registry::region_for_page_slug( (string) get_post_field( 'post_name', $page_id ) );
        if ( $region === null ) {
            return '';
        }
        if ( ! empty( self::$rendered[ $region ] ) ) {
            return '';
        }
        return self::render( $region );
    }

    private static function content_has_board_entries( string $content ): bool {
        if ( strpos( $content, 'ptprm-board-line' ) !== false ) {
            return true;
        }
        if ( strpos( $content, 'ptprm-board-card-role' ) !== false ) {
            $name_pos = strpos( $content, 'ptprm-board-card-name' );
            if ( $name_pos !== false ) {
                $snippet = substr( $content, $name_pos, 400 );
                if ( is_string( $snippet ) && preg_match( '/ptprm-board-card-name">\s*[^<\s]/', $snippet ) ) {
                    return true;
                }
            }
        }
        // Shortcode lama: cangkang judul + daftar kosong dianggap belum ada entri.
        if ( strpos( $content, 'ptprm-board' ) !== false
            && preg_match( '/<div class="ptprm-board-list">\s*<\/div>/', $content ) ) {
            return false;
        }
        return false;
    }

    private static function strip_board_markup( string $content ): string {
        $needle = 'ptprm-board';
        while ( ( $pos = strpos( $content, $needle ) ) !== false ) {
            $start = strrpos( substr( $content, 0, $pos ), '<div' );
            if ( $start === false ) {
                break;
            }
            $depth = 0;
            $len   = strlen( $content );
            $end   = null;
            for ( $i = $start; $i < $len; $i++ ) {
                if ( substr( $content, $i, 4 ) === '<div' ) {
                    $depth++;
                    $i += 3;
                    continue;
                }
                if ( substr( $content, $i, 6 ) === '</div>' ) {
                    $depth--;
                    if ( $depth === 0 ) {
                        $end = $i + 6;
                        break;
                    }
                    $i += 5;
                }
            }
            if ( $end === null ) {
                break;
            }
            $content = substr( $content, 0, $start ) . substr( $content, $end );
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
        if ( $items === [] || ! PTPRM_Board_Registry::items_have_displayable_names( $items ) ) {
            return '';
        }

        ob_start();
        echo '<div class="ptprm-board ptprm-board--' . esc_attr( $region ) . '">';

        $board_title = trim( (string) ( $meta['title'] ?? '' ) );
        if ( $board_title !== '' ) {
            echo '<h2 class="ptprm-board-title">' . esc_html( $board_title ) . '</h2>';
        }

        if ( ! empty( $meta['has_featured_photos'] ) ) {
            self::render_featured_row( $region, $items );
        }

        self::render_list( $region, $items, ! empty( $meta['has_featured_photos'] ) );
        echo '</div>';

        $html = (string) ob_get_clean();
        if ( self::content_has_board_entries( $html ) ) {
            self::$rendered[ $region ] = true;
        }

        return $html;
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
            $role = trim( (string) ( $item['role'] ?? '' ) );
            $name = trim( (string) ( $item['name'] ?? '' ) );
            $img  = self::image_url( (string) ( $item['image'] ?? '' ) );
            echo '<article class="ptprm-board-card">';
            if ( $img !== '' ) {
                echo '<div class="ptprm-board-card-photo"><img src="' . esc_url( $img ) . '" alt="" loading="lazy"></div>';
            } else {
                echo '<div class="ptprm-board-card-photo ptprm-board-card-photo--placeholder" aria-hidden="true"></div>';
            }
            if ( $role !== '' ) {
                echo '<p class="ptprm-board-card-role">' . esc_html( $role ) . '</p>';
            }
            if ( $name !== '' ) {
                echo '<h3 class="ptprm-board-card-name">' . esc_html( $name ) . '</h3>';
            }
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
