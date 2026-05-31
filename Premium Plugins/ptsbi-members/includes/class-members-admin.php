<?php
/**
 * Panel admin anggota di frontend (import, export, approve, pencarian, foto pengurus).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'PTPRM_Members_Admin', false ) ) {
    return;
}

class PTPRM_Members_Admin {

    private const NONCE_APPROVE  = 'ptprm_admin_member_approve';
    private const NONCE_REJECT   = 'ptprm_admin_member_reject';
    private const NONCE_TEAM     = 'ptprm_admin_team_photos';
    private const NONCE_EXPORT   = 'ptprm_members_export_xlsx';

    public function __construct() {
        add_action( 'init', [ $this, 'handle_approve' ], 20 );
        add_action( 'init', [ $this, 'handle_reject' ], 20 );
        add_action( 'init', [ $this, 'handle_team_photos' ], 20 );
        add_action( 'init', [ $this, 'handle_export' ], 5 );
        add_filter( 'ptprm_admin_portal_tabs', [ $this, 'register_tabs' ] );
        add_action( 'ptprm_admin_portal_tab_anggota', [ $this, 'render_members_tab' ] );
        add_action( 'ptprm_admin_portal_tab_struktur', [ $this, 'render_struktur_tab' ] );
    }

    public static function needs_directory_assets(): bool {
        if ( ! class_exists( 'PTPRM_Admin_Portal' ) || ! PTPRM_Admin_Portal::is_admin_page() ) {
            return class_exists( 'PTPRM_Bidang_Portal' ) && is_page();
        }
        $tab = sanitize_key( (string) ( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return in_array( $tab, [ 'anggota', 'struktur' ], true )
            || ( class_exists( 'PTPRM_Bidang_Registry' ) && PTPRM_Bidang_Registry::is_sekretariat_user() );
    }

    public function register_tabs( array $tabs ): array {
        if ( ! $this->can_members() ) {
            return $tabs;
        }
        $tabs['anggota'] = __( 'Data Anggota', 'ptsbi-members' );
        if ( class_exists( 'PTPRM_Access' ) && PTPRM_Access::can_manage_org_settings() ) {
            $tabs['struktur'] = __( 'Foto Pengurus', 'ptsbi-members' );
        }
        return $tabs;
    }

    private function can_members(): bool {
        return class_exists( 'PTPRM_Access' ) && PTPRM_Access::can_approve_members();
    }

    private function portal_url( string $tab = 'anggota', array $args = [] ): string {
        if ( class_exists( 'PTPRM_Admin_Portal' ) ) {
            return PTPRM_Admin_Portal::portal_url( $tab, $args );
        }
        return home_url( '/panel-pengurus/' );
    }

    public function handle_export(): void {
        if ( empty( $_GET['ptprm_export_members'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! is_user_logged_in() || ! $this->can_export() ) {
            wp_die( esc_html__( 'Akses ditolak.', 'ptsbi-members' ) );
        }
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), self::NONCE_EXPORT ) ) {
            wp_die( esc_html__( 'Sesi tidak valid.', 'ptsbi-members' ) );
        }
        PTPRM_Members::stream_export_xlsx();
    }

    private function can_export(): bool {
        if ( $this->can_members() ) {
            return true;
        }
        return class_exists( 'PTPRM_Bidang_Registry' ) && PTPRM_Bidang_Registry::is_sekretariat_user();
    }

    public function handle_approve(): void {
        if ( empty( $_POST['ptprm_approve_member_id'] ) || ! $this->can_members() ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_APPROVE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_APPROVE ] ) ), 'ptprm_admin_member_approve' ) ) {
            return;
        }
        $user_id = (int) $_POST['ptprm_approve_member_id'];
        $ok      = PTPRM_Members::approve_pending_registration( $user_id );
        $msg     = $ok ? __( 'Pendaftaran disetujui.', 'ptsbi-members' ) : __( 'Gagal menyetujui.', 'ptsbi-members' );
        wp_safe_redirect( add_query_arg( 'ptprm_members_notice', rawurlencode( $msg ), $this->portal_url( 'anggota' ) ) );
        exit;
    }

    public function handle_reject(): void {
        if ( empty( $_POST['ptprm_reject_member_id'] ) || ! $this->can_members() ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_REJECT ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_REJECT ] ) ), 'ptprm_admin_member_reject' ) ) {
            return;
        }
        $user_id = (int) $_POST['ptprm_reject_member_id'];
        $ok      = PTPRM_Members::reject_and_delete_pending_registration( $user_id );
        $msg     = $ok ? __( 'Pendaftaran ditolak.', 'ptsbi-members' ) : __( 'Gagal menolak.', 'ptsbi-members' );
        wp_safe_redirect( add_query_arg( 'ptprm_members_notice', rawurlencode( $msg ), $this->portal_url( 'anggota' ) ) );
        exit;
    }

    public function handle_team_photos(): void {
        if ( empty( $_POST['ptprm_admin_team_photos'] ) || ! class_exists( 'PTPRM_Access' ) || ! PTPRM_Access::can_manage_org_settings() ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_TEAM ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_TEAM ] ) ), 'ptprm_admin_team_photos' ) ) {
            return;
        }
        $names  = isset( $_POST['team_name'] ) && is_array( $_POST['team_name'] ) ? wp_unslash( $_POST['team_name'] ) : [];
        $roles  = isset( $_POST['team_role'] ) && is_array( $_POST['team_role'] ) ? wp_unslash( $_POST['team_role'] ) : [];
        $groups = isset( $_POST['team_group'] ) && is_array( $_POST['team_group'] ) ? wp_unslash( $_POST['team_group'] ) : [];
        $images = isset( $_POST['team_image'] ) && is_array( $_POST['team_image'] ) ? wp_unslash( $_POST['team_image'] ) : [];
        $rows   = [];
        foreach ( $names as $i => $name ) {
            $rows[] = [
                'name'  => $name,
                'role'  => $roles[ $i ] ?? '',
                'group' => $groups[ $i ] ?? '',
                'image' => $images[ $i ] ?? '',
            ];
        }
        $clean = function_exists( 'ptprm_sanitize_team_items' ) ? ptprm_sanitize_team_items( $rows ) : [];
        $opts  = (array) get_option( PTPRM_OPTION, [] );
        $opts['team_items'] = wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
        $opts['team_show']  = 1;
        update_option( PTPRM_OPTION, array_merge( ptsmb_options(), $opts ) );
        wp_safe_redirect( add_query_arg( 'ptprm_saved', '1', $this->portal_url( 'struktur' ) ) );
        exit;
    }

    public function render_members_tab(): void {
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

        $total      = PTPRM_Members::count_members();
        $export_url = wp_nonce_url(
            add_query_arg( 'ptprm_export_members', '1', $this->portal_url( 'anggota' ) ),
            self::NONCE_EXPORT
        );

        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p><strong>' . esc_html( sprintf( __( 'Total: %d keluarga', 'ptsbi-members' ), $total ) ) . '</strong></p>';

        $pending = PTPRM_Members::list_pending_registrations();
        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Pendaftaran Pending', 'ptsbi-members' ) . '</h3>';
        if ( $pending ) {
            echo '<div style="overflow:auto"><table class="ptprm-member-table"><thead><tr>';
            echo '<th>Nama</th><th>Email</th><th>No HP</th><th>Waktu</th><th></th></tr></thead><tbody>';
            foreach ( $pending as $row ) {
                $uid = (int) ( $row['user_id'] ?? 0 );
                if ( $uid <= 0 ) {
                    continue;
                }
                echo '<tr><td>' . esc_html( (string) ( $row['name'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['email'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['wa'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td><td>';
                echo '<form method="post" style="display:inline">';
                wp_nonce_field( 'ptprm_admin_member_approve', self::NONCE_APPROVE );
                echo '<input type="hidden" name="ptprm_approve_member_id" value="' . esc_attr( (string) $uid ) . '">';
                echo '<button type="submit" class="ptprm-cta ptprm-cta-2 ptprm-cta-size-small"><span class="ptprm-cta-label">' . esc_html__( 'Approve', 'ptsbi-members' ) . '</span></button></form> ';
                echo '<form method="post" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Tolak pendaftaran ini?', 'ptsbi-members' ) ) . '\')">';
                wp_nonce_field( 'ptprm_admin_member_reject', self::NONCE_REJECT );
                echo '<input type="hidden" name="ptprm_reject_member_id" value="' . esc_attr( (string) $uid ) . '">';
                echo '<button type="submit" class="ptprm-cta ptprm-cta-outline ptprm-cta-size-small"><span class="ptprm-cta-label">' . esc_html__( 'Tolak', 'ptsbi-members' ) . '</span></button></form>';
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p class="ptprm-portal-help">' . esc_html__( 'Tidak ada pendaftaran pending.', 'ptsbi-members' ) . '</p>';
        }

        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Import CSV (DAMI / template)', 'ptsbi-members' ) . '</h3>';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Format DATA DAMI 2024 DKI didukung (pemisah ;). Status Hula/Boru otomatis jadi Anak/Boru.', 'ptsbi-members' ) . ' ';
        echo '<a href="' . esc_url( PTSMB_URL . 'assets/data/template-import-anggota.csv' ) . '" download>' . esc_html__( 'Template CSV', 'ptsbi-members' ) . '</a></p>';
        echo '<form method="post" enctype="multipart/form-data" class="ptprm-member-form">';
        wp_nonce_field( 'ptprm_members_import_csv' );
        echo '<input type="hidden" name="ptprm_members_import_csv" value="1">';
        echo '<p><input type="file" name="ptprm_members_csv" accept=".csv,text/csv" required></p>';
        echo '<p><label><input type="checkbox" name="replace_all" value="1"> ' . esc_html__( 'Kosongkan data lama sebelum import', 'ptsbi-members' ) . '</label></p>';
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Import CSV', 'ptsbi-members' ) . '</span></button>';
        echo '</form>';

        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Export XLSX', 'ptsbi-members' ) . '</h3>';
        echo '<a class="ptprm-cta ptprm-cta-outline ptprm-cta-size-medium" href="' . esc_url( $export_url ) . '"><span class="ptprm-cta-label">' . esc_html__( 'Unduh XLSX', 'ptsbi-members' ) . '</span></a>';
        echo '</div>';

        echo '<div class="ptprm-member-card ptprm-portal-card" style="margin-top:1.5rem">';
        echo '<h3 class="ptprm-admin-h3">' . esc_html__( 'Pencarian Anggota', 'ptsbi-members' ) . '</h3>';
        echo do_shortcode( '[ptprm_member_directory]' );
        echo '</div>';
    }

    public function render_struktur_tab(): void {
        if ( isset( $_GET['ptprm_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html__( 'Foto pengurus pusat disimpan.', 'ptsbi-members' ) . '</p>';
        }
        $items = function_exists( 'ptprm_get_team_items' ) ? ptprm_get_team_items() : [];
        if ( ! $items ) {
            $items = [ [ 'name' => '', 'role' => '', 'group' => '', 'image' => '' ] ];
        }
        echo '<div class="ptprm-member-card ptprm-portal-card">';
        echo '<p class="ptprm-portal-help">' . esc_html__( 'Unggah foto pengurus pusat (maks. 5) untuk beranda / struktur organisasi. Daftar teks pengurus wilayah di tab Pengurus Wilayah pada Premium Plugin.', 'ptsbi-members' ) . '</p>';
        echo '<form method="post" class="ptprm-member-form">';
        wp_nonce_field( 'ptprm_admin_team_photos', self::NONCE_TEAM );
        echo '<input type="hidden" name="ptprm_admin_team_photos" value="1">';
        foreach ( $items as $i => $item ) {
            $img = (string) ( $item['image'] ?? '' );
            if ( is_numeric( $img ) ) {
                $img = (string) wp_get_attachment_url( (int) $img );
            }
            echo '<fieldset style="margin-bottom:1rem;padding:1rem;border:1px solid #ddd">';
            echo '<label><span>' . esc_html__( 'Nama', 'ptsbi-members' ) . '</span><input type="text" name="team_name[]" value="' . esc_attr( (string) ( $item['name'] ?? '' ) ) . '"></label>';
            echo '<label><span>' . esc_html__( 'Jabatan', 'ptsbi-members' ) . '</span><input type="text" name="team_role[]" value="' . esc_attr( (string) ( $item['role'] ?? '' ) ) . '"></label>';
            echo '<label><span>' . esc_html__( 'Grup', 'ptsbi-members' ) . '</span><input type="text" name="team_group[]" value="' . esc_attr( (string) ( $item['group'] ?? '' ) ) . '"></label>';
            echo '<label><span>' . esc_html__( 'URL foto', 'ptsbi-members' ) . '</span><input type="url" name="team_image[]" value="' . esc_attr( $img ) . '" placeholder="https://..."></label>';
            echo '</fieldset>';
        }
        echo '<button type="submit" class="ptprm-cta ptprm-cta-1 ptprm-cta-size-medium"><span class="ptprm-cta-label">' . esc_html__( 'Simpan Foto Pengurus', 'ptsbi-members' ) . '</span></button>';
        echo '</form></div>';
    }
}
