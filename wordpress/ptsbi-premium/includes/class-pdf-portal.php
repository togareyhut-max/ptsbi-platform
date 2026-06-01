<?php
/**
 * Panel Dokumen PDF di frontend (panel pengurus) — daftar nama + shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Pdf_Portal {

    private const NONCE_SAVE = 'ptprm_portal_pdf_save';

    public function __construct() {
        add_action( 'init', [ $this, 'handle_save' ], 20 );
        add_filter( 'ptprm_admin_portal_tabs', [ $this, 'register_tab' ] );
        add_action( 'ptprm_admin_portal_tab_dokumen', [ $this, 'render_tab' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public static function can_manage_pdf(): bool {
        return class_exists( 'PTPRM_Access' ) && ( PTPRM_Access::can_manage_org_settings() || PTPRM_Access::can_manage() );
    }

    public function register_tab( array $tabs ): array {
        if ( ! self::can_manage_pdf() ) {
            return $tabs;
        }
        $tabs['dokumen'] = __( 'Dokumen PDF', 'ptsbi-premium' );
        return $tabs;
    }

    public function enqueue_assets(): void {
        if ( ! is_user_logged_in() || ! class_exists( 'PTPRM_Admin_Portal' ) || ! PTPRM_Admin_Portal::is_admin_page() ) {
            return;
        }
        $tab = sanitize_key( (string) ( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $tab !== 'dokumen' ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'ptprm-portal-pdf',
            PTPRM_URL . 'assets/js/portal-pdf.js',
            [ 'jquery' ],
            PTPRM_VERSION,
            true
        );
        wp_enqueue_style(
            'ptprm-portal-pdf',
            PTPRM_URL . 'assets/css/portal-pdf.css',
            [ 'ptprm-frontend' ],
            PTPRM_VERSION
        );
    }

    public function handle_save(): void {
        if ( empty( $_POST['ptprm_portal_pdf_save'] ) || ! self::can_manage_pdf() ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_SAVE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_SAVE ] ) ), 'ptprm_portal_pdf_save' ) ) {
            $url = class_exists( 'PTPRM_Admin_Portal' )
                ? PTPRM_Admin_Portal::portal_url(
                    'dokumen',
                    [
                        'ptprm_settings_error' => rawurlencode(
                            function_exists( 'ptprm_portal_session_expired_message' )
                                ? ptprm_portal_session_expired_message()
                                : __( 'Sesi form kedaluwarsa. Muat ulang halaman lalu simpan lagi.', 'ptsbi-premium' )
                        ),
                    ]
                )
                : home_url( '/' );
            wp_safe_redirect( $url );
            exit;
        }

        $raw = isset( $_POST['ptprm_pdf_items_json'] ) ? wp_unslash( (string) $_POST['ptprm_pdf_items_json'] ) : '[]';
        $decoded = json_decode( $raw, true );
        $items = is_array( $decoded ) ? ptprm_sanitize_pdf_items( $decoded ) : [];

        $opts = (array) get_option( PTPRM_OPTION, [] );
        $opts['pdf_items'] = wp_json_encode( $items, JSON_UNESCAPED_UNICODE );
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( array_merge( ptprm_options(), $opts ) ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );

        if ( class_exists( 'PTPRM_Membership_Sync' ) ) {
            PTPRM_Membership_Sync::push_pdf_items( $items );
        }

        $url = class_exists( 'PTPRM_Admin_Portal' )
            ? PTPRM_Admin_Portal::portal_url( 'dokumen', [ 'ptprm_saved' => '1' ] )
            : home_url( '/' );
        wp_safe_redirect( $url );
        exit;
    }

    public function render_tab(): void {
        if ( ! self::can_manage_pdf() ) {
            echo '<p>' . esc_html__( 'Anda tidak punya akses mengelola dokumen PDF.', 'ptsbi-premium' ) . '</p>';
            return;
        }

        if ( isset( $_GET['ptprm_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Daftar dokumen PDF tersimpan.', 'ptsbi-premium' ) . '</p>';
        }

        $items = ptprm_get_pdf_items();
        foreach ( $items as $i => $item ) {
            $fid = (int) ( $item['file'] ?? 0 );
            if ( $fid > 0 ) {
                $path = get_attached_file( $fid );
                $items[ $i ]['file_name'] = $path ? basename( $path ) : get_the_title( $fid );
            }
        }
        $json_b64 = base64_encode( wp_json_encode( $items, JSON_UNESCAPED_UNICODE ) );

        echo '<div class="ptprm-member-card ptprm-portal-card ptprm-pdf-portal">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Kelola daftar PDF berdasarkan nama dokumen. ID shortcode dibuat otomatis dari judul. Salin shortcode tombol atau hover untuk dipakai di halaman.', 'ptsbi-premium' ) . '</p>';

        $form_action = class_exists( 'PTPRM_Admin_Portal' ) ? PTPRM_Admin_Portal::portal_url( 'dokumen' ) : '';
        echo '<form method="post" id="ptprm-portal-pdf-form" class="ptprm-pdf-list-form" action="' . esc_url( $form_action ) . '">';
        wp_nonce_field( 'ptprm_portal_pdf_save', self::NONCE_SAVE );
        echo '<input type="hidden" name="ptprm_portal_pdf_save" value="1">';
        echo '<input type="hidden" name="ptprm_pdf_items_json" id="ptprm-portal-pdf-json" value="">';

        echo '<ul class="ptprm-pdf-name-list" id="ptprm-portal-pdf-list" data-json-b64="' . esc_attr( $json_b64 ) . '"></ul>';

        echo '<p><button type="button" class="button button-secondary" id="ptprm-portal-pdf-add">+ ' . esc_html__( 'Tambah PDF', 'ptsbi-premium' ) . '</button></p>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan daftar PDF', 'ptsbi-premium' ) . '</span></button>';
        echo '</form>';
        echo '</div>';

        echo '<script type="text/template" id="ptprm-portal-pdf-row-tpl">';
        echo '<li class="ptprm-pdf-name-row" data-index="{{index}}">';
        echo '<label class="ptprm-pdf-name-label"><span>' . esc_html__( 'Nama dokumen', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" class="regular-text" data-field="title" value=""></label>';
        echo '<input type="hidden" data-field="id" value=""><input type="hidden" data-field="file" value="">';
        echo '<input type="hidden" data-field="link_label" value="' . esc_attr__( 'Lihat dokumen', 'ptsbi-premium' ) . '">';
        echo '<div class="ptprm-pdf-name-file"><button type="button" class="button ptprm-pdf-pick">' . esc_html__( 'Pilih file', 'ptsbi-premium' ) . '</button>';
        echo '<button type="button" class="button-link ptprm-pdf-clear">' . esc_html__( 'Hapus file', 'ptsbi-premium' ) . '</button>';
        echo '<span class="ptprm-pdf-file-name"></span></div>';
        echo '<div class="ptprm-pdf-shortcodes"><div class="ptprm-pdf-sc-block"><span class="ptprm-label">' . esc_html__( 'Tombol', 'ptsbi-premium' ) . '</span>';
        echo '<code class="ptprm-pdf-sc-button"></code> <button type="button" class="button button-small ptprm-pdf-copy" data-which="button">' . esc_html__( 'Salin', 'ptsbi-premium' ) . '</button></div>';
        echo '<div class="ptprm-pdf-sc-block"><span class="ptprm-label">' . esc_html__( 'Hover', 'ptsbi-premium' ) . '</span>';
        echo '<code class="ptprm-pdf-sc-hover"></code> <button type="button" class="button button-small ptprm-pdf-copy" data-which="hover">' . esc_html__( 'Salin', 'ptsbi-premium' ) . '</button></div></div>';
        echo '<button type="button" class="button-link-delete ptprm-pdf-row-remove" aria-label="' . esc_attr__( 'Hapus baris', 'ptsbi-premium' ) . '">' . esc_html__( 'Hapus', 'ptsbi-premium' ) . '</button>';
        echo '</li>';
        echo '</script>';
    }
}
