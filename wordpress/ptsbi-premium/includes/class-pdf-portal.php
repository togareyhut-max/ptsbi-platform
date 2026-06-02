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

    /**
     * @return list<array{id:string,title:string,file:string,link_label:string,file_name?:string}>
     */
    private static function items_for_admin_form(): array {
        $o   = ptprm_options();
        $raw = isset( $o['pdf_items'] ) ? (string) $o['pdf_items'] : '';
        $items = [];
        if ( $raw !== '' && $raw !== '[]' ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }
                    $title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
                    $id    = sanitize_key( (string) ( $row['id'] ?? '' ) );
                    if ( $id === '' && $title !== '' ) {
                        $id = sanitize_title( $title );
                    }
                    $file_id = is_numeric( $row['file'] ?? '' ) ? (int) $row['file'] : 0;
                    $file_name = '';
                    if ( $file_id > 0 ) {
                        $path = get_attached_file( $file_id );
                        $file_name = $path ? basename( $path ) : get_the_title( $file_id );
                    }
                    $items[] = [
                        'id'         => $id,
                        'title'      => $title,
                        'file'       => $file_id > 0 ? (string) $file_id : '',
                        'link_label' => sanitize_text_field( (string) ( $row['link_label'] ?? __( 'Lihat dokumen', 'ptsbi-premium' ) ) ),
                        'file_name'  => $file_name,
                    ];
                }
            }
        }
        if ( ! $items ) {
            $items[] = [
                'id'         => '',
                'title'      => '',
                'file'       => '',
                'link_label' => __( 'Lihat dokumen', 'ptsbi-premium' ),
                'file_name'  => '',
            ];
        }
        return $items;
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function shortcodes_for_item( array $item ): array {
        $id = sanitize_key( (string) ( $item['id'] ?? '' ) );
        if ( $id === '' ) {
            $title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
            $id    = $title !== '' ? sanitize_title( $title ) : '';
        }
        if ( $id === '' ) {
            return [ 'button' => '', 'hover' => '' ];
        }
        return [
            'button' => '[ptprm_pdf id="' . $id . '" style="button"]',
            'hover'  => '[ptprm_pdf id="' . $id . '" style="hover" label="Lihat dokumen"]Teks di sini…[/ptprm_pdf]',
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function render_pdf_row( array $item, int $index ): void {
        $sc = self::shortcodes_for_item( $item );
        echo '<li class="ptprm-pdf-name-row" data-index="' . esc_attr( (string) $index ) . '">';
        echo '<label class="ptprm-pdf-name-label"><span>' . esc_html__( 'Nama dokumen', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" class="regular-text" data-field="title" value="' . esc_attr( (string) ( $item['title'] ?? '' ) ) . '"></label>';
        echo '<input type="hidden" data-field="id" value="' . esc_attr( (string) ( $item['id'] ?? '' ) ) . '">';
        echo '<input type="hidden" data-field="file" value="' . esc_attr( (string) ( $item['file'] ?? '' ) ) . '">';
        echo '<input type="hidden" data-field="link_label" value="' . esc_attr( (string) ( $item['link_label'] ?? __( 'Lihat dokumen', 'ptsbi-premium' ) ) ) . '">';
        echo '<div class="ptprm-pdf-name-file">';
        echo '<button type="button" class="button ptprm-pdf-pick">' . esc_html__( 'Pilih file', 'ptsbi-premium' ) . '</button>';
        echo '<button type="button" class="button-link ptprm-pdf-clear">' . esc_html__( 'Hapus file', 'ptsbi-premium' ) . '</button>';
        echo '<span class="ptprm-pdf-file-name">' . esc_html( (string) ( $item['file_name'] ?? '' ) ) . '</span>';
        echo '</div>';
        echo '<div class="ptprm-pdf-shortcodes">';
        echo '<div class="ptprm-pdf-sc-block"><span class="ptprm-label">' . esc_html__( 'Tombol', 'ptsbi-premium' ) . '</span>';
        echo '<code class="ptprm-pdf-sc-button">' . esc_html( $sc['button'] ) . '</code> ';
        echo '<button type="button" class="button button-small ptprm-pdf-copy" data-which="button">' . esc_html__( 'Salin', 'ptsbi-premium' ) . '</button></div>';
        echo '<div class="ptprm-pdf-sc-block"><span class="ptprm-label">' . esc_html__( 'Hover', 'ptsbi-premium' ) . '</span>';
        echo '<code class="ptprm-pdf-sc-hover">' . esc_html( $sc['hover'] ) . '</code> ';
        echo '<button type="button" class="button button-small ptprm-pdf-copy" data-which="hover">' . esc_html__( 'Salin', 'ptsbi-premium' ) . '</button></div>';
        echo '</div>';
        echo '<button type="button" class="button-link-delete ptprm-pdf-row-remove" aria-label="' . esc_attr__( 'Hapus baris', 'ptsbi-premium' ) . '">' . esc_html__( 'Hapus', 'ptsbi-premium' ) . '</button>';
        echo '</li>';
    }

    public function handle_save(): void {
        if ( empty( $_POST['ptprm_portal_pdf_save'] ) || ! self::can_manage_pdf() ) {
            return;
        }
        if ( ! function_exists( 'ptprm_verify_portal_form_nonce' ) || ! ptprm_verify_portal_form_nonce( 'ptprm_portal_pdf_save' ) ) {
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

        $raw     = isset( $_POST['ptprm_pdf_items_json'] ) ? wp_unslash( (string) $_POST['ptprm_pdf_items_json'] ) : '';
        $decoded = $raw !== '' ? json_decode( $raw, true ) : null;
        if ( ! is_array( $decoded ) ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_settings_error',
                    rawurlencode( __( 'Data PDF tidak terkirim. Muat ulang halaman lalu coba lagi.', 'ptsbi-premium' ) ),
                    PTPRM_Admin_Portal::portal_url( 'dokumen' )
                )
            );
            exit;
        }

        $items = ptprm_sanitize_pdf_items( $decoded );

        $opts              = (array) get_option( PTPRM_OPTION, [] );
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

        $items = self::items_for_admin_form();

        echo '<div class="ptprm-member-card ptprm-portal-card ptprm-pdf-portal">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Kelola daftar PDF berdasarkan nama dokumen. ID shortcode dibuat otomatis dari judul. Salin shortcode tombol atau hover untuk dipakai di halaman.', 'ptsbi-premium' ) . '</p>';

        $form_action = class_exists( 'PTPRM_Admin_Portal' ) ? PTPRM_Admin_Portal::portal_url( 'dokumen' ) : '';
        echo '<form method="post" id="ptprm-portal-pdf-form" class="ptprm-pdf-list-form" action="' . esc_url( $form_action ) . '">';
        wp_nonce_field( 'ptprm_portal_pdf_save' );
        echo '<input type="hidden" name="ptprm_portal_pdf_save" value="1">';
        echo '<input type="hidden" name="ptprm_pdf_items_json" id="ptprm-portal-pdf-json" value="">';

        echo '<ul class="ptprm-pdf-name-list" id="ptprm-portal-pdf-list">';
        foreach ( $items as $i => $item ) {
            self::render_pdf_row( $item, (int) $i );
        }
        echo '</ul>';

        echo '<p><button type="button" class="button button-secondary" id="ptprm-portal-pdf-add">+ ' . esc_html__( 'Tambah PDF', 'ptsbi-premium' ) . '</button></p>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan daftar PDF', 'ptsbi-premium' ) . '</span></button>';
        echo '</form>';
        echo '</div>';

        echo '<template id="ptprm-portal-pdf-row-tpl"><ul>';
        self::render_pdf_row(
            [
                'id'         => '',
                'title'      => '',
                'file'       => '',
                'link_label' => __( 'Lihat dokumen', 'ptsbi-premium' ),
                'file_name'  => '',
            ],
            0
        );
        echo '</ul></template>';
    }
}
