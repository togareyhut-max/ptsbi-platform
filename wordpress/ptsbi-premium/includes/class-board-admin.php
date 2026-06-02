<?php
/**
 * Kelola pengurus pusat & wilayah dari panel admin organisasi.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Board_Admin {

    public function __construct() {
        add_filter( 'ptprm_admin_portal_tabs', [ $this, 'register_tab' ] );
        add_action( 'ptprm_admin_portal_tab_pengurus', [ $this, 'render_tab' ] );
        add_action( 'init', [ $this, 'handle_save' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_media_front' ] );
    }

    public function enqueue_media( string $hook ): void {
        if ( strpos( $hook, 'ptprm' ) === false ) {
            return;
        }
        wp_enqueue_media();
    }

    public function enqueue_media_front(): void {
        if ( ! class_exists( 'PTPRM_Admin_Portal' ) || ! PTPRM_Admin_Portal::is_admin_page() ) {
            return;
        }
        if ( ! isset( $_GET['tab'] ) || sanitize_key( (string) $_GET['tab'] ) !== 'pengurus' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! PTPRM_Access::can_manage_org_settings() ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'ptprm-board-admin',
            PTPRM_URL . 'assets/js/board-admin.js',
            [ 'jquery' ],
            PTPRM_VERSION,
            true
        );
    }

    /**
     * @param array<string,string> $tabs
     * @return array<string,string>
     */
    public function register_tab( array $tabs ): array {
        if ( PTPRM_Access::can_manage_org_settings() ) {
            $tabs['pengurus'] = __( 'Pengurus Wilayah', 'ptsbi-premium' );
        }
        return $tabs;
    }

    public function handle_save(): void {
        if ( empty( $_POST['ptprm_board_save'] ) || ! PTPRM_Access::can_manage_org_settings() ) {
            return;
        }
        if ( ! function_exists( 'ptprm_verify_portal_form_nonce' ) || ! ptprm_verify_portal_form_nonce( 'ptprm_board_save' ) ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_board_error',
                    rawurlencode( ptprm_portal_session_expired_message() ),
                    PTPRM_Admin_Portal::portal_url( 'pengurus' )
                )
            );
            exit;
        }
        $region = sanitize_key( (string) ( $_POST['board_region'] ?? 'pusat' ) );
        $raw    = isset( $_POST['ptprm_board_json'] ) ? wp_unslash( (string) $_POST['ptprm_board_json'] ) : '[]';
        $decoded = json_decode( $raw, true );
        $items   = is_array( $decoded ) ? ptprm_sanitize_board_items( $decoded ) : [];
        PTPRM_Board_Registry::save_items( $region, $items );
        wp_safe_redirect(
            add_query_arg(
                [
                    'ptprm_board_saved' => '1',
                    'board_region'      => $region,
                ],
                PTPRM_Admin_Portal::portal_url( 'pengurus' )
            )
        );
        exit;
    }

    public function render_tab(): void {
        if ( ! PTPRM_Access::can_manage_org_settings() ) {
            echo '<p>' . esc_html__( 'Akses ditolak.', 'ptsbi-premium' ) . '</p>';
            return;
        }

        $region = sanitize_key( (string) ( $_GET['board_region'] ?? 'pusat' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( PTPRM_Board_Registry::regions()[ $region ] ) ) {
            $region = 'pusat';
        }

        if ( isset( $_GET['ptprm_board_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Daftar pengurus disimpan.', 'ptsbi-premium' ) . '</p>';
        }
        if ( isset( $_GET['ptprm_board_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $err = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_board_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $err !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $err ) . '</p>';
            }
        }

        $items    = PTPRM_Board_Registry::get_items( $region );
        $json_b64 = base64_encode( wp_json_encode( $items, JSON_UNESCAPED_UNICODE ) );
        $meta     = PTPRM_Board_Registry::regions()[ $region ];

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Kelola daftar pengurus per wilayah. Pengurus Pusat: unggah foto untuk Ketua Umum, Sekretaris Umum, dan Bendahara Umum.', 'ptsbi-premium' ) . '</p>';

        echo '<nav class="ptprm-board-region-tabs">';
        foreach ( PTPRM_Board_Registry::regions() as $slug => $rmeta ) {
            $active = $slug === $region ? ' is-active' : '';
            $url    = add_query_arg( 'board_region', $slug, PTPRM_Admin_Portal::portal_url( 'pengurus' ) );
            echo '<a class="ptprm-portal-tab' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $rmeta['title'] ) . '</a>';
        }
        echo '</nav>';

        echo '<form method="post" id="ptprm-board-admin-form" action="' . esc_url( PTPRM_Admin_Portal::portal_url( 'pengurus', [ 'board_region' => $region ] ) ) . '">';
        wp_nonce_field( 'ptprm_board_save' );
        echo '<input type="hidden" name="ptprm_board_save" value="1">';
        echo '<input type="hidden" name="board_region" value="' . esc_attr( $region ) . '">';
        echo '<input type="hidden" name="ptprm_board_json" id="ptprm-board-json" value="">';
        echo '<input type="hidden" id="ptprm-board-region" value="' . esc_attr( $region ) . '">';
        echo '<input type="hidden" id="ptprm-board-has-featured" value="' . ( ! empty( $meta['has_featured_photos'] ) ? '1' : '0' ) . '">';

        echo '<ul class="ptprm-board-admin-list" id="ptprm-board-admin-list" data-json-b64="' . esc_attr( $json_b64 ) . '"></ul>';
        echo '<p><button type="button" class="button button-secondary" id="ptprm-board-add">+ ' . esc_html__( 'Tambah baris', 'ptsbi-premium' ) . '</button></p>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Pengurus', 'ptsbi-premium' ) . '</span></button>';
        echo '</form></div>';

        echo '<script type="text/template" id="ptprm-board-row-tpl">';
        echo '<li class="ptprm-board-admin-row">';
        echo '<label><span>' . esc_html__( 'Jabatan', 'ptsbi-premium' ) . '</span><input type="text" data-field="role"></label>';
        echo '<label><span>' . esc_html__( 'Nama', 'ptsbi-premium' ) . '</span><input type="text" data-field="name" required></label>';
        echo '<label><span>' . esc_html__( 'Kelompok (opsional)', 'ptsbi-premium' ) . '</span><input type="text" data-field="group" placeholder="Dewan Penasehat"></label>';
        echo '<label class="ptprm-board-photo-field" hidden><span>' . esc_html__( 'Foto (ID media)', 'ptsbi-premium' ) . '</span><input type="text" data-field="image"><button type="button" class="button ptprm-board-pick-image">' . esc_html__( 'Pilih foto', 'ptsbi-premium' ) . '</button></label>';
        echo '<label><input type="checkbox" data-field="featured"> ' . esc_html__( 'Tampilkan dengan foto (pusat)', 'ptsbi-premium' ) . '</label>';
        echo '<button type="button" class="button-link-delete ptprm-board-remove">&times;</button>';
        echo '</li></script>';
    }
}
