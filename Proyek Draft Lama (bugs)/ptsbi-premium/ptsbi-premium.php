<?php
/**
 * Plugin Name: PTSBI Premium
 * Plugin URI: https://ptsbi.org
 * Description: Tampilan premium ptsbi.org: font nyaman di laptop, layout rapi (HP/iPad/monitor), menu desktop. Tanpa CTA di halaman Tarombo — akses app lewat beranda.
 * Version: 1.4.0
 * Author: PTSBI Digital
 * Author URI: https://ptsbi.org
 * License: GPL-2.0-or-later
 * Text Domain: ptsbi-premium
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PTSBI_PREMIUM_VERSION', '1.4.0');
define('PTSBI_PREMIUM_FILE', __FILE__);
define('PTSBI_PREMIUM_DIR', plugin_dir_path(__FILE__));
define('PTSBI_PREMIUM_URL', plugin_dir_url(__FILE__));

function ptsbi_premium_default_settings(): array {
    return [
        'tarombo_app_url' => 'https://tarombo.ptsbi.org',
        'tarombo_register_url' => '',
        'org_name' => 'Punguan Toga Samosir Boru Bere Ibebere',
        'org_email' => 'info@ptsbi.org',
        'enable_styles' => true,
        'enable_cta' => true,
        'enable_tarombo_page_button' => true,
        'tarombo_page_button_label' => 'Lihat Tarombo Kami',
        'enable_seo_fallback' => true,
        'enable_comments_off_posts' => true,
        'tarombo_page_slug' => 'tarombo',
    ];
}

register_activation_hook(__FILE__, static function (): void {
    $defaults = ptsbi_premium_default_settings();
    $existing = get_option('ptsbi_premium_settings', []);
    if (!is_array($existing)) {
        $existing = [];
    }
    update_option('ptsbi_premium_settings', wp_parse_args($existing, $defaults));
    set_transient('ptsbi_premium_activated', 1, DAY_IN_SECONDS);
});

register_deactivation_hook(__FILE__, static function (): void {
    delete_transient('ptsbi_premium_activated');
});

add_action('plugins_loaded', static function (): void {
    $saved = get_option('ptsbi_premium_settings', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    $merged = wp_parse_args($saved, ptsbi_premium_default_settings());
    if (empty($merged['tarombo_app_url'])) {
        $merged['tarombo_app_url'] = 'https://tarombo.ptsbi.org';
    }
    if ($saved !== $merged) {
        update_option('ptsbi_premium_settings', $merged);
    }
});

final class PTSBI_Premium {
    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'activation_notice']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('after_setup_theme', [__CLASS__, 'setup']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 999);
        add_action('wp_head', [__CLASS__, 'preconnect'], 2);
        add_action('wp_head', [__CLASS__, 'seo_meta'], 5);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_filter('nav_menu_link_attributes', [__CLASS__, 'nav_link_attrs'], 10, 3);
        add_shortcode('ptsbi_tarombo_cta', [__CLASS__, 'shortcode_tarombo_cta']);
        add_filter('the_content', [__CLASS__, 'append_tarombo_page_button'], 20);
        add_action('wp_footer', [__CLASS__, 'footer_brand'], 5);
        add_filter('comment_form_defaults', [__CLASS__, 'disable_comments_message']);
        add_filter('comments_open', [__CLASS__, 'comments_closed'], 20, 2);
    }

    public static function get_options(): array {
        return wp_parse_args(get_option('ptsbi_premium_settings', []), ptsbi_premium_default_settings());
    }

    public static function activation_notice(): void {
        if (!get_transient('ptsbi_premium_activated') || !current_user_can('manage_options')) {
            return;
        }
        delete_transient('ptsbi_premium_activated');
        add_action('admin_notices', static function (): void {
            $url = admin_url('options-general.php?page=ptsbi-premium');
            echo '<div class="notice notice-success is-dismissible"><p><strong>PTSBI Premium v' . esc_html(PTSBI_PREMIUM_VERSION) . ' aktif.</strong> ';
            echo 'Font &amp; layout diperbesar. Akses Tarombo lewat tombol <strong>Bergabung</strong> di beranda. <a href="' . esc_url($url) . '">Pengaturan</a></p></div>';
        });
    }

    public static function setup(): void {
        add_theme_support('title-tag');
        add_theme_support('responsive-embeds');
    }

    public static function enqueue_assets(): void {
        if (is_admin() || !self::get_options()['enable_styles']) {
            return;
        }
        $deps = [];
        if (wp_style_is('elementor-frontend', 'registered')) {
            $deps[] = 'elementor-frontend';
        }
        wp_enqueue_style('ptsbi-premium', PTSBI_PREMIUM_URL . 'assets/ptsbi-premium.css', $deps, PTSBI_PREMIUM_VERSION);
        wp_enqueue_script('ptsbi-premium', PTSBI_PREMIUM_URL . 'assets/ptsbi-premium.js', [], PTSBI_PREMIUM_VERSION, true);
    }

    public static function preconnect(): void {
        if (is_admin() || !self::get_options()['enable_styles']) {
            return;
        }
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        wp_enqueue_style('ptsbi-premium-fonts', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap', [], null);
    }

    public static function seo_meta(): void {
        $opts = self::get_options();
        if (!$opts['enable_seo_fallback'] || defined('WPSEO_VERSION') || class_exists('RankMath', false)) {
            return;
        }
        $site = $opts['org_name'] ?: get_bloginfo('name');
        $desc = get_bloginfo('description');
        if (is_singular()) {
            $post_desc = has_excerpt() ? get_the_excerpt() : wp_trim_words(wp_strip_all_tags(get_the_content()), 28);
            if ($post_desc) {
                $desc = $post_desc;
            }
        }
        $desc = wp_strip_all_tags($desc);
        $url = is_singular() ? get_permalink() : home_url('/');
        $title = wp_get_document_title();
        $image = get_site_icon_url(512);
        if (is_singular() && has_post_thumbnail()) {
            $image = get_the_post_thumbnail_url(null, 'large') ?: $image;
        }
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        if (is_front_page()) {
            echo '<script type="application/ld+json">' . wp_json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $site,
                'url' => home_url('/'),
                'description' => $desc,
                'logo' => $image,
                'email' => $opts['org_email'] ?: null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        }
    }

    public static function body_class(array $classes): array {
        if (self::get_options()['enable_styles']) {
            $classes[] = 'ptsbi-premium-active';
        }
        if (self::is_tarombo_page()) {
            $classes[] = 'ptsbi-page-tarombo';
        }
        return $classes;
    }

    public static function nav_link_attrs(array $atts, $item, $args): array {
        $atts['class'] = trim(($atts['class'] ?? '') . ' ptsbi-nav-link');
        return $atts;
    }

    public static function is_tarombo_page(): bool {
        return is_page(self::get_options()['tarombo_page_slug'] ?: 'tarombo');
    }

    /** Shortcode opsional — default tidak dipakai di halaman Tarombo. */
    public static function shortcode_tarombo_cta($atts = []): string {
        if (!self::get_options()['enable_cta']) {
            return '';
        }
        $opts = self::get_options();
        $atts = shortcode_atts(['app_url' => $opts['tarombo_app_url'], 'register_url' => $opts['tarombo_register_url']], $atts, 'ptsbi_tarombo_cta');
        return self::render_tarombo_cta($atts['app_url'], $atts['register_url']);
    }

    public static function render_tarombo_cta(string $app_url = '', string $register_url = ''): string {
        return self::render_tarombo_page_button($app_url);
    }

    public static function render_tarombo_page_button(string $app_url = ''): string {
        $opts = self::get_options();
        $app_url = ($app_url ?: $opts['tarombo_app_url']) ?: 'https://tarombo.ptsbi.org';
        $label = $opts['tarombo_page_button_label'] ?: 'Lihat Tarombo Kami';
        ob_start();
        ?>
        <div class="ptsbi-content-shell ptsbi-tarombo-cta-wrap">
            <section class="ptsbi-tarombo-cta" aria-label="Akses aplikasi Tarombo">
                <div class="ptsbi-tarombo-cta__inner">
                    <h2 class="ptsbi-tarombo-cta__title">Pohon Tarombo PTSBI</h2>
                    <p class="ptsbi-tarombo-cta__lead">Lihat silsilah keluarga besar marga Samosir melalui aplikasi Tarombo.</p>
                    <div class="ptsbi-tarombo-cta__actions">
                        <a class="ptsbi-btn ptsbi-btn--gold" href="<?php echo esc_url($app_url); ?>" rel="noopener"><?php echo esc_html($label); ?></a>
                    </div>
                </div>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function append_tarombo_page_button(string $content): string {
        if (!self::is_tarombo_page() || empty(self::get_options()['enable_tarombo_page_button'])) {
            return $content;
        }
        if (strpos($content, 'ptsbi-tarombo-cta-wrap') !== false) {
            return $content;
        }
        return $content . self::render_tarombo_page_button();
    }

    public static function footer_brand(): void {
        if (is_front_page() && self::get_options()['enable_styles']) {
            echo '<div class="ptsbi-footer-accent" aria-hidden="true"></div>';
        }
    }

    public static function disable_comments_message(array $defaults): array {
        $defaults['title_reply'] = 'Komentar ditutup untuk artikel ini.';
        return $defaults;
    }

    public static function comments_closed(bool $open, int $post_id): bool {
        if (self::get_options()['enable_comments_off_posts'] && get_post_type($post_id) === 'post') {
            return false;
        }
        return $open;
    }

    public static function admin_menu(): void {
        add_options_page('PTSBI Premium', 'PTSBI Premium', 'manage_options', 'ptsbi-premium', [__CLASS__, 'settings_page']);
    }

    public static function register_settings(): void {
        register_setting('ptsbi_premium', 'ptsbi_premium_settings', ['type' => 'array', 'sanitize_callback' => [__CLASS__, 'sanitize_settings']]);
    }

    public static function sanitize_settings($input): array {
        $existing = self::get_options();
        $d = ptsbi_premium_default_settings();
        if (!is_array($input)) {
            return $existing;
        }
        return [
            'tarombo_app_url' => esc_url_raw($input['tarombo_app_url'] ?? $existing['tarombo_app_url']),
            'tarombo_register_url' => esc_url_raw($input['tarombo_register_url'] ?? $existing['tarombo_register_url']),
            'org_name' => sanitize_text_field($input['org_name'] ?? $existing['org_name'] ?: $d['org_name']),
            'org_email' => sanitize_email($input['org_email'] ?? $existing['org_email']),
            'enable_styles' => array_key_exists('enable_styles', $input) ? !empty($input['enable_styles']) : (bool) $existing['enable_styles'],
            'enable_cta' => array_key_exists('enable_cta', $input) ? !empty($input['enable_cta']) : (bool) $existing['enable_cta'],
            'enable_tarombo_page_button' => array_key_exists('enable_tarombo_page_button', $input) ? !empty($input['enable_tarombo_page_button']) : (bool) ($existing['enable_tarombo_page_button'] ?? true),
            'tarombo_page_button_label' => sanitize_text_field($input['tarombo_page_button_label'] ?? $existing['tarombo_page_button_label'] ?? $d['tarombo_page_button_label']),
            'enable_seo_fallback' => array_key_exists('enable_seo_fallback', $input) ? !empty($input['enable_seo_fallback']) : (bool) $existing['enable_seo_fallback'],
            'enable_comments_off_posts' => array_key_exists('enable_comments_off_posts', $input) ? !empty($input['enable_comments_off_posts']) : (bool) $existing['enable_comments_off_posts'],
            'tarombo_page_slug' => sanitize_title($input['tarombo_page_slug'] ?? $existing['tarombo_page_slug'] ?: 'tarombo'),
        ];
    }

    public static function settings_page(): void {
        $o = self::get_options();
        ?>
        <div class="wrap">
            <h1>PTSBI Premium <small style="font-weight:normal;color:#666;">v<?php echo esc_html(PTSBI_PREMIUM_VERSION); ?></small></h1>
            <p>Aplikasi Tarombo diakses dari beranda (<strong>Bergabung Sekarang</strong>). Halaman <code>/tarombo/</code> hanya penjelasan.</p>
            <form method="post" action="options.php">
                <?php settings_fields('ptsbi_premium'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="tarombo_app_url">URL aplikasi Tarombo</label></th>
                        <td>
                            <input name="ptsbi_premium_settings[tarombo_app_url]" id="tarombo_app_url" type="url" class="large-text" value="<?php echo esc_attr($o['tarombo_app_url']); ?>" />
                            <p class="description">Tombol di halaman <code>/tarombo/</code> dan shortcode.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tarombo_page_button_label">Label tombol halaman Tarombo</label></th>
                        <td><input name="ptsbi_premium_settings[tarombo_page_button_label]" id="tarombo_page_button_label" type="text" class="regular-text" value="<?php echo esc_attr($o['tarombo_page_button_label'] ?? 'Lihat Tarombo Kami'); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="tarombo_page_slug">Slug halaman penjelasan</label></th>
                        <td><input name="ptsbi_premium_settings[tarombo_page_slug]" id="tarombo_page_slug" type="text" value="<?php echo esc_attr($o['tarombo_page_slug']); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="org_name">Nama organisasi</label></th>
                        <td><input name="ptsbi_premium_settings[org_name]" id="org_name" type="text" class="large-text" value="<?php echo esc_attr($o['org_name']); ?>" /></td>
                    </tr>
                </table>
                <p><label><input type="checkbox" name="ptsbi_premium_settings[enable_styles]" value="1" <?php checked($o['enable_styles']); ?> /> <strong>Tampilan premium</strong> — font lebih besar (laptop) + layout rapi semua halaman</label></p>
                <p><label><input type="checkbox" name="ptsbi_premium_settings[enable_tarombo_page_button]" value="1" <?php checked(!empty($o['enable_tarombo_page_button'])); ?> /> Tombol <strong>Lihat Tarombo Kami</strong> di halaman <code>/tarombo/</code></label></p>
                <p><label><input type="checkbox" name="ptsbi_premium_settings[enable_cta]" value="1" <?php checked($o['enable_cta']); ?> /> Shortcode <code>[ptsbi_tarombo_cta]</code> (opsional)</label></p>
                <p><label><input type="checkbox" name="ptsbi_premium_settings[enable_seo_fallback]" value="1" <?php checked($o['enable_seo_fallback']); ?> /> SEO dasar (off jika Yoast aktif)</label></p>
                <p><label><input type="checkbox" name="ptsbi_premium_settings[enable_comments_off_posts]" value="1" <?php checked($o['enable_comments_off_posts']); ?> /> Tutup komentar berita</label></p>
                <?php submit_button('Simpan'); ?>
            </form>
        </div>
        <?php
    }
}

PTSBI_Premium::init();
