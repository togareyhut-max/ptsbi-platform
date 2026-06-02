<?php
/**
 * Panel frontend per bidang kerja.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Bidang_Portal {

    public function __construct() {
        add_shortcode( 'ptprm_bidang_panel', [ $this, 'shortcode' ] );
        add_action( 'init', [ $this, 'handle_save_content' ], 20 );
        add_action( 'init', [ $this, 'handle_save_post' ], 20 );
        add_filter( 'ptprm_subpage_hero_skip', [ $this, 'skip_hero' ] );
    }

    public function skip_hero( bool $skip ): bool {
        return $skip || PTPRM_Bidang_Registry::is_bidang_panel_page();
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function shortcode( $atts ): string {
        $atts = shortcode_atts( [ 'slug' => '' ], is_array( $atts ) ? $atts : [], 'ptprm_bidang_panel' );
        $slug = sanitize_key( (string) $atts['slug'] );
        if ( $slug === '' && PTPRM_Bidang_Registry::is_bidang_panel_page() ) {
            $slug = PTPRM_Bidang_Registry::bidang_slug_from_panel_page();
        }
        return $this->render_panel( $slug );
    }

    private function render_panel( string $slug ): string {
        if ( ! isset( PTPRM_Bidang_Registry::bidangs()[ $slug ] ) ) {
            return '';
        }
        if ( ! is_user_logged_in() || ! PTPRM_Bidang_Registry::user_can_manage_bidang( $slug ) ) {
            ob_start();
            echo '<div class="ptprm-member-card ptprm-portal-card"><p>' . esc_html__( 'Silakan masuk dengan akun bidang Anda.', 'ptsbi-premium' ) . '</p>';
            echo '<a class="ptprm-cta ptprm-cta-1" href="' . esc_url( class_exists( 'PTPRM_Login_Portal' ) ? PTPRM_Login_Portal::login_url() : wp_login_url() ) . '"><span class="ptprm-cta-label">' . esc_html__( 'Rumah Anggota', 'ptsbi-premium' ) . '</span></a></div>';
            return (string) ob_get_clean();
        }

        $meta = PTPRM_Bidang_Registry::bidangs()[ $slug ];
        $tab  = sanitize_key( (string) ( $_GET['tab'] ?? 'konten' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tabs = [
            'konten'      => __( 'Visi & Program', 'ptsbi-premium' ),
            'berita'      => __( 'Berita / Kegiatan', 'ptsbi-premium' ),
            'pengumuman'  => __( 'Pengumuman', 'ptsbi-premium' ),
        ];
        if ( ! empty( $meta['is_sekretariat'] ) ) {
            $tabs['anggota'] = __( 'Data Anggota', 'ptsbi-premium' );
        }
        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'konten';
        }

        ob_start();
        echo '<div class="ptprm-portal-wrap ptprm-bidang-portal">';
        echo '<header class="ptprm-portal-head"><h2 class="ptprm-portal-title">' . esc_html( (string) $meta['title'] ) . '</h2>';
        echo '<p class="ptprm-portal-greet">' . esc_html__( 'Panel pengelolaan bidang', 'ptsbi-premium' ) . '</p></header>';
        echo '<nav class="ptprm-portal-tabs">';
        foreach ( $tabs as $key => $label ) {
            $active = $tab === $key ? ' is-active' : '';
            echo '<a class="ptprm-portal-tab' . esc_attr( $active ) . '" href="' . esc_url( PTPRM_Bidang_Registry::panel_url( $slug, $key ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav><div class="ptprm-portal-panel">';
        if ( class_exists( 'PTPRM_Cache_Purge' ) ) {
            PTPRM_Cache_Purge::render_notice();
        }
        if ( isset( $_GET['ptprm_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Disimpan.', 'ptsbi-premium' ) . '</p>';
        }
        if ( isset( $_GET['ptprm_bidang_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html__( 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.', 'ptsbi-premium' ) . '</p>';
        }
        switch ( $tab ) {
            case 'berita':
                $this->render_post_form( $slug, 'kegiatan' );
                break;
            case 'pengumuman':
                $this->render_post_form( $slug, 'pengumuman', true );
                break;
            case 'anggota':
                $this->render_anggota_tab();
                break;
            default:
                $this->render_content_form( $slug );
        }
        echo '</div><footer class="ptprm-portal-footbar ptprm-portal-footbar--tools">';
        if ( class_exists( 'PTPRM_Cache_Purge' ) ) {
            PTPRM_Cache_Purge::render_purge_button( PTPRM_Bidang_Registry::panel_url( $slug, $tab ) );
        }
        PTPRM_Access::render_logout_link();
        echo '</footer></div>';
        return (string) ob_get_clean();
    }

    public function handle_save_content(): void {
        if ( empty( $_POST['ptprm_bidang_content_save'] ) ) {
            return;
        }
        $slug = sanitize_key( (string) ( $_POST['bidang_slug'] ?? '' ) );
        if ( ! PTPRM_Bidang_Registry::user_can_manage_bidang( $slug ) ) {
            return;
        }
        if ( ! function_exists( 'ptprm_verify_portal_form_nonce' ) || ! ptprm_verify_portal_form_nonce( 'ptprm_bidang_content_save' ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_bidang_error', '1', PTPRM_Bidang_Registry::panel_url( $slug, 'konten' ) ) );
            exit;
        }

        $items = [];
        $judul  = isset( $_POST['program_judul'] ) ? (array) $_POST['program_judul'] : [];
        $tujuan = isset( $_POST['program_tujuan'] ) ? (array) $_POST['program_tujuan'] : [];
        $sas    = isset( $_POST['program_sasaran'] ) ? (array) $_POST['program_sasaran'] : [];
        $waktu  = isset( $_POST['program_waktu'] ) ? (array) $_POST['program_waktu'] : [];
        $max    = max( count( $judul ), count( $tujuan ), count( $sas ), count( $waktu ) );
        for ( $i = 0; $i < $max; $i++ ) {
            $items[] = [
                'judul'   => wp_unslash( (string) ( $judul[ $i ] ?? '' ) ),
                'tujuan'  => wp_unslash( (string) ( $tujuan[ $i ] ?? '' ) ),
                'sasaran' => wp_unslash( (string) ( $sas[ $i ] ?? '' ) ),
                'waktu'   => wp_unslash( (string) ( $waktu[ $i ] ?? '' ) ),
            ];
        }

        PTPRM_Bidang_Registry::save_content(
            $slug,
            [
                'visi'    => wp_unslash( (string) ( $_POST['bidang_visi'] ?? '' ) ),
                'misi'    => wp_unslash( (string) ( $_POST['bidang_misi'] ?? '' ) ),
                'program' => wp_unslash( (string) ( $_POST['bidang_program'] ?? '' ) ), // legacy fallback
                'program_items'    => $items,
                'undangan_post_id' => (int) ( $_POST['undangan_post_id'] ?? 0 ),
            ]
        );
        wp_safe_redirect( add_query_arg( 'ptprm_saved', '1', PTPRM_Bidang_Registry::panel_url( $slug, 'konten' ) ) );
        exit;
    }

    public function handle_save_post(): void {
        if ( empty( $_POST['ptprm_bidang_post_save'] ) ) {
            return;
        }
        $slug = sanitize_key( (string) ( $_POST['bidang_slug'] ?? '' ) );
        $type = sanitize_key( (string) ( $_POST['bidang_post_type'] ?? 'kegiatan' ) );
        if ( ! PTPRM_Bidang_Registry::user_can_manage_bidang( $slug ) ) {
            return;
        }
        if ( ! function_exists( 'ptprm_verify_portal_form_nonce' ) || ! ptprm_verify_portal_form_nonce( 'ptprm_bidang_post_save' ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_bidang_error', '1', PTPRM_Bidang_Registry::panel_url( $slug, $type === 'pengumuman' ? 'pengumuman' : 'berita' ) ) );
            exit;
        }
        $title   = sanitize_text_field( wp_unslash( (string) ( $_POST['post_title'] ?? '' ) ) );
        $content = wp_kses_post( wp_unslash( (string) ( $_POST['post_content'] ?? '' ) ) );
        if ( $title === '' ) {
            return;
        }
        $cat_slug = PTPRM_Bidang_Registry::category_slug( $slug, $type );
        $term     = get_term_by( 'slug', $cat_slug, 'category' );
        $cats     = $term instanceof WP_Term ? [ (int) $term->term_id ] : [];

        $post_id = wp_insert_post(
            [
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'post',
                'post_author'  => get_current_user_id(),
                'post_category'=> $cats,
            ],
            true
        );
        if ( ! is_wp_error( $post_id ) && ! empty( $_POST['featured_image_id'] ) ) {
            set_post_thumbnail( (int) $post_id, (int) $_POST['featured_image_id'] );
        }
        wp_safe_redirect( add_query_arg( 'ptprm_saved', '1', PTPRM_Bidang_Registry::panel_url( $slug, $type === 'pengumuman' ? 'pengumuman' : 'berita' ) ) );
        exit;
    }

    private function render_content_form( string $slug ): void {
        $c = PTPRM_Bidang_Registry::get_content( $slug );
        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<form method="post" action="' . esc_url( PTPRM_Bidang_Registry::panel_url( $slug, 'konten' ) ) . '">';
        wp_nonce_field( 'ptprm_bidang_content_save' );
        echo '<input type="hidden" name="ptprm_bidang_content_save" value="1"><input type="hidden" name="bidang_slug" value="' . esc_attr( $slug ) . '">';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Visi', 'ptsbi-premium' ) . '</span><textarea name="bidang_visi" rows="4">' . esc_textarea( $c['visi'] ) . '</textarea></label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Misi', 'ptsbi-premium' ) . '</span><textarea name="bidang_misi" rows="4">' . esc_textarea( $c['misi'] ) . '</textarea></label>';
        echo '<h3 class="ptprm-admin-h3" style="margin-top:1rem;">' . esc_html__( 'Program unggulan (terstruktur)', 'ptsbi-premium' ) . '</h3>';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Isi waktu dengan format bulan dan tahun, misalnya: Juni 2006 – Desember 2027.', 'ptsbi-premium' ) . '</p>';
        $items = isset( $c['program_items'] ) && is_array( $c['program_items'] ) ? $c['program_items'] : [];
        if ( $items === [] ) {
            $items = [ [ 'judul' => '', 'tujuan' => '', 'sasaran' => '', 'waktu' => '' ] ];
        }
        echo '<div class="ptprm-bidang-program-editor">';
        foreach ( $items as $idx => $it ) {
            $j = is_array( $it ) ? (string) ( $it['judul'] ?? '' ) : '';
            $t = is_array( $it ) ? (string) ( $it['tujuan'] ?? '' ) : '';
            $s = is_array( $it ) ? (string) ( $it['sasaran'] ?? '' ) : '';
            $w = is_array( $it ) ? (string) ( $it['waktu'] ?? '' ) : '';
            echo '<div class="ptprm-bidang-program-row">';
            echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Nama program/kegiatan', 'ptsbi-premium' ) . '</span><input type="text" name="program_judul[]" value="' . esc_attr( $j ) . '"></label>';
            echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Tujuan', 'ptsbi-premium' ) . '</span><textarea name="program_tujuan[]" rows="2">' . esc_textarea( $t ) . '</textarea></label>';
            echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Sasaran', 'ptsbi-premium' ) . '</span><textarea name="program_sasaran[]" rows="2">' . esc_textarea( $s ) . '</textarea></label>';
            echo '<label><span>' . esc_html__( 'Waktu (bulan–tahun)', 'ptsbi-premium' ) . '</span><input type="text" name="program_waktu[]" value="' . esc_attr( $w ) . '" placeholder="Juni 2006 – Desember 2027"></label>';
            echo '<button type="button" class="button ptprm-bidang-remove" data-ptprm-remove-row style="margin-top:.6rem;">' . esc_html__( 'Hapus baris', 'ptsbi-premium' ) . '</button>';
            echo '</div>';
        }
        echo '<div class="ptprm-bidang-program-actions" data-ptprm-program-actions style="margin:.75rem 0;">';
        echo '<button type="button" class="button button-secondary" data-ptprm-add-row>' . esc_html__( 'Tambah program', 'ptsbi-premium' ) . '</button>';
        echo '</div>';
        echo '</div>';

        echo '<h3 class="ptprm-admin-h3" style="margin-top:1rem;">' . esc_html__( 'Undangan/Pengumuman utama', 'ptsbi-premium' ) . '</h3>';
        $cat_slug = PTPRM_Bidang_Registry::category_slug( $slug, 'pengumuman' );
        $term     = get_term_by( 'slug', $cat_slug, 'category' );
        $selected = (int) ( $c['undangan_post_id'] ?? 0 );
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Pilih postingan pengumuman yang ditampilkan (opsional)', 'ptsbi-premium' ) . '</span>';
        echo '<select name="undangan_post_id"><option value="0">' . esc_html__( 'Otomatis pakai yang terbaru', 'ptsbi-premium' ) . '</option>';
        if ( $term instanceof WP_Term ) {
            $q = new WP_Query( [ 'cat' => (int) $term->term_id, 'posts_per_page' => 20, 'post_status' => 'publish' ] );
            while ( $q->have_posts() ) {
                $q->the_post();
                $pid = (int) get_the_ID();
                echo '<option value="' . esc_attr( (string) $pid ) . '"' . selected( $selected, $pid, false ) . '>' . esc_html( get_the_title() ) . '</option>';
            }
            wp_reset_postdata();
        }
        echo '</select></label>';

        echo '<details style="margin-top:1rem;"><summary>' . esc_html__( 'Legacy (opsional)', 'ptsbi-premium' ) . '</summary>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Program unggulan (teks lama)', 'ptsbi-premium' ) . '</span><textarea name="bidang_program" rows="6">' . esc_textarea( $c['program'] ) . '</textarea></label>';
        echo '</details>';

        echo '<button type="submit" class="ptprm-cta ptprm-cta-1"><span class="ptprm-cta-label">' . esc_html__( 'Simpan', 'ptsbi-premium' ) . '</span></button></form></div>';
    }

    private function render_post_form( string $slug, string $type, bool $poster = false ): void {
        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">' . esc_html( $poster ? __( 'Tambah pengumuman/undangan (gunakan gambar unggulan sebagai poster).', 'ptsbi-premium' ) : __( 'Tambah berita atau laporan kegiatan bidang.', 'ptsbi-premium' ) ) . '</p>';
        echo '<form method="post" class="ptprm-member-form" action="' . esc_url( PTPRM_Bidang_Registry::panel_url( $slug, $poster ? 'pengumuman' : 'berita' ) ) . '">';
        wp_nonce_field( 'ptprm_bidang_post_save' );
        echo '<input type="hidden" name="ptprm_bidang_post_save" value="1">';
        echo '<input type="hidden" name="bidang_slug" value="' . esc_attr( $slug ) . '">';
        echo '<input type="hidden" name="bidang_post_type" value="' . esc_attr( $type ) . '">';
        echo '<label><span>' . esc_html__( 'Judul', 'ptsbi-premium' ) . '</span><input type="text" name="post_title" required></label>';
        if ( $poster ) {
            echo '<label><span>' . esc_html__( 'ID gambar poster (media)', 'ptsbi-premium' ) . '</span><input type="number" name="featured_image_id" min="0"></label>';
        }
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Isi / keterangan', 'ptsbi-premium' ) . '</span><textarea name="post_content" rows="8"></textarea></label>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1"><span class="ptprm-cta-label">' . esc_html__( 'Publikasikan', 'ptsbi-premium' ) . '</span></button></form></div>';
        $cat_slug = PTPRM_Bidang_Registry::category_slug( $slug, $type );
        $term     = get_term_by( 'slug', $cat_slug, 'category' );
        if ( $term instanceof WP_Term ) {
            $q = new WP_Query( [ 'cat' => (int) $term->term_id, 'posts_per_page' => 10 ] );
            if ( $q->have_posts() ) {
                echo '<div class="ptprm-member-card"><h3>' . esc_html__( 'Terbaru', 'ptsbi-premium' ) . '</h3><ul>';
                while ( $q->have_posts() ) {
                    $q->the_post();
                    echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
                }
                echo '</ul></div>';
                wp_reset_postdata();
            }
        }
    }

    private function render_anggota_tab(): void {
        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Pencarian anggota lengkap (alamat & No HP). Gunakan unduh XLSX atau cetak untuk laporan.', 'ptsbi-premium' ) . '</p>';
        if ( class_exists( 'PTPRM_Members' ) ) {
            PTPRM_Members::render_export_download_control();
            echo '<p><button type="button" class="button button-secondary" onclick="window.print()">' . esc_html__( 'Cetak / PDF', 'ptsbi-premium' ) . '</button></p>';
            echo do_shortcode( '[ptprm_member_directory]' );
        }
        echo '</div>';
    }
}
