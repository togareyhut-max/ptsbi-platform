<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lapisan universal — setiap install WP punya pengaturan sendiri (bukan ptsbi.org).
 */
class KontenKit_Universal {
	public const FILTER_APPLY = 'kontenkit_apply_content_filters';
	public const FILTER_POST_TYPES = 'kontenkit_content_post_types';

	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'maybe_import_legacy' ] );
	}

	/** Post types yang mendapat meta box, TOC, share, dll. */
	public static function content_post_types(): array {
		$o     = KontenKit_Settings::get();
		$raw   = (string) ( $o['content_post_types'] ?? 'post,page' );
		$types = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		$types = array_values( array_unique( array_map( 'sanitize_key', $types ) ) );
		foreach ( $types as $pt ) {
			if ( ! post_type_exists( $pt ) || ! is_post_type_viewable( $pt ) ) {
				continue;
			}
		}
		$types = array_filter( $types, static fn ( $pt ) => post_type_exists( $pt ) && is_post_type_viewable( $pt ) );
		if ( ! $types ) {
			$types = [ 'post' ];
		}
		return apply_filters( self::FILTER_POST_TYPES, $types );
	}

	public static function is_content_singular( ?string $post_type = null ): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$pt = $post_type ?? get_post_type();
		return $pt && in_array( $pt, self::content_post_types(), true );
	}

	public static function in_main_content_loop(): bool {
		return in_the_loop() && is_main_query();
	}

	public static function should_apply_content_filters(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return false;
		}
		return (bool) apply_filters( self::FILTER_APPLY, true );
	}

	public static function site_name(): string {
		$o = KontenKit_Settings::get();
		$custom = trim( (string) ( $o['site_display_name'] ?? '' ) );
		return $custom !== '' ? $custom : (string) get_bloginfo( 'name' );
	}

	public static function home_label(): string {
		$o = KontenKit_Settings::get();
		$custom = trim( (string) ( $o['breadcrumb_home_label'] ?? '' ) );
		return $custom !== '' ? $custom : __( 'Beranda', 'kontenkit-core' );
	}

	public static function publisher_name(): string {
		$o = KontenKit_Settings::get();
		$custom = trim( (string) ( $o['publisher_name'] ?? '' ) );
		return $custom !== '' ? $custom : self::site_name();
	}

	public static function publisher_logo_url(): string {
		$o = KontenKit_Settings::get();
		$custom = esc_url( (string) ( $o['publisher_logo_url'] ?? '' ) );
		if ( $custom ) {
			return $custom;
		}
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}

	/** Warna brand: setting plugin → Customizer → default netral. */
	public static function brand_color(): string {
		$o = KontenKit_Settings::get();
		if ( ! empty( $o['auto_sync_wp_identity'] ) ) {
			$from_theme = get_theme_mod( 'kontenkit_brand_color' );
			if ( ! $from_theme ) {
				$from_theme = get_theme_mod( 'primary_color' );
			}
			if ( $from_theme && sanitize_hex_color( $from_theme ) ) {
				return sanitize_hex_color( $from_theme );
			}
		}
		$c = sanitize_hex_color( $o['brand_color'] ?? '' );
		return $c ?: '#2563eb';
	}

	public static function accent_color(): string {
		$o = KontenKit_Settings::get();
		if ( ! empty( $o['auto_sync_wp_identity'] ) ) {
			$from_theme = get_theme_mod( 'kontenkit_accent_color' );
			if ( ! $from_theme ) {
				$from_theme = get_theme_mod( 'accent_color' );
			}
			if ( $from_theme && sanitize_hex_color( $from_theme ) ) {
				return sanitize_hex_color( $from_theme );
			}
		}
		$c = sanitize_hex_color( $o['accent_color'] ?? '' );
		return $c ?: '#f59e0b';
	}

	/**
	 * Impor pengaturan dari opsi Bloggingpro / tema (sekali per site).
	 */
	public static function import_legacy_options( bool $force = false ): array {
		$flag = get_option( 'kontenkit_legacy_import_done' );
		if ( $flag && ! $force ) {
			return [ 'skipped' => true, 'message' => __( 'Impor sudah pernah dilakukan.', 'kontenkit-core' ) ];
		}

		$current = KontenKit_Settings::get();
		$merged  = $current;
		$sources = [];

		$candidates = [
			'bloggingpro_options',
			'bloggingpro_settings',
			'bloggingpro_theme_options',
			'bloggingpro_core_options',
			'ptsbi_theme_options',
			'ptsbi_options',
		];

		foreach ( $candidates as $key ) {
			$val = get_option( $key );
			if ( is_array( $val ) && $val ) {
				$sources[] = $key;
				$merged = self::map_legacy_array( $merged, $val );
			}
		}

		$stylesheet = get_stylesheet();
		$mods       = get_option( 'theme_mods_' . $stylesheet );
		if ( is_array( $mods ) ) {
			$sources[] = 'theme_mods_' . $stylesheet;
			$merged    = self::map_legacy_array( $merged, $mods );
		}

		// Jangan bawa license / domain lock.
		unset( $merged['license_key'], $merged['license'], $merged['domain'] );

		update_option( KontenKit_Settings::OPTION, KontenKit_Settings::sanitize_static( $merged ) );
		update_option( 'kontenkit_legacy_import_done', gmdate( 'c' ) );

		return [
			'ok'      => true,
			'sources' => $sources,
			'message' => $sources
				? sprintf(
					/* translators: %s: comma-separated option names */
					__( 'Impor dari: %s', 'kontenkit-core' ),
					implode( ', ', $sources )
				)
				: __( 'Tidak ada opsi Bloggingpro/PTSBI di database situs ini — pakai pengaturan default situs.', 'kontenkit-core' ),
		];
	}

	public static function maybe_import_legacy(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['kontenkit_import_legacy'] ) && $_GET['kontenkit_import_legacy'] === '1' ) {
			check_admin_referer( 'kontenkit_import_legacy' );
			$result = self::import_legacy_options( true );
			set_transient( 'kontenkit_admin_notice', $result['message'], 30 );
			wp_safe_redirect( admin_url( 'admin.php?page=kontenkit-core' ) );
			exit;
		}
		if ( isset( $_GET['kontenkit_reset_site'] ) && $_GET['kontenkit_reset_site'] === '1' ) {
			check_admin_referer( 'kontenkit_reset_site' );
			update_option( KontenKit_Settings::OPTION, KontenKit_Settings::defaults() );
			delete_option( 'kontenkit_legacy_import_done' );
			set_transient( 'kontenkit_admin_notice', __( 'Pengaturan direset ke default universal.', 'kontenkit-core' ), 30 );
			wp_safe_redirect( admin_url( 'admin.php?page=kontenkit-core' ) );
			exit;
		}
		$msg = get_transient( 'kontenkit_admin_notice' );
		if ( $msg ) {
			delete_transient( 'kontenkit_admin_notice' );
			add_action( 'admin_notices', static function () use ( $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			} );
		}
	}

	private static function map_legacy_array( array $out, array $legacy ): array {
		$map = [
			'social_share'      => [ 'social_share', 'enable_share', 'share_buttons' ],
			'author_box'        => [ 'author_box', 'enable_author_box' ],
			'related_posts'     => [ 'related_posts', 'enable_related' ],
			'breadcrumbs'       => [ 'breadcrumbs', 'enable_breadcrumb' ],
			'reading_time'      => [ 'reading_time', 'enable_reading_time' ],
			'table_of_contents' => [ 'table_of_contents', 'enable_toc', 'toc' ],
			'og_meta'           => [ 'og_meta', 'open_graph', 'enable_og' ],
			'brand_color'       => [ 'brand_color', 'primary_color', 'color_primary' ],
			'accent_color'      => [ 'accent_color', 'secondary_color', 'color_accent' ],
			'excerpt_length'    => [ 'excerpt_length', 'excerpt_words' ],
		];

		foreach ( $map as $kk_key => $legacy_keys ) {
			foreach ( $legacy_keys as $lk ) {
				if ( ! array_key_exists( $lk, $legacy ) ) {
					continue;
				}
				$val = $legacy[ $lk ];
				if ( in_array( $kk_key, [ 'brand_color', 'accent_color' ], true ) && is_string( $val ) ) {
					$c = sanitize_hex_color( $val );
					if ( $c ) {
						$out[ $kk_key ] = $c;
					}
				} elseif ( $kk_key === 'excerpt_length' && is_numeric( $val ) ) {
					$out[ $kk_key ] = (int) $val;
				} elseif ( in_array( $kk_key, [ 'social_share', 'author_box', 'related_posts', 'breadcrumbs', 'reading_time', 'table_of_contents', 'og_meta' ], true ) ) {
					$out[ $kk_key ] = filter_var( $val, FILTER_VALIDATE_BOOLEAN ) || (int) $val === 1 ? 1 : 0;
				}
				break;
			}
		}
		return $out;
	}

	/** Taxonomies untuk artikel terkait (post = category, lain = first public tax). */
	public static function related_tax_query( int $post_id ): array {
		$pt = get_post_type( $post_id );
		if ( $pt === 'post' ) {
			$cats = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
			return $cats ? [ 'category__in' => $cats ] : [];
		}
		$taxes = get_object_taxonomies( $pt, 'objects' );
		foreach ( $taxes as $tax ) {
			if ( ! $tax->public || ! $tax->show_ui ) {
				continue;
			}
			$terms = wp_get_object_terms( $post_id, $tax->name, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) && $terms ) {
				return [
					'tax_query' => [
						[
							'taxonomy' => $tax->name,
							'field'    => 'term_id',
							'terms'    => $terms,
						],
					],
				];
			}
		}
		return [];
	}
}
