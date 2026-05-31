<?php
defined( 'ABSPATH' ) || exit;

class KontenKit_SEO {
	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'og_tags' ], 5 );
		add_action( 'wp_head', [ __CLASS__, 'schema' ], 20 );
	}

	private static function opts(): array {
		return KontenKit_Settings::get();
	}

	public static function og_tags(): void {
		if ( empty( self::opts()['og_meta'] ) || ! is_singular() ) {
			return;
		}
		$id    = get_queried_object_id();
		$title = wp_get_document_title();
		$desc  = get_the_excerpt( $id ) ?: get_bloginfo( 'description' );
		$url   = get_permalink( $id );
		$img   = get_the_post_thumbnail_url( $id, 'large' ) ?: '';
		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		if ( $img ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . '" />' . "\n";
		}
	}

	public static function schema(): void {
		if ( empty( self::opts()['schema_article'] ) || ! is_singular( 'post' ) ) {
			return;
		}
		$data = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => get_the_title(),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'author'           => [
				'@type' => 'Person',
				'name'  => get_the_author(),
			],
			'publisher'        => [
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			],
			'mainEntityOfPage' => get_permalink(),
		];
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
