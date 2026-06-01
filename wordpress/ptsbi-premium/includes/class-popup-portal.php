<?php
/**
 * Panel admin organisasi: kelola foto & deskripsi pop-up iklan (tanpa pengaturan waktu).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Popup_Portal {

    public function __construct() {
        add_filter( 'ptprm_admin_portal_tabs', [ $this, 'register_tab' ] );
        add_action( 'ptprm_admin_portal_tab_iklan', [ $this, 'render_tab' ] );
        add_action( 'init', [ $this, 'handle_save' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_media' ] );
    }

    public function enqueue_media(): void {
        if ( ! class_exists( 'PTPRM_Admin_Portal' ) || ! PTPRM_Admin_Portal::is_admin_page() ) {
            return;
        }
        if ( sanitize_key( (string) ( $_GET['tab'] ?? '' ) ) !== 'iklan' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! PTPRM_Access::can_manage_org_settings() ) {
            return;
        }
        wp_enqueue_media();
    }

    /**
     * @param array<string,string> $tabs
     */
    public function register_tab( array $tabs ): array {
        if ( PTPRM_Access::can_manage_org_settings() ) {
            $tabs['iklan'] = __( 'Iklan Pop-up', 'ptsbi-premium' );
        }
        return $tabs;
    }

    public function handle_save(): void {
        if ( empty( $_POST['ptprm_popup_portal_save'] ) || ! PTPRM_Access::can_manage_org_settings() ) {
            return;
        }
        if ( ! function_exists( 'ptprm_verify_portal_form_nonce' ) || ! ptprm_verify_portal_form_nonce( 'ptprm_popup_portal_save' ) ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_popup_error',
                    rawurlencode( ptprm_portal_session_expired_message() ),
                    PTPRM_Admin_Portal::portal_url( 'iklan' )
                )
            );
            exit;
        }
        $existing = (array) get_option( PTPRM_OPTION, [] );
        $patch    = [
            'popup_enabled' => ! empty( $_POST['popup_enabled'] ) ? 1 : 0,
            'popup_image'   => (string) (int) ( $_POST['popup_image'] ?? 0 ),
            'popup_title'   => sanitize_text_field( wp_unslash( (string) ( $_POST['popup_title'] ?? '' ) ) ),
            'popup_body'    => sanitize_textarea_field( wp_unslash( (string) ( $_POST['popup_body'] ?? '' ) ) ),
        ];
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( array_merge( $existing, $patch ) ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );
        wp_safe_redirect( add_query_arg( 'ptprm_saved', '1', PTPRM_Admin_Portal::portal_url( 'iklan' ) ) );
        exit;
    }

    public function render_tab(): void {
        $o = ptprm_options();
        if ( isset( $_GET['ptprm_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Iklan pop-up disimpan. Pengaturan waktu & tampilan halaman diatur administrator backend.', 'ptsbi-premium' ) . '</p>';
        }
        $img_id = (int) ( $o['popup_image'] ?? 0 );
        $img    = $img_id > 0 ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Unggah foto dan deskripsi iklan. Jadwal, delay, dan halaman tampil dikelola di wp-admin oleh administrator situs.', 'ptsbi-premium' ) . '</p>';
        echo '<form method="post" action="' . esc_url( PTPRM_Admin_Portal::portal_url( 'iklan' ) ) . '">';
        wp_nonce_field( 'ptprm_popup_portal_save' );
        echo '<input type="hidden" name="ptprm_popup_portal_save" value="1">';
        echo '<label class="ptprm-member-full"><input type="checkbox" name="popup_enabled" value="1"' . checked( ! empty( $o['popup_enabled'] ), true, false ) . '> ' . esc_html__( 'Aktifkan pop-up', 'ptsbi-premium' ) . '</label>';
        echo '<label><span>' . esc_html__( 'Judul', 'ptsbi-premium' ) . '</span><input type="text" name="popup_title" value="' . esc_attr( (string) ( $o['popup_title'] ?? '' ) ) . '"></label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Deskripsi', 'ptsbi-premium' ) . '</span><textarea name="popup_body" rows="5">' . esc_textarea( (string) ( $o['popup_body'] ?? '' ) ) . '</textarea></label>';
        echo '<label><span>' . esc_html__( 'ID foto (media library)', 'ptsbi-premium' ) . '</span><input type="number" name="popup_image" id="ptprm-popup-image-id" value="' . esc_attr( (string) $img_id ) . '"></label>';
        if ( $img ) {
            echo '<p><img src="' . esc_url( $img ) . '" alt="" style="max-width:240px;height:auto"></p>';
        }
        echo '<p><button type="button" class="button" id="ptprm-popup-pick-image">' . esc_html__( 'Pilih foto', 'ptsbi-premium' ) . '</button></p>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Iklan', 'ptsbi-premium' ) . '</span></button></form></div>';
        echo '<script>jQuery(function($){ $("#ptprm-popup-pick-image").on("click",function(e){e.preventDefault();var f=wp.media({title:"Foto iklan",multiple:false});f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#ptprm-popup-image-id").val(a.id);});f.open();});});</script>';
    }
}
