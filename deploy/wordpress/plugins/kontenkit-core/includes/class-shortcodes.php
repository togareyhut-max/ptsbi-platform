<?php
defined( 'ABSPATH' ) || exit;

class KontenKit_Shortcodes {
	public static function init(): void {
		$map = [
			'kk_button'       => 'button',
			'kk_alert'        => 'alert',
			'kk_columns'      => 'columns',
			'kk_icon_box'     => 'icon_box',
			'kk_accordion'    => 'accordion',
			'kk_quote'        => 'quote',
			'kk_youtube'      => 'youtube',
			'kk_pricing'      => 'pricing',
			'kk_testimonials' => 'testimonials',
			'kk_portfolio'    => 'portfolio',
			'kk_cta'          => 'cta',
			'kk_spacer'       => 'spacer',
			'kk_divider'      => 'divider',
		];
		foreach ( $map as $tag => $method ) {
			add_shortcode( $tag, [ __CLASS__, $method ] );
		}
	}

	public static function button( $atts ): string {
		$a = shortcode_atts( [
			'url'   => '#',
			'text'  => __( 'Selengkapnya', 'kontenkit-core' ),
			'class' => '',
			'style' => '',
		], $atts, 'kk_button' );
		$style = in_array( $a['style'], [ 'accent', 'outline', 'ghost' ], true ) ? ' kk-button--' . $a['style'] : '';
		return sprintf(
			'<p class="kk-button-wrap"><a class="kk-button%s %s" href="%s">%s</a></p>',
			esc_attr( $style ),
			esc_attr( $a['class'] ),
			esc_url( $a['url'] ),
			esc_html( $a['text'] )
		);
	}

	public static function alert( $atts, ?string $content = null ): string {
		$a    = shortcode_atts( [ 'type' => 'info', 'title' => '' ], $atts, 'kk_alert' );
		$type = in_array( $a['type'], [ 'info', 'success', 'warning', 'error' ], true ) ? $a['type'] : 'info';
		$head = $a['title'] ? '<strong class="kk-alert__title">' . esc_html( $a['title'] ) . '</strong>' : '';
		return sprintf(
			'<div class="kk-alert kk-alert-%s">%s<div class="kk-alert__body">%s</div></div>',
			esc_attr( $type ),
			$head,
			wp_kses_post( do_shortcode( (string) $content ) )
		);
	}

	public static function columns( $atts, ?string $content = null ): string {
		$a    = shortcode_atts( [ 'cols' => '2', 'gap' => 'normal' ], $atts, 'kk_columns' );
		$cols = max( 2, min( 4, (int) $a['cols'] ) );
		$gap  = in_array( $a['gap'], [ 'tight', 'normal', 'wide' ], true ) ? $a['gap'] : 'normal';
		return sprintf(
			'<div class="kk-columns kk-columns-%d kk-columns--gap-%s">%s</div>',
			$cols,
			esc_attr( $gap ),
			wp_kses_post( do_shortcode( (string) $content ) )
		);
	}

	public static function icon_box( $atts, ?string $content = null ): string {
		$a = shortcode_atts( [
			'icon'  => '★',
			'title' => '',
			'align' => 'left',
		], $atts, 'kk_icon_box' );
		return sprintf(
			'<div class="kk-icon-box kk-icon-box--%s"><span class="kk-icon-box__icon" aria-hidden="true">%s</span><div><h3 class="kk-icon-box__title">%s</h3><div class="kk-icon-box__text">%s</div></div></div>',
			esc_attr( $a['align'] ),
			esc_html( $a['icon'] ),
			esc_html( $a['title'] ),
			wp_kses_post( do_shortcode( (string) $content ) )
		);
	}

	public static function accordion( $atts, ?string $content = null ): string {
		$a = shortcode_atts( [ 'title' => __( 'Pertanyaan', 'kontenkit-core' ), 'open' => '' ], $atts, 'kk_accordion' );
		$open = strtolower( $a['open'] ) === 'yes' ? ' open' : '';
		return sprintf(
			'<details class="kk-accordion"%s><summary class="kk-accordion__title">%s</summary><div class="kk-accordion__body">%s</div></details>',
			$open,
			esc_html( $a['title'] ),
			wp_kses_post( do_shortcode( (string) $content ) )
		);
	}

	public static function quote( $atts, ?string $content = null ): string {
		$a = shortcode_atts( [ 'author' => '', 'role' => '' ], $atts, 'kk_quote' );
		$cite = '';
		if ( $a['author'] ) {
			$cite = '<cite class="kk-quote__author">' . esc_html( $a['author'] );
			if ( $a['role'] ) {
				$cite .= ' <span class="kk-quote__role">' . esc_html( $a['role'] ) . '</span>';
			}
			$cite .= '</cite>';
		}
		return sprintf(
			'<blockquote class="kk-quote"><p>%s</p>%s</blockquote>',
			wp_kses_post( do_shortcode( (string) $content ) ),
			$cite
		);
	}

	public static function youtube( $atts ): string {
		$a = shortcode_atts( [ 'id' => '', 'url' => '' ], $atts, 'kk_youtube' );
		$id  = $a['id'];
		if ( ! $id && $a['url'] ) {
			if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{6,})/', $a['url'], $m ) ) {
				$id = $m[1];
			}
		}
		if ( ! $id ) {
			return '';
		}
		return sprintf(
			'<div class="kk-video kk-video--youtube"><iframe src="https://www.youtube-nocookie.com/embed/%s" title="YouTube" loading="lazy" allowfullscreen></iframe></div>',
			esc_attr( $id )
		);
	}

	public static function pricing( $atts ): string {
		$a = shortcode_atts( [
			'name'        => __( 'Paket', 'kontenkit-core' ),
			'price'       => '0',
			'period'      => '/bulan',
			'features'    => '',
			'btn'         => __( 'Pilih', 'kontenkit-core' ),
			'url'         => '#',
			'highlighted' => '',
		], $atts, 'kk_pricing' );
		$hi   = strtolower( $a['highlighted'] ) === 'yes' ? ' kk-pricing--highlight' : '';
		$feat = array_filter( array_map( 'trim', explode( '|', $a['features'] ) ) );
		$list = '';
		foreach ( $feat as $f ) {
			$list .= '<li>' . esc_html( $f ) . '</li>';
		}
		return sprintf(
			'<div class="kk-pricing%s"><h3 class="kk-pricing__name">%s</h3><p class="kk-pricing__price"><span class="kk-pricing__amount">%s</span><span class="kk-pricing__period">%s</span></p><ul class="kk-pricing__features">%s</ul><a class="kk-button kk-button--accent" href="%s">%s</a></div>',
			esc_attr( $hi ),
			esc_html( $a['name'] ),
			esc_html( $a['price'] ),
			esc_html( $a['period'] ),
			$list,
			esc_url( $a['url'] ),
			esc_html( $a['btn'] )
		);
	}

	public static function testimonials( $atts ): string {
		$a = shortcode_atts( [ 'count' => '6', 'columns' => '3' ], $atts, 'kk_testimonials' );
		if ( ! post_type_exists( 'kk_testimonial' ) ) {
			return '';
		}
		$q = new WP_Query( [
			'post_type'      => 'kk_testimonial',
			'posts_per_page' => max( 1, min( 12, (int) $a['count'] ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		if ( ! $q->have_posts() ) {
			return '';
		}
		$cols = max( 1, min( 4, (int) $a['columns'] ) );
		$html = '<div class="kk-testimonials kk-testimonials--cols-' . esc_attr( (string) $cols ) . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$role = get_post_meta( get_the_ID(), '_kk_role', true );
			$html .= '<article class="kk-testimonial">';
			if ( has_post_thumbnail() ) {
				$html .= '<div class="kk-testimonial__avatar">' . get_the_post_thumbnail( get_the_ID(), 'thumbnail' ) . '</div>';
			}
			$html .= '<blockquote>' . wp_kses_post( get_the_content() ) . '</blockquote>';
			$html .= '<footer><strong>' . esc_html( get_the_title() ) . '</strong>';
			if ( $role ) {
				$html .= '<span>' . esc_html( $role ) . '</span>';
			}
			$html .= '</footer></article>';
		}
		wp_reset_postdata();
		return $html . '</div>';
	}

	public static function portfolio( $atts ): string {
		$a = shortcode_atts( [ 'count' => '8', 'columns' => '4' ], $atts, 'kk_portfolio' );
		if ( ! post_type_exists( 'kk_portfolio' ) ) {
			return '';
		}
		$q = new WP_Query( [
			'post_type'      => 'kk_portfolio',
			'posts_per_page' => max( 1, min( 24, (int) $a['count'] ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		if ( ! $q->have_posts() ) {
			return '';
		}
		$cols = max( 2, min( 4, (int) $a['columns'] ) );
		$html = '<div class="kk-portfolio kk-portfolio--cols-' . esc_attr( (string) $cols ) . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$html .= '<a class="kk-portfolio__item" href="' . esc_url( get_permalink() ) . '">';
			if ( has_post_thumbnail() ) {
				$html .= get_the_post_thumbnail( get_the_ID(), 'medium_large' );
			}
			$html .= '<span class="kk-portfolio__title">' . esc_html( get_the_title() ) . '</span></a>';
		}
		wp_reset_postdata();
		return $html . '</div>';
	}

	public static function cta( $atts ): string {
		$a = shortcode_atts( [
			'title' => __( 'Siap memulai?', 'kontenkit-core' ),
			'text'  => '',
			'btn'   => __( 'Hubungi kami', 'kontenkit-core' ),
			'url'   => '#',
		], $atts, 'kk_cta' );
		$text = $a['text'] ? '<p>' . esc_html( $a['text'] ) . '</p>' : '';
		return sprintf(
			'<div class="kk-cta"><div class="kk-cta__inner"><h2 class="kk-cta__title">%s</h2>%s<a class="kk-button kk-button--accent" href="%s">%s</a></div></div>',
			esc_html( $a['title'] ),
			$text,
			esc_url( $a['url'] ),
			esc_html( $a['btn'] )
		);
	}

	public static function spacer( $atts ): string {
		$a = shortcode_atts( [ 'height' => '2rem' ], $atts, 'kk_spacer' );
		$h   = preg_match( '/^\d+(\.\d+)?(px|rem|em|%)$/', $a['height'] ) ? $a['height'] : '2rem';
		return '<div class="kk-spacer" style="height:' . esc_attr( $h ) . '" aria-hidden="true"></div>';
	}

	public static function divider( $atts ): string {
		$a = shortcode_atts( [ 'style' => 'line' ], $atts, 'kk_divider' );
		$s = in_array( $a['style'], [ 'line', 'dots', 'gradient' ], true ) ? $a['style'] : 'line';
		return '<hr class="kk-divider kk-divider--' . esc_attr( $s ) . '" />';
	}
}
