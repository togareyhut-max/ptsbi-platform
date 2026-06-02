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
                if ( class_exists( 'PTPRM_Board_Display' ) ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo PTPRM_Board_Display::render_for_current_page();
                }
            }
            ?>
        </div>
    </div>
</main>

<?php
get_footer();
