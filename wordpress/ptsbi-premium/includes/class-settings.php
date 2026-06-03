<?php
/**
 * Admin settings page: tabs + sanitize + render.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Settings {

    const PAGE  = 'ptprm-settings';
    const GROUP = 'ptprm_group';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_post_ptprm_save_settings', [ $this, 'handle_save' ] );
        add_action( 'admin_post_ptprm_import_backup', [ $this, 'handle_import_backup' ] );
        add_action( 'wp_ajax_ptprm_save_settings', [ $this, 'handle_ajax_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'load-toplevel_page_' . self::PAGE, [ $this, 'no_cache_headers' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( PTPRM_FILE ), [ $this, 'action_links' ] );
    }

    /** Admin settings tidak boleh di-cache (form lama = simpan gagal). */
    public function no_cache_headers(): void {
        nocache_headers();
    }

    /**
     * Ambil raw POST — JSON / base64 (1 field) atau array klasik.
     *
     * @return array<string, mixed>
     */
    private function raw_from_request() {
        $json = null;

        if ( isset( $_POST['ptprm_payload_b64'] ) && is_string( $_POST['ptprm_payload_b64'] ) ) {
            $b64  = preg_replace( '/\s+/', '', wp_unslash( $_POST['ptprm_payload_b64'] ) );
            $json = base64_decode( $b64, true );
        }

        if ( ( ! is_string( $json ) || $json === '' ) && isset( $_POST['ptprm_payload'] ) && is_string( $_POST['ptprm_payload'] ) ) {
            $json = wp_unslash( $_POST['ptprm_payload'] );
        }

        if ( is_string( $json ) && $json !== '' ) {
            $decoded = $this->decode_payload_json( $json );
            if ( is_array( $decoded ) && count( $decoded ) > 0 ) {
                return $decoded;
            }
        }

        if ( isset( $_POST[ PTPRM_OPTION ] ) && is_array( $_POST[ PTPRM_OPTION ] ) ) {
            $classic = wp_unslash( $_POST[ PTPRM_OPTION ] );
            return is_array( $classic ) ? $classic : [];
        }

        return [];
    }

    /**
     * @param string $json Raw JSON string.
     * @return array<string, mixed>|null
     */
    private function decode_payload_json( $json ) {
        $decoded = json_decode( $json, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }
        if ( function_exists( 'wp_json_decode' ) ) {
            $decoded = wp_json_decode( $json, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }
        $stripped = wp_unslash( $json );
        if ( $stripped !== $json ) {
            $decoded = json_decode( $stripped, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }
        return null;
    }

    /**
     * @return string Redirect URL after save.
     */
    private function persist_and_redirect_url() {
        $raw = $this->raw_from_request();
        if ( empty( $raw ) ) {
            return PTPRM_Bootstrap::settings_admin_url( [ 'ptprm-save-error' => 'empty' ] );
        }

        if ( count( $raw ) < 8 ) {
            $stored = get_option( PTPRM_OPTION, [] );
            if ( is_array( $stored ) && $stored ) {
                $raw = array_merge( ptprm_normalize_option_for_storage( $stored ), $raw );
            }
        }
        if ( count( $raw ) < 8 ) {
            return PTPRM_Bootstrap::settings_admin_url( [ 'ptprm-save-error' => 'partial' ] );
        }

        $clean = $this->sanitize( $raw );
        $clean['_site_fingerprint'] = PTPRM_Bootstrap::site_fingerprint();
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( $clean ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );
        wp_cache_delete( 'alloptions', 'options' );

        return PTPRM_Bootstrap::settings_admin_url( [ 'settings-updated' => 'true' ] );
    }

    /** Pulihkan pengaturan dari file JSON (cadangan Unduh cadangan JSON). */
    public function handle_import_backup() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Anda tidak punya izin mengimpor cadangan ini.', 'ptsbi-premium' ) );
        }
        check_admin_referer( 'ptprm_import_backup' );

        $redirect = PTPRM_Bootstrap::settings_admin_url();
        if ( empty( $_FILES['ptprm_backup_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ptprm_backup_file']['tmp_name'] ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm-import-error', 'nofile', $redirect ) );
            exit;
        }

        $raw = file_get_contents( $_FILES['ptprm_backup_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( ! is_string( $raw ) || $raw === '' ) {
            wp_safe_redirect( add_query_arg( 'ptprm-import-error', 'empty', $redirect ) );
            exit;
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) || ! ptprm_is_importable_option_array( $data ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm-import-error', 'invalid', $redirect ) );
            exit;
        }

        $clean = ptprm_import_option_from_array( $data );
        update_option( PTPRM_OPTION, $clean, true );
        wp_cache_delete( PTPRM_OPTION, 'options' );
        wp_cache_delete( 'alloptions', 'options' );
        delete_transient( 'ptprm_storage_repaired_' . PTPRM_VERSION );

        wp_safe_redirect( add_query_arg( 'ptprm-imported', '1', $redirect ) );
        exit;
    }

    /** Fallback POST klasik (admin-post.php). */
    public function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Anda tidak punya izin menyimpan pengaturan ini.', 'ptsbi-premium' ) );
        }
        $action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
        if ( 'ptprm_save_settings' !== $action ) {
            wp_safe_redirect(
                PTPRM_Bootstrap::settings_admin_url(
                    [
                        'ptprm-save-error' => 'bad-action',
                    ]
                )
            );
            exit;
        }
        check_admin_referer( self::GROUP . '-options' );
        wp_safe_redirect( $this->persist_and_redirect_url() );
        exit;
    }

    /** Simpan via admin-ajax.php — dipakai form admin (1 payload JSON). */
    public function handle_ajax_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Anda tidak punya izin menyimpan pengaturan ini.', 'ptsbi-premium' ) ], 403 );
        }

        $nonce = '';
        if ( isset( $_POST['_ajax_nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) );
        } elseif ( isset( $_POST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
        }
        if ( ! wp_verify_nonce( $nonce, self::GROUP . '-options' ) ) {
            wp_send_json_error(
                [
                    'message' => __( 'Sesi admin habis — refresh halaman (F5), lalu simpan lagi. Pastikan URL admin sama dengan Pengaturan → Umum (siteurl).', 'ptsbi-premium' ),
                ],
                403
            );
        }

        $url = $this->persist_and_redirect_url();
        if ( false !== strpos( $url, 'ptprm-save-error=empty' ) ) {
            wp_send_json_error(
                [
                    'message' => __( 'Server tidak menerima data pengaturan. Refresh halaman, pastikan plugin v1.2.2+, lalu coba lagi.', 'ptsbi-premium' ),
                ],
                400
            );
        }
        if ( false !== strpos( $url, 'ptprm-save-error=partial' ) ) {
            wp_send_json_error(
                [
                    'message' => __( 'Data form tidak lengkap (terpotong firewall atau cache). Nonaktifkan sementara pemblokir POST di security plugin, purge cache, refresh, lalu simpan.', 'ptsbi-premium' ),
                ],
                400
            );
        }

        wp_send_json_success( [ 'redirect' => $url ] );
    }

    public function menu() {
        add_menu_page(
            __( 'Premium Plugin', 'ptsbi-premium' ),
            __( 'Premium Plugin', 'ptsbi-premium' ),
            'manage_options',
            self::PAGE,
            [ $this, 'render' ],
            'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2.5" y="2.5" width="15" height="15" rx="2"/><path d="M10 5l1.3 3 3.2.2-2.5 2 .8 3.2L10 11.7 7.2 13.4l.8-3.2-2.5-2 3.2-.2z"/></svg>'
            ),
            58
        );
    }

    public function action_links( $links ) {
        $url = admin_url( 'admin.php?page=' . self::PAGE );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Pengaturan', 'ptsbi-premium' ) . '</a>' );
        return $links;
    }

    public function assets( $hook ) {
        if ( 'toplevel_page_' . self::PAGE !== $hook ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'ptprm-admin', PTPRM_URL . 'assets/css/admin.css', [], PTPRM_VERSION );

        wp_register_script( 'ptprm-save', '', [], PTPRM_VERSION, true );
        wp_enqueue_script( 'ptprm-save' );
        wp_add_inline_script( 'ptprm-save', $this->inline_save_script(), 'before' );

        wp_enqueue_script( 'ptprm-admin', PTPRM_URL . 'assets/js/admin.js', [ 'jquery', 'wp-color-picker', 'jquery-ui-sortable', 'ptprm-save' ], PTPRM_VERSION, true );
        wp_localize_script( 'ptprm-admin', 'PTPRM_ADMIN', [
            'pluginUrl' => PTPRM_URL,
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( self::GROUP . '-options' ),
            'i18n'      => [
                'saving'  => __( 'Menyimpan…', 'ptsbi-premium' ),
                'save'    => __( 'Simpan Perubahan', 'ptsbi-premium' ),
                'error'   => __( 'Gagal menyimpan. Refresh halaman, login ulang, lalu coba lagi.', 'ptsbi-premium' ),
            ],
        ] );
    }

    /**
     * Simpan via admin-post.php (tanpa admin-ajax) — paling andal di semua host.
     */
    private function inline_save_script(): string {
        return <<<'JS'
(function () {
    'use strict';
    function utf8ToBase64(str) {
        try { return btoa(unescape(encodeURIComponent(str))); } catch (e) { return btoa(str); }
    }
    function collect(form) {
        var data = {}, seen = {}, re = /^ptprm_options\[(.+)\]$/;
        function set(k, v) { if (!k || seen[k]) return; data[k] = v; seen[k] = true; }
        function ingest(el, key) {
            if (!key) return;
            if (el.type === 'checkbox') { set(key, el.checked ? '1' : '0'); return; }
            if (el.type === 'radio') { if (el.checked) set(key, el.value); return; }
            if (el.tagName === 'SELECT' || el.type === 'hidden' || el.type === 'text' || el.type === 'url' || el.type === 'number' || el.type === 'range' || el.tagName === 'TEXTAREA') {
                set(key, el.value);
            }
        }
        form.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (el) {
            if (el.disabled) return;
            var m = el.name && el.name.match(re);
            if (m) ingest(el, m[1]);
        });
        form.querySelectorAll('[data-ptprm]').forEach(function (el) {
            ingest(el, el.getAttribute('data-ptprm'));
        });
        return data;
    }
    function readRepeaterJson(hidden) {
        if (!hidden) return [];
        var b64 = hidden.getAttribute('data-ptprm-json-b64');
        if (b64) {
            try {
                var raw = atob(b64);
                try { raw = decodeURIComponent(escape(raw)); } catch (e2) {}
                return JSON.parse(raw);
            } catch (e) {}
        }
        try { return JSON.parse(hidden.value || '[]'); } catch (e2) { return []; }
    }
    function syncRepeaters(form) {
        [
            { type: 'values', hidden: '#ptprm-values-items-json', wrap: '#ptprm-values-repeater' },
            { type: 'menu', hidden: '#ptprm-header-menu-json', wrap: '#ptprm-menu-repeater' },
            { type: 'team', hidden: '#ptprm-team-items-json', wrap: '#ptprm-team-repeater' },
            { type: 'pdf', hidden: '#ptprm-pdf-items-json', wrap: '#ptprm-pdf-repeater' }
        ].forEach(function (cfg) {
            var hidden = form.querySelector(cfg.hidden);
            var list = form.querySelector(cfg.wrap + ' .ptprm-repeater-list');
            if (!hidden || !list) return;
            if (!list.children.length) {
                var existing = readRepeaterJson(hidden);
                if (existing.length) {
                    hidden.value = JSON.stringify(existing);
                    return;
                }
            }
            var items = [];
            list.querySelectorAll('.ptprm-repeater-row').forEach(function (row) {
                if (cfg.type === 'values') {
                    var t = (row.querySelector('[data-field="title"]') || {}).value || '';
                    t = t.trim();
                    if (!t) return;
                    items.push({
                        icon: (row.querySelector('[data-field="icon"]') || {}).value || 'users',
                        title: t,
                        desc: (row.querySelector('[data-field="desc"]') || {}).value || ''
                    });
                } else if (cfg.type === 'team') {
                    var nm = (row.querySelector('[data-field="name"]') || {}).value || '';
                    nm = nm.trim();
                    if (!nm) return;
                    items.push({
                        image: (row.querySelector('[data-field="image"]') || {}).value || '',
                        name: nm,
                        role: (row.querySelector('[data-field="role"]') || {}).value || '',
                        url: (row.querySelector('[data-field="url"]') || {}).value || '',
                        group: (row.querySelector('[data-field="group"]') || {}).value || ''
                    });
                } else if (cfg.type === 'pdf') {
                    var pdf = (row.querySelector('[data-field="pdf"]') || {}).value || '';
                    pdf = String(pdf).trim();
                    if (!pdf) return;
                    items.push({
                        title: (row.querySelector('[data-field="title"]') || {}).value || '',
                        pdf: pdf,
                        cover: (row.querySelector('[data-field="cover"]') || {}).value || ''
                    });
                } else {
                    var label = (row.querySelector('[data-field="label"]') || {}).value || '';
                    label = label.trim();
                    if (!label) return;
                    var children = [];
                    row.querySelectorAll('.ptprm-menu-children-list > .ptprm-menu-child-row').forEach(function (childRow) {
                        var cl = (childRow.querySelector('[data-field="label"]') || {}).value || '';
                        cl = cl.trim();
                        if (!cl) return;
                        children.push({
                            label: cl,
                            url: (childRow.querySelector('[data-field="url"]') || {}).value || '/',
                            target: (childRow.querySelector('[data-field="target"]') || {}).value || '_self',
                            highlight: 0,
                            children: []
                        });
                    });
                    items.push({
                        label: label,
                        url: (row.querySelector('[data-field="url"]') || {}).value || '/',
                        target: (row.querySelector('[data-field="target"]') || {}).value || '_self',
                        highlight: (row.querySelector('[data-field="highlight"]') || {}).checked ? 1 : 0,
                        children: children
                    });
                }
            });
            hidden.value = JSON.stringify(items);
        });
    }
    function bind() {
        var form = document.getElementById('ptprm-form');
        if (!form || form.dataset.ptprmSaveReady) return;
        form.dataset.ptprmSaveReady = '1';
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('#submit, [name="ptprm_save"]');
            if (btn) { btn.disabled = true; btn.value = 'Menyimpan…'; }
            syncRepeaters(form);
            if (window.ptprmSyncHomeSectionsOrder) {
                window.ptprmSyncHomeSectionsOrder();
            }
            if (window.jQuery && jQuery.fn.wpColorPicker) {
                form.querySelectorAll('.ptprm-color').forEach(function (el) {
                    var $el = jQuery(el);
                    if ($el.hasClass('wp-color-picker')) {
                        try { var c = $el.wpColorPicker('color'); if (c) el.value = c; } catch (err) {}
                    }
                });
            }
            var json = JSON.stringify(collect(form));
            var b64 = form.querySelector('#ptprm-payload-b64-field');
            if (b64) b64.value = utf8ToBase64(json);
            form.querySelectorAll('input[name="action"]').forEach(function (el, i) {
                if (i > 0) el.remove();
                else el.value = 'ptprm_save_settings';
            });
            var keep = { action: 1, ptprm_payload_b64: 1, _wpnonce: 1, _wp_http_referer: 1 };
            form.querySelectorAll('[name]').forEach(function (el) {
                if (!el.name || keep[el.name]) return;
                el.disabled = true;
            });
            form.submit();
        }, true);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
JS;
    }

    public function sanitize( $input ) {
        if ( ! is_array( $input ) ) {
            return ptprm_options();
        }
        $existing = ptprm_options();
        $d        = ptprm_defaults();
        $c        = [];

        $bools = [
            'enabled',
            'hero_show', 'hero_eyebrow_show', 'hero_title_show', 'hero_sub_show',
            'hero_slideshow', 'hero_slideshow_pause', 'hero_slideshow_dots',
            'hero_overlay_gradient',
            'hero_eyebrow_shadow', 'hero_title_shadow', 'hero_sub_shadow',
            'cta1_show', 'cta2_show',
            'about_show', 'values_show', 'activities_show', 'stats_show', 'visit_show',
            'visit_show_address', 'visit_show_hours', 'visit_show_wa',
            'stats_animate',
            'footer_show', 'footer_show_social',
            'subpage_hero_show', 'subpage_breadcrumb', 'subpage_ornament', 'subpage_gorga', 'subpage_eyebrow_auto',
            'team_show', 'gallery_show', 'gallery_lightbox',
            'gallery_arrows', 'gallery_dots', 'gallery_pause',
            'tarombo_digital_show',
            'members_register_show', 'members_directory_show',
            'banner_show', 'banner_cta2_show',
            'faq_show',
            'popup_enabled', 'popup_show_home', 'popup_show_subpage', 'popup_show_close',
            'sticky_header', 'enable_tarombo_page_button',
            'header_use_plugin', 'header_show_cta', 'header_transparent_home', 'header_hide_theme',
        ];
        foreach ( $bools as $k ) {
            if ( array_key_exists( $k, $input ) ) {
                $c[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
            } else {
                $c[ $k ] = (int) ( $existing[ $k ] ?? $d[ $k ] ?? 0 );
            }
        }

        $text_fields = [
            'org_name', 'whatsapp', 'hours',
            'hero_eyebrow_text', 'hero_title_text',
            'hero_height_mode', 'hero_text_align_h', 'hero_text_align_v', 'hero_animation', 'hero_img_position',
            'hero_eyebrow_weight', 'hero_eyebrow_style',
            'hero_title_weight', 'hero_title_style',
            'hero_sub_weight', 'hero_sub_style',
            'font_heading', 'font_body',
            'hero_eyebrow_font', 'hero_title_font', 'hero_sub_font',
            'cta1_label', 'cta1_target', 'cta1_icon', 'cta1_style', 'cta1_size',
            'cta2_label', 'cta2_target', 'cta2_icon', 'cta2_style', 'cta2_size', 'cta2_mode',
            'cta2_inquiry_title', 'cta2_inquiry_submit',
            'tarombo_page_button_label', 'tarombo_page_slug',
            'tarombo_cta_title', 'tarombo_cta_lead',
            'tarombo_digital_eyebrow', 'tarombo_digital_title', 'tarombo_digital_subtitle',
            'tarombo_digital_preview_url', 'tarombo_digital_new_tab_name',
            'about_eyebrow', 'about_title', 'about_layout', 'about_cta_label',
            'values_eyebrow', 'values_title', 'values_hover_hint', 'team_layout',
            'activities_eyebrow', 'activities_title', 'activities_more_label',
            'stats_eyebrow', 'stats_title',
            'stats_a_num', 'stats_a_label', 'stats_b_num', 'stats_b_label',
            'stats_c_num', 'stats_c_label', 'stats_d_num', 'stats_d_label',
            'visit_eyebrow', 'visit_title',
            'footer_col1_label', 'footer_col2_label', 'footer_copyright',
            'team_eyebrow', 'team_title', 'team_more_label',
            'gallery_eyebrow', 'gallery_title',
            'banner_eyebrow', 'banner_title',
            'banner_cta_label', 'banner_cta2_label',
            'faq_eyebrow', 'faq_title',
            'faq_1_q', 'faq_2_q', 'faq_3_q', 'faq_4_q', 'faq_5_q',
            'faq_6_q', 'faq_7_q', 'faq_8_q', 'faq_9_q', 'faq_10_q',
            'gallery_layout',
            'popup_orientation', 'popup_frequency',
            'popup_eyebrow', 'popup_title', 'popup_cta_label', 'popup_cta_target', 'popup_close_text',
            'header_layout', 'header_nav_style', 'header_menu_source', 'header_cta_label', 'header_cta_style',
            'portal_login_slug', 'members_login_slug', 'members_portal_slug', 'admin_login_slug', 'admin_portal_slug',
        ];
        foreach ( $text_fields as $k ) {
            if ( in_array( $k, [ 'portal_login_slug', 'members_login_slug', 'members_portal_slug', 'admin_login_slug', 'admin_portal_slug' ], true ) ) {
                $raw = isset( $input[ $k ] ) ? sanitize_title( (string) $input[ $k ] ) : '';
                $c[ $k ] = $raw !== '' ? $raw : ( $existing[ $k ] ?? $d[ $k ] ?? '' );
                continue;
            }
            $c[ $k ] = isset( $input[ $k ] )
                ? sanitize_text_field( (string) $input[ $k ] )
                : ( $existing[ $k ] ?? $d[ $k ] ?? '' );
        }
        if ( isset( $c['cta2_mode'] ) && ! in_array( $c['cta2_mode'], [ 'link', 'inquiry' ], true ) ) {
            $c['cta2_mode'] = 'inquiry';
        }
        if ( isset( $c['tarombo_page_slug'] ) ) {
            $c['tarombo_page_slug'] = sanitize_title( $c['tarombo_page_slug'] ?: 'tarombo' );
        }
        if ( isset( $c['team_layout'] ) && ! in_array( $c['team_layout'], [ 'grid', 'grouped' ], true ) ) {
            $c['team_layout'] = 'grid';
        }
        if ( isset( $c['hero_img_position'] ) ) {
            $c['hero_img_position'] = ptprm_sanitize_hero_img_position( $c['hero_img_position'] );
        }
        if ( isset( $c['hero_height_mode'] ) ) {
            $c['hero_height_mode'] = ptprm_sanitize_hero_height_mode( $c['hero_height_mode'] );
        }
        if ( isset( $input['pattern_style'] ) ) {
            $ps = sanitize_key( (string) $input['pattern_style'] );
            $c['pattern_style'] = isset( ptprm_pattern_choices()[ $ps ] ) ? $ps : ( $existing['pattern_style'] ?? 'gorga' );
        }

        $values_raw   = $input['values_items'] ?? ( $existing['values_items'] ?? '' );
        $values_clean = ptprm_sanitize_values_items( $values_raw );
        if ( ! $values_clean ) {
            $values_clean = ptprm_get_values_items( $existing );
        }
        $c['values_items'] = wp_json_encode( $values_clean, JSON_UNESCAPED_UNICODE );

        // Urutan section beranda (JSON array of slugs)
        if ( isset( $input['home_sections_order'] ) ) {
            $raw = (string) $input['home_sections_order'];
            $decoded = json_decode( $raw, true );
            $valid = ptprm_home_section_slugs();
            $out = [];
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $v ) {
                    $slug = sanitize_key( (string) $v );
                    if ( in_array( $slug, $valid, true ) && ! in_array( $slug, $out, true ) ) {
                        $out[] = $slug;
                    }
                }
            }
            foreach ( ptprm_default_home_sections_order() as $slug ) {
                if ( in_array( $slug, $valid, true ) && ! in_array( $slug, $out, true ) ) {
                    $out[] = $slug;
                }
            }
            $c['home_sections_order'] = wp_json_encode( $out, JSON_UNESCAPED_UNICODE );
        } else {
            $c['home_sections_order'] = $existing['home_sections_order'] ?? '';
        }

        $team_raw   = $input['team_items'] ?? ( $existing['team_items'] ?? '' );
        $team_clean = ptprm_sanitize_team_items( $team_raw );
        if ( ! $team_clean ) {
            $team_clean = ptprm_get_team_items( $existing );
        }
        $c['team_items'] = wp_json_encode( $team_clean, JSON_UNESCAPED_UNICODE );

        $pdf_raw   = $input['pdf_lightbox_items'] ?? ( $existing['pdf_lightbox_items'] ?? '' );
        $pdf_clean = ptprm_sanitize_pdf_lightbox_items( $pdf_raw );
        $c['pdf_lightbox_items'] = wp_json_encode( $pdf_clean, JSON_UNESCAPED_UNICODE );
        if ( isset( $c['pdf_publications_page_slug'] ) ) {
            $c['pdf_publications_page_slug'] = sanitize_title( (string) $c['pdf_publications_page_slug'] ) ?: 'publikasi-dan-dokumentasi';
        }
        if ( isset( $c['pdf_publications_intro'] ) ) {
            $c['pdf_publications_intro'] = sanitize_textarea_field( (string) $c['pdf_publications_intro'] );
        }

        $sub_max_raw = isset( $input['header_submenu_max'] )
            ? (int) $input['header_submenu_max']
            : (int) ( $existing['header_submenu_max'] ?? $d['header_submenu_max'] ?? 10 );
        $sub_max     = max( 1, min( 20, $sub_max_raw ) );

        $menu_raw   = $input['header_menu_items'] ?? ( $existing['header_menu_items'] ?? '' );
        $menu_clean = ptprm_sanitize_header_menu_items( $menu_raw, 0, $sub_max );
        if ( ! $menu_clean ) {
            $menu_clean = ptprm_get_header_menu_items( $existing );
        }
        $c['header_menu_items'] = wp_json_encode( $menu_clean, JSON_UNESCAPED_UNICODE );

        if ( isset( $c['header_layout'] ) && ! in_array( $c['header_layout'], [ 'classic', 'centered', 'split', 'minimal' ], true ) ) {
            $c['header_layout'] = 'split';
        }
        if ( isset( $c['header_nav_style'] ) && ! in_array( $c['header_nav_style'], [ 'pill', 'underline', 'plain' ], true ) ) {
            $c['header_nav_style'] = 'pill';
        }
        if ( isset( $c['header_menu_source'] ) && ! in_array( $c['header_menu_source'], [ 'custom', 'wp' ], true ) ) {
            $c['header_menu_source'] = 'custom';
        }
        if ( isset( $c['header_cta_style'] ) && ! in_array( $c['header_cta_style'], [ 'accent', 'outline', 'ghost' ], true ) ) {
            $c['header_cta_style'] = 'accent';
        }
        $c['header_wp_menu'] = isset( $input['header_wp_menu'] ) ? absint( $input['header_wp_menu'] ) : (int) ( $existing['header_wp_menu'] ?? 0 );

        $textarea_fields = [
            'address', 'hero_sub_text', 'about_body', 'values_subtitle',
            'activities_subtitle', 'visit_intro',
            'footer_tagline', 'footer_col1_links', 'footer_col2_links',
            'subpage_default_intro',
            'team_subtitle',
            'gallery_subtitle', 'gallery_ids',
            'banner_subtitle',
            'faq_subtitle',
            'faq_1_a', 'faq_2_a', 'faq_3_a', 'faq_4_a', 'faq_5_a',
            'faq_6_a', 'faq_7_a', 'faq_8_a', 'faq_9_a', 'faq_10_a',
            'popup_body',
            'cta2_inquiry_default', 'cta2_inquiry_hint',
        ];
        foreach ( $textarea_fields as $k ) {
            $c[ $k ] = isset( $input[ $k ] )
                ? sanitize_textarea_field( (string) $input[ $k ] )
                : ( $existing[ $k ] ?? $d[ $k ] ?? '' );
        }

        // Google Maps — simpan iframe HTML lengkap ATAU URL embed yang valid
        $map_raw = isset( $input['visit_map_url'] ) ? trim( (string) $input['visit_map_url'] ) : '';
        if ( $map_raw !== '' && stripos( $map_raw, '<iframe' ) !== false ) {
            $c['visit_map_url'] = ptprm_sanitize_map_iframe_html( $map_raw );
        } elseif ( $map_raw !== '' ) {
            $c['visit_map_url'] = ptprm_extract_map_src( $map_raw );
        } else {
            $c['visit_map_url'] = '';
        }

        $url_fields = [
            'fb_url', 'ig_url', 'yt_url', 'tt_url',
            'cta1_url', 'cta2_url', 'about_cta_url', 'activities_more_url',
            'hero_img_desktop', 'hero_img_tablet', 'hero_img_mobile',
            'hero_slide_1', 'hero_slide_2', 'hero_slide_3', 'hero_slide_4', 'hero_slide_5',
            'about_image', 'header_logo',
            'team_more_url',
            'banner_image',
            'banner_cta_url', 'banner_cta2_url',
            'tarombo_app_url', 'header_cta_url',
            'popup_image', 'popup_image_2', 'popup_image_3', 'popup_cta_url',
            'popup_link_1', 'popup_link_2', 'popup_link_3',
        ];
        foreach ( $url_fields as $k ) {
            $v = isset( $input[ $k ] ) ? trim( (string) $input[ $k ] ) : '';
            if ( $v === '' ) {
                $c[ $k ] = '';
            } elseif ( ctype_digit( $v ) ) {
                $c[ $k ] = (int) $v;
            } else {
                $c[ $k ] = esc_url_raw( $v );
            }
        }

        $color_fields = [
            'color_primary', 'color_accent', 'color_text', 'color_text_soft', 'color_cream',
            'hero_overlay_color',
            'hero_eyebrow_color', 'hero_title_color', 'hero_sub_color',
            'cta1_bg', 'cta1_color', 'cta1_bg_hover', 'cta1_color_hover',
            'cta2_bg', 'cta2_color', 'cta2_bg_hover', 'cta2_color_hover',
            'header_bg_color', 'header_text_color',
        ];
        foreach ( $color_fields as $k ) {
            $v = isset( $input[ $k ] ) ? trim( (string) $input[ $k ] ) : '';
            if ( $v === 'transparent' ) {
                $c[ $k ] = 'transparent';
            } else {
                $hex = sanitize_hex_color( $v );
                $c[ $k ] = $hex ? $hex : ( $existing[ $k ] ?? $d[ $k ] ?? '' );
            }
        }

        $int_fields = [
            'container_max'              => [ 600, 1600 ],
            'header_logo_max_height'     => [ 28, 120 ],
            'header_menu_font_size'      => [ 12, 20 ],
            'header_submenu_max'         => [ 1, 20 ],
            'hero_overlay_opacity'       => [ 0, 100 ],
            'hero_slideshow_interval'    => [ 2500, 15000 ],
            'hero_height_desktop'        => [ 320, 1400 ],
            'hero_height_mobile'         => [ 320, 1200 ],
            'hero_text_max_width'        => [ 240, 1200 ],
            'hero_text_gap'              => [ 0, 80 ],
            'hero_eyebrow_size'          => [ 8, 60 ],
            'hero_eyebrow_size_mobile'   => [ 8, 60 ],
            'hero_eyebrow_letter_spacing'=> [ 0, 80 ],
            'hero_title_size'            => [ 20, 160 ],
            'hero_title_size_mobile'     => [ 16, 120 ],
            'hero_title_letter_spacing'  => [ -10, 30 ],
            'hero_title_line_height'     => [ 80, 200 ],
            'hero_sub_size'              => [ 10, 40 ],
            'hero_sub_size_mobile'       => [ 10, 36 ],
            'hero_sub_letter_spacing'    => [ -10, 30 ],
            'hero_sub_line_height'       => [ 100, 240 ],
            'cta1_radius'                => [ 0, 60 ],
            'cta2_radius'                => [ 0, 60 ],
            'activities_count'           => [ 1, 12 ],
            'members_per_page'         => [ 10, 50 ],
            'activities_category'        => [ 0, 99999 ],
            'tarombo_digital_iframe_height' => [ 240, 900 ],
            'subpage_overlay'            => [ 0, 100 ],
            'team_columns'               => [ 2, 6 ],
            'gallery_columns'            => [ 2, 6 ],
            'gallery_autoplay'           => [ 1000, 10000 ],
            'gallery_speed'              => [ 10, 120 ],
            'banner_overlay'             => [ 0, 100 ],
            'popup_delay'                => [ 0, 60000 ],
            'popup_slide_interval'       => [ 1500, 15000 ],
            'popup_overlay_opacity'      => [ 0, 100 ],
            // Tipografi global
            'type_h2_size'        => [ 18, 80 ],
            'type_h2_size_m'      => [ 16, 64 ],
            'type_h2_lh'          => [ 90, 200 ],
            'type_lead_size'      => [ 12, 32 ],
            'type_lead_size_m'    => [ 12, 28 ],
            'type_lead_lh'        => [ 110, 220 ],
            'type_eyebrow_size'   => [ 8, 30 ],
            'type_eyebrow_size_m' => [ 8, 26 ],
            'type_body_size'      => [ 12, 26 ],
            'type_body_size_m'    => [ 12, 24 ],
            'type_body_lh'        => [ 120, 220 ],
            'type_li_size'        => [ 12, 26 ],
            'type_li_size_m'      => [ 12, 24 ],
            'type_sub_h2_size'    => [ 18, 60 ],
            'type_sub_h2_size_m'  => [ 16, 48 ],
            'type_sub_h3_size'    => [ 16, 48 ],
            'type_sub_h3_size_m'  => [ 14, 36 ],
            'type_sub_body_size'  => [ 12, 26 ],
            'type_sub_body_size_m'=> [ 12, 24 ],
            'type_sub_body_lh'    => [ 120, 220 ],
            'type_sub_li_size'    => [ 12, 26 ],
            'type_sub_li_size_m'  => [ 12, 24 ],
        ];
        foreach ( $int_fields as $k => $range ) {
            $v = isset( $input[ $k ] ) ? (int) $input[ $k ] : (int) ( $existing[ $k ] ?? $d[ $k ] ?? 0 );
            $c[ $k ] = max( $range[0], min( $range[1], $v ) );
        }

        $new_primary = sanitize_hex_color( (string) ( $c['color_primary'] ?? '' ) ) ?: '';
        $old_primary = sanitize_hex_color( (string) ( $existing['color_primary'] ?? '' ) ) ?: '';
        $new_accent  = sanitize_hex_color( (string) ( $c['color_accent'] ?? '' ) ) ?: '';
        $old_accent  = sanitize_hex_color( (string) ( $existing['color_accent'] ?? '' ) ) ?: '';

        if ( $new_primary !== $old_primary || $new_accent !== $old_accent ) {
            $overlay_ops = [
                'hero_overlay_opacity'  => (int) ( $c['hero_overlay_opacity'] ?? $existing['hero_overlay_opacity'] ?? 55 ),
                'subpage_overlay'       => (int) ( $c['subpage_overlay'] ?? $existing['subpage_overlay'] ?? 70 ),
                'banner_overlay'        => (int) ( $c['banner_overlay'] ?? $existing['banner_overlay'] ?? 68 ),
                'popup_overlay_opacity' => (int) ( $c['popup_overlay_opacity'] ?? $existing['popup_overlay_opacity'] ?? 70 ),
            ];
            $derived = ptprm_derive_brand_colors(
                $c['color_primary'] ?? $existing['color_primary'] ?? '#0A1F3D',
                $c['color_accent'] ?? $existing['color_accent'] ?? '#C9A44C',
                $overlay_ops
            );
            foreach ( $derived as $dk => $dv ) {
                $c[ $dk ] = $dv;
            }
        }

        return array_merge( $existing, $c );
    }

    /* ===================================================================== */
    /* RENDER                                                                */
    /* ===================================================================== */

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $o   = ptprm_options();
        $opt = PTPRM_OPTION;
        $tabs = [
            'umum'      => 'Umum',
            'header'    => 'Header',
            'tipografi' => 'Tipografi',
            'hero'      => 'Hero',
            'beranda'   => 'Beranda',
            'popup'     => 'Pop-up Iklan',
            'subpage'   => 'Sub-halaman',
            'footer'    => 'Footer',
            'pdf_lightbox' => 'Panel PDF Lightbox',
        ];
        ?>
        <div class="wrap ptprm-wrap">
            <?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pengaturan disimpan. Jika tampilan situs belum berubah, klik Purge All di LiteSpeed Cache.', 'ptsbi-premium' ); ?></p></div>
            <?php endif; ?>
            <?php
            $save_err = isset( $_GET['ptprm-save-error'] ) ? sanitize_key( wp_unslash( $_GET['ptprm-save-error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( 'empty' === $save_err ) :
                ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Penyimpanan gagal: server tidak menerima data. Upload plugin v1.2.2+, refresh halaman admin, lalu simpan lagi. Cek juga security plugin / fail2ban yang memblokir admin-ajax.php.', 'ptsbi-premium' ); ?></p></div>
            <?php elseif ( 'partial' === $save_err ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Penyimpanan gagal: data form tidak lengkap. Purge cache (LiteSpeed), refresh, lalu simpan. Jika masih gagal, coba browser lain atau nonaktifkan sementara firewall/WAF.', 'ptsbi-premium' ); ?></p></div>
            <?php elseif ( 'bad-action' === $save_err ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Penyimpanan gagal: form admin kedaluwarsa (cache). Tekan Ctrl+F5 atau Purge All di LiteSpeed Cache, lalu simpan lagi. Pastikan plugin v1.2.5+.', 'ptsbi-premium' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['ptprm-imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cadangan JSON berhasil dipulihkan. Cek gambar (ID media mungkin perlu diunggah ulang di situs ini), lalu klik Simpan jika ada perubahan kecil.', 'ptsbi-premium' ); ?></p></div>
            <?php endif; ?>
            <?php
            $import_err = isset( $_GET['ptprm-import-error'] ) ? sanitize_key( wp_unslash( $_GET['ptprm-import-error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $import_err ) :
                ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Pemulihan JSON gagal. Pilih file .json dari tombol Unduh cadangan JSON (bukan file lain).', 'ptsbi-premium' ); ?></p></div>
            <?php endif; ?>
            <div class="ptprm-topbar">
                <div class="ptprm-brand">
                    <strong><?php esc_html_e( 'Premium Organization', 'ptsbi-premium' ); ?></strong>
                    <span class="ptprm-ver">v<?php echo esc_html( PTPRM_VERSION ); ?></span>
                    <p class="ptprm-site-id">
                        <?php
                        printf(
                            /* translators: 1: site URL, 2: blog name */
                            esc_html__( 'Situs ini: %1$s — %2$s', 'ptsbi-premium' ),
                            esc_html( home_url( '/' ) ),
                            esc_html( get_bloginfo( 'name' ) )
                        );
                        ?>
                    </p>
                </div>
                <div class="ptprm-topbar-actions">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="button button-secondary"><?php esc_html_e( 'Buka Beranda', 'ptsbi-premium' ); ?></a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ptprm-settings&ptprm_export_backup=1' ), 'ptprm_export_backup' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Unduh cadangan JSON', 'ptsbi-premium' ); ?></a>
                    <form class="ptprm-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="ptprm_import_backup" />
                        <?php wp_nonce_field( 'ptprm_import_backup' ); ?>
                        <label class="screen-reader-text" for="ptprm-backup-file"><?php esc_html_e( 'File cadangan JSON', 'ptsbi-premium' ); ?></label>
                        <input type="file" id="ptprm-backup-file" name="ptprm_backup_file" accept=".json,application/json" required />
                        <?php submit_button( __( 'Pulihkan dari JSON', 'ptsbi-premium' ), 'secondary', 'ptprm_import', false ); ?>
                    </form>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ptprm-settings&ptprm_apply_demo=1' ), 'ptprm_apply_demo' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Isi contoh copywriting', 'ptsbi-premium' ); ?></a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ptprm-settings&ptprm_create_pages=1' ), 'ptprm_create_pages' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Buat halaman standar', 'ptsbi-premium' ); ?></a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ptprm-settings&ptprm_sync_wp=1' ), 'ptprm_sync_wp' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Sesuaikan dengan situs ini', 'ptsbi-premium' ); ?></a>
                </div>
            <p class="description" style="margin:-8px 0 16px;">
                <?php esc_html_e( 'File JSON tidak diletakkan di folder server — unduh dari situs sumber, lalu di situs baru pilih file dan klik Pulihkan dari JSON.', 'ptsbi-premium' ); ?>
            </p>
            </div>
            <?php if ( PTPRM_Bootstrap::has_legacy_ptsbi_copy( $o ) ) : ?>
                <div class="notice notice-info"><p><?php esc_html_e( 'Masih ada teks default PTSBI/tarombo. Klik "Sesuaikan dengan situs ini" untuk mengganti nama organisasi dan contoh teks sesuai website Anda.', 'ptsbi-premium' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ptprm-form" id="ptprm-form">
                <input type="hidden" name="action" value="ptprm_save_settings" />
                <?php
                // Jangan pakai settings_fields() — ia menambah action=update yang menimpa ptprm_save_settings.
                wp_nonce_field( self::GROUP . '-options' );
                ?>
                <input type="hidden" id="ptprm-payload-b64-field" name="ptprm_payload_b64" value="" />

                <div class="ptprm-layout">
                    <aside class="ptprm-sidebar">
                        <ul class="ptprm-tabs">
                            <?php foreach ( $tabs as $slug => $label ) : ?>
                                <li><a href="#tab-<?php echo esc_attr( $slug ); ?>" data-tab="<?php echo esc_attr( $slug ); ?>" class="<?php echo 'umum' === $slug ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="ptprm-save-box">
                            <?php submit_button( 'Simpan Perubahan', 'primary', 'ptprm_save', false ); ?>
                        </div>
                    </aside>

                    <main class="ptprm-main">
                        <?php $this->panel_umum( $o, $opt ); ?>
                        <?php $this->panel_header( $o, $opt ); ?>
                        <?php $this->panel_tipografi( $o, $opt ); ?>
                        <?php $this->panel_hero( $o, $opt ); ?>
                        <?php $this->panel_beranda( $o, $opt ); ?>
                        <?php $this->panel_popup( $o, $opt ); ?>
                        <?php $this->panel_subpage( $o, $opt ); ?>
                        <?php $this->panel_footer( $o, $opt ); ?>
                        <?php $this->panel_pdf_lightbox( $o, $opt ); ?>
                    </main>
                </div>
            </form>
        </div>
        <?php
    }

    /* -- PANEL: UMUM -- */

    private function panel_umum( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-umum" data-panel="umum">
            <h2>Pengaturan Umum</h2>
            <p class="ptprm-help">Identitas organisasi, warna, font, dan kontak global.</p>

            <div class="ptprm-card">
                <h3>Identitas</h3>
                <?php $this->t( 'Nama organisasi', 'org_name', $o, $opt ); ?>
            </div>

            <div class="ptprm-grid-2">
                <div class="ptprm-card">
                    <h3>Warna Brand</h3>
                    <?php
                    $this->color( 'Warna Primer (dominan)', 'color_primary',   $o, $opt );
                    $this->color( 'Warna Aksen',            'color_accent',    $o, $opt );
                    $this->color( 'Warna Teks Utama',     'color_text',      $o, $opt );
                    $this->color( 'Warna Teks Halus',     'color_text_soft', $o, $opt );
                    $this->color( 'Warna Krem (BG soft)', 'color_cream',     $o, $opt );
                    ?>
                    <p class="ptprm-help"><?php esc_html_e( 'Palet premium mengubah warna seluruh section (hero, footer, statistik, header, CTA, overlay) — termasuk palet terang. Overlay disetel optimal per palet.', 'ptsbi-premium' ); ?></p>
                    <div class="ptprm-presets ptprm-presets--themes">
                        <?php foreach ( ptprm_color_theme_presets() as $slug => $preset ) :
                            $bundle     = ptprm_color_preset_bundle( $slug );
                            $bundle_b64 = $bundle ? base64_encode( (string) wp_json_encode( $bundle ) ) : '';
                            $tone_class = ( $preset['tone'] ?? 'dark' ) === 'light' ? ' ptprm-preset-swatch--light' : '';
                            ?>
                            <button type="button" class="ptprm-preset-swatch<?php echo esc_attr( $tone_class ); ?>" data-ptprm-preset="<?php echo esc_attr( $slug ); ?>"
                                data-preset-bundle="<?php echo esc_attr( $bundle_b64 ); ?>"
                                title="<?php echo esc_attr( $preset['label'] ); ?>">
                                <span class="ptprm-preset-swatch__bar" style="background:<?php echo esc_attr( $preset['primary'] ); ?>"></span>
                                <span class="ptprm-preset-swatch__dot" style="background:<?php echo esc_attr( $preset['accent'] ); ?>"></span>
                                <span class="ptprm-preset-swatch__label"><?php echo esc_html( $preset['label'] ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ptprm-card">
                    <h3>Font Default</h3>
                    <?php
                    $this->select( 'Font Heading', 'font_heading', $o, $opt, ptprm_font_choices() );
                    $this->select( 'Font Body',    'font_body',    $o, $opt, ptprm_font_choices() );
                    $this->number( 'Lebar konten maks. (px)', 'container_max', $o, $opt, 600, 1600 );
                    ?>
                </div>
            </div>

            <div class="ptprm-card" style="margin-top:16px;">
                <h3><?php esc_html_e( 'Corak ornamen latar', 'ptsbi-premium' ); ?></h3>
                <p class="ptprm-help"><?php esc_html_e( 'Corak halus di hero tanpa gambar, statistik, placeholder foto, banner, dan sub-halaman. Pilih kosong untuk tampilan polos tanpa corak.', 'ptsbi-premium' ); ?></p>
                <?php $this->select( __( 'Jenis corak', 'ptsbi-premium' ), 'pattern_style', $o, $opt, ptprm_pattern_choices() ); ?>
            </div>

            <div class="ptprm-grid-2">
                <div class="ptprm-card">
                    <h3>Kontak & Alamat</h3>
                    <?php
                    $this->t( 'Nomor WhatsApp (mis. +6281xxxx)', 'whatsapp', $o, $opt );
                    $this->ta( 'Alamat lengkap', 'address', $o, $opt, 3 );
                    $this->t( 'Jam operasional', 'hours', $o, $opt );
                    ?>
                </div>
                <div class="ptprm-card">
                    <h3>Sosial Media</h3>
                    <?php
                    $this->url( 'Facebook',  'fb_url', $o, $opt );
                    $this->url( 'Instagram', 'ig_url', $o, $opt );
                    $this->url( 'YouTube',   'yt_url', $o, $opt );
                    $this->url( 'TikTok',    'tt_url', $o, $opt );
                    ?>
                </div>
            </div>

            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Halaman aplikasi eksternal (opsional)', 'ptsbi-premium' ); ?></h3>
                <?php
                $this->bool( __( 'Tampilkan blok CTA di halaman khusus (slug di bawah)', 'ptsbi-premium' ), 'enable_tarombo_page_button', $o, $opt );
                $this->url( __( 'URL aplikasi / layanan eksternal', 'ptsbi-premium' ), 'tarombo_app_url', $o, $opt );
                $this->t( __( 'Judul blok CTA', 'ptsbi-premium' ), 'tarombo_cta_title', $o, $opt );
                $this->ta( __( 'Deskripsi singkat', 'ptsbi-premium' ), 'tarombo_cta_lead', $o, $opt, 2 );
                $this->t( __( 'Label tombol', 'ptsbi-premium' ), 'tarombo_page_button_label', $o, $opt );
                $this->t( __( 'Slug halaman WP (mis. layanan)', 'ptsbi-premium' ), 'tarombo_page_slug', $o, $opt );
                ?>
                <p class="ptprm-help"><?php esc_html_e( 'Kosongkan URL jika tidak dipakai. Setiap website punya pengaturan sendiri di database WordPress ini.', 'ptsbi-premium' ); ?> <code>/<?php echo esc_html( $o['tarombo_page_slug'] ?: 'layanan' ); ?>/</code></p>
            </div>
        </section>
        <?php
    }

    /* -- PANEL: HEADER -- */

    private function panel_header( $o, $opt ) {
        $menus = [ 0 => __( '— Pilih menu WordPress —', 'ptsbi-premium' ) ];
        foreach ( wp_get_nav_menus() as $menu ) {
            $menus[ (int) $menu->term_id ] = $menu->name;
        }
        ?>
        <section class="ptprm-panel" id="tab-header" data-panel="header" hidden>
            <h2><?php esc_html_e( 'Header — Logo & Menu', 'ptsbi-premium' ); ?></h2>
            <p class="ptprm-help"><?php esc_html_e( 'Header premium menggantikan menu tema (Astra/dll.) dengan logo, navigasi modern, dan tombol CTA. Setelah mengubah menu, klik Buat halaman standar jika halaman belum ada.', 'ptsbi-premium' ); ?></p>

            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Aktivasi', 'ptsbi-premium' ); ?></h3>
                <?php
                $this->bool( __( 'Gunakan header Premium Plugin', 'ptsbi-premium' ), 'header_use_plugin', $o, $opt );
                $this->bool( __( 'Sembunyikan header tema (disarankan)', 'ptsbi-premium' ), 'header_hide_theme', $o, $opt );
                $this->bool( __( 'Header tetap saat scroll', 'ptsbi-premium' ), 'sticky_header', $o, $opt );
                $this->bool( __( 'Transparan di beranda (di atas hero)', 'ptsbi-premium' ), 'header_transparent_home', $o, $opt );
                ?>
            </div>

            <div class="ptprm-grid-2">
                <div class="ptprm-card">
                    <h3><?php esc_html_e( 'Logo', 'ptsbi-premium' ); ?></h3>
                    <?php
                    $this->image( __( 'Logo (PNG/SVG disarankan)', 'ptsbi-premium' ), 'header_logo', $o, $opt );
                    $this->number( __( 'Tinggi logo maks. (px)', 'ptsbi-premium' ), 'header_logo_max_height', $o, $opt, 28, 120 );
                    $this->number( __( 'Ukuran font menu (px)', 'ptsbi-premium' ), 'header_menu_font_size', $o, $opt, 12, 20 );
                    ?>
                    <p class="ptprm-help"><?php esc_html_e( 'Jika logo kosong, nama organisasi ditampilkan sebagai teks.', 'ptsbi-premium' ); ?></p>
                </div>
                <div class="ptprm-card">
                    <h3><?php esc_html_e( 'Gaya modern', 'ptsbi-premium' ); ?></h3>
                    <?php
                    $this->select(
                        __( 'Tata letak', 'ptsbi-premium' ),
                        'header_layout',
                        $o,
                        $opt,
                        [
                            'split'    => __( 'Split — logo kiri, menu tengah, CTA kanan', 'ptsbi-premium' ),
                            'classic'  => __( 'Classic — logo & menu satu baris', 'ptsbi-premium' ),
                            'centered' => __( 'Centered — logo tengah, menu di bawah', 'ptsbi-premium' ),
                            'minimal'  => __( 'Minimal — logo + menu ringkas', 'ptsbi-premium' ),
                        ]
                    );
                    $this->select(
                        __( 'Gaya tautan menu', 'ptsbi-premium' ),
                        'header_nav_style',
                        $o,
                        $opt,
                        [
                            'pill'      => __( 'Pill — latar bulat saat aktif/hover', 'ptsbi-premium' ),
                            'underline' => __( 'Underline — garis bawah emas', 'ptsbi-premium' ),
                            'plain'     => __( 'Plain — teks bersih', 'ptsbi-premium' ),
                        ]
                    );
                    $this->color( __( 'Latar header (kosong = warna primer)', 'ptsbi-premium' ), 'header_bg_color', $o, $opt );
                    $this->color( __( 'Warna teks menu', 'ptsbi-premium' ), 'header_text_color', $o, $opt );
                    ?>
                </div>
            </div>

            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Menu navigasi', 'ptsbi-premium' ); ?></h3>
                <p class="ptprm-help">
                    <?php esc_html_e( 'Submenu dropdown: di Tampilan → Menu seret item ke kanan di bawah induk, lalu pilih “Menu WordPress”. Atau pakai daftar kustom dan tambah submenu di setiap item utama.', 'ptsbi-premium' ); ?>
                </p>
                <?php
                $this->select(
                    __( 'Sumber menu', 'ptsbi-premium' ),
                    'header_menu_source',
                    $o,
                    $opt,
                    [
                        'wp'     => __( 'Menu WordPress (disarankan untuk dropdown)', 'ptsbi-premium' ),
                        'custom' => __( 'Daftar kustom (di bawah)', 'ptsbi-premium' ),
                    ]
                );
                $this->select( __( 'Menu WordPress', 'ptsbi-premium' ), 'header_wp_menu', $o, $opt, $menus );
                $this->number( __( 'Maks. item submenu per menu utama', 'ptsbi-premium' ), 'header_submenu_max', $o, $opt, 1, 20 );
                $this->repeater_header_menu( $o, $opt );
                ?>
            </div>

            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Tombol CTA header', 'ptsbi-premium' ); ?></h3>
                <?php
                $this->bool( __( 'Tampilkan tombol CTA', 'ptsbi-premium' ), 'header_show_cta', $o, $opt );
                $this->t( __( 'Label tombol', 'ptsbi-premium' ), 'header_cta_label', $o, $opt );
                $this->t( __( 'URL tombol', 'ptsbi-premium' ), 'header_cta_url', $o, $opt );
                $this->select(
                    __( 'Gaya tombol', 'ptsbi-premium' ),
                    'header_cta_style',
                    $o,
                    $opt,
                    [
                        'accent'  => __( 'Accent — solid emas', 'ptsbi-premium' ),
                        'outline' => __( 'Outline — garis putih', 'ptsbi-premium' ),
                        'ghost'   => __( 'Ghost — transparan', 'ptsbi-premium' ),
                    ]
                );
                ?>
            </div>
        </section>
        <?php
    }

    /* -- PANEL: TIPOGRAFI -- */

    private function panel_tipografi( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-tipografi" data-panel="tipografi" hidden>
            <h2>Tipografi Global</h2>
            <p class="ptprm-help">Atur ukuran font (desktop &amp; mobile) untuk seluruh teks di Beranda dan Sub-halaman. Hero punya kontrol sendiri di tab Hero — jadi yang di sini berpengaruh ke Tentang, Nilai, Kegiatan, Statistik, Kunjungi, Pengurus, FAQ, dll. plus seluruh isi Sub-halaman (Program Kerja, Tarombo, dll.).</p>

            <div class="ptprm-card">
                <h3>Beranda — Section Umum</h3>
                <div class="ptprm-grid-3">
                    <?php
                    $this->number( 'Eyebrow desktop (px)',  'type_eyebrow_size',   $o, $opt, 8, 30 );
                    $this->number( 'Eyebrow mobile (px)',   'type_eyebrow_size_m', $o, $opt, 8, 26 );
                    $this->number( 'Judul H2 desktop (px)', 'type_h2_size',        $o, $opt, 18, 80 );
                    $this->number( 'Judul H2 mobile (px)',  'type_h2_size_m',      $o, $opt, 16, 64 );
                    $this->number( 'Line-height H2 (×0.01)','type_h2_lh',          $o, $opt, 90, 200 );
                    $this->number( 'Lead/subjudul desktop', 'type_lead_size',      $o, $opt, 12, 32 );
                    $this->number( 'Lead/subjudul mobile',  'type_lead_size_m',    $o, $opt, 12, 28 );
                    $this->number( 'Line-height lead (×0.01)','type_lead_lh',      $o, $opt, 110, 220 );
                    $this->number( 'Paragraf desktop',      'type_body_size',      $o, $opt, 12, 26 );
                    $this->number( 'Paragraf mobile',       'type_body_size_m',    $o, $opt, 12, 24 );
                    $this->number( 'Line-height body (×0.01)','type_body_lh',      $o, $opt, 120, 220 );
                    $this->number( 'List/li desktop',       'type_li_size',        $o, $opt, 12, 26 );
                    $this->number( 'List/li mobile',        'type_li_size_m',      $o, $opt, 12, 24 );
                    ?>
                </div>
            </div>

            <div class="ptprm-card">
                <h3>Sub-halaman — Konten</h3>
                <p class="ptprm-help">Ukuran untuk teks yang Anda tulis di editor halaman (Program Kerja, Tarombo, dll.).</p>
                <div class="ptprm-grid-3">
                    <?php
                    $this->number( 'H2 desktop (px)',       'type_sub_h2_size',     $o, $opt, 18, 60 );
                    $this->number( 'H2 mobile (px)',        'type_sub_h2_size_m',   $o, $opt, 16, 48 );
                    $this->number( 'H3 desktop (px)',       'type_sub_h3_size',     $o, $opt, 16, 48 );
                    $this->number( 'H3 mobile (px)',        'type_sub_h3_size_m',   $o, $opt, 14, 36 );
                    $this->number( 'Paragraf desktop',      'type_sub_body_size',   $o, $opt, 12, 26 );
                    $this->number( 'Paragraf mobile',       'type_sub_body_size_m', $o, $opt, 12, 24 );
                    $this->number( 'Line-height body (×0.01)','type_sub_body_lh',   $o, $opt, 120, 220 );
                    $this->number( 'List/li desktop',       'type_sub_li_size',     $o, $opt, 12, 26 );
                    $this->number( 'List/li mobile',        'type_sub_li_size_m',   $o, $opt, 12, 24 );
                    ?>
                </div>
            </div>
        </section>
        <?php
    }

    /* -- PANEL: POP-UP IKLAN -- */

    private function panel_popup( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-popup" data-panel="popup" hidden>
            <h2>Pop-up Iklan</h2>
            <p class="ptprm-help">Pop-up muncul setelah delay tertentu. Bisa diatur tampil sekali per session, sehari sekali, atau setiap kunjungan. Gambar bisa portrait (vertikal) atau landscape (horizontal).</p>

            <div class="ptprm-card">
                <h3>Aktivasi &amp; Perilaku</h3>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->bool( 'Aktifkan pop-up',                'popup_enabled',     $o, $opt );
                        $this->bool( 'Tampilkan di Beranda',           'popup_show_home',   $o, $opt );
                        $this->bool( 'Tampilkan di Sub-halaman',       'popup_show_subpage',$o, $opt );
                        $this->select( 'Frekuensi tampil', 'popup_frequency', $o, $opt, [
                            'session' => 'Sekali per session (sampai tutup browser)',
                            'day'     => 'Sekali sehari per perangkat',
                            'always'  => 'Setiap kunjungan halaman',
                        ] );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->number( 'Delay sebelum muncul (ms, 0–60000)', 'popup_delay', $o, $opt, 0, 60000 );
                        $this->range(  'Opasitas overlay gelap (0–100%)',    'popup_overlay_opacity', $o, $opt, 0, 100 );
                        $this->bool(   'Tampilkan tombol "Tidak sekarang"',  'popup_show_close', $o, $opt );
                        $this->t(      'Teks tombol "tidak sekarang"',       'popup_close_text', $o, $opt );
                        ?>
                    </div>
                </div>
            </div>

            <div class="ptprm-card">
                <h3>Orientasi &amp; Auto-slide</h3>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->select( 'Orientasi pop-up', 'popup_orientation', $o, $opt, [
                            'portrait'  => 'Portrait (vertikal) — gambar 3:4 di kiri, teks di kanan',
                            'landscape' => 'Landscape (horizontal) — gambar 16:9 di atas, teks di bawah',
                        ] );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->number( 'Interval rotasi foto (ms)', 'popup_slide_interval', $o, $opt, 1500, 15000 );
                        ?>
                        <p class="ptprm-help" style="margin: 4px 0 0;">Hanya berlaku jika Anda mengisi lebih dari 1 foto.</p>
                    </div>
                </div>
            </div>

            <div class="ptprm-card">
                <h3>Foto Iklan (sampai 3 foto)</h3>
                <p class="ptprm-help">Isi 1–3 foto. Jika lebih dari 1, foto akan berotasi otomatis. Setiap foto bisa punya link sendiri (opsional) — klik foto akan membuka link tersebut. Disarankan: portrait min. <strong>900×1200px</strong>, landscape min. <strong>1280×720px</strong>.</p>
                <div class="ptprm-grid-3">
                    <div class="ptprm-mini-card">
                        <strong>Foto 1</strong>
                        <?php $this->image( 'Gambar', 'popup_image',   $o, $opt ); ?>
                        <?php $this->url(   'Link saat foto diklik (opsional)', 'popup_link_1', $o, $opt ); ?>
                    </div>
                    <div class="ptprm-mini-card">
                        <strong>Foto 2 (opsional)</strong>
                        <?php $this->image( 'Gambar', 'popup_image_2', $o, $opt ); ?>
                        <?php $this->url(   'Link saat foto diklik (opsional)', 'popup_link_2', $o, $opt ); ?>
                    </div>
                    <div class="ptprm-mini-card">
                        <strong>Foto 3 (opsional)</strong>
                        <?php $this->image( 'Gambar', 'popup_image_3', $o, $opt ); ?>
                        <?php $this->url(   'Link saat foto diklik (opsional)', 'popup_link_3', $o, $opt ); ?>
                    </div>
                </div>
            </div>

            <div class="ptprm-card">
                <h3>Teks &amp; Tombol</h3>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t(  'Eyebrow',   'popup_eyebrow', $o, $opt );
                        $this->t(  'Judul',     'popup_title',   $o, $opt );
                        $this->ta( 'Paragraf',  'popup_body',    $o, $opt, 4 );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->t(  'Label tombol utama', 'popup_cta_label', $o, $opt );
                        $this->url( 'URL tombol',        'popup_cta_url',   $o, $opt );
                        $this->select( 'Target',         'popup_cta_target', $o, $opt, [
                            '_self'  => 'Tab sama',
                            '_blank' => 'Tab baru',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }

    /* -- PANEL: HERO -- */

    private function panel_hero( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-hero" data-panel="hero" hidden>
            <h2>Hero & Tombol CTA</h2>
            <p class="ptprm-help">Atur gambar, teks, dan 2 tombol. Preview di bawah update otomatis saat Anda mengetik.</p>

            <?php $this->section_toggle( 'hero_show', 'Tampilkan section Hero', $o, $opt ); ?>

            <!-- LIVE PREVIEW -->
            <div class="ptprm-preview-card">
                <div class="ptprm-preview-bar">
                    <strong>Preview</strong>
                    <span class="ptprm-preview-help">Tidak persis 1:1 dengan situs; tujuan untuk gambaran cepat saat mengedit.</span>
                    <div class="ptprm-preview-modes" role="group" aria-label="Mode preview">
                        <button type="button" class="button button-small ptprm-preview-mode is-active" data-ptprm-preview-mode="desktop">Desktop</button>
                        <button type="button" class="button button-small ptprm-preview-mode" data-ptprm-preview-mode="tablet">Tablet</button>
                        <button type="button" class="button button-small ptprm-preview-mode" data-ptprm-preview-mode="mobile">HP</button>
                    </div>
                </div>
                <div class="ptprm-preview-frame pv-mode-desktop" id="ptprm-hero-preview" data-ptprm-preview>
                    <div class="pv-media"><img src="" alt="" id="pv-img"><div class="pv-overlay" id="pv-overlay"></div></div>
                    <div class="pv-inner" id="pv-inner">
                        <span class="pv-eyebrow" id="pv-eyebrow"></span>
                        <h1 class="pv-title" id="pv-title"></h1>
                        <p class="pv-sub" id="pv-sub"></p>
                        <div class="pv-cta-row">
                            <a class="pv-cta pv-cta1" id="pv-cta1"><span class="pv-cta-icon" id="pv-cta1-icon"></span><span id="pv-cta1-label"></span></a>
                            <a class="pv-cta pv-cta2" id="pv-cta2"><span class="pv-cta-icon" id="pv-cta2-icon"></span><span id="pv-cta2-label"></span></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Banner berganti (hingga 5 gambar)', 'ptsbi-premium' ); ?></h3>
                <p class="ptprm-help"><?php esc_html_e( 'Isi 2 slot atau lebih untuk slideshow otomatis. Slot kosong diabaikan. Jika semua slot kosong, dipakai gambar desktop di bawah.', 'ptsbi-premium' ); ?></p>
                <div class="ptprm-grid-2 ptprm-hero-slides-grid">
                    <?php
                    for ( $si = 1; $si <= 5; $si++ ) {
                        $this->image(
                            sprintf(
                                /* translators: %d: slide number */
                                __( 'Gambar banner %d', 'ptsbi-premium' ),
                                $si
                            ),
                            'hero_slide_' . $si,
                            $o,
                            $opt
                        );
                    }
                    ?>
                </div>
                <?php
                $this->bool( __( 'Aktifkan pergantian otomatis', 'ptsbi-premium' ), 'hero_slideshow', $o, $opt );
                $this->number( __( 'Interval (ms, 2500–15000)', 'ptsbi-premium' ), 'hero_slideshow_interval', $o, $opt, 2500, 15000 );
                $this->bool( __( 'Jeda saat kursor di atas hero', 'ptsbi-premium' ), 'hero_slideshow_pause', $o, $opt );
                $this->bool( __( 'Tampilkan titik indikator', 'ptsbi-premium' ), 'hero_slideshow_dots', $o, $opt );
                ?>
            </div>

            <div class="ptprm-grid-2">
                <div class="ptprm-card">
                    <h3><?php esc_html_e( 'Gambar latar tunggal (responsif)', 'ptsbi-premium' ); ?></h3>
                    <p class="ptprm-help"><?php esc_html_e( 'Cadangan jika slot banner di atas kosong. Tablet/mobile opsional.', 'ptsbi-premium' ); ?></p>
                    <?php
                    $this->image( 'Gambar Desktop (≥1920×900)', 'hero_img_desktop', $o, $opt );
                    $this->image( 'Gambar Tablet (opsional)',    'hero_img_tablet',  $o, $opt );
                    $this->image( 'Gambar Mobile (opsional)',    'hero_img_mobile',  $o, $opt );
                    $this->select( 'Posisi fokus gambar', 'hero_img_position', $o, $opt, ptprm_hero_img_position_choices() );
                    ?>
                </div>
                <div class="ptprm-card">
                    <h3>Overlay & Tinggi</h3>
                    <?php
                    $this->color( 'Warna overlay',          'hero_overlay_color',   $o, $opt );
                    $this->range( 'Opasitas overlay (0–100%)', 'hero_overlay_opacity', $o, $opt, 0, 100 );
                    $this->bool(  'Gradient overlay (kiri lebih gelap)', 'hero_overlay_gradient', $o, $opt );
                    $this->select( 'Tinggi hero', 'hero_height_mode', $o, $opt, [
                        'small'    => 'Kecil (≈60vh) — sub-halaman / preview',
                        'standard' => 'Standar (80vh)',
                        'tall'     => 'Tinggi (90vh)',
                        'full'     => 'Penuh layar (100vh) — rekomendasi',
                        'custom'   => 'Custom (px)',
                    ] );
                    echo '<p class="description">' . esc_html__( 'Mode Penuh layar = satu layar di beranda. Di beranda, teks & CTA otomatis di tengah-kiri (seperti layout referensi).', 'ptsbi-premium' ) . '</p>';
                    $this->number( 'Tinggi custom desktop (px)', 'hero_height_desktop', $o, $opt, 320, 1400 );
                    $this->number( 'Tinggi custom mobile (px)',  'hero_height_mobile',  $o, $opt, 320, 1200 );
                    echo '<p class="description">' . esc_html__( 'Tinggi custom (px) hanya dipakai jika mode hero = Custom.', 'ptsbi-premium' ) . '</p>';
                    ?>
                </div>
            </div>

            <div class="ptprm-card">
                <h3>Layout Teks</h3>
                <div class="ptprm-grid-3">
                    <?php
                    $this->select( 'Posisi horizontal', 'hero_text_align_h', $o, $opt, [
                        'left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan',
                    ] );
                    $this->select( 'Posisi vertikal', 'hero_text_align_v', $o, $opt, [
                        'top' => 'Atas', 'middle' => 'Tengah', 'bottom' => 'Bawah',
                    ] );
                    $this->select( 'Animasi muncul', 'hero_animation', $o, $opt, [
                        'none' => 'Tidak ada', 'fade' => 'Fade', 'fade-up' => 'Fade-up', 'slide-in' => 'Slide-in',
                    ] );
                    $this->number( 'Lebar maks. teks (px)', 'hero_text_max_width', $o, $opt, 240, 1200 );
                    $this->number( 'Jarak antar elemen (px)', 'hero_text_gap', $o, $opt, 0, 80 );
                    ?>
                </div>
            </div>

            <?php $this->hero_text_card( 'Eyebrow (label kecil)', 'hero_eyebrow', $o, $opt, false ); ?>
            <?php $this->hero_text_card( 'Judul utama (H1)',      'hero_title',   $o, $opt, true  ); ?>
            <?php $this->hero_text_card( 'Subjudul (paragraf)',   'hero_sub',     $o, $opt, true  ); ?>

            <?php $this->cta_card( 'Tombol 1 (Primer)', 1, $o, $opt ); ?>
            <?php $this->cta_card( 'Tombol 2 (Sekunder)', 2, $o, $opt ); ?>
        </section>
        <?php
    }

    private function hero_text_card( $title, $prefix, $o, $opt, $with_line_height = false ) {
        ?>
        <div class="ptprm-card">
            <h3><?php echo esc_html( $title ); ?></h3>
            <?php $this->bool( 'Tampilkan', $prefix . '_show', $o, $opt ); ?>
            <?php
            if ( $prefix === 'hero_sub' ) {
                $this->ta( 'Teks', $prefix . '_text', $o, $opt, 3 );
            } else {
                $this->t( 'Teks', $prefix . '_text', $o, $opt );
            }
            ?>
            <div class="ptprm-grid-3">
                <?php
                $this->select( 'Font', $prefix . '_font', $o, $opt, ptprm_font_choices() );
                $this->select( 'Bobot', $prefix . '_weight', $o, $opt, [
                    '300' => '300 Light', '400' => '400 Regular', '500' => '500 Medium',
                    '600' => '600 Semi-bold', '700' => '700 Bold', '800' => '800 Extra-bold',
                ] );
                $this->select( 'Style', $prefix . '_style', $o, $opt, [
                    'normal' => 'Normal', 'italic' => 'Italic', 'uppercase' => 'UPPERCASE', 'capitalize' => 'Capitalize',
                ] );
                $this->color( 'Warna', $prefix . '_color', $o, $opt );
                $this->number( 'Ukuran desktop (px)', $prefix . '_size',        $o, $opt, 8, 200 );
                $this->number( 'Ukuran mobile (px)',  $prefix . '_size_mobile', $o, $opt, 8, 160 );
                $this->number( 'Letter spacing (×0.01em)', $prefix . '_letter_spacing', $o, $opt, -10, 80 );
                if ( $with_line_height ) {
                    $this->number( 'Line height (×0.01)', $prefix . '_line_height', $o, $opt, 80, 240 );
                }
                $this->bool( 'Bayangan teks', $prefix . '_shadow', $o, $opt );
                ?>
            </div>
        </div>
        <?php
    }

    private function cta_card( $title, $i, $o, $opt ) {
        ?>
        <div class="ptprm-card">
            <h3><?php echo esc_html( $title ); ?></h3>
            <?php $this->bool( 'Tampilkan', "cta{$i}_show", $o, $opt ); ?>
            <div class="ptprm-grid-3">
                <?php
                $this->t(   'Label tombol', "cta{$i}_label", $o, $opt );
                $this->url( 'URL tujuan',   "cta{$i}_url",   $o, $opt );
                $this->select( 'Target',    "cta{$i}_target", $o, $opt, [
                    '_self' => 'Tab sama', '_blank' => 'Tab baru',
                ] );
                $this->select( 'Ikon',      "cta{$i}_icon",  $o, $opt, ptprm_icon_choices() );
                $this->select( 'Style',     "cta{$i}_style", $o, $opt, [
                    'solid'   => 'Solid',
                    'outline' => 'Outline',
                    'ghost'   => 'Ghost',
                ] );
                $this->select( 'Ukuran',    "cta{$i}_size",  $o, $opt, [
                    'small' => 'Kecil', 'medium' => 'Sedang', 'large' => 'Besar',
                ] );
                $this->color( 'BG',           "cta{$i}_bg",          $o, $opt );
                $this->color( 'Teks',         "cta{$i}_color",       $o, $opt );
                $this->color( 'BG hover',     "cta{$i}_bg_hover",    $o, $opt );
                $this->color( 'Teks hover',   "cta{$i}_color_hover", $o, $opt );
                $this->number( 'Radius (px)', "cta{$i}_radius",      $o, $opt, 0, 60 );
                if ( $i === 2 ) {
                    $this->select( 'Mode tombol', 'cta2_mode', $o, $opt, [
                        'inquiry' => 'Form masukan → WhatsApp',
                        'link'    => 'Link langsung (URL di atas)',
                    ] );
                    $this->t( 'Judul form', 'cta2_inquiry_title', $o, $opt );
                    $this->ta( 'Petunjuk singkat', 'cta2_inquiry_hint', $o, $opt, 2 );
                    $this->ta( 'Teks awal (contoh pertanyaan)', 'cta2_inquiry_default', $o, $opt, 3 );
                    $this->t( 'Label tombol kirim', 'cta2_inquiry_submit', $o, $opt );
                    echo '<p class="ptprm-help">Mode <strong>Form masukan</strong> membuka popup; pesan dikirim ke nomor WhatsApp di tab Umum.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /* -- PANEL: BERANDA (selain Hero) -- */

    private function panel_beranda( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-beranda" data-panel="beranda" hidden>
            <h2>Section Beranda</h2>
            <p class="ptprm-help">Anda bisa mengubah urutan section (setelah Hero) dengan drag &amp; drop. Toggle “Tampilkan” tetap dihormati.</p>

            <?php
            $labels = ptprm_home_section_labels();
            $order = ptprm_get_home_sections_order( $o );
            ?>
            <div class="ptprm-card">
                <h3>Urutan section (setelah Hero)</h3>
                <p class="ptprm-help">Seret untuk mengubah urutan. Section yang dimatikan (toggle off) tetap tidak tampil meski urutannya di atas.</p>
                <ul class="ptprm-sortable" id="ptprm-home-sections-sort" data-ptprm-sortable>
                    <?php foreach ( $order as $slug ) : ?>
                        <li class="ptprm-sortable__item" data-section="<?php echo esc_attr( $slug ); ?>">
                            <span class="ptprm-sortable__handle" aria-hidden="true">⋮⋮</span>
                            <span class="ptprm-sortable__label"><?php echo esc_html( $labels[ $slug ] ?? $slug ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <input type="hidden" name="<?php echo esc_attr( $this->n( 'home_sections_order', $opt ) ); ?>"
                    value="<?php echo esc_attr( wp_json_encode( $order ) ); ?>"
                    data-ptprm="home_sections_order" />
            </div>

            <!-- ANGGOTA -->
            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Anggota (setelah Hero)', 'ptsbi-premium' ); ?></h3>
                <p class="ptprm-help"><?php esc_html_e( 'Form pendaftaran publik di beranda. Pencarian anggota hanya di Area Anggota (tab Cari Anggota) setelah login.', 'ptsbi-premium' ); ?></p>
                <?php
                $this->bool( __( 'Tampilkan form daftar anggota', 'ptsbi-premium' ), 'members_register_show', $o, $opt );
                $this->number( __( 'Baris per halaman (direktori)', 'ptsbi-premium' ), 'members_per_page', $o, $opt, 10, 50 );
                $this->t( __( 'Slug Rumah Anggota (login tunggal)', 'ptsbi-premium' ), 'portal_login_slug', $o, $opt );
                $this->t( __( 'Slug area anggota', 'ptsbi-premium' ), 'members_portal_slug', $o, $opt );
                ?>
                <p class="ptprm-help">
                    <?php
                    $login_slug = sanitize_title( (string) ( $o['portal_login_slug'] ?? $o['members_login_slug'] ?? 'rumah-anggota' ) );
                    esc_html_e( 'Semua peran masuk lewat satu halaman login (menu & tombol Rumah Anggota), lalu diarahkan ke panel masing-masing.', 'ptsbi-premium' );
                    echo '<br><strong>' . esc_html__( 'Login', 'ptsbi-premium' ) . ':</strong> ';
                    echo '<code>/' . esc_html( $login_slug ?: 'rumah-anggota' ) . '/</code>';
                    echo '<br><strong>' . esc_html__( 'Anggota', 'ptsbi-premium' ) . ':</strong> ';
                    echo '<code>/' . esc_html( $o['members_portal_slug'] ?? 'area-anggota' ) . '/</code>';
                    echo '<br><strong>' . esc_html__( 'Pengurus', 'ptsbi-premium' ) . ':</strong> ';
                    echo '<code>/' . esc_html( $o['admin_portal_slug'] ?? 'panel-pengurus' ) . '/</code>';
                    echo '<br><em>' . esc_html__( 'Halaman /masuk/ dan /masuk-pengurus/ lama otomatis dialihkan.', 'ptsbi-premium' ) . '</em>';
                    ?>
                </p>
                <p>
                    <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ptprm-settings&ptprm_member_pages=1' ), 'ptprm_member_pages' ) ); ?>">
                        <?php esc_html_e( 'Buat / perbarui halaman portal', 'ptsbi-premium' ); ?>
                    </a>
                    <?php if ( class_exists( 'PTPRM_Default_Accounts' ) ) : ?>
                    <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ptprm-settings&ptprm_default_accounts=1' ), 'ptprm_default_accounts' ) ); ?>">
                        <?php esc_html_e( 'Buat / reset akun demo', 'ptsbi-premium' ); ?>
                    </a>
                    <?php endif; ?>
                </p>
                <?php if ( class_exists( 'PTPRM_Default_Accounts' ) ) : ?>
                <div class="ptprm-default-accounts" style="margin-top:1rem;padding:1rem;background:#f6f7f7;border-radius:6px;">
                    <h4 style="margin:0 0 .5rem;"><?php esc_html_e( 'Akun demo (uji coba)', 'ptsbi-premium' ); ?></h4>
                    <p class="ptprm-help" style="margin-top:0;"><?php esc_html_e( 'Password sementara untuk admin & pengurus organisasi. Akun demo anggota otomatis terkunci setelah ada anggota terdaftar (bukan akun demo).', 'ptsbi-premium' ); ?></p>
                    <table class="widefat striped" style="max-width:720px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Peran', 'ptsbi-premium' ); ?></th>
                                <th><?php esc_html_e( 'Username', 'ptsbi-premium' ); ?></th>
                                <th><?php esc_html_e( 'Password', 'ptsbi-premium' ); ?></th>
                                <th><?php esc_html_e( 'Login', 'ptsbi-premium' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( PTPRM_Default_Accounts::credentials_for_admin() as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row['label'] ); ?><br><small><?php echo esc_html( $row['note'] ); ?></small></td>
                                <td><code><?php echo esc_html( $row['login'] ); ?></code></td>
                                <td><code><?php echo esc_html( $row['password'] ); ?></code></td>
                                <td><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Buka', 'ptsbi-premium' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php
                $this->t( __( 'Slug panel pengurus', 'ptsbi-premium' ), 'admin_portal_slug', $o, $opt );
                ?>
            </div>

            <!-- ABOUT -->
            <div class="ptprm-card">
                <h3>Tentang Kami</h3>
                <?php $this->bool( 'Tampilkan section', 'about_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t( 'Eyebrow', 'about_eyebrow', $o, $opt );
                        $this->t( 'Judul',  'about_title',   $o, $opt );
                        $this->ta( 'Isi paragraf', 'about_body', $o, $opt, 6 );
                        $this->t( 'Label tombol (opsional)', 'about_cta_label', $o, $opt );
                        $this->url( 'URL tombol (opsional)',  'about_cta_url',  $o, $opt );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->image( 'Gambar (opsional)', 'about_image', $o, $opt );
                        $this->select( 'Layout', 'about_layout', $o, $opt, [
                            'image-left'  => 'Gambar kiri, teks kanan',
                            'image-right' => 'Gambar kanan, teks kiri',
                            'text-only'   => 'Teks saja',
                        ] );
                        ?>
                    </div>
                </div>
            </div>

            <!-- VALUES -->
            <div class="ptprm-card">
                <h3>Nilai-Nilai Kami</h3>
                <?php
                $this->bool( 'Tampilkan section', 'values_show', $o, $opt );
                $this->t(  'Eyebrow',  'values_eyebrow',  $o, $opt );
                $this->t(  'Judul',    'values_title',    $o, $opt );
                $this->ta( 'Subjudul', 'values_subtitle', $o, $opt, 2 );
                $this->t( 'Teks petunjuk (jika kartu tanpa penjelasan)', 'values_hover_hint', $o, $opt );
                ?>
                <?php $this->repeater_values( $o, $opt ); ?>
            </div>

            <!-- ACTIVITIES -->
            <div class="ptprm-card">
                <h3>Kegiatan Kami</h3>
                <?php $this->bool( 'Tampilkan section', 'activities_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t(  'Eyebrow',  'activities_eyebrow',  $o, $opt );
                        $this->t(  'Judul',    'activities_title',    $o, $opt );
                        $this->ta( 'Subjudul', 'activities_subtitle', $o, $opt, 2 );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->number( 'Jumlah kartu', 'activities_count', $o, $opt, 1, 12 );
                        $this->category_select( 'Kategori', 'activities_category', $o, $opt );
                        $this->t(   'Label tombol "lihat semua"', 'activities_more_label', $o, $opt );
                        $this->url( 'URL halaman semua kegiatan', 'activities_more_url',   $o, $opt );
                        ?>
                    </div>
                </div>
                <p class="ptprm-help">Data diambil otomatis dari post WordPress terbaru. Pastikan post punya gambar utama (featured image).</p>
            </div>

            <!-- GALLERY -->
            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Galeri Foto', 'ptsbi-premium' ); ?></h3>
                <?php $this->bool( __( 'Tampilkan section', 'ptsbi-premium' ), 'gallery_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t( __( 'Eyebrow', 'ptsbi-premium' ), 'gallery_eyebrow', $o, $opt );
                        $this->t( __( 'Judul', 'ptsbi-premium' ), 'gallery_title', $o, $opt );
                        $this->ta( __( 'Subjudul', 'ptsbi-premium' ), 'gallery_subtitle', $o, $opt, 2 );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->select( __( 'Tampilan galeri', 'ptsbi-premium' ), 'gallery_layout', $o, $opt, [
                            'grid'    => __( 'Grid statis (kotak rapi)', 'ptsbi-premium' ),
                            'slider'  => __( 'Slider otomatis (tombol kiri/kanan + dots)', 'ptsbi-premium' ),
                            'marquee' => __( 'Marquee bergerak terus', 'ptsbi-premium' ),
                        ] );
                        $this->number( __( 'Foto per layar (desktop, 2–6)', 'ptsbi-premium' ), 'gallery_columns', $o, $opt, 2, 6 );
                        $this->bool( __( 'Aktifkan lightbox saat foto diklik', 'ptsbi-premium' ), 'gallery_lightbox', $o, $opt );
                        ?>
                    </div>
                </div>
                <div class="ptprm-grid-2">
                    <div class="ptprm-mini-card">
                        <strong><?php esc_html_e( 'Pengaturan Slider', 'ptsbi-premium' ); ?></strong>
                        <?php
                        $this->number( __( 'Auto-play (ms, 1000–10000)', 'ptsbi-premium' ), 'gallery_autoplay', $o, $opt, 1000, 10000 );
                        $this->bool( __( 'Tampilkan tombol panah', 'ptsbi-premium' ), 'gallery_arrows', $o, $opt );
                        $this->bool( __( 'Tampilkan dots indikator', 'ptsbi-premium' ), 'gallery_dots', $o, $opt );
                        $this->bool( __( 'Pause saat hover', 'ptsbi-premium' ), 'gallery_pause', $o, $opt );
                        ?>
                    </div>
                    <div class="ptprm-mini-card">
                        <strong><?php esc_html_e( 'Pengaturan Marquee', 'ptsbi-premium' ); ?></strong>
                        <?php
                        $this->number( __( 'Durasi loop penuh (detik, 10–120)', 'ptsbi-premium' ), 'gallery_speed', $o, $opt, 10, 120 );
                        ?>
                        <p class="ptprm-help" style="margin: 6px 0 0;"><?php esc_html_e( 'Angka lebih besar = gerakan lebih pelan.', 'ptsbi-premium' ); ?></p>
                    </div>
                </div>
                <?php $this->gallery_picker( __( 'Foto galeri (pilih banyak sekaligus)', 'ptsbi-premium' ), 'gallery_ids', $o, $opt ); ?>
            </div>

            <!-- STATS -->
            <div class="ptprm-card">
                <h3>Statistik</h3>
                <?php
                $this->bool( 'Tampilkan section', 'stats_show', $o, $opt );
                $this->t( 'Eyebrow', 'stats_eyebrow', $o, $opt );
                $this->t( 'Judul',   'stats_title',   $o, $opt );
                $this->bool( 'Animasi naik angka saat scroll', 'stats_animate', $o, $opt );
                ?>
                <div class="ptprm-grid-2">
                    <?php foreach ( [ 'a', 'b', 'c', 'd' ] as $k ) : ?>
                        <div class="ptprm-mini-card">
                            <strong>Statistik <?php echo strtoupper( $k ); ?></strong>
                            <?php
                            $this->t( 'Angka (mis. 10+)',  "stats_{$k}_num",   $o, $opt );
                            $this->t( 'Label',             "stats_{$k}_label", $o, $opt );
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TAROMBO DIGITAL -->
            <div class="ptprm-card">
                <h3>Tarombo Digital <span class="ptprm-tag">preview: tarombo.ptsbi.org</span></h3>
                <?php $this->bool( 'Tampilkan section', 'tarombo_digital_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t( 'Eyebrow',  'tarombo_digital_eyebrow',  $o, $opt );
                        $this->t( 'Judul',    'tarombo_digital_title',    $o, $opt );
                        $this->t( 'Subjudul', 'tarombo_digital_subtitle', $o, $opt );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->url( 'URL preview (iframe)', 'tarombo_digital_preview_url', $o, $opt );
                        $this->number( 'Tinggi iframe (px)', 'tarombo_digital_iframe_height', $o, $opt, 240, 900 );
                        $this->t( 'Nama di tautan tab baru', 'tarombo_digital_new_tab_name', $o, $opt );
                        ?>
                    </div>
                </div>
                <p class="ptprm-help">
                    Preview memakai <code>iframe</code>. Jika server target menolak embed, halaman ini mungkin tampil kosong—tautan <strong>Buka di … Tab baru</strong> tetap tersedia (kata pengganti … bisa diisi di atas).
                </p>
            </div>

            <!-- VISIT -->
            <div class="ptprm-card">
                <h3>Kunjungi Kami</h3>
                <?php $this->bool( 'Tampilkan section', 'visit_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t(  'Eyebrow',  'visit_eyebrow', $o, $opt );
                        $this->t(  'Judul',    'visit_title',   $o, $opt );
                        $this->ta( 'Paragraf', 'visit_intro',   $o, $opt, 3 );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->bool( 'Tampilkan alamat',  'visit_show_address', $o, $opt );
                        $this->bool( 'Tampilkan jam',     'visit_show_hours',   $o, $opt );
                        $this->bool( 'Tampilkan WA', 'visit_show_wa', $o, $opt );
                        ?>
                    </div>
                </div>
                <div class="ptprm-card ptprm-card--map" style="margin-top:16px;">
                    <h4><?php esc_html_e( 'Peta Google Maps', 'ptsbi-premium' ); ?></h4>
                    <p class="ptprm-help"><?php esc_html_e( 'Prioritas: (1) kode embed di bawah, (2) alamat lengkap di tab Umum → Kontak & Alamat.', 'ptsbi-premium' ); ?></p>
                    <?php $this->ta( 'Tempel HTML embed (iframe) dari Google Maps', 'visit_map_url', $o, $opt, 6 ); ?>
                    <p class="ptprm-help"><?php esc_html_e( 'Di Google Maps: Bagikan → Sematkan peta → Salin HTML, lalu tempel di sini. Kosongkan field ini jika ingin peta otomatis dari alamat Umum.', 'ptsbi-premium' ); ?></p>
                </div>
            </div>
        </section>
        <?php
    }

    /* -- PANEL: SECTION TAMBAHAN -- */

    private function panel_sections( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-sections" data-panel="sections" hidden>
            <h2>Section Tambahan</h2>
            <p class="ptprm-help">Empat section opsional yang umum dipakai organisasi. Semua <strong>default mati</strong> — aktifkan yang Anda perlukan.</p>

            <!-- TEAM -->
            <div class="ptprm-card">
                <h3>Pengurus / Tim <span class="ptprm-tag">posisi: setelah Nilai</span></h3>
                <?php $this->bool( 'Tampilkan section', 'team_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t(  'Eyebrow',  'team_eyebrow',  $o, $opt );
                        $this->t(  'Judul',    'team_title',    $o, $opt );
                        $this->ta( 'Subjudul', 'team_subtitle', $o, $opt, 2 );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->select( 'Tata letak', 'team_layout', $o, $opt, [
                            'grid'    => 'Grid datar (kolom otomatis)',
                            'grouped' => 'Kelompok per bidang/jabatan (isi kolom Grup)',
                        ] );
                        $this->number( 'Kolom maks. desktop (2–6)', 'team_columns', $o, $opt, 2, 6 );
                        $this->t(  'Label tombol "lihat semua" (opsional)', 'team_more_label', $o, $opt );
                        $this->url( 'URL tombol (opsional)',                'team_more_url',   $o, $opt );
                        ?>
                    </div>
                </div>

                <?php $this->repeater_team( $o, $opt ); ?>
            </div>

            <!-- BANNER CTA -->
            <div class="ptprm-card">
                <h3>Banner Ajakan <span class="ptprm-tag">posisi: setelah Statistik</span></h3>
                <?php $this->bool( 'Tampilkan section', 'banner_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <div>
                        <?php
                        $this->t(  'Eyebrow',  'banner_eyebrow',  $o, $opt );
                        $this->t(  'Judul',    'banner_title',    $o, $opt );
                        $this->ta( 'Subjudul', 'banner_subtitle', $o, $opt, 3 );
                        ?>
                    </div>
                    <div>
                        <?php
                        $this->image( 'Gambar latar', 'banner_image', $o, $opt );
                        $this->range( 'Opasitas overlay gelap (0–100%)', 'banner_overlay', $o, $opt, 0, 100 );
                        ?>
                    </div>
                </div>
                <div class="ptprm-grid-2">
                    <div class="ptprm-mini-card">
                        <strong>Tombol Utama</strong>
                        <?php
                        $this->t(  'Label', 'banner_cta_label', $o, $opt );
                        $this->url( 'URL',   'banner_cta_url',   $o, $opt );
                        ?>
                    </div>
                    <div class="ptprm-mini-card">
                        <strong>Tombol Sekunder (opsional)</strong>
                        <?php
                        $this->bool( 'Tampilkan tombol kedua', 'banner_cta2_show', $o, $opt );
                        $this->t(  'Label', 'banner_cta2_label', $o, $opt );
                        $this->url( 'URL (kosongkan = pakai WhatsApp)', 'banner_cta2_url',   $o, $opt );
                        ?>
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <div class="ptprm-card">
                <h3>Pertanyaan Umum (FAQ) <span class="ptprm-tag">posisi: setelah Kunjungi</span></h3>
                <?php $this->bool( 'Tampilkan section', 'faq_show', $o, $opt ); ?>
                <div class="ptprm-grid-2">
                    <?php
                    $this->t(  'Eyebrow',  'faq_eyebrow',  $o, $opt );
                    $this->t(  'Judul',    'faq_title',    $o, $opt );
                    ?>
                </div>
                <?php $this->ta( 'Subjudul', 'faq_subtitle', $o, $opt, 2 ); ?>

                <p class="ptprm-help">Isi maksimal 10 tanya–jawab. Slot dengan pertanyaan kosong tidak ditampilkan. HTML sederhana diizinkan di jawaban.</p>
                <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                    <div class="ptprm-mini-card">
                        <strong>Q&amp;A <?php echo $i; ?></strong>
                        <?php
                        $this->t(  'Pertanyaan', "faq_{$i}_q", $o, $opt );
                        $this->ta( 'Jawaban',    "faq_{$i}_a", $o, $opt, 3 );
                        ?>
                    </div>
                <?php endfor; ?>
            </div>
        </section>
        <?php
    }

    private function gallery_picker( $label, $key, $o, $opt ) {
        $val = (string) $this->v( $key, $o );
        $ids = array_filter( array_map( 'intval', explode( ',', $val ) ) );
        ?>
        <div class="ptprm-field ptprm-gallery-field" data-ptprm-gallery>
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <input type="hidden" name="<?php echo $this->n( $key, $opt ); ?>" value="<?php echo esc_attr( $val ); ?>" class="ptprm-gallery-value" data-ptprm="<?php echo esc_attr( $key ); ?>">
            <div class="ptprm-gallery-thumbs">
                <?php foreach ( $ids as $id ) :
                    $u = wp_get_attachment_image_url( (int) $id, 'thumbnail' );
                    if ( ! $u ) continue;
                    ?>
                    <span class="ptprm-gallery-thumb" data-id="<?php echo (int) $id; ?>" style="background-image:url('<?php echo esc_url( $u ); ?>');">
                        <button type="button" class="ptprm-gallery-remove" aria-label="Hapus">&times;</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="ptprm-gallery-actions">
                <button type="button" class="button ptprm-gallery-add">Tambah / Pilih foto</button>
                <button type="button" class="button ptprm-gallery-clear">Kosongkan</button>
            </div>
            <p class="ptprm-help">Pilih beberapa foto sekaligus dari Media Library. Anda bisa men-drag untuk mengubah urutan.</p>
        </div>
        <?php
    }

    /* -- PANEL: SUBPAGE -- */

    private function panel_subpage( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-subpage" data-panel="subpage" hidden>
            <h2>Header Sub-halaman</h2>
            <p class="ptprm-help"><?php esc_html_e( 'Hero sub-halaman seragam. Featured image dipakai jika ada; jika tidak, latar warna primer + corak dari tab Umum (bisa kosong).', 'ptsbi-premium' ); ?></p>

            <div class="ptprm-card">
                <?php
                $this->bool( 'Tampilkan hero sub-halaman',    'subpage_hero_show',  $o, $opt );
                $this->bool( 'Tampilkan breadcrumb',          'subpage_breadcrumb', $o, $opt );
                $this->bool( 'Tampilkan ornamen emas',        'subpage_ornament',   $o, $opt );
                $this->bool( 'Tampilkan corak latar di hero', 'subpage_gorga',      $o, $opt );
                $this->bool( 'Eyebrow otomatis (= judul kapital)', 'subpage_eyebrow_auto', $o, $opt );
                $this->range( 'Opasitas overlay (0–100%)',    'subpage_overlay',    $o, $opt, 0, 100 );
                $this->ta(    'Intro default (jika halaman tidak punya)', 'subpage_default_intro', $o, $opt, 3 );
                ?>
            </div>
        </section>
        <?php
    }

    /* -- PANEL: FOOTER -- */

    private function panel_footer( $o, $opt ) {
        ?>
        <section class="ptprm-panel" id="tab-footer" data-panel="footer" hidden>
            <h2>Footer</h2>
            <p class="ptprm-help">Footer plugin akan menggantikan footer tema otomatis.</p>

            <div class="ptprm-card">
                <?php
                $this->bool( 'Tampilkan footer plugin', 'footer_show', $o, $opt );
                $site_name = get_bloginfo( 'name' );
                $site_desc = get_bloginfo( 'description' );
                ?>
                <p class="ptprm-help">
                    <?php
                    printf(
                        /* translators: %s: site title from WordPress General settings */
                        esc_html__( 'Nama di footer mengikuti Judul situs WordPress: %s', 'ptsbi-premium' ),
                        '<strong>' . esc_html( $site_name ) . '</strong>'
                    );
                    ?>
                </p>
                <?php
                $this->ta(
                    __( 'Deskripsi footer (custom)', 'ptsbi-premium' ),
                    'footer_tagline',
                    $o,
                    $opt,
                    3
                );
                ?>
                <p class="ptprm-help">
                    <?php
                    if ( trim( (string) ( $o['footer_tagline'] ?? '' ) ) === '' && $site_desc ) {
                        printf(
                            /* translators: %s: site tagline */
                            esc_html__( 'Kosong — memakai Slogan situs: %s', 'ptsbi-premium' ),
                            '<em>' . esc_html( $site_desc ) . '</em>'
                        );
                    } else {
                        esc_html_e( 'Kosongkan field di atas untuk memakai Slogan situs dari Pengaturan → Umum WordPress.', 'ptsbi-premium' );
                    }
                    ?>
                </p>
            </div>

            <div class="ptprm-grid-2">
                <div class="ptprm-card">
                    <h3>Kolom Tautan 1</h3>
                    <?php
                    $this->t(  'Judul kolom', 'footer_col1_label', $o, $opt );
                    $this->ta( 'Tautan (satu per baris, format: Teks|URL)', 'footer_col1_links', $o, $opt, 6 );
                    ?>
                </div>
                <div class="ptprm-card">
                    <h3>Kolom Tautan 2</h3>
                    <?php
                    $this->t(  'Judul kolom', 'footer_col2_label', $o, $opt );
                    $this->ta( 'Tautan (satu per baris, format: Teks|URL)', 'footer_col2_links', $o, $opt, 6 );
                    ?>
                </div>
            </div>

            <div class="ptprm-card">
                <?php
                $this->bool( 'Tampilkan ikon sosial media', 'footer_show_social', $o, $opt );
                $this->t( 'Baris copyright (gunakan {year} dan {org})', 'footer_copyright', $o, $opt );
                ?>
            </div>
        </section>
        <?php
    }

    /* -- PANEL: PDF LIGHTBOX -- */

    private function panel_pdf_lightbox( $o, $opt ) {
        $cols = max( 2, min( 4, (int) ( $o['pdf_lightbox_columns'] ?? 3 ) ) );
        $sc_default = class_exists( 'PTPRM_Pdf_Lightbox' ) ? PTPRM_Pdf_Lightbox::format_shortcode( 'default', $cols ) : '[ptprm_pdf_lightbox]';
        $sc_hover   = class_exists( 'PTPRM_Pdf_Lightbox' ) ? PTPRM_Pdf_Lightbox::format_shortcode( 'hover', $cols ) : '[ptprm_pdf_lightbox variant="hover"]';
        $sc_pub     = class_exists( 'PTPRM_Pdf_Lightbox' ) ? PTPRM_Pdf_Lightbox::format_publications_shortcode( $cols ) : '[ptprm_pdf_publications]';
        $html_default = class_exists( 'PTPRM_Pdf_Lightbox' ) ? PTPRM_Pdf_Lightbox::format_html_embed( $o, 'default', $cols ) : '';
        $html_hover   = class_exists( 'PTPRM_Pdf_Lightbox' ) ? PTPRM_Pdf_Lightbox::format_html_embed( $o, 'hover', $cols ) : '';
        $pub_url    = ptprm_publications_page_url( $o );
        $setup_url  = wp_nonce_url(
            admin_url( 'admin.php?page=ptprm-settings&ptprm_setup_publications=1' ),
            'ptprm_setup_publications'
        );
        ?>
        <section class="ptprm-panel" id="tab-pdf_lightbox" data-panel="pdf_lightbox" hidden>
            <h2><?php esc_html_e( 'Panel PDF Lightbox', 'ptsbi-premium' ); ?></h2>
            <p class="ptprm-help">
                <?php esc_html_e( 'Kelola PDF organisasi di sini. Daftar otomatis tampil di halaman Publikasi & Dokumentasi (menu header) dengan tombol Baca (lightbox) dan Unduh.', 'ptsbi-premium' ); ?>
            </p>

            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Halaman Publikasi & Dokumentasi', 'ptsbi-premium' ); ?></h3>
                <p class="ptprm-help">
                    <?php esc_html_e( 'Menu header: Publikasi & Dokumentasi → membuka halaman daftar buku/PDF.', 'ptsbi-premium' ); ?>
                    <a href="<?php echo esc_url( $pub_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Lihat halaman', 'ptsbi-premium' ); ?></a>
                </p>
                <?php $this->t( __( 'Slug halaman (opsional)', 'ptsbi-premium' ), 'pdf_publications_page_slug', $o, $opt ); ?>
                <?php $this->ta( __( 'Teks pengantar di halaman publikasi', 'ptsbi-premium' ), 'pdf_publications_intro', $o, $opt, 2 ); ?>
                <p>
                    <a href="<?php echo esc_url( $setup_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Buat / sinkron halaman & menu', 'ptsbi-premium' ); ?></a>
                </p>
            </div>

            <div class="ptprm-card">
                <h3><?php esc_html_e( 'Daftar dokumen PDF', 'ptsbi-premium' ); ?></h3>
                <?php $this->number( __( 'Kolom pada kode (2–4)', 'ptsbi-premium' ), 'pdf_lightbox_columns', $o, $opt, 2, 4 ); ?>
                <p class="ptprm-help"><?php esc_html_e( 'Maksimal 24 dokumen. Simpan perubahan setelah menambah/mengubah PDF agar kode di bawah ikut terbarui.', 'ptsbi-premium' ); ?></p>
                <?php $this->repeater_pdf_lightbox( $o, $opt ); ?>
            </div>

            <div class="ptprm-card ptprm-pdf-code-card">
                <h3><?php esc_html_e( 'Kode shortcode (halaman lain)', 'ptsbi-premium' ); ?></h3>
                <?php $this->pdf_code_field( __( 'Halaman publikasi (baca + unduh)', 'ptsbi-premium' ), $sc_pub, 'ptprm-pdf-sc-pub' ); ?>
                <?php $this->pdf_code_field( __( 'Lightbox biasa', 'ptsbi-premium' ), $sc_default, 'ptprm-pdf-sc-default' ); ?>
                <?php $this->pdf_code_field( __( 'Lightbox hover (overlay pada kartu)', 'ptsbi-premium' ), $sc_hover, 'ptprm-pdf-sc-hover' ); ?>
            </div>

            <div class="ptprm-card ptprm-pdf-code-card">
                <h3><?php esc_html_e( 'Kode HTML (blok HTML kustom)', 'ptsbi-premium' ); ?></h3>
                <p class="ptprm-help"><?php esc_html_e( 'Alternatif jika tidak memakai shortcode. Struktur sama; lightbox tetap aktif lewat atribut data-ptprm-pdf-lightbox.', 'ptsbi-premium' ); ?></p>
                <?php $this->pdf_code_field( __( 'HTML — biasa', 'ptsbi-premium' ), $html_default, 'ptprm-pdf-html-default', true ); ?>
                <?php $this->pdf_code_field( __( 'HTML — hover', 'ptsbi-premium' ), $html_hover, 'ptprm-pdf-html-hover', true ); ?>
            </div>
        </section>
        <?php
    }

    /**
     * @param bool $multiline
     */
    private function pdf_code_field( string $label, string $code, string $id, bool $multiline = false ): void {
        $rows = $multiline ? max( 6, min( 24, substr_count( $code, "\n" ) + 2 ) ) : 2;
        ?>
        <div class="ptprm-pdf-code-row">
            <div class="ptprm-pdf-code-row-head">
                <strong><?php echo esc_html( $label ); ?></strong>
                <button type="button" class="button button-secondary ptprm-copy-code" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Salin', 'ptsbi-premium' ); ?></button>
            </div>
            <textarea id="<?php echo esc_attr( $id ); ?>" class="large-text code ptprm-pdf-code-output" rows="<?php echo (int) $rows; ?>" readonly><?php echo esc_textarea( $code ); ?></textarea>
        </div>
        <?php
    }

    /* ===================================================================== */
    /* FIELDS                                                                */
    /* ===================================================================== */

    private function repeater_header_menu( $o, $opt ) {
        $sub_max  = ptprm_header_submenu_max( $o );
        $items    = [];
        if ( ! empty( $o['header_menu_items'] ) ) {
            $decoded = json_decode( (string) $o['header_menu_items'], true );
            if ( is_array( $decoded ) ) {
                $items = ptprm_sanitize_header_menu_items( $decoded, 0, $sub_max );
            }
        }
        if ( ! $items && class_exists( 'PTPRM_Pages' ) ) {
            $items = PTPRM_Pages::default_menu_items_for_options();
        }
        $json     = wp_json_encode( $items, JSON_UNESCAPED_UNICODE );
        $json_b64 = base64_encode( (string) $json );
        ?>
        <input type="hidden" id="ptprm-header-menu-json" value="" data-ptprm="header_menu_items" data-ptprm-json-b64="<?php echo esc_attr( $json_b64 ); ?>" />
        <div class="ptprm-repeater" id="ptprm-menu-repeater" data-type="menu" data-tpl="#ptprm-tpl-menu-row" data-submenu-max="<?php echo esc_attr( (string) $sub_max ); ?>">
            <p class="ptprm-help"><?php esc_html_e( 'Item utama = menu atas. Klik “+ Tambah submenu” untuk dropdown (label + URL per baris).', 'ptsbi-premium' ); ?></p>
            <div class="ptprm-repeater-list"></div>
            <p><button type="button" class="button button-secondary ptprm-repeater-add">+ <?php esc_html_e( 'Tambah item menu', 'ptsbi-premium' ); ?></button></p>
        </div>
        <script type="text/template" id="ptprm-tpl-menu-row">
            <div class="ptprm-repeater-row ptprm-mini-card" data-index="{{index}}">
                <div class="ptprm-repeater-row-head">
                    <strong><?php esc_html_e( 'Item', 'ptsbi-premium' ); ?> {{num}}</strong>
                    <button type="button" class="button-link-delete ptprm-repeater-remove"><?php esc_html_e( 'Hapus', 'ptsbi-premium' ); ?></button>
                </div>
                <div class="ptprm-grid-2">
                    <label class="ptprm-field"><span class="ptprm-label"><?php esc_html_e( 'Label', 'ptsbi-premium' ); ?></span>
                        <input type="text" class="regular-text" data-field="label" value="{{label}}" /></label>
                    <label class="ptprm-field"><span class="ptprm-label">URL</span>
                        <input type="text" class="regular-text" data-field="url" value="{{url}}" placeholder="/tentang-kami/" /></label>
                </div>
                <div class="ptprm-grid-2">
                    <label class="ptprm-field"><span class="ptprm-label"><?php esc_html_e( 'Target', 'ptsbi-premium' ); ?></span>
                        <select data-field="target">
                            <option value="_self"><?php esc_html_e( 'Tab sama', 'ptsbi-premium' ); ?></option>
                            <option value="_blank"><?php esc_html_e( 'Tab baru', 'ptsbi-premium' ); ?></option>
                        </select></label>
                    <label class="ptprm-field ptprm-field-inline"><input type="checkbox" data-field="highlight" />
                        <span><?php esc_html_e( 'Sorot (tampil seperti tombol)', 'ptsbi-premium' ); ?></span></label>
                </div>
                <div class="ptprm-menu-children-wrap">
                    <p class="ptprm-menu-children-title"><strong><?php esc_html_e( 'Submenu (dropdown)', 'ptsbi-premium' ); ?></strong></p>
                    <div class="ptprm-menu-children-list"></div>
                    <p><button type="button" class="button button-secondary ptprm-menu-child-add">+ <?php esc_html_e( 'Tambah submenu', 'ptsbi-premium' ); ?></button></p>
                </div>
            </div>
        </script>
        <script type="text/template" id="ptprm-tpl-menu-child-row">
            <div class="ptprm-menu-child-row ptprm-mini-card">
                <div class="ptprm-repeater-row-head">
                    <strong><?php esc_html_e( 'Submenu', 'ptsbi-premium' ); ?></strong>
                    <button type="button" class="button-link-delete ptprm-menu-child-remove"><?php esc_html_e( 'Hapus', 'ptsbi-premium' ); ?></button>
                </div>
                <div class="ptprm-grid-2">
                    <label class="ptprm-field"><span class="ptprm-label"><?php esc_html_e( 'Label', 'ptsbi-premium' ); ?></span>
                        <input type="text" class="regular-text" data-field="label" value="" /></label>
                    <label class="ptprm-field"><span class="ptprm-label">URL</span>
                        <input type="text" class="regular-text" data-field="url" value="" placeholder="/halaman/" /></label>
                </div>
                <label class="ptprm-field"><span class="ptprm-label"><?php esc_html_e( 'Target', 'ptsbi-premium' ); ?></span>
                    <select data-field="target">
                        <option value="_self"><?php esc_html_e( 'Tab sama', 'ptsbi-premium' ); ?></option>
                        <option value="_blank"><?php esc_html_e( 'Tab baru', 'ptsbi-premium' ); ?></option>
                    </select></label>
            </div>
        </script>
        <?php
    }

    private function repeater_values( $o, $opt ) {
        $items   = ptprm_get_values_items( $o );
        $icons   = ptprm_icon_choices();
        $json    = wp_json_encode( $items, JSON_UNESCAPED_UNICODE );
        $json_b64 = base64_encode( (string) $json );
        ?>
        <input type="hidden" id="ptprm-values-items-json" value="" data-ptprm="values_items" data-ptprm-json-b64="<?php echo esc_attr( $json_b64 ); ?>" />
        <div class="ptprm-repeater" id="ptprm-values-repeater" data-type="values" data-tpl="#ptprm-tpl-value-row">
            <p class="ptprm-help">Tambah nilai sebanyak yang diperlukan. <strong>Penjelasan</strong> ditampilkan di kartu (rata kiri, mudah dibaca). Petunjuk hover hanya untuk kartu tanpa penjelasan. Grid menyesuaikan jumlah kartu.</p>
            <div class="ptprm-repeater-list"></div>
            <p><button type="button" class="button button-secondary ptprm-repeater-add">+ Tambah nilai</button></p>
        </div>
        <script type="text/template" id="ptprm-tpl-value-row">
            <div class="ptprm-repeater-row ptprm-mini-card" data-index="{{index}}">
                <div class="ptprm-repeater-row-head">
                    <strong>Nilai {{num}}</strong>
                    <button type="button" class="button-link-delete ptprm-repeater-remove">Hapus</button>
                </div>
                <label class="ptprm-field"><span class="ptprm-label">Ikon</span>
                    <select class="ptprm-repeater-icon" data-field="icon"><?php
                    foreach ( $icons as $val => $lab ) {
                        if ( $val === 'none' ) {
                            continue;
                        }
                        echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
                    }
                    ?></select>
                </label>
                <label class="ptprm-field"><span class="ptprm-label">Judul nilai</span>
                    <input type="text" class="regular-text ptprm-repeater-title" data-field="title" value="" />
                </label>
                <label class="ptprm-field"><span class="ptprm-label">Penjelasan</span>
                    <textarea rows="3" class="large-text ptprm-repeater-desc" data-field="desc"></textarea>
                </label>
            </div>
        </script>
        <?php
    }

    private function repeater_team( $o, $opt ) {
        $items = ptprm_get_team_items( $o );
        $json  = wp_json_encode( $items, JSON_UNESCAPED_UNICODE );
        $json_b64 = base64_encode( (string) $json );
        ?>
        <input type="hidden" id="ptprm-team-items-json" value="" data-ptprm="team_items" data-ptprm-json-b64="<?php echo esc_attr( $json_b64 ); ?>" />
        <div class="ptprm-repeater" id="ptprm-team-repeater" data-type="team" data-tpl="#ptprm-tpl-team-row">
            <p class="ptprm-help">Maksimal <strong>12 pengurus</strong>. Isi <strong>Grup</strong> (mis. Ketua, Sekretaris) lalu pilih tata letak <em>Kelompok per bidang</em> bila perlu.</p>
            <div class="ptprm-repeater-list"></div>
            <p><button type="button" class="button button-secondary ptprm-repeater-add">+ Tambah pengurus</button></p>
        </div>
        <script type="text/template" id="ptprm-tpl-team-row">
            <div class="ptprm-repeater-row ptprm-mini-card" data-index="{{index}}">
                <div class="ptprm-repeater-row-head">
                    <strong>Pengurus {{num}}</strong>
                    <button type="button" class="button-link-delete ptprm-repeater-remove">Hapus</button>
                </div>
                <div class="ptprm-image-field ptprm-repeater-image-field">
                    <span class="ptprm-label">Foto</span>
                    <div class="ptprm-image-preview" style="background-image:url({{preview}})"></div>
                    <input type="hidden" class="ptprm-image-value ptprm-repeater-image" data-field="image" value="{{image}}" />
                    <p>
                        <button type="button" class="button ptprm-image-pick">Pilih</button>
                        <button type="button" class="button ptprm-image-clear">Hapus</button>
                    </p>
                </div>
                <label class="ptprm-field"><span class="ptprm-label">Grup / bidang (opsional)</span>
                    <input type="text" class="regular-text ptprm-repeater-group" data-field="group" value="" placeholder="mis. Ketua Umum" />
                </label>
                <label class="ptprm-field"><span class="ptprm-label">Nama</span>
                    <input type="text" class="regular-text ptprm-repeater-name" data-field="name" value="" />
                </label>
                <label class="ptprm-field"><span class="ptprm-label">Jabatan</span>
                    <input type="text" class="regular-text ptprm-repeater-role" data-field="role" value="" />
                </label>
                <label class="ptprm-field"><span class="ptprm-label">Link profil (opsional)</span>
                    <input type="url" class="regular-text ptprm-repeater-url" data-field="url" value="" placeholder="https://" />
                </label>
            </div>
        </script>
        <?php
    }

    private function repeater_pdf_lightbox( $o, $opt ) {
        $items    = ptprm_get_pdf_lightbox_items( $o );
        $json     = wp_json_encode( $items, JSON_UNESCAPED_UNICODE );
        $json_b64 = base64_encode( (string) $json );
        ?>
        <input type="hidden" id="ptprm-pdf-items-json" value="" data-ptprm="pdf_lightbox_items" data-ptprm-json-b64="<?php echo esc_attr( $json_b64 ); ?>" />
        <div class="ptprm-repeater" id="ptprm-pdf-repeater" data-type="pdf" data-tpl="#ptprm-tpl-pdf-row">
            <p class="ptprm-help"><?php esc_html_e( 'Maksimal 24 dokumen. Unggah file PDF; sampul (cover) opsional — jika kosong, tampil ikon PDF.', 'ptsbi-premium' ); ?></p>
            <div class="ptprm-repeater-list"></div>
            <p><button type="button" class="button button-secondary ptprm-repeater-add">+ <?php esc_html_e( 'Tambah PDF', 'ptsbi-premium' ); ?></button></p>
        </div>
        <script type="text/template" id="ptprm-tpl-pdf-row">
            <div class="ptprm-repeater-row ptprm-mini-card" data-index="{{index}}">
                <div class="ptprm-repeater-row-head">
                    <strong><?php esc_html_e( 'Dokumen', 'ptsbi-premium' ); ?> {{num}}</strong>
                    <button type="button" class="button-link-delete ptprm-repeater-remove"><?php esc_html_e( 'Hapus', 'ptsbi-premium' ); ?></button>
                </div>
                <label class="ptprm-field"><span class="ptprm-label"><?php esc_html_e( 'Judul', 'ptsbi-premium' ); ?></span>
                    <input type="text" class="regular-text" data-field="title" value="" />
                </label>
                <div class="ptprm-image-field ptprm-repeater-pdf-field">
                    <span class="ptprm-label"><?php esc_html_e( 'File PDF', 'ptsbi-premium' ); ?></span>
                    <p class="ptprm-pdf-filename">{{pdf_name}}</p>
                    <input type="hidden" class="ptprm-pdf-value" data-field="pdf" value="{{pdf}}" />
                    <p>
                        <button type="button" class="button ptprm-pdf-pick"><?php esc_html_e( 'Pilih PDF', 'ptsbi-premium' ); ?></button>
                        <button type="button" class="button ptprm-pdf-clear"><?php esc_html_e( 'Hapus', 'ptsbi-premium' ); ?></button>
                    </p>
                </div>
                <div class="ptprm-image-field ptprm-repeater-image-field">
                    <span class="ptprm-label"><?php esc_html_e( 'Sampul (opsional)', 'ptsbi-premium' ); ?></span>
                    <div class="ptprm-image-preview" style="background-image:url({{cover_preview}})"></div>
                    <input type="hidden" class="ptprm-image-value ptprm-repeater-image" data-field="cover" value="{{cover}}" />
                    <p>
                        <button type="button" class="button ptprm-image-pick"><?php esc_html_e( 'Pilih gambar', 'ptsbi-premium' ); ?></button>
                        <button type="button" class="button ptprm-image-clear"><?php esc_html_e( 'Hapus', 'ptsbi-premium' ); ?></button>
                    </p>
                </div>
            </div>
        </script>
        <?php
    }

    private function n( $key, $opt ) { return esc_attr( $opt . '[' . $key . ']' ); }
    private function v( $key, $o )   { return isset( $o[ $key ] ) ? $o[ $key ] : ''; }

    private function t( $label, $key, $o, $opt ) {
        ?>
        <label class="ptprm-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <input type="text" class="regular-text" name="<?php echo $this->n( $key, $opt ); ?>" value="<?php echo esc_attr( $this->v( $key, $o ) ); ?>" data-ptprm="<?php echo esc_attr( $key ); ?>">
        </label>
        <?php
    }

    private function ta( $label, $key, $o, $opt, $rows = 4 ) {
        ?>
        <label class="ptprm-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <textarea rows="<?php echo (int) $rows; ?>" class="large-text" name="<?php echo $this->n( $key, $opt ); ?>" data-ptprm="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $this->v( $key, $o ) ); ?></textarea>
        </label>
        <?php
    }

    private function url( $label, $key, $o, $opt ) {
        ?>
        <label class="ptprm-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <input type="url" class="regular-text" name="<?php echo $this->n( $key, $opt ); ?>" value="<?php echo esc_attr( $this->v( $key, $o ) ); ?>" placeholder="https://" data-ptprm="<?php echo esc_attr( $key ); ?>">
        </label>
        <?php
    }

    private function number( $label, $key, $o, $opt, $min = 0, $max = 1000 ) {
        ?>
        <label class="ptprm-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <input type="number" min="<?php echo (int) $min; ?>" max="<?php echo (int) $max; ?>" name="<?php echo $this->n( $key, $opt ); ?>" value="<?php echo esc_attr( $this->v( $key, $o ) ); ?>" data-ptprm="<?php echo esc_attr( $key ); ?>">
        </label>
        <?php
    }

    private function range( $label, $key, $o, $opt, $min = 0, $max = 100 ) {
        $val = (int) $this->v( $key, $o );
        ?>
        <label class="ptprm-field ptprm-range">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?> <span class="ptprm-range-val"><?php echo $val; ?></span></span>
            <input type="range" min="<?php echo (int) $min; ?>" max="<?php echo (int) $max; ?>" name="<?php echo $this->n( $key, $opt ); ?>" value="<?php echo (int) $val; ?>" data-ptprm="<?php echo esc_attr( $key ); ?>">
        </label>
        <?php
    }

    private function color( $label, $key, $o, $opt ) {
        ?>
        <label class="ptprm-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <input type="text" class="ptprm-color regular-text" name="<?php echo $this->n( $key, $opt ); ?>" value="<?php echo esc_attr( $this->v( $key, $o ) ); ?>" data-ptprm="<?php echo esc_attr( $key ); ?>" data-default-color="<?php echo esc_attr( $this->v( $key, $o ) ); ?>">
        </label>
        <?php
    }

    private function bool( $label, $key, $o, $opt ) {
        ?>
        <label class="ptprm-switch">
            <input type="checkbox" name="<?php echo $this->n( $key, $opt ); ?>" value="1" <?php checked( 1, (int) $this->v( $key, $o ) ); ?> data-ptprm="<?php echo esc_attr( $key ); ?>">
            <span><?php echo esc_html( $label ); ?></span>
        </label>
        <?php
    }

    private function section_toggle( $key, $label, $o, $opt ) {
        echo '<div class="ptprm-card ptprm-card-toggle">';
        $this->bool( $label, $key, $o, $opt );
        echo '</div>';
    }

    private function select( $label, $key, $o, $opt, $options ) {
        $val = (string) $this->v( $key, $o );
        ?>
        <label class="ptprm-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <select name="<?php echo $this->n( $key, $opt ); ?>" data-ptprm="<?php echo esc_attr( $key ); ?>">
                <?php foreach ( $options as $k => $lab ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $val, (string) $k ); ?>><?php echo esc_html( $lab ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }

    private function image( $label, $key, $o, $opt ) {
        $val = $this->v( $key, $o );
        $url = ptprm_image_url( $val );
        ?>
        <div class="ptprm-field ptprm-image-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <div class="ptprm-image-control">
                <div class="ptprm-image-preview" style="<?php echo $url ? 'background-image:url(' . esc_url( $url ) . ');' : ''; ?>"></div>
                <input type="hidden" name="<?php echo $this->n( $key, $opt ); ?>" value="<?php echo esc_attr( $val ); ?>" class="ptprm-image-value" data-ptprm="<?php echo esc_attr( $key ); ?>">
                <button type="button" class="button ptprm-image-pick">Pilih gambar</button>
                <button type="button" class="button ptprm-image-clear">Hapus</button>
            </div>
        </div>
        <?php
    }

    private function category_select( $label, $key, $o, $opt ) {
        $val   = (int) $this->v( $key, $o );
        $terms = get_categories( [ 'hide_empty' => false ] );
        ?>
        <label class="ptprm-field">
            <span class="ptprm-label"><?php echo esc_html( $label ); ?></span>
            <select name="<?php echo $this->n( $key, $opt ); ?>" data-ptprm="<?php echo esc_attr( $key ); ?>">
                <option value="0" <?php selected( 0, $val ); ?>>— semua kategori —</option>
                <?php foreach ( $terms as $term ) : ?>
                    <option value="<?php echo (int) $term->term_id; ?>" <?php selected( $val, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }
}
