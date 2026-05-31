<?php
/**
 * Build inline CSS from saved settings (global + per-section).
 * Output is medium-specificity, intentionally NOT using !important on typography
 * so Elementor inline styles always win when the user customises in Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_CSS_Builder {

    public function build() {
        $o = ptsbi_pe_get_options();
        $css = '';

        // Global CSS variables.
        $css .= ':root{';
        $css .= '--studio-primary:' . esc_html( $o['color_primary'] ) . ';';
        $css .= '--studio-primary-2:' . esc_html( $this->shade( $o['color_primary'], -12 ) ) . ';';
        $css .= '--studio-accent:' . esc_html( $o['color_accent'] ) . ';';
        $css .= '--studio-accent-2:' . esc_html( $this->shade( $o['color_accent'], 16 ) ) . ';';
        $css .= '--studio-text:' . esc_html( $o['color_text'] ) . ';';
        $css .= '--studio-text-soft:' . esc_html( $o['color_text_soft'] ) . ';';
        $css .= '--studio-max:' . intval( $o['container_max'] ) . 'px;';

        $heading_font = ptsbi_pe_resolve_font( $o['font_heading'] );
        $body_font    = ptsbi_pe_resolve_font( $o['font_body'] );
        if ( $heading_font ) $css .= '--studio-font-heading:' . esc_html( $heading_font ) . ';';
        if ( $body_font )    $css .= '--studio-font-body:' . esc_html( $body_font ) . ';';
        $css .= '}';

        // Per-section blocks.
        foreach ( array_keys( ptsbi_pe_section_types() ) as $slug ) {
            $css .= $this->build_section_css( $slug, $o );
        }

        return $css;
    }

    private function build_section_css( $slug, $o ) {
        $sel = '[data-studio-section="' . $slug . '"]';
        $css = '';

        // Enabled / hidden
        $enabled = (int) ( $o[ "sec_{$slug}_enabled" ] ?? 1 );
        if ( ! $enabled ) {
            $css .= $sel . '{display:none !important;}';
            return $css;
        }

        $bg  = $o[ "sec_{$slug}_bg_color" ] ?? '';
        $pt  = $o[ "sec_{$slug}_pad_top" ] ?? '';
        $pb  = $o[ "sec_{$slug}_pad_bottom" ] ?? '';
        $ptM = $o[ "sec_{$slug}_pad_top_mobile" ] ?? '';
        $pbM = $o[ "sec_{$slug}_pad_bottom_mobile" ] ?? '';

        if ( $bg !== '' || $pt !== '' || $pb !== '' ) {
            $rules = '';
            if ( $bg !== '' ) $rules .= 'background-color:' . esc_html( $bg ) . ' !important;';
            if ( $pt !== '' ) $rules .= 'padding-top:' . intval( $pt ) . 'px !important;';
            if ( $pb !== '' ) $rules .= 'padding-bottom:' . intval( $pb ) . 'px !important;';
            if ( $rules ) $css .= $sel . '{' . $rules . '}';
        }

        if ( $ptM !== '' || $pbM !== '' ) {
            $rulesM = '';
            if ( $ptM !== '' ) $rulesM .= 'padding-top:' . intval( $ptM ) . 'px !important;';
            if ( $pbM !== '' ) $rulesM .= 'padding-bottom:' . intval( $pbM ) . 'px !important;';
            if ( $rulesM ) $css .= '@media (max-width:768px){' . $sel . '{' . $rulesM . '}}';
        }

        // Typography (NO !important on size/color/font - Elementor must win)
        $hFont = ptsbi_pe_resolve_font( $o[ "sec_{$slug}_heading_font" ] ?? 'theme-default' );
        $hCol  = $o[ "sec_{$slug}_heading_color" ] ?? '';
        $hSize = $o[ "sec_{$slug}_heading_size" ] ?? '';
        $hSizeM = $o[ "sec_{$slug}_heading_size_mobile" ] ?? '';

        if ( $hFont || $hCol || $hSize !== '' ) {
            $r = '';
            if ( $hFont ) $r .= 'font-family:' . esc_html( $hFont ) . ';';
            if ( $hCol )  $r .= 'color:' . esc_html( $hCol ) . ';';
            if ( $hSize !== '' ) $r .= 'font-size:' . intval( $hSize ) . 'px;';
            if ( $r ) $css .= $sel . ' :is(h1,h2,h3,h4,h5,h6){' . $r . '}';
        }
        if ( $hSizeM !== '' ) {
            $css .= '@media (max-width:768px){' . $sel . ' :is(h1,h2,h3,h4,h5,h6){font-size:' . intval( $hSizeM ) . 'px;}}';
        }

        $bFont = ptsbi_pe_resolve_font( $o[ "sec_{$slug}_body_font" ] ?? 'theme-default' );
        $bCol  = $o[ "sec_{$slug}_body_color" ] ?? '';
        $bSize = $o[ "sec_{$slug}_body_size" ] ?? '';
        $bSizeM = $o[ "sec_{$slug}_body_size_mobile" ] ?? '';

        if ( $bFont || $bCol || $bSize !== '' ) {
            $r = '';
            if ( $bFont ) $r .= 'font-family:' . esc_html( $bFont ) . ';';
            if ( $bCol )  $r .= 'color:' . esc_html( $bCol ) . ';';
            if ( $bSize !== '' ) $r .= 'font-size:' . intval( $bSize ) . 'px;';
            if ( $r ) $css .= $sel . ' :is(p,li,span,a){' . $r . '}';
        }
        if ( $bSizeM !== '' ) {
            $css .= '@media (max-width:768px){' . $sel . ' :is(p,li){font-size:' . intval( $bSizeM ) . 'px;}}';
        }

        return $css;
    }

    private function shade( $hex, $percent ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) !== 6 ) return '#' . $hex;
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        $adj = function ( $c ) use ( $percent ) {
            $c = $c + ( $c * ( $percent / 100 ) );
            return max( 0, min( 255, (int) round( $c ) ) );
        };
        return sprintf( '#%02X%02X%02X', $adj( $r ), $adj( $g ), $adj( $b ) );
    }
}
