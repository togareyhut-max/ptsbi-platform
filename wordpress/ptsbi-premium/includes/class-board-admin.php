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
        if ( ! PTPRM_Access::can_manage_org_settings() && ! PTPRM_Access::can_manage() ) {
            return;
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'ringkasan'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $tab, [ 'pengurus', 'ringkasan', 'struktur' ], true ) ) {
            return;
        }
        if ( $tab === 'pengurus' || $tab === 'struktur' ) {
            wp_enqueue_media();
        }
        if ( $tab === 'pengurus' ) {
            wp_enqueue_script(
                'ptprm-board-admin',
                PTPRM_URL . 'assets/js/board-admin.js',
                [ 'jquery' ],
                PTPRM_VERSION,
                true
            );
        }
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
        $raw    = isset( $_POST['ptprm_board_json'] ) ? wp_unslash( (string) $_POST['ptprm_board_json'] ) : '';

        if ( trim( $raw ) === '' ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_board_error',
                    rawurlencode( __( 'Data pengurus tidak terkirim. Muat ulang halaman, cek semua baris memiliki nama, lalu simpan lagi.', 'ptsbi-premium' ) ),
                    PTPRM_Admin_Portal::portal_url( 'pengurus', [ 'board_region' => $region ] )
                )
            );
            exit;
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_board_error',
                    rawurlencode( __( 'Format data tidak valid. Muat ulang halaman lalu simpan lagi.', 'ptsbi-premium' ) ),
                    PTPRM_Admin_Portal::portal_url( 'pengurus', [ 'board_region' => $region ] )
                )
            );
            exit;
        }

        $items = ptprm_sanitize_board_items( $decoded );
        if ( $items === [] ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_board_error',
                    rawurlencode( __( 'Tidak ada baris tersimpan: pastikan setiap pengurus memiliki nama.', 'ptsbi-premium' ) ),
                    PTPRM_Admin_Portal::portal_url( 'pengurus', [ 'board_region' => $region ] )
                )
            );
            exit;
        }

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

        $items = PTPRM_Board_Registry::get_items( $region );
        $json  = wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( ! is_string( $json ) ) {
            $json = '[]';
        }
        $json_b64 = base64_encode( $json );
        $meta     = PTPRM_Board_Registry::regions()[ $region ];

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">';
        echo esc_html__( 'Kelola daftar pengurus per wilayah.', 'ptsbi-premium' );
        if ( ! empty( $meta['has_featured_photos'] ) ) {
            echo ' ' . esc_html__( 'Pengurus Pusat: isi URL foto (atau pilih dari media) untuk Ketua Umum, Sekretaris Umum, dan Bendahara Umum, lalu centang Tampilkan dengan foto.', 'ptsbi-premium' );
        }
        echo '</p>';

        echo '<nav class="ptprm-board-region-tabs">';
        foreach ( PTPRM_Board_Registry::regions() as $slug => $rmeta ) {
            $active = $slug === $region ? ' is-active' : '';
            $url    = add_query_arg( 'board_region', $slug, PTPRM_Admin_Portal::portal_url( 'pengurus' ) );
            echo '<a class="ptprm-portal-tab' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $rmeta['title'] ) . '</a>';
        }
        echo '</nav>';

        $form_class = 'ptprm-board-admin-form';
        if ( ! empty( $meta['has_featured_photos'] ) ) {
            $form_class .= ' ptprm-board-admin-form--photos';
        }
        echo '<form method="post" id="ptprm-board-admin-form" class="' . esc_attr( $form_class ) . '" action="' . esc_url( PTPRM_Admin_Portal::portal_url( 'pengurus', [ 'board_region' => $region ] ) ) . '">';
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
    }
}
