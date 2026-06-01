<?php
/**
 * Dokumen PDF — upload di admin, shortcode / trigger di halaman mana pun, lightbox publik.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Pdf_Lightbox {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 1000 );
        add_shortcode( 'ptprm_pdf', [ __CLASS__, 'shortcode' ] );
        add_filter( 'the_content', [ __CLASS__, 'inline_hover_in_content' ], 20 );
    }

    /**
     * Lepas <p> yang hanya membungkus span hover (editor sering memecah blok).
     */
    public static function inline_hover_in_content( string $content ): string {
        if ( strpos( $content, 'ptprm-pdf-hover' ) === false ) {
            return $content;
        }
        $content = preg_replace(
            '#<p>\s*(<span class="ptprm-pdf-hover\b[^>]*>.*?</span>)\s*</p>#isu',
            '$1',
            $content
        );
        $content = preg_replace(
            '#(<span class="ptprm-pdf-hover\b[^>]*>.*?</span>)\s*<br\s*/?>\s*#isu',
            '$1 ',
            $content
        );
        return $content;
    }

    public static function enqueue(): void {
        $items = ptprm_get_pdf_items();
        if ( ! $items ) {
            return;
        }

        $map = [];
        foreach ( $items as $item ) {
            $id = (string) ( $item['id'] ?? '' );
            if ( $id === '' ) {
                continue;
            }
            $url = self::pdf_url_for_item( $item );
            if ( $url === '' ) {
                continue;
            }
            $map[ $id ] = [
                'url'   => $url,
                'title' => (string) ( $item['title'] ?? '' ),
            ];
        }
        if ( ! $map ) {
            return;
        }

        wp_enqueue_script(
            'ptprm-pdf-lightbox',
            PTPRM_URL . 'assets/js/pdf-lightbox.js',
            [],
            PTPRM_VERSION,
            true
        );

        wp_localize_script(
            'ptprm-pdf-lightbox',
            'PTPRM_PDF',
            [
                'items'        => $map,
                'openLabel'    => __( 'Buka di tab baru', 'ptsbi-premium' ),
                'downloadLabel'=> __( 'Unduh PDF', 'ptsbi-premium' ),
                'closeLabel'   => __( 'Tutup', 'ptsbi-premium' ),
            ]
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public static function pdf_url_for_item( array $item ): string {
        $file_id = (int) ( $item['file'] ?? 0 );
        if ( $file_id <= 0 ) {
            return '';
        }
        $url = wp_get_attachment_url( $file_id );
        if ( ! is_string( $url ) || $url === '' ) {
            return '';
        }
        $mime = get_post_mime_type( $file_id );
        if ( $mime && strpos( $mime, 'pdf' ) === false ) {
            return '';
        }
        return $url;
    }

    /**
     * @param array<string,mixed>|null $item
     * @param array<string,string>   $atts
     */
    public static function render_trigger( ?array $item, array $atts = [] ): string {
        if ( ! is_array( $item ) ) {
            return '';
        }
        $id = (string) ( $item['id'] ?? '' );
        if ( $id === '' || self::pdf_url_for_item( $item ) === '' ) {
            return '';
        }

        $style = sanitize_key( $atts['style'] ?? 'button' );
        if ( ! in_array( $style, [ 'button', 'link', 'thumb', 'hover' ], true ) ) {
            $style = 'button';
        }

        $label = trim( (string) ( $atts['label'] ?? '' ) );
        if ( $label === '' ) {
            $label = trim( (string) ( $item['link_label'] ?? '' ) );
        }
        if ( $label === '' ) {
            $label = __( 'Lihat dokumen', 'ptsbi-premium' );
        }

        $title   = (string) ( $item['title'] ?? $label );
        $class   = trim( (string) ( $atts['class'] ?? '' ) );
        $content = trim( (string) ( $atts['content'] ?? '' ) );

        if ( $style === 'hover' ) {
            $text = $content !== '' ? $content : trim( (string) ( $atts['text'] ?? '' ) );
            if ( $text === '' ) {
                $text = $title;
            }
            $classes = 'ptprm-pdf-hover';
            if ( $class !== '' ) {
                $classes .= ' ' . esc_attr( $class );
            }
            $aria = sprintf(
                /* translators: 1: document title, 2: action label */
                __( '%1$s — %2$s', 'ptsbi-premium' ),
                wp_strip_all_tags( $text ),
                $label
            );
            return sprintf(
                '<span class="%1$s" data-ptprm-pdf-id="%2$s" tabindex="0" role="button" aria-label="%3$s">%4$s<span class="ptprm-pdf-hover-action" aria-hidden="true">%5$s</span></span>',
                esc_attr( $classes ),
                esc_attr( $id ),
                esc_attr( $aria ),
                esc_html( $text ),
                esc_html( $label )
            );
        }

        if ( $style === 'thumb' ) {
            $thumb = ptprm_image_url( $item['thumb'] ?? '' );
            $classes = 'ptprm-pdf-trigger ptprm-pdf-trigger-thumb';
            if ( $class !== '' ) {
                $classes .= ' ' . esc_attr( $class );
            }
            ob_start();
            echo '<button type="button" class="' . esc_attr( $classes ) . '" data-ptprm-pdf-id="' . esc_attr( $id ) . '" aria-label="' . esc_attr( $title ) . '">';
            if ( $thumb !== '' ) {
                echo '<span class="ptprm-pdf-thumb-img"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async"></span>';
            } else {
                echo '<span class="ptprm-pdf-thumb-placeholder" aria-hidden="true">PDF</span>';
            }
            echo '<span class="ptprm-pdf-thumb-label">' . esc_html( $title ) . '</span>';
            echo '</button>';
            return (string) ob_get_clean();
        }

        if ( $style === 'link' ) {
            $classes = 'ptprm-pdf-trigger ptprm-pdf-trigger-link';
            if ( $class !== '' ) {
                $classes .= ' ' . esc_attr( $class );
            }
            return sprintf(
                '<a href="#" class="%1$s" data-ptprm-pdf-id="%2$s" role="button">%3$s</a>',
                esc_attr( $classes ),
                esc_attr( $id ),
                esc_html( $label )
            );
        }

        $classes = 'ptprm-pdf-trigger ptprm-cta ptprm-cta-1 ptprm-cta-size-medium';
        if ( $class !== '' ) {
            $classes .= ' ' . esc_attr( $class );
        }
        return sprintf(
            '<button type="button" class="%1$s" data-ptprm-pdf-id="%2$s"><span class="ptprm-cta-label">%3$s</span></button>',
            esc_attr( $classes ),
            esc_attr( $id ),
            esc_html( $label )
        );
    }

    /**
     * [ptprm_pdf id="ad-art" label="..." style="button|link|thumb|hover" class=""]
     * Hover: bungkus teks SK — [ptprm_pdf id="sk-ahu" style="hover" label="Lihat SK"]Teks SK…[/ptprm_pdf]
     *
     * @param array<string,string>|string $atts
     * @param string|null                $content
     */
    public static function shortcode( $atts = [], $content = null ): string {
        $atts = shortcode_atts(
            [
                'id'    => '',
                'label' => '',
                'style' => 'button',
                'class' => '',
                'text'  => '',
            ],
            is_array( $atts ) ? $atts : [],
            'ptprm_pdf'
        );

        $id = sanitize_key( (string) $atts['id'] );
        if ( $id === '' ) {
            return is_string( $content ) ? $content : '';
        }

        if ( is_string( $content ) && trim( $content ) !== '' ) {
            $atts['content'] = trim( $content );
        }

        $item = ptprm_get_pdf_item( $id );
        $html = self::render_trigger( $item, $atts );
        return $html !== '' ? $html : ( is_string( $content ) ? $content : '' );
    }
}
