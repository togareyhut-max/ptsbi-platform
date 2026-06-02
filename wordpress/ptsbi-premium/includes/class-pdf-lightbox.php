<?php
/**
 * Galeri PDF + lightbox — shortcode, halaman publikasi, HTML embed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Pdf_Lightbox {

    public function __construct() {
        add_shortcode( 'ptprm_pdf_lightbox', [ __CLASS__, 'shortcode' ] );
        add_shortcode( 'ptprm_pdf_gallery', [ __CLASS__, 'shortcode' ] );
        add_shortcode( 'ptprm_pdf_publications', [ __CLASS__, 'shortcode_publications' ] );
    }

    /**
     * @param array<string,string>|string $atts
     */
    public static function shortcode( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'columns' => '',
                'variant' => 'default',
            ],
            is_array( $atts ) ? $atts : [],
            'ptprm_pdf_lightbox'
        );
        $cols    = (int) ( $atts['columns'] ?: 0 );
        $variant = sanitize_key( (string) $atts['variant'] );
        if ( ! in_array( $variant, [ 'default', 'hover', 'plain' ], true ) ) {
            $variant = 'default';
        }
        ob_start();
        self::render( ptprm_options(), $cols, $variant );
        return (string) ob_get_clean();
    }

    /**
     * Daftar publikasi di halaman (baca + unduh).
     *
     * @param array<string,string>|string $atts
     */
    public static function shortcode_publications( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'columns' => '',
            ],
            is_array( $atts ) ? $atts : [],
            'ptprm_pdf_publications'
        );
        $cols = (int) ( $atts['columns'] ?: 0 );
        ob_start();
        self::render_publications( ptprm_options(), $cols );
        return (string) ob_get_clean();
    }

    /**
     * Shortcode siap tempel.
     */
    public static function format_shortcode( string $variant = 'default', int $columns = 0 ): string {
        $parts = [];
        if ( $variant !== '' && $variant !== 'default' ) {
            $parts[] = 'variant="' . $variant . '"';
        }
        if ( $columns > 0 ) {
            $parts[] = 'columns="' . max( 2, min( 4, $columns ) ) . '"';
        }
        if ( $parts === [] ) {
            return '[ptprm_pdf_lightbox]';
        }
        return '[ptprm_pdf_lightbox ' . implode( ' ', $parts ) . ']';
    }

    public static function format_publications_shortcode( int $columns = 0 ): string {
        if ( $columns > 0 ) {
            return '[ptprm_pdf_publications columns="' . max( 2, min( 4, $columns ) ) . '"]';
        }
        return '[ptprm_pdf_publications]';
    }

    /**
     * HTML blok (butuh CSS/JS plugin di halaman — otomatis jika tema memuat frontend plugin).
     *
     * @param array<string,mixed>|null $o
     */
    public static function format_html_embed( ?array $o = null, string $variant = 'default', int $columns = 0 ): string {
        $o     = $o ?? ptprm_options();
        $items = ptprm_get_pdf_lightbox_items( $o );
        if ( $items === [] ) {
            return '<!-- ptprm_pdf_lightbox: belum ada dokumen PDF -->';
        }
        return self::build_markup( $items, self::resolve_columns( $o, $columns ), $variant, false, [] );
    }

    /**
     * @param array<string,mixed> $o
     */
    private static function resolve_columns( array $o, int $override ): int {
        if ( $override > 0 ) {
            return max( 2, min( 4, $override ) );
        }
        return max( 2, min( 4, (int) ( $o['pdf_lightbox_columns'] ?? 3 ) ) );
    }

    /**
     * @param array<string,mixed> $o
     * @param array{eyebrow?:string,title?:string,subtitle?:string} $heading
     */
    public static function render( array $o, int $columns_override = 0, string $variant = 'default', bool $wrap_section = false, array $heading = [] ): void {
        $items = ptprm_get_pdf_lightbox_items( $o );
        if ( $items === [] ) {
            return;
        }
        $cols = self::resolve_columns( $o, $columns_override );
        echo self::build_markup( $items, $cols, $variant, $wrap_section, $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @param array<string,mixed> $o
     */
    public static function render_publications( array $o, int $columns_override = 0 ): void {
        $items = ptprm_get_pdf_lightbox_items( $o );
        $cols  = self::resolve_columns( $o, $columns_override );
        $intro = trim( (string) ( $o['pdf_publications_intro'] ?? '' ) );
        echo self::build_publications_markup( $items, $cols, $intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public static function build_publications_markup( array $items, int $cols, string $intro = '' ): string {
        $html = '<div class="ptprm-pdf-publications" style="--ptprm-pdf-cols:' . (int) $cols . ';">';

        if ( $intro !== '' ) {
            $html .= '<p class="ptprm-pdf-publications-intro">' . esc_html( $intro ) . '</p>';
        }

        if ( $items === [] ) {
            $html .= '<p class="ptprm-empty">' . esc_html__( 'Belum ada publikasi atau dokumen PDF. Tambahkan dari Premium Plugin → Panel PDF Lightbox.', 'ptsbi-premium' ) . '</p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<ul class="ptprm-pdf-publications-list">';
        foreach ( $items as $item ) {
            $pdf_url = (string) ( $item['pdf_url'] ?? '' );
            if ( $pdf_url === '' ) {
                continue;
            }
            $label = (string) ( $item['title'] ?? __( 'Dokumen PDF', 'ptsbi-premium' ) );
            $cover = (string) ( $item['cover_url'] ?? '' );

            $html .= '<li class="ptprm-pdf-publications-item">';
            $html .= '<div class="ptprm-pdf-publications-cover-wrap">';
            $html .= '<a class="ptprm-pdf-publications-cover" href="' . esc_url( $pdf_url ) . '" data-ptprm-pdf-lightbox data-title="' . esc_attr( $label ) . '">';
            if ( $cover !== '' ) {
                $html .= '<span class="ptprm-pdf-cover" style="background-image:url(' . esc_url( $cover ) . ')"></span>';
            } else {
                $html .= '<span class="ptprm-pdf-cover ptprm-pdf-cover--placeholder" aria-hidden="true">PDF</span>';
            }
            $html .= '</a></div>';
            $html .= '<div class="ptprm-pdf-publications-body">';
            $html .= '<h3 class="ptprm-pdf-publications-title">' . esc_html( $label ) . '</h3>';
            $html .= '<div class="ptprm-pdf-publications-actions">';
            $html .= '<a class="ptprm-pdf-pub-btn ptprm-pdf-pub-btn--read" href="' . esc_url( $pdf_url ) . '" data-ptprm-pdf-lightbox data-title="' . esc_attr( $label ) . '">' . esc_html__( 'Baca', 'ptsbi-premium' ) . '</a>';
            $html .= '<a class="ptprm-pdf-pub-btn ptprm-pdf-pub-btn--download" href="' . esc_url( $pdf_url ) . '" download target="_blank" rel="noopener noreferrer">' . esc_html__( 'Unduh', 'ptsbi-premium' ) . '</a>';
            $html .= '</div></div></li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param array{eyebrow?:string,title?:string,subtitle?:string} $heading
     */
    public static function build_markup( array $items, int $cols, string $variant = 'default', bool $wrap_section = false, array $heading = [] ): string {
        if ( $items === [] ) {
            return '';
        }

        $variant = sanitize_key( $variant );
        if ( ! in_array( $variant, [ 'default', 'hover', 'plain' ], true ) ) {
            $variant = 'default';
        }

        $grid_class = 'ptprm-pdf-grid';
        if ( $variant === 'hover' ) {
            $grid_class .= ' ptprm-pdf-grid--hover';
        } elseif ( $variant === 'plain' ) {
            $grid_class .= ' ptprm-pdf-grid--plain';
        }

        $html = '';
        if ( $wrap_section ) {
            $html .= '<section class="ptprm-section ptprm-pdf-lightbox-section" style="--ptprm-pdf-cols:' . (int) $cols . ';">';
            $eyebrow  = (string) ( $heading['eyebrow'] ?? '' );
            $title    = (string) ( $heading['title'] ?? '' );
            $subtitle = (string) ( $heading['subtitle'] ?? '' );
            if ( $eyebrow !== '' || $title !== '' || $subtitle !== '' ) {
                $html .= '<div class="ptprm-section-head">';
                if ( $eyebrow !== '' ) {
                    $html .= '<p class="ptprm-eyebrow">' . esc_html( $eyebrow ) . '</p>';
                }
                if ( $title !== '' ) {
                    $html .= '<h2 class="ptprm-section-title">' . esc_html( $title ) . '</h2>';
                }
                if ( $subtitle !== '' ) {
                    $html .= '<p class="ptprm-section-sub">' . esc_html( $subtitle ) . '</p>';
                }
                $html .= '</div>';
            }
        } else {
            $html .= '<div class="ptprm-pdf-lightbox-embed" style="--ptprm-pdf-cols:' . (int) $cols . ';">';
        }

        $html .= '<div class="' . esc_attr( $grid_class ) . '">';
        foreach ( $items as $item ) {
            $pdf_url = (string) ( $item['pdf_url'] ?? '' );
            if ( $pdf_url === '' ) {
                continue;
            }
            $label = (string) ( $item['title'] ?? __( 'Dokumen PDF', 'ptsbi-premium' ) );
            $cover = (string) ( $item['cover_url'] ?? '' );
            $icon  = $cover !== '' ? '' : ' ptprm-pdf-card--icon';

            $html .= '<a class="ptprm-pdf-card' . esc_attr( $icon ) . '" href="' . esc_url( $pdf_url ) . '" data-ptprm-pdf-lightbox data-title="' . esc_attr( $label ) . '">';
            if ( $cover !== '' ) {
                $html .= '<span class="ptprm-pdf-cover" style="background-image:url(' . esc_url( $cover ) . ')"></span>';
            } else {
                $html .= '<span class="ptprm-pdf-cover ptprm-pdf-cover--placeholder" aria-hidden="true">PDF</span>';
            }
            if ( $variant === 'hover' ) {
                $html .= '<span class="ptprm-pdf-hover-cap"><span class="ptprm-pdf-hover-title">' . esc_html( $label ) . '</span>';
                $html .= '<span class="ptprm-pdf-hover-hint">' . esc_html__( 'Klik untuk membaca', 'ptsbi-premium' ) . '</span></span>';
            }
            $html .= '<span class="ptprm-pdf-label">' . esc_html( $label ) . '</span>';
            $html .= '</a>';
        }
        $html .= '</div>';
        $html .= $wrap_section ? '</section>' : '</div>';

        return $html;
    }
}
