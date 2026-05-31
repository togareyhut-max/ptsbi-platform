<?php
/**
 * Frontend hooks: body class + small meta data island for the JS detector.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Frontend {

    public function __construct() {
        add_filter( 'body_class', [ $this, 'body_class' ] );
        add_action( 'wp_head',    [ $this, 'print_meta' ], 99 );
    }

    public function body_class( $classes ) {
        if ( ! ptsbi_pe_is_enabled() ) {
            return $classes;
        }
        $classes[] = 'studio-active';

        if ( is_front_page() || is_home() ) {
            $classes[] = 'studio-home';
        }
        if ( is_page() ) {
            $slug = get_post_field( 'post_name', get_queried_object_id() );
            if ( $slug ) {
                $classes[] = 'studio-page-' . sanitize_html_class( $slug );
            }
        }

        return $classes;
    }

    public function print_meta() {
        if ( ! ptsbi_pe_is_enabled() ) return;

        $meta = [
            'isHome' => ( is_front_page() || is_home() ),
            'isPage' => is_page(),
            'slug'   => is_page() ? get_post_field( 'post_name', get_queried_object_id() ) : '',
            'title'  => is_page() ? get_the_title( get_queried_object_id() ) : '',
        ];
        echo '<script type="application/json" id="studio-pe-page-meta">' . wp_json_encode( $meta ) . '</script>';
    }
}
