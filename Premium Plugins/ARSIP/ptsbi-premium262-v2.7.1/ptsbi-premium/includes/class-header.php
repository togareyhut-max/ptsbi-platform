<?php
/**
 * Header premium plugin: logo, menu modern, CTA.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Header {

    public function __construct() {
        add_action( 'wp_body_open', [ $this, 'render' ], 5 );
        add_filter( 'body_class', [ $this, 'body_class' ] );
        add_filter( 'wp_nav_menu_args', [ $this, 'filter_wp_menu_args' ], 10, 1 );
        add_filter( 'nav_menu_css_class', [ $this, 'nav_menu_css_class' ], 10, 2 );
    }

    /**
     * @param string[] $classes CSS classes.
     * @param \WP_Post  $item  Menu item.
     * @return string[]
     */
    public function nav_menu_css_class( $classes, $item ) {
        if ( in_array( 'menu-item-has-children', $classes, true ) ) {
            $classes[] = 'has-children';
        }
        return $classes;
    }

    public function body_class( $classes ) {
        if ( is_admin() || ! self::is_active() ) {
            return $classes;
        }
        $classes[] = 'ptprm-plugin-header';
        $o         = ptprm_options();
        $classes[] = 'ptprm-header-layout-' . sanitize_html_class( $o['header_layout'] ?? 'split' );
        $classes[] = 'ptprm-header-nav-' . sanitize_html_class( $o['header_nav_style'] ?? 'pill' );
        if ( ! empty( $o['header_transparent_home'] ) && ( is_front_page() || is_home() ) ) {
            $classes[] = 'ptprm-header-transparent-home';
        }
        if ( ! empty( $o['header_hide_theme'] ) ) {
            $classes[] = 'ptprm-hide-theme-header';
        }
        return $classes;
    }

    public static function is_active(): bool {
        return ! empty( ptprm_get( 'header_use_plugin', 1 ) );
    }

    public function render(): void {
        if ( is_admin() || ! self::is_active() ) {
            return;
        }
        $o = ptprm_options();

        $logo_url = ptprm_image_url( $o['header_logo'] ?? '' );
        $org      = PTPRM_Bootstrap::org_name();
        $home     = home_url( '/' );
        $layout   = in_array( $o['header_layout'] ?? '', [ 'classic', 'centered', 'split', 'minimal' ], true )
            ? $o['header_layout']
            : 'split';
        $nav_style = in_array( $o['header_nav_style'] ?? '', [ 'pill', 'underline', 'plain' ], true )
            ? $o['header_nav_style']
            : 'pill';

        $bg = $o['header_bg_color'] ?? '';
        if ( ! $bg ) {
            $bg = $o['color_primary'] ?? '#0A1F3D';
        }
        $text = $o['header_text_color'] ?? '#ffffff';
        $max_h    = max( 28, min( 120, (int) ( $o['header_logo_max_height'] ?? 48 ) ) );
        $menu_fs  = max( 12, min( 20, (int) ( $o['header_menu_font_size'] ?? 15 ) ) );

        $style = sprintf(
            '--ptprm-header-bg:%s;--ptprm-header-text:%s;--ptprm-header-logo-max:%dpx;--ptprm-header-menu-size:%dpx;',
            esc_attr( $bg ),
            esc_attr( $text ),
            $max_h,
            $menu_fs
        );

        $classes = [
            'ptprm-site-header',
            'ptprm-site-header--' . $layout,
            'ptprm-site-header--nav-' . $nav_style,
        ];
        if ( ! empty( $o['sticky_header'] ) ) {
            $classes[] = 'ptprm-site-header--sticky';
        }

        echo '<header id="ptprm-site-header" class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '" role="banner">';
        echo '<div class="ptprm-site-header__inner">';

        echo '<div class="ptprm-site-header__brand">';
        echo '<a class="ptprm-site-header__logo-link" href="' . esc_url( $home ) . '" rel="home">';
        if ( $logo_url ) {
            echo '<img class="ptprm-site-header__logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $org ) . '" decoding="async" />';
        } else {
            echo '<span class="ptprm-site-header__logo-text">' . esc_html( $org ) . '</span>';
        }
        echo '</a>';
        echo '</div>';

        echo '<div class="ptprm-site-header__actions ptprm-site-header__actions--bar">';
        $this->render_header_cta( $o );
        echo '</div>';

        echo '<div class="ptprm-site-header__nav-col ptprm-site-header__nav-col--desktop" aria-hidden="false">';
        echo '<nav class="ptprm-site-header__nav" aria-label="' . esc_attr__( 'Menu utama', 'ptsbi-premium' ) . '">';
        $this->render_menu( $o );
        echo '</nav>';
        echo '</div>';

        echo '<button type="button" class="ptprm-site-header__toggle" aria-expanded="false" aria-controls="ptprm-site-nav" aria-label="' . esc_attr__( 'Buka menu', 'ptsbi-premium' ) . '">';
        echo '<span class="ptprm-site-header__toggle-bar" aria-hidden="true"></span>';
        echo '<span class="ptprm-site-header__toggle-bar" aria-hidden="true"></span>';
        echo '<span class="ptprm-site-header__toggle-bar" aria-hidden="true"></span>';
        echo '</button>';

        echo '</div></header>';

        $this->render_mobile_drawer( $o, $org );
    }

    /**
     * Drawer HP di luar &lt;header&gt; agar position:fixed menutup seluruh layar (bukan strip di tengah).
     *
     * @param array<string, mixed> $o   Options.
     * @param string               $org Site name.
     */
    private function render_mobile_drawer( array $o, string $org ): void {
        echo '<div id="ptprm-site-nav" class="ptprm-mobile-nav-root" aria-hidden="true">';
        echo '<div class="ptprm-mobile-drawer__backdrop" data-ptprm-drawer-close tabindex="-1" aria-hidden="true"></div>';
        echo '<div class="ptprm-mobile-drawer__panel" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Menu navigasi', 'ptsbi-premium' ) . '">';
        echo '<div class="ptprm-mobile-drawer__head">';
        echo '<div class="ptprm-mobile-drawer__brand">';
        echo '<span class="ptprm-mobile-drawer__eyebrow">' . esc_html__( 'Navigasi', 'ptsbi-premium' ) . '</span>';
        echo '<span class="ptprm-mobile-drawer__title">' . esc_html( $org ) . '</span>';
        echo '</div>';
        echo '<button type="button" class="ptprm-mobile-drawer__close" data-ptprm-drawer-close aria-label="' . esc_attr__( 'Tutup menu', 'ptsbi-premium' ) . '">';
        echo '<span aria-hidden="true"></span><span aria-hidden="true"></span>';
        echo '</button>';
        echo '</div>';
        echo '<nav class="ptprm-site-header__nav ptprm-site-header__nav--mobile" aria-label="' . esc_attr__( 'Menu utama', 'ptsbi-premium' ) . '">';
        $this->render_menu( $o );
        echo '</nav>';
        echo '<div class="ptprm-site-header__actions ptprm-site-header__actions--drawer">';
        $this->render_header_cta( $o );
        echo '</div>';
        echo '<div class="ptprm-mobile-drawer__ornament" aria-hidden="true">' . ptprm_ornament_svg() . '</div>'; // phpcs:ignore
        echo '</div></div>';
    }

    /**
     * @param array<string, mixed> $o Options.
     */
    private function render_header_cta( array $o ): void {
        if ( empty( $o['header_show_cta'] ) || empty( $o['header_cta_label'] ) ) {
            return;
        }
        $cta_url = ptprm_resolve_url( $o['header_cta_url'] ?? '/kontak/' );
        $cta_cls = 'ptprm-site-header__cta ptprm-site-header__cta--' . sanitize_html_class( $o['header_cta_style'] ?? 'accent' );
        echo '<a class="' . esc_attr( $cta_cls ) . '" href="' . esc_url( $cta_url ) . '">' . esc_html( $o['header_cta_label'] ) . '</a>';
    }

    /**
     * @param array<string, mixed> $o Options.
     */
    private function render_menu( array $o ): void {
        $source = $o['header_menu_source'] ?? 'custom';
        $menu_id = (int) ( $o['header_wp_menu'] ?? 0 );

        if ( $source === 'wp' && $menu_id && is_nav_menu( $menu_id ) ) {
            wp_nav_menu(
                [
                    'menu'       => $menu_id,
                    'container'  => false,
                    'menu_class' => 'ptprm-menu ptprm-menu--wp',
                    'fallback_cb'=> [ $this, 'render_custom_menu' ],
                    'depth'      => 2,
                ]
            );
            return;
        }

        $this->render_custom_menu();
    }

    public function render_custom_menu(): void {
        $items = ptprm_get_header_menu_items();
        if ( ! $items ) {
            return;
        }

        echo '<ul class="ptprm-menu">';
        foreach ( $items as $item ) {
            $this->render_menu_item( $item );
        }
        echo '</ul>';
    }

    /**
     * @param array{label:string,url:string,target:string,highlight?:int,children?:array} $item Item.
     */
    private function render_menu_item( array $item ): void {
        $label = $item['label'] ?? '';
        if ( $label === '' ) {
            return;
        }
        $url    = ptprm_resolve_url( $item['url'] ?? '#' );
        $target = ( $item['target'] ?? '_self' ) === '_blank' ? '_blank' : '_self';
        $rel    = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
        $kids   = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : [];
        $has_kids = count( array_filter( $kids, static function ( $c ) {
            return is_array( $c ) && trim( (string) ( $c['label'] ?? '' ) ) !== '';
        } ) ) > 0;

        $li_cls = [];
        if ( ! empty( $item['highlight'] ) ) {
            $li_cls[] = 'is-cta';
        }
        if ( $has_kids ) {
            $li_cls[] = 'has-children';
            $li_cls[] = 'menu-item-has-children';
        }
        if ( self::is_current_url( $url ) ) {
            $li_cls[] = 'is-active';
        }

        echo '<li class="' . esc_attr( implode( ' ', $li_cls ) ) . '">';
        if ( $has_kids ) {
            echo '<div class="ptprm-menu-item-head">';
            echo '<a href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $label ) . '</a>';
            echo '<button type="button" class="ptprm-submenu-toggle" aria-expanded="false" aria-haspopup="true" aria-label="'
                . esc_attr(
                    sprintf(
                        /* translators: %s: parent menu label */
                        __( 'Buka submenu %s', 'ptsbi-premium' ),
                        $label
                    )
                )
                . '"><span class="ptprm-submenu-toggle-icon" aria-hidden="true"></span></button>';
            echo '</div>';
            echo '<ul class="ptprm-submenu">';
            foreach ( $kids as $child ) {
                if ( is_array( $child ) ) {
                    $this->render_submenu_item( $child );
                }
            }
            echo '</ul>';
        } else {
            echo '<a href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $label ) . '</a>';
        }
        echo '</li>';
    }

    /**
     * Item submenu (satu tingkat, tanpa dropdown bersarang).
     *
     * @param array{label:string,url:string,target:string} $item Item.
     */
    private function render_submenu_item( array $item ): void {
        $label = $item['label'] ?? '';
        if ( $label === '' ) {
            return;
        }
        $url    = ptprm_resolve_url( $item['url'] ?? '#' );
        $target = ( $item['target'] ?? '_self' ) === '_blank' ? '_blank' : '_self';
        $rel    = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
        $li_cls = self::is_current_url( $url ) ? 'is-active' : '';

        echo '<li class="' . esc_attr( $li_cls ) . '">';
        echo '<a href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $label ) . '</a>';
        echo '</li>';
    }

    private static function is_current_url( string $url ): bool {
        if ( ! $url || $url === '#' ) {
            return false;
        }
        $current = trailingslashit( (string) wp_parse_url( home_url( add_query_arg( [] ) ), PHP_URL_PATH ) );
        $path    = trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
        return $path === $current;
    }

    /**
     * @param array<string, mixed> $args Nav menu args.
     * @return array<string, mixed>
     */
    public function filter_wp_menu_args( $args ) {
        if ( ! self::is_active() || ( $args['theme_location'] ?? '' ) !== 'ptprm-primary' ) {
            return $args;
        }
        $args['container']  = false;
        $args['menu_class'] = 'ptprm-menu ptprm-menu--wp';
        return $args;
    }
}
