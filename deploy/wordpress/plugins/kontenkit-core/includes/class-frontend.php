<?php
defined( 'ABSPATH' ) || exit;

class KontenKit_Frontend {
	public static function init(): void {
		add_filter( 'excerpt_length', [ __CLASS__, 'excerpt_length' ], 20 );
		add_filter( 'the_content', [ __CLASS__, 'inject_single_header' ], 4 );
		add_filter( 'the_content', [ __CLASS__, 'subtitle_before_content' ], 5 );
		add_filter( 'the_content', [ __CLASS__, 'table_of_contents' ], 12 );
		add_filter( 'the_content', [ __CLASS__, 'append_single_extras' ], 25 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'wp_head', [ __CLASS__, 'inline_brand_css' ], 99 );
		add_action( 'loop_start', [ __CLASS__, 'maybe_breadcrumbs' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maintenance' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'count_view' ], 5 );
		add_action( 'wp_footer', [ __CLASS__, 'sticky_share' ] );
	}

	private static function opts(): array {
		return KontenKit_Settings::get();
	}

	public static function maintenance(): void {
		$o = self::opts();
		if ( empty( $o['maintenance'] ) || current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_die(
			'<div class="kk-maintenance"><h1>' . esc_html__( 'Pemeliharaan', 'kontenkit-core' ) . '</h1><p>' . esc_html( $o['maintenance_msg'] ) . '</p></div>',
			esc_html__( 'Pemeliharaan', 'kontenkit-core' ),
			[ 'response' => 503 ]
		);
	}

	public static function count_view(): void {
		$o = self::opts();
		if ( empty( $o['post_views'] ) || ! is_singular( 'post' ) || is_preview() ) {
			return;
		}
		$id = get_queried_object_id();
		if ( ! $id ) {
			return;
		}
		$views = (int) get_post_meta( $id, '_kk_views', true );
		update_post_meta( $id, '_kk_views', $views + 1 );
	}

	public static function assets(): void {
		wp_enqueue_style( 'kontenkit-core', KONTENKIT_CORE_URL . 'assets/frontend.css', [], KONTENKIT_CORE_VERSION );
		$css = trim( (string) self::opts()['custom_css'] );
		if ( $css ) {
			wp_add_inline_style( 'kontenkit-core', $css );
		}
	}

	public static function inline_brand_css(): void {
		$o = self::opts();
		echo '<style id="kontenkit-brand">:root{--kk-brand:' . esc_attr( $o['brand_color'] ) . ';--kk-accent:' . esc_attr( $o['accent_color'] ) . ';}</style>' . "\n";
	}

	public static function excerpt_length( int $length ): int {
		return (int) self::opts()['excerpt_length'];
	}

	public static function inject_single_header( string $content ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$o = self::opts();
		$out = '';
		$label = get_post_meta( get_the_ID(), '_kk_label', true );
		if ( $label ) {
			$out .= '<span class="kk-label">' . esc_html( $label ) . '</span>';
		}
		if ( ! empty( $o['post_meta_bar'] ) ) {
			$out .= self::meta_bar();
		}
		$video = get_post_meta( get_the_ID(), '_kk_video_url', true );
		if ( $video && preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{6,})/', $video, $m ) ) {
			$out .= '<div class="kk-video kk-video--featured"><iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $m[1] ) . '" loading="lazy" allowfullscreen></iframe></div>';
		}
		return $out ? $out . $content : $content;
	}

	private static function meta_bar(): string {
		$id    = get_the_ID();
		$parts = [
			'<time datetime="' . esc_attr( get_the_date( 'c', $id ) ) . '">' . esc_html( get_the_date( '', $id ) ) . '</time>',
			'<span class="kk-meta__author">' . esc_html( get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $id ) ) ) . '</span>',
		];
		$o = self::opts();
		if ( ! empty( $o['reading_time'] ) ) {
			$parts[] = '<span class="kk-meta__read">' . esc_html( self::reading_time( $id ) ) . '</span>';
		}
		if ( ! empty( $o['post_views'] ) ) {
			$v = (int) get_post_meta( $id, '_kk_views', true );
			$parts[] = '<span class="kk-meta__views">' . esc_html( sprintf( _n( '%s view', '%s views', $v, 'kontenkit-core' ), number_format_i18n( $v ) ) ) . '</span>';
		}
		return '<div class="kk-meta-bar">' . implode( '<span class="kk-meta__sep">·</span>', $parts ) . '</div>';
	}

	private static function reading_time( int $post_id ): string {
		$words = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
		$min   = max( 1, (int) ceil( $words / 200 ) );
		return sprintf( _n( '%d menit baca', '%d menit baca', $min, 'kontenkit-core' ), $min );
	}

	public static function subtitle_before_content( string $content ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$sub = get_post_meta( get_the_ID(), '_kk_subtitle', true );
		if ( ! $sub ) {
			return $content;
		}
		return '<p class="kk-subtitle">' . esc_html( $sub ) . '</p>' . $content;
	}

	public static function table_of_contents( string $content ): string {
		$o = self::opts();
		if ( empty( $o['table_of_contents'] ) || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! preg_match_all( '/<h([2-3])[^>]*>(.*?)<\/h\1>/is', $content, $heads, PREG_SET_ORDER ) ) {
			return $content;
		}
		$min = (int) $o['toc_min_headings'];
		if ( count( $heads ) < $min ) {
			return $content;
		}
		$toc  = '<nav class="kk-toc" aria-label="' . esc_attr__( 'Daftar isi', 'kontenkit-core' ) . '"><p class="kk-toc__title">' . esc_html__( 'Daftar isi', 'kontenkit-core' ) . '</p><ol>';
		$used = [];
		foreach ( $heads as $i => $h ) {
			$text = wp_strip_all_tags( $h[2] );
			$slug = sanitize_title( $text );
			if ( isset( $used[ $slug ] ) ) {
				$used[ $slug ]++;
				$slug .= '-' . $used[ $slug ];
			} else {
				$used[ $slug ] = 0;
			}
			$id = 'kk-' . $slug;
			$toc .= '<li class="kk-toc__h' . esc_attr( $h[1] ) . '"><a href="#' . esc_attr( $id ) . '">' . esc_html( $text ) . '</a></li>';
			$content = preg_replace(
				'/' . preg_quote( $h[0], '/' ) . '/',
				'<h' . $h[1] . ' id="' . esc_attr( $id ) . '">' . $h[2] . '</h' . $h[1] . '>',
				$content,
				1
			);
		}
		$toc .= '</ol></nav>';
		return $toc . $content;
	}

	public static function maybe_breadcrumbs(): void {
		$o = self::opts();
		if ( empty( $o['breadcrumbs'] ) || ( ! is_singular() && ! is_archive() && ! is_home() ) ) {
			return;
		}
		echo '<nav class="kk-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'kontenkit-core' ) . '">';
		echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Beranda', 'kontenkit-core' ) . '</a>';
		if ( is_category() || is_single() ) {
			$cat = get_the_category();
			if ( ! empty( $cat[0] ) ) {
				echo ' <span class="kk-breadcrumbs__sep">›</span> <a href="' . esc_url( get_category_link( $cat[0]->term_id ) ) . '">' . esc_html( $cat[0]->name ) . '</a>';
			}
		}
		if ( is_single() ) {
			echo ' <span class="kk-breadcrumbs__sep">›</span> <span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
		}
		echo '</nav>';
	}

	public static function append_single_extras( string $content ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$o     = self::opts();
		$extra = '';
		if ( ! empty( $o['newsletter'] ) ) {
			$extra .= self::newsletter_box();
		}
		if ( ! empty( $o['social_share'] ) ) {
			$extra .= self::social_share( false );
		}
		if ( ! empty( $o['author_box'] ) ) {
			$extra .= self::author_box();
		}
		if ( ! empty( $o['related_posts'] ) ) {
			$extra .= self::related_posts( (int) $o['related_count'], ! empty( $o['related_grid'] ) );
		}
		return $content . $extra;
	}

	public static function sticky_share(): void {
		$o = self::opts();
		if ( empty( $o['sticky_share'] ) || empty( $o['social_share'] ) || ! is_singular( 'post' ) ) {
			return;
		}
		echo self::social_share( true );
	}

	private static function social_share( bool $sticky ): string {
		$url   = rawurlencode( get_permalink() );
		$title = rawurlencode( get_the_title() );
		$cls   = $sticky ? 'kk-share kk-share--sticky' : 'kk-share';
		$html  = '<div class="' . esc_attr( $cls ) . '"><span class="kk-share__label">' . esc_html__( 'Bagikan', 'kontenkit-core' ) . '</span><div class="kk-share__links">';
		$links = [
			'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
			'x'         => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
			'whatsapp'  => 'https://wa.me/?text=' . $title . '%20' . $url,
			'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
			'telegram'  => 'https://t.me/share/url?url=' . $url . '&text=' . $title,
		];
		foreach ( $links as $name => $href ) {
			$html .= '<a class="kk-share__' . esc_attr( $name ) . '" href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( ucfirst( $name ) ) . '</a>';
		}
		$html .= '</div></div>';
		return $html;
	}

	private static function newsletter_box(): string {
		$o = self::opts();
		return sprintf(
			'<aside class="kk-newsletter"><div class="kk-newsletter__inner"><h3>%s</h3><p>%s</p><form class="kk-newsletter__form" action="#" method="post" onsubmit="return false;"><input type="email" placeholder="%s" required /><button type="submit" class="kk-button kk-button--accent">%s</button></form><p class="kk-newsletter__note">%s</p></div></aside>',
			esc_html( $o['newsletter_title'] ),
			esc_html( $o['newsletter_text'] ),
			esc_attr__( 'Email Anda', 'kontenkit-core' ),
			esc_html( $o['newsletter_btn'] ),
			esc_html__( 'Hubungkan ke layanan newsletter (Mailchimp, dll.) via tema atau form plugin.', 'kontenkit-core' )
		);
	}

	private static function author_box(): string {
		$id   = (int) get_the_author_meta( 'ID' );
		$desc = get_the_author_meta( 'description', $id );
		$url  = get_author_posts_url( $id );
		return '<aside class="kk-author kk-author--premium">' .
			get_avatar( $id, 96, '', '', [ 'class' => 'kk-author__avatar' ] ) .
			'<div class="kk-author__body"><span class="kk-author__label">' . esc_html__( 'Penulis', 'kontenkit-core' ) . '</span>' .
			'<strong class="kk-author__name">' . esc_html( get_the_author() ) . '</strong>' .
			( $desc ? '<p>' . esc_html( $desc ) . '</p>' : '' ) .
			'<a class="kk-author__more" href="' . esc_url( $url ) . '">' . esc_html__( 'Semua artikel penulis', 'kontenkit-core' ) . ' →</a></div></aside>';
	}

	private static function related_posts( int $count, bool $grid ): string {
		$cats = wp_get_post_categories( get_the_ID(), [ 'fields' => 'ids' ] );
		if ( ! $cats ) {
			return '';
		}
		$q = new WP_Query( [
			'category__in'   => $cats,
			'post__not_in'   => [ get_the_ID() ],
			'posts_per_page' => $count,
			'orderby'        => 'rand',
		] );
		if ( ! $q->have_posts() ) {
			return '';
		}
		$wrap = $grid ? 'kk-related kk-related--grid' : 'kk-related';
		$html = '<section class="' . esc_attr( $wrap ) . '"><h3 class="kk-related__title">' . esc_html__( 'Artikel terkait', 'kontenkit-core' ) . '</h3>';
		if ( $grid ) {
			$html .= '<div class="kk-related__grid">';
			while ( $q->have_posts() ) {
				$q->the_post();
				$html .= '<article class="kk-related__card"><a href="' . esc_url( get_permalink() ) . '">';
				if ( has_post_thumbnail() ) {
					$html .= get_the_post_thumbnail( get_the_ID(), 'medium' );
				}
				$html .= '<h4>' . esc_html( get_the_title() ) . '</h4><time>' . esc_html( get_the_date() ) . '</time></a></article>';
			}
			$html .= '</div>';
		} else {
			$html .= '<ul>';
			while ( $q->have_posts() ) {
				$q->the_post();
				$html .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
			}
			$html .= '</ul>';
		}
		wp_reset_postdata();
		return $html . '</section>';
	}
}
