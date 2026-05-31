<?php
defined( 'ABSPATH' ) || exit;

class KontenKit_Settings {
	public const OPTION = 'kontenkit_core_options';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'update_option_' . self::OPTION, [ __CLASS__, 'after_save' ], 10, 2 );
	}

	public static function after_save( $old, $new ): void {
		if ( is_array( $old ) && is_array( $new ) ) {
			if ( ( $old['cpt_portfolio'] ?? 0 ) !== ( $new['cpt_portfolio'] ?? 0 )
				|| ( $old['cpt_testimonial'] ?? 0 ) !== ( $new['cpt_testimonial'] ?? 0 ) ) {
				flush_rewrite_rules();
			}
		}
	}

	public static function activate(): void {
		if ( ! get_option( self::OPTION ) ) {
			update_option( self::OPTION, self::defaults() );
		}
		flush_rewrite_rules();
	}

	public static function defaults(): array {
		return [
			'site_display_name'   => '',
			'breadcrumb_home_label' => '',
			'publisher_name'      => '',
			'publisher_logo_url'  => '',
			'content_post_types'  => 'post,page',
			'auto_sync_wp_identity' => 1,
			'excerpt_only_archives' => 1,
			'brand_color'       => '#2563eb',
			'accent_color'      => '#f59e0b',
			'custom_css'        => '',
			'social_share'      => 1,
			'sticky_share'      => 1,
			'author_box'        => 1,
			'related_posts'     => 1,
			'related_count'     => 4,
			'related_grid'      => 1,
			'breadcrumbs'       => 1,
			'excerpt_length'    => 28,
			'reading_time'      => 1,
			'post_meta_bar'     => 1,
			'post_views'        => 1,
			'table_of_contents' => 1,
			'toc_min_headings'  => 3,
			'newsletter'        => 1,
			'newsletter_title'  => 'Berlangganan newsletter',
			'newsletter_text'   => 'Dapatkan artikel terbaru langsung ke email Anda.',
			'newsletter_btn'    => 'Langganan',
			'og_meta'           => 1,
			'schema_article'    => 1,
			'cpt_portfolio'     => 1,
			'cpt_testimonial'   => 1,
			'maintenance'       => 0,
			'maintenance_msg'   => 'Situs sedang pemeliharaan. Silakan kembali lagi nanti.',
		];
	}

	public static function get(): array {
		return wp_parse_args( (array) get_option( self::OPTION, [] ), self::defaults() );
	}

	public static function menu(): void {
		add_menu_page(
			__( 'KontenKit', 'kontenkit-core' ),
			__( 'KontenKit', 'kontenkit-core' ),
			'manage_options',
			'kontenkit-core',
			[ __CLASS__, 'render' ],
			'dashicons-star-filled',
			58
		);
	}

	public static function register(): void {
		register_setting( 'kontenkit_core', self::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
			'default'           => self::defaults(),
		] );
	}

	private static function bool( $v ): int {
		return empty( $v ) ? 0 : 1;
	}

	public static function sanitize( $input ): array {
		return self::sanitize_static( is_array( $input ) ? $input : [] );
	}

	public static function sanitize_static( array $input ): array {
		$d   = self::defaults();
		$out = $d;
		$out['site_display_name']    = sanitize_text_field( $input['site_display_name'] ?? $d['site_display_name'] );
		$out['breadcrumb_home_label'] = sanitize_text_field( $input['breadcrumb_home_label'] ?? $d['breadcrumb_home_label'] );
		$out['publisher_name']       = sanitize_text_field( $input['publisher_name'] ?? $d['publisher_name'] );
		$out['publisher_logo_url']   = esc_url_raw( $input['publisher_logo_url'] ?? $d['publisher_logo_url'] );
		$pts = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) ( $input['content_post_types'] ?? $d['content_post_types'] ) ) ) ) );
		$out['content_post_types']   = $pts ? implode( ',', $pts ) : 'post,page';
		$out['auto_sync_wp_identity'] = self::bool( $input['auto_sync_wp_identity'] ?? $d['auto_sync_wp_identity'] );
		$out['excerpt_only_archives'] = self::bool( $input['excerpt_only_archives'] ?? $d['excerpt_only_archives'] );
		$out['brand_color']  = sanitize_hex_color( $input['brand_color'] ?? $d['brand_color'] ) ?: $d['brand_color'];
		$out['accent_color']  = sanitize_hex_color( $input['accent_color'] ?? $d['accent_color'] ) ?: $d['accent_color'];
		$out['custom_css']    = wp_strip_all_tags( (string) ( $input['custom_css'] ?? '' ) );
		$out['social_share']  = self::bool( $input['social_share'] ?? 0 );
		$out['sticky_share']  = self::bool( $input['sticky_share'] ?? 0 );
		$out['author_box']    = self::bool( $input['author_box'] ?? 0 );
		$out['related_posts'] = self::bool( $input['related_posts'] ?? 0 );
		$out['related_grid']  = self::bool( $input['related_grid'] ?? 0 );
		$out['breadcrumbs']   = self::bool( $input['breadcrumbs'] ?? 0 );
		$out['reading_time']  = self::bool( $input['reading_time'] ?? 0 );
		$out['post_meta_bar'] = self::bool( $input['post_meta_bar'] ?? 0 );
		$out['post_views']    = self::bool( $input['post_views'] ?? 0 );
		$out['table_of_contents'] = self::bool( $input['table_of_contents'] ?? 0 );
		$out['newsletter']    = self::bool( $input['newsletter'] ?? 0 );
		$out['og_meta']       = self::bool( $input['og_meta'] ?? 0 );
		$out['schema_article'] = self::bool( $input['schema_article'] ?? 0 );
		$out['cpt_portfolio'] = self::bool( $input['cpt_portfolio'] ?? 0 );
		$out['cpt_testimonial'] = self::bool( $input['cpt_testimonial'] ?? 0 );
		$out['maintenance']   = self::bool( $input['maintenance'] ?? 0 );
		$out['related_count']  = max( 2, min( 12, (int) ( $input['related_count'] ?? 4 ) ) );
		$out['excerpt_length'] = max( 10, min( 80, (int) ( $input['excerpt_length'] ?? 28 ) ) );
		$out['toc_min_headings'] = max( 2, min( 8, (int) ( $input['toc_min_headings'] ?? 3 ) ) );
		$out['newsletter_title'] = sanitize_text_field( $input['newsletter_title'] ?? $d['newsletter_title'] );
		$out['newsletter_text']  = sanitize_textarea_field( $input['newsletter_text'] ?? $d['newsletter_text'] );
		$out['newsletter_btn']   = sanitize_text_field( $input['newsletter_btn'] ?? $d['newsletter_btn'] );
		$out['maintenance_msg']  = sanitize_textarea_field( $input['maintenance_msg'] ?? $d['maintenance_msg'] );
		return $out;
	}

	public static function assets( string $hook ): void {
		if ( strpos( $hook, 'kontenkit' ) === false ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'kontenkit-admin', KONTENKIT_CORE_URL . 'assets/admin.css', [], KONTENKIT_CORE_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'kontenkit-admin', KONTENKIT_CORE_URL . 'assets/admin.js', [ 'jquery', 'wp-color-picker' ], KONTENKIT_CORE_VERSION, true );
	}

	public static function render(): void {
		require KONTENKIT_CORE_DIR . 'templates/admin-dashboard.php';
	}
}
