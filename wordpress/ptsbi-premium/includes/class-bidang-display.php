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
        self::render_program_section( $slug, $content );
        self::render_kegiatan_reports( $slug );
        self::render_undangan_button( $slug, $content );
        echo '</div>';
        return (string) ob_get_clean();
    }

    /**
     * @param array{visi:string,misi:string,program:string,program_items:array<int,array{judul:string,tujuan:string,sasaran:string,waktu:string}>,undangan_post_id:int} $content
     */
    private static function render_program_section( string $slug, array $content ): void {
        $items = (array) ( $content['program_items'] ?? [] );
        if ( $items === [] && (string) ( $content['program'] ?? '' ) === '' ) {
            return;
        }
        echo '<section class="ptprm-bidang-block"><h3>' . esc_html__( 'Program Unggulan', 'ptsbi-premium' ) . '</h3>';
        if ( $items !== [] ) {
            echo '<div class="ptprm-bidang-program-grid">';
            foreach ( $items as $it ) {
                if ( ! is_array( $it ) ) {
                    continue;
                }
                $judul  = (string) ( $it['judul'] ?? '' );
                $tujuan = (string) ( $it['tujuan'] ?? '' );
                $sas    = (string) ( $it['sasaran'] ?? '' );
                $waktu  = (string) ( $it['waktu'] ?? '' );
                echo '<article class="ptprm-bidang-program-card">';
                echo '<h4 class="ptprm-bidang-program-title">' . esc_html( $judul !== '' ? $judul : __( 'Program', 'ptsbi-premium' ) ) . '</h4>';
                if ( $waktu !== '' ) {
                    echo '<p class="ptprm-bidang-program-meta"><strong>' . esc_html__( 'Waktu', 'ptsbi-premium' ) . ':</strong> ' . esc_html( $waktu ) . '</p>';
                }
                if ( $tujuan !== '' ) {
                    echo '<p><strong>' . esc_html__( 'Tujuan', 'ptsbi-premium' ) . ':</strong> ' . esc_html( $tujuan ) . '</p>';
                }
                if ( $sas !== '' ) {
                    echo '<p><strong>' . esc_html__( 'Sasaran', 'ptsbi-premium' ) . ':</strong> ' . esc_html( $sas ) . '</p>';
                }
                echo '</article>';
            }
            echo '</div>';
        } else {
            echo '<div class="ptprm-bidang-text">' . wp_kses_post( wpautop( (string) $content['program'] ) ) . '</div>';
        }
        echo '</section>';
    }

    private static function render_kegiatan_reports( string $bidang_slug ): void {
        $cat_slug = PTPRM_Bidang_Registry::category_slug( $bidang_slug, 'kegiatan' );
        $term     = get_term_by( 'slug', $cat_slug, 'category' );
        if ( ! $term instanceof WP_Term ) {
            return;
        }
        $q = new WP_Query(
            [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 12,
                'cat'            => (int) $term->term_id,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );
        echo '<section class="ptprm-bidang-block"><h3>' . esc_html__( 'Laporan Kegiatan Terlaksana', 'ptsbi-premium' ) . '</h3>';
        if ( ! $q->have_posts() ) {
            echo '<p class="ptprm-bidang-muted">' . esc_html__( 'Belum ada laporan kegiatan yang dipublikasi.', 'ptsbi-premium' ) . '</p></section>';
            return;
        }
        echo '<ul class="ptprm-bidang-post-list ptprm-bidang-post-list--compact">';
        while ( $q->have_posts() ) {
            $q->the_post();
            echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
        }
        echo '</ul>';
        wp_reset_postdata();
        echo '</section>';
    }

    /**
     * @param array{undangan_post_id:int} $content
     */
    private static function render_undangan_button( string $bidang_slug, array $content ): void {
        $post_id = (int) ( $content['undangan_post_id'] ?? 0 );
        if ( $post_id <= 0 ) {
            $cat_slug = PTPRM_Bidang_Registry::category_slug( $bidang_slug, 'pengumuman' );
            $term     = get_term_by( 'slug', $cat_slug, 'category' );
            if ( $term instanceof WP_Term ) {
                $q = new WP_Query(
                    [
                        'post_type'      => 'post',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'cat'            => (int) $term->term_id,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        'fields'         => 'ids',
                    ]
                );
                if ( $q->have_posts() ) {
                    $ids = (array) $q->posts;
                    $post_id = (int) ( $ids[0] ?? 0 );
                }
                wp_reset_postdata();
            }
        }
        echo '<section class="ptprm-bidang-block"><h3>' . esc_html__( 'Pengumuman & Undangan', 'ptsbi-premium' ) . '</h3>';
        if ( $post_id <= 0 ) {
            echo '<p class="ptprm-bidang-muted">' . esc_html__( 'Belum ada pengumuman/undangan.', 'ptsbi-premium' ) . '</p></section>';
            return;
        }
        $title = get_the_title( $post_id );
        echo '<button type="button" class="ptprm-cta ptprm-cta-1 ptprm-bidang-undangan-btn" data-ptprm-modal-open="bidang-undangan" data-ptprm-post-id="' . esc_attr( (string) $post_id ) . '">';
        echo '<span class="ptprm-cta-label">' . esc_html__( 'Lihat Undangan / Pengumuman', 'ptsbi-premium' ) . '</span>';
        echo '</button>';
        echo '<p class="ptprm-bidang-muted" style="margin-top:.6rem;">' . esc_html( $title ) . '</p>';

        $thumb = get_the_post_thumbnail_url( $post_id, 'large' );
        $content_html = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
        $date = get_the_date( '', $post_id );

        echo '<div class="ptprm-modal" data-ptprm-modal="bidang-undangan" aria-hidden="true">';
        echo '<div class="ptprm-modal__backdrop" data-ptprm-modal-close></div>';
        echo '<div class="ptprm-modal__dialog" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Undangan / Pengumuman', 'ptsbi-premium' ) . '">';
        echo '<div class="ptprm-modal__header">';
        echo '<div class="ptprm-modal__title-wrap">';
        echo '<h4 class="ptprm-modal__title">' . esc_html( $title ) . '</h4>';
        if ( $date ) {
            echo '<div class="ptprm-modal__meta">' . esc_html( $date ) . '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="ptprm-modal__close" data-ptprm-modal-close aria-label="' . esc_attr__( 'Tutup', 'ptsbi-premium' ) . '">&times;</button>';
        echo '</div>';
        echo '<div class="ptprm-modal__body">';
        if ( $thumb ) {
            echo '<p><img class="ptprm-modal__poster" src="' . esc_url( $thumb ) . '" alt=""></p>';
        }
        echo '<div class="ptprm-modal__content">' . wp_kses_post( $content_html ) . '</div>';
        echo '</div>';
        echo '<div class="ptprm-modal__footer">';
        echo '<a class="ptprm-cta ptprm-cta-2 ptprm-cta-size-medium" href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" rel="noopener"><span class="ptprm-cta-label">' . esc_html__( 'Buka di tab baru', 'ptsbi-premium' ) . '</span></a>';
        echo '<button type="button" class="ptprm-cta ptprm-cta--ghost ptprm-cta-size-medium" data-ptprm-modal-close><span class="ptprm-cta-label">' . esc_html__( 'Tutup', 'ptsbi-premium' ) . '</span></button>';
        echo '</div>';
        echo '</div></div>';
        echo '</section>';
    }
}
