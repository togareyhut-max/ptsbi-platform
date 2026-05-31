<?php
/**
 * Template: Homepage (rendered by Premium Plugin).
 * Theme header & footer tetap dipanggil supaya menu/script tema utuh.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header(); ?>

<main id="ptprm-home-main" class="ptprm-home-main" role="main">
    <?php PTPRM_Renderer::home(); ?>
</main>

<?php
get_footer();
