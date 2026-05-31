<?php
defined( 'ABSPATH' ) || exit;

class KontenKit_CPT {
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ], 5 );
	}

	public static function register(): void {
		$o = KontenKit_Settings::get();
		if ( ! empty( $o['cpt_portfolio'] ) ) {
			register_post_type( 'kk_portfolio', [
				'labels'       => [
					'name'          => __( 'Portfolio', 'kontenkit-core' ),
					'singular_name' => __( 'Portfolio', 'kontenkit-core' ),
					'add_new_item'  => __( 'Tambah Portfolio', 'kontenkit-core' ),
				],
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => [ 'slug' => 'portfolio' ],
				'menu_icon'    => 'dashicons-portfolio',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
				'show_in_rest' => true,
			] );
		}
		if ( ! empty( $o['cpt_testimonial'] ) ) {
			register_post_type( 'kk_testimonial', [
				'labels'       => [
					'name'          => __( 'Testimonial', 'kontenkit-core' ),
					'singular_name' => __( 'Testimonial', 'kontenkit-core' ),
					'add_new_item'  => __( 'Tambah Testimonial', 'kontenkit-core' ),
				],
				'public'       => true,
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'testimonial' ],
				'menu_icon'    => 'dashicons-format-quote',
				'supports'     => [ 'title', 'editor', 'thumbnail' ],
				'show_in_rest' => true,
			] );
			register_post_meta( 'kk_testimonial', '_kk_role', [
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			] );
		}
	}
}
