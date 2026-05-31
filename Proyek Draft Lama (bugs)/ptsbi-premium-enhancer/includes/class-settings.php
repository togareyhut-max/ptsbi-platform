<?php
/**
 * Admin "Section Studio" page with sidebar tabs + section controls.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Settings {

    const PAGE_SLUG  = 'section-studio';
    const GROUP_SLUG = 'studio_pe_group';

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( PTSBI_PE_FILE ), [ $this, 'action_links' ] );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Section Studio', 'ptsbi-premium-enhancer' ),
            __( 'Section Studio', 'ptsbi-premium-enhancer' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ],
            $this->menu_icon_svg(),
            58
        );
    }

    private function menu_icon_svg() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="16" height="4" rx="1"/><rect x="2" y="9" width="7" height="8" rx="1"/><rect x="11" y="9" width="7" height="3.5" rx="1"/><rect x="11" y="14" width="7" height="3" rx="1"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    public function register_settings() {
        register_setting( self::GROUP_SLUG, PTSBI_PE_OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
            'default'           => ptsbi_pe_defaults(),
        ] );
    }

    public function sanitize( $input ) {
        if ( ! is_array( $input ) ) {
            $input = [];
        }
        $defaults = ptsbi_pe_defaults();
        $clean    = [];

        // Booleans (checkboxes).
        $bools = [
            'enabled', 'cta1_show', 'cta2_show', 'cta2_use_whatsapp',
            'fix_hero_contrast', 'fix_hero_overlay', 'fix_sticky_header', 'fix_address_label', 'fix_hours_label',
            'fix_dup_sections', 'fix_double_footer', 'fix_news_card',
            'stats_animate', 'show_back_to_top',
        ];
        foreach ( $bools as $k ) {
            $clean[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
        }

        // Per-section booleans.
        foreach ( array_keys( ptsbi_pe_section_types() ) as $slug ) {
            $clean[ "sec_{$slug}_enabled" ] = ! empty( $input[ "sec_{$slug}_enabled" ] ) ? 1 : 0;
        }

        // Layout mode.
        $mode = isset( $input['layout_mode'] ) ? $input['layout_mode'] : 'elementor';
        $clean['layout_mode'] = in_array( $mode, [ 'elementor', 'auto' ], true ) ? $mode : 'elementor';

        // Address (textarea).
        $clean['address'] = isset( $input['address'] )
            ? sanitize_textarea_field( (string) $input['address'] )
            : ( $defaults['address'] ?? '' );
        $clean['cta2_inquiry_default'] = isset( $input['cta2_inquiry_default'] )
            ? sanitize_textarea_field( (string) $input['cta2_inquiry_default'] )
            : ( $defaults['cta2_inquiry_default'] ?? '' );

        // Strings.
        $strings = [
            'plugin_label', 'cta1_label', 'cta2_label', 'cta2_icon', 'cta1_style', 'cta2_style', 'cta2_mode',
            'cta2_inquiry_title', 'cta2_inquiry_hint', 'cta2_inquiry_submit',
            'whatsapp_number', 'font_heading', 'font_body',
            'stat_a_num', 'stat_a_label', 'stat_b_num', 'stat_b_label',
            'stat_c_num', 'stat_c_label', 'stat_d_num', 'stat_d_label',
        ];
        foreach ( $strings as $k ) {
            $clean[ $k ] = isset( $input[ $k ] ) ? ptsbi_pe_safe_text( $input[ $k ] ) : ( $defaults[ $k ] ?? '' );
        }
        $mode = isset( $input['cta2_mode'] ) ? $input['cta2_mode'] : ( $defaults['cta2_mode'] ?? 'inquiry' );
        $clean['cta2_mode'] = in_array( $mode, [ 'link', 'inquiry' ], true ) ? $mode : 'inquiry';

        // URLs.
        $urls = [ 'cta1_url', 'cta2_url', 'fb_url', 'ig_url', 'yt_url', 'tt_url' ];
        foreach ( $urls as $k ) {
            $clean[ $k ] = isset( $input[ $k ] ) ? ptsbi_pe_safe_url( $input[ $k ] ) : '';
        }

        // Numeric ints.
        $ints = [
            'container_max'       => [ 600, 1600 ],
            'news_image_height'   => [ 80, 600 ],
            'news_overlay_opacity'=> [ 0, 100 ],
        ];
        foreach ( $ints as $k => $range ) {
            $clean[ $k ] = isset( $input[ $k ] ) ? (int) ptsbi_pe_safe_int( $input[ $k ], $range[0], $range[1] ) : $defaults[ $k ];
        }

        // Colors.
        $colors = [
            'color_primary', 'color_accent', 'color_text', 'color_text_soft',
            'cta1_color_bg', 'cta1_color_text', 'cta2_color_bg', 'cta2_color_text',
        ];
        foreach ( $colors as $k ) {
            $v = isset( $input[ $k ] ) ? ptsbi_pe_safe_color( $input[ $k ] ) : '';
            $clean[ $k ] = $v ? $v : ( $defaults[ $k ] ?? '' );
        }

        // Per-section blocks.
        foreach ( array_keys( ptsbi_pe_section_types() ) as $slug ) {
            $section_defaults = ptsbi_pe_section_defaults();
            foreach ( $section_defaults as $k => $dflt ) {
                if ( $k === 'enabled' ) continue; // handled above
                $field = "sec_{$slug}_{$k}";
                $val   = isset( $input[ $field ] ) ? $input[ $field ] : '';

                if ( strpos( $k, 'color' ) !== false ) {
                    $val = ptsbi_pe_safe_color( $val );
                } elseif ( strpos( $k, 'size' ) !== false || strpos( $k, 'pad_' ) !== false ) {
                    $val = ptsbi_pe_safe_int( $val, 0, 400 );
                } else {
                    $val = ptsbi_pe_safe_text( $val );
                }
                $clean[ $field ] = $val;
            }
        }

        return $clean;
    }

    public function enqueue( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style(
            'studio-pe-admin',
            PTSBI_PE_URL . 'assets/css/ptsbi-admin.css',
            [],
            PTSBI_PE_VERSION
        );
        wp_enqueue_script(
            'studio-pe-admin',
            PTSBI_PE_URL . 'assets/js/ptsbi-admin.js',
            [ 'jquery', 'wp-color-picker' ],
            PTSBI_PE_VERSION,
            true
        );
    }

    public function action_links( $links ) {
        $url      = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        $settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Buka Studio', 'ptsbi-premium-enhancer' ) . '</a>';
        array_unshift( $links, $settings );
        return $links;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $o    = ptsbi_pe_get_options();
        $opt  = PTSBI_PE_OPTION;
        $home = home_url( '/' );
        ?>
        <div class="wrap studio-wrap">
            <div class="studio-topbar">
                <div class="studio-brand">
                    <span class="studio-brand-icon"></span>
                    <strong>Section Studio</strong>
                    <span class="studio-brand-ver">v<?php echo esc_html( PTSBI_PE_VERSION ); ?></span>
                </div>
                <div class="studio-topbar-right">
                    <a href="<?php echo esc_url( $home ); ?>" target="_blank" class="button button-secondary">Buka Beranda</a>
                </div>
            </div>

            <form method="post" action="options.php" class="studio-form">
                <?php settings_fields( self::GROUP_SLUG ); ?>

                <div class="studio-layout">
                    <aside class="studio-sidebar">
                        <ul class="studio-tabs" role="tablist">
                            <li><a href="#tab-global"  data-tab="global"  class="is-active"><span class="i">🌐</span> Global</a></li>
                            <li><a href="#tab-hero"    data-tab="hero">   <span class="i">🎯</span> Hero & CTA</a></li>
                            <li><a href="#tab-about"   data-tab="about">  <span class="i">ℹ️</span> Tentang</a></li>
                            <li><a href="#tab-values"  data-tab="values"> <span class="i">💎</span> Nilai-Nilai</a></li>
                            <li><a href="#tab-news"    data-tab="news">   <span class="i">📰</span> Berita / Kegiatan</a></li>
                            <li><a href="#tab-stats"   data-tab="stats">  <span class="i">📊</span> Statistik</a></li>
                            <li><a href="#tab-contact" data-tab="contact"><span class="i">📍</span> Kontak</a></li>
                            <li><a href="#tab-footer"  data-tab="footer"> <span class="i">🦶</span> Footer</a></li>
                            <li><a href="#tab-fixes"   data-tab="fixes">  <span class="i">🛠️</span> Perbaikan</a></li>
                        </ul>
                        <div class="studio-save-row">
                            <?php submit_button( 'Simpan Perubahan', 'primary studio-save', 'submit', false ); ?>
                        </div>
                    </aside>

                    <main class="studio-main">
                        <?php $this->panel_global( $o, $opt ); ?>
                        <?php $this->panel_hero( $o, $opt ); ?>
                        <?php $this->panel_section( 'about',   'Tentang',          $o, $opt ); ?>
                        <?php $this->panel_section( 'values',  'Nilai-Nilai',      $o, $opt ); ?>
                        <?php $this->panel_news(   $o, $opt ); ?>
                        <?php $this->panel_stats(  $o, $opt ); ?>
                        <?php $this->panel_section( 'contact', 'Kontak/Kunjungi',  $o, $opt ); ?>
                        <?php $this->panel_section( 'footer',  'Footer',           $o, $opt ); ?>
                        <?php $this->panel_fixes(  $o, $opt ); ?>
                    </main>
                </div>
            </form>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------- */
    /* PANELS                                                            */
    /* ---------------------------------------------------------------- */

    private function panel_global( $o, $opt ) {
        ?>
        <section class="studio-panel" id="tab-global" data-tab-panel="global">
            <h2 class="studio-h2">Pengaturan Global</h2>
            <p class="studio-help">Pengaturan ini berlaku untuk seluruh halaman. Setting di Elementor selalu menang atas pengaturan ini.</p>

            <div class="studio-card">
                <h3>Status</h3>
                <label class="studio-switch">
                    <input type="checkbox" name="<?php echo esc_attr($opt); ?>[enabled]" value="1" <?php checked( 1, $o['enabled'] ); ?>>
                    <span>Aktifkan Section Studio</span>
                </label>
            </div>

            <div class="studio-card">
                <h3><?php esc_html_e( 'Tata letak Beranda', 'ptsbi-premium-enhancer' ); ?></h3>
                <p class="studio-help"><?php esc_html_e( 'Mode Elementor: tarik widget Section Studio di halaman Beranda (disarankan). Mode Otomatis: plugin menyisipkan section lewat JavaScript (legacy).', 'ptsbi-premium-enhancer' ); ?></p>
                <?php
                $mode = $o['layout_mode'] ?? 'elementor';
                ?>
                <label class="studio-radio">
                    <input type="radio" name="<?php echo esc_attr( $opt ); ?>[layout_mode]" value="elementor" <?php checked( 'elementor', $mode ); ?>>
                    <span><strong>Elementor</strong> — drag &amp; drop widget (Nilai-Nilai, Kunjungi Kami, Footer)</span>
                </label>
                <label class="studio-radio">
                    <input type="radio" name="<?php echo esc_attr( $opt ); ?>[layout_mode]" value="auto" <?php checked( 'auto', $mode ); ?>>
                    <span><strong>Otomatis</strong> — plugin menempelkan section sendiri (bisa menempel ke blok lain)</span>
                </label>
            </div>

            <div class="studio-card">
                <h3><?php esc_html_e( 'Alamat default', 'ptsbi-premium-enhancer' ); ?></h3>
                <p class="studio-help"><?php esc_html_e( 'Dipakai widget Kunjungi Kami & Footer jika alamat di widget dikosongkan.', 'ptsbi-premium-enhancer' ); ?></p>
                <textarea class="large-text" rows="3" name="<?php echo esc_attr( $opt ); ?>[address]"><?php echo esc_textarea( $o['address'] ?? '' ); ?></textarea>
            </div>

            <div class="studio-grid-2">
                <div class="studio-card">
                    <h3>Warna Brand</h3>
                    <?php $this->field_color( 'Warna Primer (Navy)', 'color_primary', $o, $opt ); ?>
                    <?php $this->field_color( 'Warna Aksen (Emas)',  'color_accent',  $o, $opt ); ?>
                    <?php $this->field_color( 'Warna Teks Utama',    'color_text',    $o, $opt ); ?>
                    <?php $this->field_color( 'Warna Teks Halus',    'color_text_soft', $o, $opt ); ?>
                </div>
                <div class="studio-card">
                    <h3>Font Default</h3>
                    <?php $this->field_font( 'Font Heading', 'font_heading', $o, $opt ); ?>
                    <?php $this->field_font( 'Font Body',    'font_body',    $o, $opt ); ?>
                    <?php $this->field_int( 'Lebar Konten Maksimum (px)', 'container_max', $o, $opt, 600, 1600 ); ?>
                </div>
            </div>

            <div class="studio-card">
                <h3>Kontak & Sosial Media</h3>
                <?php $this->field_text( 'Nomor WhatsApp',    'whatsapp_number', $o, $opt, '+62 812 xxxx xxxx' ); ?>
                <div class="studio-grid-2">
                    <?php $this->field_url( 'Facebook',  'fb_url', $o, $opt ); ?>
                    <?php $this->field_url( 'Instagram', 'ig_url', $o, $opt ); ?>
                    <?php $this->field_url( 'YouTube',   'yt_url', $o, $opt ); ?>
                    <?php $this->field_url( 'TikTok',    'tt_url', $o, $opt ); ?>
                </div>
            </div>
        </section>
        <?php
    }

    private function panel_hero( $o, $opt ) {
        ?>
        <section class="studio-panel" id="tab-hero" data-tab-panel="hero">
            <h2 class="studio-h2">Hero & Tombol CTA</h2>
            <p class="studio-help">Atur dua tombol di Hero secara mandiri. Tombol 2 default-nya "Hubungi WA" yang otomatis pakai nomor WhatsApp dari tab Global.</p>

            <?php $this->section_visibility( 'hero', 'Hero', $o, $opt ); ?>

            <div class="studio-grid-2">
                <div class="studio-card">
                    <h3>Tombol 1 (Primary)</h3>
                    <label class="studio-switch">
                        <input type="checkbox" name="<?php echo esc_attr($opt);?>[cta1_show]" value="1" <?php checked( 1, $o['cta1_show'] ); ?>>
                        <span>Tampilkan tombol 1</span>
                    </label>
                    <?php $this->field_text( 'Label Tombol 1', 'cta1_label', $o, $opt, 'Bergabung Sekarang' ); ?>
                    <?php $this->field_url(  'URL Tujuan',     'cta1_url',   $o, $opt ); ?>
                    <?php $this->field_select( 'Gaya Tombol', 'cta1_style', $o, $opt, [
                        'filled'  => 'Filled (terisi)',
                        'outline' => 'Outline (garis)',
                        'ghost'   => 'Ghost (transparan)',
                    ] ); ?>
                    <div class="studio-grid-2">
                        <?php $this->field_color( 'Warna Background', 'cta1_color_bg',   $o, $opt ); ?>
                        <?php $this->field_color( 'Warna Teks',        'cta1_color_text', $o, $opt ); ?>
                    </div>
                </div>

                <div class="studio-card">
                    <h3>Tombol 2 (Secondary)</h3>
                    <label class="studio-switch">
                        <input type="checkbox" name="<?php echo esc_attr($opt);?>[cta2_show]" value="1" <?php checked( 1, $o['cta2_show'] ); ?>>
                        <span>Tampilkan tombol 2</span>
                    </label>
                    <?php $this->field_text( 'Label Tombol 2', 'cta2_label', $o, $opt, 'Hubungi WA' ); ?>
                    <?php $this->field_select( 'Mode Tombol 2', 'cta2_mode', $o, $opt, [
                        'inquiry' => 'Form masukan → WhatsApp',
                        'link'    => 'Link langsung (URL / WA)',
                    ] ); ?>
                    <?php $this->field_url(  'URL Tujuan (mode Link; kosong = pakai WhatsApp Global)', 'cta2_url', $o, $opt ); ?>
                    <label class="studio-switch">
                        <input type="checkbox" name="<?php echo esc_attr($opt);?>[cta2_use_whatsapp]" value="1" <?php checked( 1, $o['cta2_use_whatsapp'] ); ?>>
                        <span>Otomatis pakai nomor WhatsApp Global jika URL kosong (mode Link)</span>
                    </label>
                    <h4 style="margin:1rem 0 .5rem;">Form masukan (mode Form → WhatsApp)</h4>
                    <?php $this->field_text( 'Judul form', 'cta2_inquiry_title', $o, $opt, 'Kirim Pertanyaan' ); ?>
                    <?php $this->field_text( 'Petunjuk singkat', 'cta2_inquiry_hint', $o, $opt, 'Tulis pertanyaan Anda — akan dikirim lewat WhatsApp.' ); ?>
                    <p class="studio-field">
                        <label for="<?php echo esc_attr($opt); ?>_cta2_inquiry_default">Teks awal (contoh pertanyaan)</label>
                        <textarea id="<?php echo esc_attr($opt); ?>_cta2_inquiry_default" name="<?php echo esc_attr($opt); ?>[cta2_inquiry_default]" rows="3" class="large-text"><?php echo esc_textarea( $o['cta2_inquiry_default'] ?? '' ); ?></textarea>
                    </p>
                    <?php $this->field_text( 'Label tombol kirim', 'cta2_inquiry_submit', $o, $opt, 'Kirim via WhatsApp' ); ?>
                    <?php $this->field_select( 'Gaya Tombol', 'cta2_style', $o, $opt, [
                        'filled'      => 'Filled (terisi)',
                        'outline'     => 'Outline (garis)',
                        'ghost-light' => 'Ghost Putih (transparan)',
                        'ghost-dark'  => 'Ghost Gelap (transparan)',
                    ] ); ?>
                    <?php $this->field_select( 'Ikon', 'cta2_icon', $o, $opt, [
                        ''         => '— Tanpa ikon —',
                        'whatsapp' => 'WhatsApp',
                        'phone'    => 'Telepon',
                        'mail'     => 'Email',
                        'arrow'    => 'Panah →',
                    ] ); ?>
                    <div class="studio-grid-2">
                        <?php $this->field_color( 'Warna Background', 'cta2_color_bg',   $o, $opt ); ?>
                        <?php $this->field_color( 'Warna Teks',        'cta2_color_text', $o, $opt ); ?>
                    </div>
                </div>
            </div>

            <?php $this->section_layout_card( 'hero', $o, $opt ); ?>
            <?php $this->section_typography_card( 'hero', $o, $opt ); ?>
        </section>
        <?php
    }

    private function panel_news( $o, $opt ) {
        ?>
        <section class="studio-panel" id="tab-news" data-tab-panel="news">
            <h2 class="studio-h2">Berita / Kegiatan Kami</h2>
            <p class="studio-help">Section ini menampilkan kartu berita. Atur tinggi gambar dan opacity overlay supaya teks tetap rapi dan gambar tidak menutupi judul.</p>

            <?php $this->section_visibility( 'news', 'Berita / Kegiatan', $o, $opt ); ?>

            <div class="studio-card">
                <h3>Konfigurasi Kartu Berita</h3>
                <?php $this->field_int( 'Tinggi Gambar (px)', 'news_image_height', $o, $opt, 80, 600 ); ?>
                <?php $this->field_int( 'Opacity Overlay Hitam (%) — 0 = tanpa overlay', 'news_overlay_opacity', $o, $opt, 0, 100 ); ?>
            </div>

            <?php $this->section_layout_card( 'news', $o, $opt ); ?>
            <?php $this->section_typography_card( 'news', $o, $opt ); ?>
        </section>
        <?php
    }

    private function panel_stats( $o, $opt ) {
        ?>
        <section class="studio-panel" id="tab-stats" data-tab-panel="stats">
            <h2 class="studio-h2">Statistik</h2>
            <p class="studio-help">Atur 4 angka statistik yang ditampilkan di Beranda.</p>

            <?php $this->section_visibility( 'stats', 'Statistik', $o, $opt ); ?>

            <div class="studio-card">
                <h3>Angka Statistik</h3>
                <label class="studio-switch">
                    <input type="checkbox" name="<?php echo esc_attr($opt);?>[stats_animate]" value="1" <?php checked( 1, $o['stats_animate'] ); ?>>
                    <span>Animasi count-up saat di-scroll</span>
                </label>
                <div class="studio-grid-2" style="margin-top:14px">
                    <?php $this->field_text( 'Angka A', 'stat_a_num',   $o, $opt ); ?>
                    <?php $this->field_text( 'Label A', 'stat_a_label', $o, $opt ); ?>
                    <?php $this->field_text( 'Angka B', 'stat_b_num',   $o, $opt ); ?>
                    <?php $this->field_text( 'Label B', 'stat_b_label', $o, $opt ); ?>
                    <?php $this->field_text( 'Angka C', 'stat_c_num',   $o, $opt ); ?>
                    <?php $this->field_text( 'Label C', 'stat_c_label', $o, $opt ); ?>
                    <?php $this->field_text( 'Angka D', 'stat_d_num',   $o, $opt ); ?>
                    <?php $this->field_text( 'Label D', 'stat_d_label', $o, $opt ); ?>
                </div>
            </div>

            <?php $this->section_layout_card( 'stats', $o, $opt ); ?>
            <?php $this->section_typography_card( 'stats', $o, $opt ); ?>
        </section>
        <?php
    }

    private function panel_section( $slug, $title, $o, $opt ) {
        ?>
        <section class="studio-panel" id="tab-<?php echo esc_attr($slug); ?>" data-tab-panel="<?php echo esc_attr($slug); ?>">
            <h2 class="studio-h2"><?php echo esc_html( $title ); ?></h2>
            <p class="studio-help">Atur tampilan section <strong><?php echo esc_html( $title ); ?></strong>. Kosongkan field untuk pakai default tema/Elementor.</p>

            <?php $this->section_visibility( $slug, $title, $o, $opt ); ?>
            <?php $this->section_layout_card( $slug, $o, $opt ); ?>
            <?php $this->section_typography_card( $slug, $o, $opt ); ?>
        </section>
        <?php
    }

    private function panel_fixes( $o, $opt ) {
        ?>
        <section class="studio-panel" id="tab-fixes" data-tab-panel="fixes">
            <h2 class="studio-h2">Perbaikan Otomatis</h2>
            <p class="studio-help">Toggle perbaikan-perbaikan kecil yang plugin lakukan secara otomatis. Matikan jika ada konflik dengan setting Anda di Elementor.</p>

            <div class="studio-card">
                <h3>Perbaikan</h3>
                <?php $this->fix_toggle( 'fix_hero_contrast', 'Perbaiki kontras teks Hero (hilangkan italic & terangi)', $o, $opt ); ?>
                <?php $this->fix_toggle( 'fix_hero_overlay',  'Overlay Hero hanya di sisi teks (foto tetap terlihat utuh)', $o, $opt ); ?>
                <?php $this->fix_toggle( 'fix_sticky_header', 'Header tetap di atas saat scroll (semua halaman)', $o, $opt ); ?>
                <?php $this->fix_toggle( 'fix_address_label', 'Translate label "Address:" → "Alamat:"', $o, $opt ); ?>
                <?php $this->fix_toggle( 'fix_hours_label',   'Translate "Mon-Fri 9:00AM-5:00PM" → "Senin–Jumat, 09.00–17.00 WIB"', $o, $opt ); ?>
                <?php $this->fix_toggle( 'fix_dup_sections',  'Hapus section asli yang duplikat saat plugin override', $o, $opt ); ?>
                <?php $this->fix_toggle( 'fix_double_footer', 'Sembunyikan footer asli ketika plugin tidak menggantinya', $o, $opt ); ?>
                <?php $this->fix_toggle( 'fix_news_card',     'Perkecil gambar kartu berita & hilangkan overlay penutup', $o, $opt ); ?>
                <?php $this->fix_toggle( 'show_back_to_top',  'Tampilkan tombol "Kembali ke atas"', $o, $opt ); ?>
            </div>
        </section>
        <?php
    }

    /* ---------------------------------------------------------------- */
    /* FIELD RENDERERS                                                   */
    /* ---------------------------------------------------------------- */

    private function section_visibility( $slug, $title, $o, $opt ) {
        $key = "sec_{$slug}_enabled";
        ?>
        <div class="studio-card">
            <h3>Visibilitas Section</h3>
            <label class="studio-switch">
                <input type="checkbox" name="<?php echo esc_attr($opt);?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked( 1, (int) ( $o[ $key ] ?? 1 ) ); ?>>
                <span>Tampilkan section <?php echo esc_html( $title ); ?> di Beranda</span>
            </label>
        </div>
        <?php
    }

    private function section_layout_card( $slug, $o, $opt ) {
        ?>
        <div class="studio-card">
            <h3>Latar & Spacing</h3>
            <?php $this->field_color( 'Background Color', "sec_{$slug}_bg_color", $o, $opt ); ?>
            <div class="studio-grid-2">
                <?php $this->field_int( 'Padding Atas Desktop (px)',  "sec_{$slug}_pad_top",    $o, $opt, 0, 400 ); ?>
                <?php $this->field_int( 'Padding Bawah Desktop (px)', "sec_{$slug}_pad_bottom", $o, $opt, 0, 400 ); ?>
                <?php $this->field_int( 'Padding Atas Mobile (px)',   "sec_{$slug}_pad_top_mobile",    $o, $opt, 0, 400 ); ?>
                <?php $this->field_int( 'Padding Bawah Mobile (px)',  "sec_{$slug}_pad_bottom_mobile", $o, $opt, 0, 400 ); ?>
            </div>
        </div>
        <?php
    }

    private function section_typography_card( $slug, $o, $opt ) {
        ?>
        <div class="studio-card">
            <h3>Tipografi</h3>
            <div class="studio-grid-2">
                <div>
                    <strong>Heading</strong>
                    <?php $this->field_font(  'Font',           "sec_{$slug}_heading_font",        $o, $opt ); ?>
                    <?php $this->field_color( 'Warna',          "sec_{$slug}_heading_color",       $o, $opt ); ?>
                    <?php $this->field_int(   'Ukuran Desktop (px)', "sec_{$slug}_heading_size",   $o, $opt, 0, 100 ); ?>
                    <?php $this->field_int(   'Ukuran Mobile (px)',  "sec_{$slug}_heading_size_mobile", $o, $opt, 0, 80 ); ?>
                </div>
                <div>
                    <strong>Body / Paragraf</strong>
                    <?php $this->field_font(  'Font',           "sec_{$slug}_body_font",     $o, $opt ); ?>
                    <?php $this->field_color( 'Warna',          "sec_{$slug}_body_color",    $o, $opt ); ?>
                    <?php $this->field_int(   'Ukuran Desktop (px)', "sec_{$slug}_body_size", $o, $opt, 0, 60 ); ?>
                    <?php $this->field_int(   'Ukuran Mobile (px)',  "sec_{$slug}_body_size_mobile", $o, $opt, 0, 40 ); ?>
                </div>
            </div>
            <p class="studio-hint">Kosongkan field untuk membiarkan Elementor/tema yang menentukan.</p>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------- */
    /* FIELD HELPERS                                                     */
    /* ---------------------------------------------------------------- */

    private function field_text( $label, $key, $o, $opt, $placeholder = '' ) {
        ?>
        <div class="studio-field">
            <label><?php echo esc_html( $label ); ?></label>
            <input type="text" name="<?php echo esc_attr($opt);?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr( $o[ $key ] ?? '' ); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
        </div>
        <?php
    }

    private function field_url( $label, $key, $o, $opt ) {
        ?>
        <div class="studio-field">
            <label><?php echo esc_html( $label ); ?></label>
            <input type="url" name="<?php echo esc_attr($opt);?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr( $o[ $key ] ?? '' ); ?>" placeholder="https://...">
        </div>
        <?php
    }

    private function field_int( $label, $key, $o, $opt, $min = 0, $max = 1000 ) {
        $val = $o[ $key ] ?? '';
        ?>
        <div class="studio-field">
            <label><?php echo esc_html( $label ); ?></label>
            <input type="number" min="<?php echo intval($min);?>" max="<?php echo intval($max);?>" name="<?php echo esc_attr($opt);?>[<?php echo esc_attr($key);?>]" value="<?php echo esc_attr( $val === '' ? '' : (int) $val ); ?>">
        </div>
        <?php
    }

    private function field_color( $label, $key, $o, $opt ) {
        ?>
        <div class="studio-field">
            <label><?php echo esc_html( $label ); ?></label>
            <input type="text" class="studio-color" name="<?php echo esc_attr($opt);?>[<?php echo esc_attr($key);?>]" value="<?php echo esc_attr( $o[ $key ] ?? '' ); ?>">
        </div>
        <?php
    }

    private function field_select( $label, $key, $o, $opt, $choices ) {
        $cur = $o[ $key ] ?? '';
        ?>
        <div class="studio-field">
            <label><?php echo esc_html( $label ); ?></label>
            <select name="<?php echo esc_attr($opt);?>[<?php echo esc_attr($key);?>]">
                <?php foreach ( $choices as $val => $lbl ) : ?>
                    <option value="<?php echo esc_attr($val);?>" <?php selected( $cur, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    private function field_font( $label, $key, $o, $opt ) {
        $choices = [];
        foreach ( ptsbi_pe_font_choices() as $slug => $stack ) {
            $choices[ $slug ] = ( $slug === 'theme-default' ) ? $stack : $slug;
        }
        $this->field_select( $label, $key, $o, $opt, $choices );
    }

    private function fix_toggle( $key, $label, $o, $opt ) {
        ?>
        <label class="studio-switch">
            <input type="checkbox" name="<?php echo esc_attr($opt);?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked( 1, (int) ( $o[ $key ] ?? 1 ) ); ?>>
            <span><?php echo esc_html( $label ); ?></span>
        </label>
        <?php
    }
}
