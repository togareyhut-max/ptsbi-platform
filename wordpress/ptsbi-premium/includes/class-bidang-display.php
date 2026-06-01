<?php
/**
 * Halaman publik bidang (visi, misi, program, kegiatan, pengumuman).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Bidang_Display {

    public function __construct() {
        add_shortcode( 'ptprm_bidang', [ $this, 'shortcode_public' ] );
        add_filter( 'the_content', [ $this, 'inject_shortcode' ], 7 );
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function shortcode_public( $atts ): string {
        $atts = shortcode_atts( [ 'slug' => '' ], is_array( $atts ) ? $atts : [], 'ptprm_bidang' );
        return self::render_public( sanitize_key( (string) $atts['slug'] ) );
    }

    public function inject_shortcode( string $content ): string {
        if ( ! is_page() || ! in_the_loop() || ! is_main_query() || trim( wp_strip_all_tags( $content ) ) !== '' ) {
            return $content;
        }
        if ( ! class_exists( 'PTPRM_Bidang_Registry' ) ) {
            return $content;
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        foreach ( PTPRM_Bidang_Registry::bidangs() as $meta ) {
            if ( (string) $meta['page_slug'] === $slug ) {
                return '[ptprm_bidang slug="' . esc_attr( (string) $meta['slug'] ) . '"]';
            }
        }
        return $content;
    }

    public static function render_public( string $slug ): string {
        if ( ! class_exists( 'PTPRM_Bidang_Registry' ) || ! isset( PTPRM_Bidang_Registry::bidangs()[ $slug ] ) ) {
            return '';
        }
        $meta    = PTPRM_Bidang_Registry::bidangs()[ $slug ];
        $content = PTPRM_Bidang_Registry::get_content( $slug );

        ob_start();
        echo '<div class="ptprm-bidang-public ptprm-bidang-public--' . esc_attr( $slug ) . '">';
        echo '<h2 class="ptprm-bidang-title">' . esc_html( (string) $meta['title'] ) . '</h2>';

        if ( $content['visi'] !== '' ) {
            echo '<section class="ptprm-bidang-block"><h3>' . esc_html__( 'Visi', 'ptsbi-premium' ) . '</h3>';
            echo '<div class="ptprm-bidang-text">' . wp_kses_post( wpautop( $content['visi'] ) ) . '</div></section>';
        }
        if ( $content['misi'] !== '' ) {
            echo '<section class="ptprm-bidang-block"><h3>' . esc_html__( 'Misi', 'ptsbi-premium' ) . '</h3>';
            echo '<div class="ptprm-bidang-text">' . wp_kses_post( wpautop( $content['misi'] ) ) . '</div></section>';
        }
        if ( $content['program'] !== '' ) {
            echo '<section class="ptprm-bidang-block"><h3>' . esc_html__( 'Program Unggulan', 'ptsbi-premium' ) . '</h3>';
            echo '<div class="ptprm-bidang-text">' . wp_kses_post( wpautop( $content['program'] ) ) . '</div></section>';
        }

        self::render_posts_section( $slug, 'kegiatan', __( 'Berita & Kegiatan', 'ptsbi-premium' ) );
        self::render_posts_section( $slug, 'pengumuman', __( 'Pengumuman & Undangan', 'ptsbi-premium' ), true );
        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function render_posts_section( string $bidang_slug, string $type, string $heading, bool $gallery = false ): void {
        $cat_slug = PTPRM_Bidang_Registry::category_slug( $bidang_slug, $type );
        $term     = get_term_by( 'slug', $cat_slug, 'category' );
        if ( ! $term instanceof WP_Term ) {
            return;
        }
        $q = new WP_Query(
            [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $gallery ? 12 : 6,
                'cat'            => (int) $term->term_id,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );
        if ( ! $q->have_posts() ) {
            return;
        }
        echo '<section class="ptprm-bidang-block"><h3>' . esc_html( $heading ) . '</h3>';
        if ( $gallery ) {
            echo '<div class="ptprm-bidang-gallery">';
            while ( $q->have_posts() ) {
                $q->the_post();
                $thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
                echo '<figure class="ptprm-bidang-gallery-item">';
                if ( $thumb ) {
                    echo '<a href="' . esc_url( get_permalink() ) . '"><img src="' . esc_url( $thumb ) . '" alt=""></a>';
                }
                echo '<figcaption>' . esc_html( get_the_title() ) . '</figcaption></figure>';
            }
            echo '</div>';
        } else {
            echo '<ul class="ptprm-bidang-post-list">';
            while ( $q->have_posts() ) {
                $q->the_post();
                echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
                echo ' <time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time></li>';
            }
            echo '</ul>';
        }
        wp_reset_postdata();
        echo '</section>';
    }
}
