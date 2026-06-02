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
        if ( ! PTPRM_Access::can_manage_org_settings() ) {
            return;
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $tab !== 'pengurus' ) {
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
        $raw    = isset( $_POST['ptprm_board_json'] ) ? wp_unslash( (string) $_POST['ptprm_board_json'] ) : '';

        if ( trim( $raw ) === '' || trim( $raw ) === '[]' ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_board_error',
                    rawurlencode( __( 'Data pengurus tidak terkirim. Muat ulang halaman — pastikan semua baris tampil — lalu simpan lagi.', 'ptsbi-premium' ) ),
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

        if ( 'pusat' === $region ) {
            self::sync_homepage_team_from_board( $items );
        }

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

    /**
     * @param list<array<string,mixed>> $items
     */
    private static function sync_homepage_team_from_board( array $items ): void {
        $team = [];
        foreach ( $items as $item ) {
            if ( ! PTPRM_Board_Registry::is_featured_item( 'pusat', $item ) ) {
                continue;
            }
            $team[] = [
                'name'  => (string) ( $item['name'] ?? '' ),
                'role'  => (string) ( $item['role'] ?? '' ),
                'group' => (string) ( $item['group'] ?? '' ),
                'image' => (string) ( $item['image'] ?? '' ),
            ];
            if ( count( $team ) >= 5 ) {
                break;
            }
        }
        if ( ! $team ) {
            return;
        }
        $opts               = (array) get_option( PTPRM_OPTION, [] );
        $opts['team_items'] = wp_json_encode( ptprm_sanitize_team_items( $team ), JSON_UNESCAPED_UNICODE );
        $opts['team_show']  = 1;
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( array_merge( ptprm_options(), $opts ) ), true );
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function image_field_value( array $item ): string {
        $img = (string) ( $item['image'] ?? '' );
        if ( is_numeric( $img ) ) {
            $url = wp_get_attachment_url( (int) $img );
            if ( $url ) {
                return $url;
            }
        }
        return $img;
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function render_board_row( string $region, array $item, bool $show_photos ): void {
        $role       = (string) ( $item['role'] ?? '' );
        $is_core    = 'pusat' === $region && PTPRM_Board_Registry::is_core_photo_role( $role );
        $featured   = PTPRM_Board_Registry::is_featured_item( $region, $item );
        $show_photo = $show_photos && ( $is_core || $featured );
        $img_val    = self::image_field_value( $item );
        $row_attrs  = $is_core ? ' data-core-photo="1"' : '';

        echo '<li class="ptprm-board-admin-row"' . $row_attrs . '>';
        echo '<label><span>' . esc_html__( 'Jabatan', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" data-field="role" value="' . esc_attr( $role ) . '"></label>';
        echo '<label><span>' . esc_html__( 'Nama', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" data-field="name" value="' . esc_attr( (string) ( $item['name'] ?? '' ) ) . '" required></label>';
        echo '<label><span>' . esc_html__( 'Kelompok (opsional)', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" data-field="group" value="' . esc_attr( (string) ( $item['group'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Dewan Penasehat', 'ptsbi-premium' ) . '"></label>';

        $photo_class = 'ptprm-board-photo-field';
        if ( $is_core ) {
            $photo_class .= ' ptprm-board-photo-field--core';
        } elseif ( ! $show_photo ) {
            $photo_class .= ' is-hidden';
        }
        echo '<div class="' . esc_attr( $photo_class ) . '">';
        echo '<label><span>' . esc_html__( 'URL foto', 'ptsbi-premium' ) . '</span>';
        echo '<input type="url" data-field="image" value="' . esc_attr( $img_val ) . '" placeholder="https://..." inputmode="url" autocomplete="off"></label>';
        echo '<p class="ptprm-board-photo-actions"><button type="button" class="button ptprm-board-pick-image">' . esc_html__( 'Pilih dari media', 'ptsbi-premium' ) . '</button></p>';
        echo '</div>';

        if ( $show_photos ) {
            echo '<label><input type="checkbox" data-field="featured"' . checked( $featured, true, false ) . '> ';
            echo esc_html__( 'Tampilkan dengan foto (pusat)', 'ptsbi-premium' ) . '</label>';
        }

        echo '<button type="button" class="button-link-delete ptprm-board-remove">&times;</button>';
        echo '</li>';
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
        if ( ! $items ) {
            $items = [ [ 'name' => '', 'role' => '', 'group' => '', 'image' => '', 'featured' => 0 ] ];
        }
        $meta        = PTPRM_Board_Registry::regions()[ $region ];
        $show_photos = ! empty( $meta['has_featured_photos'] );

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Kelola daftar pengurus per wilayah.', 'ptsbi-premium' );
        if ( $show_photos ) {
            echo ' ' . esc_html__( 'Ketua Umum, Sekretaris Umum, dan Bendahara Umum memiliki kolom URL foto di bawah jabatan.', 'ptsbi-premium' );
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
        if ( $show_photos ) {
            $form_class .= ' ptprm-board-admin-form--photos';
        }
        echo '<form method="post" id="ptprm-board-admin-form" class="' . esc_attr( $form_class ) . '" action="' . esc_url( PTPRM_Admin_Portal::portal_url( 'pengurus', [ 'board_region' => $region ] ) ) . '">';
        wp_nonce_field( 'ptprm_board_save' );
        echo '<input type="hidden" name="ptprm_board_save" value="1">';
        echo '<input type="hidden" name="board_region" value="' . esc_attr( $region ) . '">';
        echo '<input type="hidden" name="ptprm_board_json" id="ptprm-board-json" value="">';
        echo '<input type="hidden" id="ptprm-board-has-featured" value="' . ( $show_photos ? '1' : '0' ) . '">';

        echo '<ul class="ptprm-board-admin-list" id="ptprm-board-admin-list">';
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            self::render_board_row( $region, $item, $show_photos );
        }
        echo '</ul>';

        echo '<p><button type="button" class="button button-secondary" id="ptprm-board-add">+ ' . esc_html__( 'Tambah baris', 'ptsbi-premium' ) . '</button></p>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Pengurus', 'ptsbi-premium' ) . '</span></button>';
        echo '</form></div>';

        echo '<template id="ptprm-board-row-tpl"><ul>';
        self::render_board_row( $region, [ 'name' => '', 'role' => '', 'group' => '', 'image' => '', 'featured' => 0 ], $show_photos );
        echo '</ul></template>';
    }
}
