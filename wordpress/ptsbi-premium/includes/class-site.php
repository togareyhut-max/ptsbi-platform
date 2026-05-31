<?php
/**
 * Fitur situs: header sticky, tombol halaman Tarombo.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Site {

    public function __construct() {
        add_filter( 'body_class', [ $this, 'body_class' ] );
        add_action( 'wp_head', [ $this, 'sticky_header_early_style' ], 9999 );
        add_filter( 'astra_sticky_header', [ $this, 'disable_astra_sticky' ] );
        add_filter( 'the_content', [ $this, 'append_tarombo_page_button' ], 20 );
        add_shortcode( 'ptprm_tarombo_button', [ $this, 'shortcode_tarombo_button' ] );
    }

    public function disable_astra_sticky( $enabled ) {
        if ( is_admin() || empty( ptprm_get( 'sticky_header', 1 ) ) ) {
            return $enabled;
        }
        return false;
    }

    public function body_class( $classes ) {
        if ( is_admin() ) {
            return $classes;
        }
        if ( ! empty( ptprm_get( 'sticky_header', 1 ) ) ) {
            $classes[] = 'ptprm-sticky-header';
        }
        return $classes;
    }

    /**
     * CSS kritis di akhir head — header fixed sebelum JS, mengalahkan sticky Astra/Elementor.
     */
    public function sticky_header_early_style() {
        if ( is_admin() || empty( ptprm_get( 'sticky_header', 1 ) ) ) {
            return;
        }
        $admin = is_admin_bar_showing();
        ?>
<style id="ptprm-sticky-early">
:root { --ptprm-header-height: 80px; }
html.ptprm-sticky-header { scroll-padding-top: var(--ptprm-header-height); }
body.ptprm-sticky-header {
    padding-top: var(--ptprm-header-height) !important;
    overflow-x: clip !important;
}
body.ptprm-sticky-header.ptprm-is-home,
body.admin-bar.ptprm-sticky-header.ptprm-is-home {
    padding-top: 0 !important;
}
body.ptprm-sticky-header #ptprm-site-header,
body.ptprm-sticky-header #masthead,
body.ptprm-sticky-header .site-header,
body.ptprm-sticky-header .elementor-location-header,
body.ptprm-sticky-header .ast-primary-header-bar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    z-index: 99990 !important;
    transform: none !important;
    margin: 0 !important;
    transition: none !important;
    box-sizing: border-box;
}
<?php if ( $admin ) : ?>
body.admin-bar.ptprm-sticky-header #ptprm-site-header,
body.admin-bar.ptprm-sticky-header #masthead,
body.admin-bar.ptprm-sticky-header .site-header,
body.admin-bar.ptprm-sticky-header .elementor-location-header,
body.admin-bar.ptprm-sticky-header .ast-primary-header-bar {
    top: 32px !important;
}
body.admin-bar.ptprm-sticky-header {
    padding-top: calc(var(--ptprm-header-height) + 32px) !important;
}
body.admin-bar.ptprm-sticky-header.ptprm-is-home {
    padding-top: 0 !important;
}
@media (max-width: 782px) {
    body.admin-bar.ptprm-sticky-header #ptprm-site-header,
    body.admin-bar.ptprm-sticky-header #masthead,
    body.admin-bar.ptprm-sticky-header .site-header,
    body.admin-bar.ptprm-sticky-header .elementor-location-header,
    body.admin-bar.ptprm-sticky-header .ast-primary-header-bar {
        top: 46px !important;
    }
    body.admin-bar.ptprm-sticky-header {
        padding-top: calc(var(--ptprm-header-height) + 46px) !important;
    }
    body.admin-bar.ptprm-sticky-header.ptprm-is-home {
        padding-top: 0 !important;
    }
}
<?php endif; ?>
</style>
        <?php
    }

    public static function is_tarombo_page(): bool {
        if ( ! is_page() ) {
            return false;
        }
        $slug = sanitize_title( ptprm_get( 'tarombo_page_slug', 'tarombo' ) ?: 'tarombo' );
        $post = get_queried_object();
        return $post instanceof WP_Post && $post->post_name === $slug;
    }

    public static function render_tarombo_page_button( $app_url = '' ): string {
        $o = ptprm_options();
        if ( empty( $o['enable_tarombo_page_button'] ) ) {
            return '';
        }
        $app_url = $app_url ?: ( $o['tarombo_app_url'] ?? '' );
        if ( ! $app_url ) {
            return '';
        }
        $label = $o['tarombo_page_button_label'] ?: __( 'Buka aplikasi', 'ptsbi-premium' );
        $title = $o['tarombo_cta_title'] ?? __( 'Aplikasi / layanan terkait', 'ptsbi-premium' );
        $lead  = $o['tarombo_cta_lead'] ?? '';

        ob_start();
        ?>
        <div class="ptprm-tarombo-cta-wrap">
            <section class="ptprm-tarombo-cta" aria-label="<?php esc_attr_e( 'Akses aplikasi eksternal', 'ptsbi-premium' ); ?>">
                <div class="ptprm-tarombo-cta__inner">
                    <h2 class="ptprm-tarombo-cta__title"><?php echo esc_html( $title ); ?></h2>
                    <?php if ( $lead ) : ?>
                    <p class="ptprm-tarombo-cta__lead"><?php echo esc_html( $lead ); ?></p>
                    <?php endif; ?>
                    <div class="ptprm-tarombo-cta__actions">
                        <a class="ptprm-cta ptprm-cta-1 ptprm-cta-solid ptprm-cta-size-medium" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="ptprm-cta-label"><?php echo esc_html( $label ); ?></span>
                        </a>
                    </div>
                </div>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function append_tarombo_page_button( $content ) {
        if ( ! self::is_tarombo_page() ) {
            return $content;
        }
        if ( strpos( $content, 'ptprm-tarombo-cta-wrap' ) !== false ) {
            return $content;
        }
        return $content . self::render_tarombo_page_button();
    }

    public function shortcode_tarombo_button( $atts ) {
        $atts = shortcode_atts(
            [ 'url' => '' ],
            $atts,
            'ptprm_tarombo_button'
        );
        return self::render_tarombo_page_button( $atts['url'] );
    }
}
