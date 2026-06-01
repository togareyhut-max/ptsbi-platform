<?php
/**
 * Panel pengurus / admin organisasi — sepenuhnya di frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Admin_Portal {

    public const SLUG_LOGIN  = 'masuk-pengurus';
    public const SLUG_PORTAL = 'panel-pengurus';

    private const NONCE_LOGOUT  = 'ptprm_admin_logout';
    private const NONCE_SETTINGS = 'ptprm_admin_settings';
    private const NONCE_POST    = 'ptprm_admin_post';
    private const NONCE_APPROVE = 'ptprm_admin_member_approve';

    private function can_access_panel(): bool {
        return PTPRM_Access::can_manage();
    }

    private function can_access_members_tab(): bool {
        return PTPRM_Access::can_approve_members();
    }

    private function can_access_posts_tab(): bool {
        return PTPRM_Access::can_manage();
    }

    private function can_access_settings_tab(): bool {
        return PTPRM_Access::can_manage_org_settings();
    }

    public function __construct() {
        add_action( 'init', [ __CLASS__, 'maybe_ensure_pages' ], 14 );
        add_action( 'init', [ $this, 'handle_save_settings' ], 20 );
        add_action( 'init', [ $this, 'handle_save_post' ], 20 );
        add_shortcode( 'ptprm_admin_portal', [ $this, 'shortcode_portal' ] );
        add_filter( 'ptprm_subpage_hero_skip', [ $this, 'skip_subpage_hero' ] );
    }

    public static function login_slug(): string {
        $o = ptprm_options();
        $s = sanitize_title( (string) ( $o['admin_login_slug'] ?? self::SLUG_LOGIN ) );
        return $s !== '' ? $s : self::SLUG_LOGIN;
    }

    public static function portal_slug(): string {
        $o = ptprm_options();
        $s = sanitize_title( (string) ( $o['admin_portal_slug'] ?? self::SLUG_PORTAL ) );
        return $s !== '' ? $s : self::SLUG_PORTAL;
    }

    public static function login_url( string $redirect = '' ): string {
        if ( class_exists( 'PTPRM_Login_Portal' ) ) {
            return PTPRM_Login_Portal::login_url( $redirect );
        }
        $url = home_url( '/' . self::login_slug() . '/' );
        if ( $redirect !== '' ) {
            $url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
        }
        return $url;
    }

    public static function portal_url( string $tab = '', array $args = [] ): string {
        $url = home_url( '/' . self::portal_slug() . '/' );
        if ( $tab !== '' ) {
            $args['tab'] = $tab;
        }
        if ( $args ) {
            $url = add_query_arg( $args, $url );
        }
        return $url;
    }

    public static function is_admin_page(): bool {
        if ( ! is_page() ) {
            return false;
        }
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        return $slug === self::portal_slug();
    }

    public static function maybe_ensure_pages(): void {
        $needs_flush = false;
        if ( ! get_option( 'ptprm_admin_pages_v1' ) ) {
            self::ensure_pages();
            update_option( 'ptprm_admin_pages_v1', 1, false );
            $needs_flush = true;
        }
        self::repair_portal_page_content();
        if ( $needs_flush ) {
            flush_rewrite_rules( false );
        }
    }

    public static function repair_portal_page_content(): void {
        $page = get_page_by_path( self::portal_slug(), OBJECT, 'page' );
        if ( ! $page instanceof WP_Post ) {
            return;
        }
        if ( strpos( (string) $page->post_content, 'ptprm_admin_portal' ) !== false ) {
            return;
        }
        wp_update_post(
            [
                'ID'           => (int) $page->ID,
                'post_content' => '[ptprm_admin_portal]',
            ]
        );
    }

    public static function ensure_pages(): array {
        $ids = [ 'portal' => 0 ];
        $pages = [
            'portal' => [
                'slug'    => self::portal_slug(),
                'title'   => __( 'Panel Pengurus', 'ptsbi-premium' ),
                'content' => '[ptprm_admin_portal]',
            ],
        ];
        foreach ( $pages as $key => $bp ) {
            $existing = get_page_by_path( $bp['slug'], OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                $ids[ $key ] = (int) $existing->ID;
                continue;
            }
            $id = wp_insert_post(
                [
                    'post_title'   => $bp['title'],
                    'post_name'    => $bp['slug'],
                    'post_content' => $bp['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ],
                true
            );
            if ( ! is_wp_error( $id ) ) {
                $ids[ $key ] = (int) $id;
            }
        }
        return $ids;
    }

    public function skip_subpage_hero( bool $skip ): bool {
        return $skip || self::is_admin_page() || PTPRM_Member_Portal::is_member_page();
    }

    public function handle_save_settings(): void {
        if ( empty( $_POST['ptprm_admin_settings'] ) || ! $this->can_access_settings_tab() ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_SETTINGS ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_SETTINGS ] ) ), 'ptprm_admin_settings' ) ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_settings_error',
                    rawurlencode( function_exists( 'ptprm_portal_session_expired_message' ) ? ptprm_portal_session_expired_message() : __( 'Sesi form kedaluwarsa. Muat ulang halaman lalu simpan lagi.', 'ptsbi-premium' ) ),
                    self::portal_url( 'informasi' )
                )
            );
            exit;
        }

        $keys = [
            'org_name', 'whatsapp', 'address', 'hours',
            'members_register_show', 'members_directory_show', 'members_per_page',
            'members_register_auto_approve',
            'members_register_notify_email',
            'members_register_notify_wa',
            'membership_api_enabled',
            'membership_api_base_url',
            'membership_api_integration_key',
            'membership_api_timeout_sec',
        ];
        $existing = (array) get_option( PTPRM_OPTION, [] );
        $patch    = [];

        foreach ( $keys as $key ) {
            if ( ! isset( $_POST[ $key ] ) ) {
                continue;
            }
            if ( in_array( $key, [ 'members_register_show', 'members_directory_show', 'members_register_auto_approve', 'members_register_notify_email', 'members_register_notify_wa', 'membership_api_enabled' ], true ) ) {
                $patch[ $key ] = ! empty( $_POST[ $key ] ) ? 1 : 0;
                continue;
            }
            if ( $key === 'members_per_page' ) {
                $patch[ $key ] = max( 10, min( 50, (int) $_POST[ $key ] ) );
                continue;
            }
            if ( $key === 'membership_api_timeout_sec' ) {
                $patch[ $key ] = max( 5, min( 60, (int) $_POST[ $key ] ) );
                continue;
            }
            $patch[ $key ] = sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
        }

        $next = array_merge( $existing, $patch );
        update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( $next ), true );
        wp_cache_delete( PTPRM_OPTION, 'options' );

        if ( class_exists( 'PTPRM_Membership_Sync' ) ) {
            PTPRM_Membership_Sync::push_options_patch( $patch );
        }

        // Bypass manual approve: jika auto approve aktif, setujui semua pending.
        if ( ! empty( $next['members_register_auto_approve'] ) && class_exists( 'PTPRM_Members' ) ) {
            $pending = PTPRM_Members::list_pending_registrations();
            foreach ( $pending as $p ) {
                PTPRM_Members::approve_pending_registration( (int) $p['user_id'] );
            }
        }

        wp_safe_redirect( add_query_arg( 'ptprm_saved', '1', self::portal_url( 'informasi' ) ) );
        exit;
    }

    public function handle_save_post(): void {
        if ( empty( $_POST['ptprm_admin_post'] ) || ! $this->can_access_posts_tab() ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_POST ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_POST ] ) ), 'ptprm_admin_post' ) ) {
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_post_error',
                    rawurlencode( function_exists( 'ptprm_portal_session_expired_message' ) ? ptprm_portal_session_expired_message() : __( 'Sesi form kedaluwarsa. Muat ulang halaman lalu simpan lagi.', 'ptsbi-premium' ) ),
                    self::portal_url( 'berita' )
                )
            );
            exit;
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $title   = sanitize_text_field( wp_unslash( (string) ( $_POST['post_title'] ?? '' ) ) );
        $content = wp_kses_post( wp_unslash( (string) ( $_POST['post_content'] ?? '' ) ) );
        $status  = ( $_POST['post_status'] ?? 'draft' ) === 'publish' ? 'publish' : 'draft';

        if ( $title === '' ) {
            wp_safe_redirect( add_query_arg( 'ptprm_post_error', rawurlencode( __( 'Judul wajib diisi.', 'ptsbi-premium' ) ), self::portal_url( 'berita' ) ) );
            exit;
        }

        $data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        ];

        if ( $post_id > 0 ) {
            $data['ID'] = $post_id;
            $result     = wp_update_post( $data, true );
        } else {
            $result = wp_insert_post( $data, true );
        }

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'ptprm_post_error', rawurlencode( $result->get_error_message() ), self::portal_url( 'berita' ) ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'ptprm_post_ok', '1', self::portal_url( 'berita', [ 'edit' => (int) $result ] ) ) );
        exit;
    }

    public function shortcode_portal(): string {
        if ( ! is_user_logged_in() || ! $this->can_access_panel() ) {
            ob_start();
            echo '<div class="ptprm-member-card ptprm-portal-card">';
            echo '<p>' . esc_html__( 'Silakan masuk melalui Rumah Anggota.', 'ptsbi-premium' ) . '</p>';
            echo '<a class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium" href="' . esc_url( self::login_url( self::portal_url() ) ) . '">';
            echo '<span class="ptprm-cta-label">' . esc_html__( 'Rumah Anggota', 'ptsbi-premium' ) . '</span></a>';
            echo '</div>';
            return (string) ob_get_clean();
        }

        $tabs = apply_filters( 'ptprm_admin_portal_tabs', [ 'ringkasan' => __( 'Ringkasan', 'ptsbi-premium' ) ] );
        if ( $this->can_access_posts_tab() && ! isset( $tabs['berita'] ) ) {
            $tabs['berita'] = __( 'Berita / Kegiatan', 'ptsbi-premium' );
        }
        if ( $this->can_access_settings_tab() && ! isset( $tabs['informasi'] ) ) {
            $tabs['informasi'] = __( 'Informasi Organisasi', 'ptsbi-premium' );
        }
        if ( $this->can_access_members_tab() && ! isset( $tabs['anggota'] ) ) {
            $tabs['anggota'] = __( 'Data Anggota', 'ptsbi-premium' );
        }

        $tab = sanitize_key( (string) ( $_GET['tab'] ?? 'ringkasan' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $allowed = array_keys( $tabs );
        if ( ! in_array( $tab, $allowed, true ) ) {
            $tab = 'ringkasan';
        }

        ob_start();
        $user = wp_get_current_user();

        echo '<div class="ptprm-portal-wrap ptprm-admin-portal-wrap">';
        echo '<header class="ptprm-portal-head">';
        echo '<h2 class="ptprm-portal-title">' . esc_html( PTPRM_Access::is_org_admin() ? __( 'Panel Admin Organisasi', 'ptsbi-premium' ) : __( 'Panel Pengurus', 'ptsbi-premium' ) ) . '</h2>';
        echo '<p class="ptprm-portal-greet">' . esc_html( sprintf( __( 'Halo, %s', 'ptsbi-premium' ), $user->display_name ?: $user->user_login ) ) . '</p>';
        echo '</header>';

        echo '<nav class="ptprm-portal-tabs">';
        foreach ( $tabs as $key => $label ) {
            $active = $tab === $key ? ' is-active' : '';
            echo '<a class="ptprm-portal-tab' . esc_attr( $active ) . '" href="' . esc_url( self::portal_url( $key ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        echo '<div class="ptprm-portal-panel">';
        $this->render_portal_flash_notices( $tab );
        switch ( $tab ) {
            case 'anggota':
            case 'struktur':
                if ( ! $this->can_access_members_tab() && $tab === 'anggota' ) {
                    $this->render_tab_dashboard();
                    break;
                }
                if ( $tab === 'struktur' && ! PTPRM_Access::can_manage_org_settings() ) {
                    $this->render_tab_dashboard();
                    break;
                }
                if ( has_action( 'ptprm_admin_portal_tab_' . $tab ) ) {
                    do_action( 'ptprm_admin_portal_tab_' . $tab );
                } elseif ( $tab === 'anggota' && $this->can_access_members_tab() ) {
                    $this->render_tab_members();
                } else {
                    $this->render_tab_dashboard();
                }
                break;
            case 'berita':
                if ( ! $this->can_access_posts_tab() ) {
                    $this->render_tab_dashboard();
                    break;
                }
                $this->render_tab_posts();
                break;
            case 'informasi':
                if ( ! $this->can_access_settings_tab() ) {
                    $this->render_tab_dashboard();
                    break;
                }
                $this->render_tab_settings();
                break;
            case 'dokumen':
                if ( ! class_exists( 'PTPRM_Pdf_Portal' ) || ! PTPRM_Pdf_Portal::can_manage_pdf() ) {
                    $this->render_tab_dashboard();
                    break;
                }
                do_action( 'ptprm_admin_portal_tab_dokumen' );
                break;
            default:
                $this->render_tab_dashboard();
        }
        echo '</div>';

        echo '<footer class="ptprm-portal-footbar">';
        PTPRM_Access::render_logout_link();
        echo '</footer>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Tampilkan pesan error/sukses dari query string (setelah redirect simpan).
     */
    private function render_portal_flash_notices( string $tab ): void {
        $map = [
            'informasi' => 'ptprm_settings_error',
            'berita'    => 'ptprm_post_error',
            'anggota'   => 'ptprm_members_error',
            'struktur'  => 'ptprm_settings_error',
            'dokumen'   => 'ptprm_settings_error',
        ];
        if ( ! isset( $map[ $tab ] ) ) {
            return;
        }
        $key = $map[ $tab ];
        if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $msg = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $msg === '' ) {
            return;
        }
        echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $msg ) . '</p>';
    }

    private function render_tab_dashboard(): void {
        $members = PTPRM_Members::count_members();
        $posts   = wp_count_posts( 'post' );
        $pub     = $posts ? (int) $posts->publish : 0;

        echo '<div class="ptprm-admin-stat-grid">';
        echo '<div class="ptprm-admin-stat"><span class="ptprm-admin-stat-num">' . (int) $members . '</span><span class="ptprm-admin-stat-label">' . esc_html__( 'Keluarga terdata', 'ptsbi-premium' ) . '</span></div>';
        echo '<div class="ptprm-admin-stat"><span class="ptprm-admin-stat-num">' . (int) $pub . '</span><span class="ptprm-admin-stat-label">' . esc_html__( 'Berita dipublikasi', 'ptsbi-premium' ) . '</span></div>';
        echo '</div>';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Semua pengelolaan dilakukan di halaman ini — tidak perlu membuka wp-admin.', 'ptsbi-premium' ) . '</p>';
    }

    private function render_tab_members(): void {
        if ( isset( $_GET['ptprm_members_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $n = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_members_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $n !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html( $n ) . '</p>';
            }
        }
        if ( isset( $_GET['ptprm_members_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $e = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_members_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $e !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $e ) . '</p>';
            }
        }

        $total = PTPRM_Members::count_members();

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p><strong>' . esc_html( sprintf( __( 'Total: %d keluarga', 'ptsbi-premium' ), $total ) ) . '</strong></p>';

        $pending = class_exists( 'PTPRM_Members' ) ? PTPRM_Members::list_pending_registrations() : [];
        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Pendaftaran Anggota', 'ptsbi-premium' ) . '</h3>';
        if ( ! empty( $pending ) ) {
            echo '<p class="ptprm-portal-help">' . esc_html__( 'Daftar pendaftar yang masih pending. Admin perlu menekan tombol Approve.', 'ptsbi-premium' ) . '</p>';
            echo '<div style="overflow:auto;">';
            echo '<table class="ptprm-member-table"><thead><tr>';
            echo '<th>Nama</th><th>Email</th><th>No WA</th><th>Waktu</th><th>Action</th>';
            echo '</tr></thead><tbody>';
            foreach ( $pending as $row ) {
                $user_id = (int) ( $row['user_id'] ?? 0 );
                if ( $user_id <= 0 ) {
                    continue;
                }
                echo '<tr>';
                echo '<td>' . esc_html( (string) ( $row['name'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['email'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['wa'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td>';
                echo '<td>';
                echo '<form method="post" style="margin:0;">';
                wp_nonce_field( 'ptprm_admin_member_approve', self::NONCE_APPROVE );
                echo '<input type="hidden" name="ptprm_approve_member_id" value="' . esc_attr( (string) $user_id ) . '">';
                echo '<button type="submit" class="ptprm-cta ptprm-cta-2 ptprm-cta-size-small"><span class="ptprm-cta-label">' . esc_html__( 'Approve', 'ptsbi-premium' ) . '</span></button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p class="ptprm-portal-help">' . esc_html__( 'Belum ada pendaftaran pending.', 'ptsbi-premium' ) . '</p>';
        }

        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Import CSV', 'ptsbi-premium' ) . '</h3>';
        echo '<form method="post" enctype="multipart/form-data" class="ptprm-member-form">';
        wp_nonce_field( 'ptprm_members_import_csv' );
        echo '<input type="hidden" name="ptprm_members_import_csv" value="1">';
        echo '<p><input type="file" name="ptprm_members_csv" accept=".csv,text/csv" required></p>';
        echo '<p><label><input type="checkbox" name="replace_all" value="1"> ' . esc_html__( 'Kosongkan data lama sebelum import', 'ptsbi-premium' ) . '</label></p>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Import CSV', 'ptsbi-premium' ) . '</span></button>';
        echo '</form>';

        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Export XLSX', 'ptsbi-premium' ) . '</h3>';
        if ( class_exists( 'PTPRM_Members' ) ) {
            PTPRM_Members::render_export_download_control();
        }
        echo '</div>';
    }

    private function render_tab_posts(): void {
        $edit_id = (int) ( $_GET['edit'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post    = $edit_id > 0 ? get_post( $edit_id ) : null;

        if ( isset( $_GET['ptprm_post_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Berita disimpan.', 'ptsbi-premium' ) . '</p>';
        }
        if ( isset( $_GET['ptprm_post_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $e = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_post_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $e !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $e ) . '</p>';
            }
        }

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<h3 class="ptprm-admin-h3">' . esc_html( $post ? __( 'Edit Berita', 'ptsbi-premium' ) : __( 'Tulis Berita Baru', 'ptsbi-premium' ) ) . '</h3>';
        echo '<form method="post" class="ptprm-member-form" action="' . esc_url( self::portal_url( 'berita', $edit_id ? [ 'edit' => $edit_id ] : [] ) ) . '">';
        wp_nonce_field( 'ptprm_admin_post', self::NONCE_POST );
        echo '<input type="hidden" name="ptprm_admin_post" value="1">';
        echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) ( $post ? $post->ID : 0 ) ) . '">';
        echo '<label><span>' . esc_html__( 'Judul', 'ptsbi-premium' ) . '</span>';
        echo '<input type="text" name="post_title" value="' . esc_attr( $post ? $post->post_title : '' ) . '" required></label>';
        echo '<label><span>' . esc_html__( 'Status', 'ptsbi-premium' ) . '</span>';
        echo '<select name="post_status">';
        echo '<option value="draft"' . selected( $post ? $post->post_status : 'draft', 'draft', false ) . '>' . esc_html__( 'Draf', 'ptsbi-premium' ) . '</option>';
        echo '<option value="publish"' . selected( $post ? $post->post_status : '', 'publish', false ) . '>' . esc_html__( 'Publikasikan', 'ptsbi-premium' ) . '</option>';
        echo '</select></label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Isi berita', 'ptsbi-premium' ) . '</span>';
        echo '<textarea name="post_content" rows="12">' . esc_textarea( $post ? $post->post_content : '' ) . '</textarea></label>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Berita', 'ptsbi-premium' ) . '</span></button>';
        echo '</form></div>';

        $q = new WP_Query(
            [
                'post_type'      => 'post',
                'post_status'    => [ 'publish', 'draft', 'pending' ],
                'posts_per_page' => 15,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Daftar berita', 'ptsbi-premium' ) . '</h3>';
        if ( $q->have_posts() ) {
            echo '<ul class="ptprm-admin-post-list">';
            while ( $q->have_posts() ) {
                $q->the_post();
                echo '<li>';
                echo '<a href="' . esc_url( self::portal_url( 'berita', [ 'edit' => get_the_ID() ] ) ) . '">' . esc_html( get_the_title() ) . '</a>';
                echo ' <em>(' . esc_html( get_post_status() ) . ')</em>';
                echo '</li>';
            }
            echo '</ul>';
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__( 'Belum ada berita.', 'ptsbi-premium' ) . '</p>';
        }
        echo '</div>';
    }

    private function render_tab_settings(): void {
        $o = ptprm_options();
        if ( isset( $_GET['ptprm_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Informasi disimpan.', 'ptsbi-premium' ) . '</p>';
        }

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Informasi penting organisasi (kontak, nama, tampilan anggota). Pengaturan desain lengkap dapat ditambahkan di sini bertahap.', 'ptsbi-premium' ) . '</p>';
        echo '<form method="post" class="ptprm-member-form" action="' . esc_url( self::portal_url( 'informasi' ) ) . '">';
        wp_nonce_field( 'ptprm_admin_settings', self::NONCE_SETTINGS );
        echo '<input type="hidden" name="ptprm_admin_settings" value="1">';
        echo '<div class="ptprm-member-grid">';
        echo '<label><span>' . esc_html__( 'Nama organisasi', 'ptsbi-premium' ) . '</span><input type="text" name="org_name" value="' . esc_attr( (string) ( $o['org_name'] ?? '' ) ) . '"></label>';
        echo '<label><span>WhatsApp</span><input type="text" name="whatsapp" value="' . esc_attr( (string) ( $o['whatsapp'] ?? '' ) ) . '"></label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Alamat', 'ptsbi-premium' ) . '</span><textarea name="address" rows="2">' . esc_textarea( (string) ( $o['address'] ?? '' ) ) . '</textarea></label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Jam operasional', 'ptsbi-premium' ) . '</span><textarea name="hours" rows="2">' . esc_textarea( (string) ( $o['hours'] ?? '' ) ) . '</textarea></label>';
        echo '<label><span>' . esc_html__( 'Baris per halaman (direktori)', 'ptsbi-premium' ) . '</span><input type="number" name="members_per_page" min="10" max="50" value="' . esc_attr( (string) ( $o['members_per_page'] ?? 25 ) ) . '"></label>';
        echo '<label class="ptprm-member-full"><input type="checkbox" name="members_register_show" value="1"' . checked( ! empty( $o['members_register_show'] ), true, false ) . '> ' . esc_html__( 'Tampilkan form daftar di beranda', 'ptsbi-premium' ) . '</label>';
        echo '<label class="ptprm-member-full"><input type="checkbox" name="members_directory_show" value="1"' . checked( ! empty( $o['members_directory_show'] ), true, false ) . '> ' . esc_html__( 'Tampilkan direktori di beranda', 'ptsbi-premium' ) . '</label>';

        echo '<label class="ptprm-member-full"><input type="checkbox" name="members_register_auto_approve" value="1"' . checked( ! empty( $o['members_register_auto_approve'] ), true, false ) . '> ' . esc_html__( 'Auto approve pendaftaran anggota', 'ptsbi-premium' ) . '</label>';
        echo '<label class="ptprm-member-full"><input type="checkbox" name="members_register_notify_email" value="1"' . checked( ! empty( $o['members_register_notify_email'] ), true, false ) . '> ' . esc_html__( 'Notifikasi email setelah approve', 'ptsbi-premium' ) . '</label>';
        echo '<label class="ptprm-member-full"><input type="checkbox" name="members_register_notify_wa" value="1"' . checked( ! empty( $o['members_register_notify_wa'] ), true, false ) . '> ' . esc_html__( 'Notifikasi WhatsApp (wa.me) dalam email', 'ptsbi-premium' ) . '</label>';

        echo '<label class="ptprm-member-full"><input type="checkbox" name="membership_api_enabled" value="1"' . checked( ! empty( $o['membership_api_enabled'] ), true, false ) . '> ' . esc_html__( 'Aktifkan Membership API terpisah', 'ptsbi-premium' ) . '</label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Base URL Membership API', 'ptsbi-premium' ) . '</span><input type="text" name="membership_api_base_url" value="' . esc_attr( (string) ( $o['membership_api_base_url'] ?? '' ) ) . '" placeholder="https://api.tarombo.ptsbi.org/v1"></label>';
        echo '<label class="ptprm-member-full"><span>' . esc_html__( 'Integration Key', 'ptsbi-premium' ) . '</span><input type="password" name="membership_api_integration_key" value="' . esc_attr( (string) ( $o['membership_api_integration_key'] ?? '' ) ) . '" autocomplete="off"></label>';
        echo '<label><span>' . esc_html__( 'Timeout API (detik)', 'ptsbi-premium' ) . '</span><input type="number" name="membership_api_timeout_sec" min="5" max="60" value="' . esc_attr( (string) ( $o['membership_api_timeout_sec'] ?? 15 ) ) . '"></label>';
        echo '</div>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Informasi', 'ptsbi-premium' ) . '</span></button>';
        echo '</form></div>';
    }
}
