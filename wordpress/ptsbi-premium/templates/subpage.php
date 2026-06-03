<?php
/**
 * Template: Sub-halaman (dengan hero premium + wrapper konten).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header(); ?>

<main id="ptprm-subpage-main" class="ptprm-subpage-main" role="main">
    <?php
    PTPRM_Renderer::subpage_hero();
    ?>
    <div class="ptprm-subpage-content">
        <div class="ptprm-container">
            <?php
            while ( have_posts() ) {
                the_post();
                the_content();
                if ( class_exists( 'PTPRM_Board_Display' ) && class_exists( 'PTPRM_Board_Registry' ) ) {
                    $page_slug = (string) get_post_field( 'post_name', get_the_ID() );
                    foreach ( PTPRM_Board_Registry::regions() as $meta ) {
                        if ( (string) $meta['page_slug'] !== $page_slug ) {
                            continue;
                        }
                        $board_html = PTPRM_Board_Display::render( (string) $meta['slug'] );
                        if ( $board_html !== '' ) {
                            echo $board_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        break;
                    }
                }
            }
            ?>
        </div>
    </div>
</main>

<?php
get_footer();
// deploy marker 20260603
