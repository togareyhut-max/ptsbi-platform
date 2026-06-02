<?php
/**
 * Universal: setiap situs WP punya opsi sendiri (ptprm_options), tidak terkunci ptsbi.org.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Bootstrap {

    public static function init(): void {
        add_action( 'after_setup_theme', [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_init', [ __CLASS__, 'admin_actions' ] );
        add_action( 'admin_init', 'ptprm_maybe_repair_stored_option', 5 );
        add_action( 'admin_notices', [ __CLASS__, 'site_mismatch_notice' ] );
    }

    public static function register_menus(): void {
        register_nav_menus(
            [
                'ptprm-primary' => __( 'Menu Premium Plugin', 'ptsbi-premium' ),
            ]
        );
    }

    public static function site_fingerprint(): string {
        return md5( home_url( '/' ) . '|' . (string) get_bloginfo( 'name' ) );
    }

    /**
     * URL halaman pengaturan + cache-buster (hindari LiteSpeed/cache mengembalikan form admin lama).
     *
     * @param array<string, string> $args Query args.
     */
    public static function settings_admin_url( array $args = [] ): string {
        $args['page']    = 'ptprm-settings';
        $args['ptprm_v'] = defined( 'PTPRM_VERSION' ) ? PTPRM_VERSION : '1';
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Nama organisasi tampilan — selalu dari setting situs ini atau blogname.
     */
    public static function org_name(): string {
        $name = trim( (string) ptprm_get( 'org_name', '' ) );
        return $name !== '' ? $name : (string) get_bloginfo( 'name' );
    }

    /**
     * Defaults baru + isi dari WordPress (bukan PTSBI).
     */
    public static function seed_for_current_site( bool $overwrite_content = false ): array {
        $d     = ptprm_defaults();
        $blog  = (string) get_bloginfo( 'name' );
        $home  = home_url( '/' );

        $d['org_name']              = $blog ?: $d['org_name'];
        $d['_site_fingerprint']     = self::site_fingerprint();
        $d['hero_eyebrow_text']     = __( 'SELAMAT DATANG', 'ptsbi-premium' );
        $d['hero_title_text']       = sprintf(
            /* translators: %s: organization name */
            __( 'Website Resmi %s', 'ptsbi-premium' ),
            $blog ?: __( 'Organisasi', 'ptsbi-premium' )
        );
        $d['hero_sub_text']         = __( 'Informasi, kegiatan, dan kontak organisasi kami — silakan jelajahi situs ini.', 'ptsbi-premium' );
        $d['cta2_inquiry_default']  = sprintf(
            __( 'Halo, saya ingin informasi lebih lanjut tentang %s.', 'ptsbi-premium' ),
            $blog ?: __( 'organisasi ini', 'ptsbi-premium' )
        );
        $d['tarombo_app_url']       = '';
        $d['tarombo_cta_title']     = __( 'Aplikasi / layanan terkait', 'ptsbi-premium' );
        $d['tarombo_cta_lead']      = __( 'Kunjungi aplikasi atau layanan digital organisasi kami.', 'ptsbi-premium' );
        $d['tarombo_page_button_label'] = __( 'Buka aplikasi', 'ptsbi-premium' );
        $d['enable_tarombo_page_button'] = 0;
        $d['footer_col2_links']     = __( "Kontak|/kontak/\nTentang|/tentang-kami/", 'ptsbi-premium' );

        if ( ! $overwrite_content ) {
            return ptprm_normalize_option_for_storage( $d );
        }

        $existing = (array) get_option( PTPRM_OPTION, [] );
        return ptprm_normalize_option_for_storage(
            array_merge(
                $d,
                $existing,
                [
                    'org_name'                   => $d['org_name'],
                    '_site_fingerprint'          => $d['_site_fingerprint'],
                    'hero_eyebrow_text'          => $d['hero_eyebrow_text'],
                    'hero_title_text'            => $d['hero_title_text'],
                    'hero_sub_text'              => $d['hero_sub_text'],
                    'cta2_inquiry_default'       => $d['cta2_inquiry_default'],
                    'tarombo_app_url'            => '',
                    'tarombo_cta_title'          => $d['tarombo_cta_title'],
                    'tarombo_cta_lead'           => $d['tarombo_cta_lead'],
                    'enable_tarombo_page_button' => 0,
                ]
            )
        );
    }

    /**
     * Pulihkan akun, shortcode panel bidang, dan halaman organisasi (setelah gangguan / upgrade).
     */
    public static function repair_site(): void {
        if ( class_exists( 'PTPRM_Access' ) ) {
            PTPRM_Access::ensure_capabilities();
        }
        if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
            PTPRM_Default_Accounts::restore_all_accounts( true );
            update_option( PTPRM_Default_Accounts::OPTION_VERSION, PTPRM_Default_Accounts::ACCOUNTS_VERSION, false );
        }
        if ( class_exists( 'PTPRM_Bidang_Registry' ) ) {
            PTPRM_Bidang_Registry::restore_all_pages();
            PTPRM_Bidang_Registry::sync_bidang_user_meta();
        }
        if ( class_exists( 'PTPRM_Board_Registry' ) ) {
            PTPRM_Board_Registry::restore_region_pages();
        }
        if ( class_exists( 'PTPRM_Login_Portal' ) ) {
            PTPRM_Login_Portal::ensure_pages();
        }
        if ( class_exists( 'PTPRM_Member_Portal' ) ) {
            PTPRM_Member_Portal::ensure_pages();
        }
        if ( class_exists( 'PTPRM_Admin_Portal' ) ) {
            PTPRM_Admin_Portal::ensure_pages();
        }
        flush_rewrite_rules( false );
    }

    public static function maybe_repair_after_upgrade(): void {
        $last = (string) get_option( 'ptprm_site_repair_version', '' );
        if ( $last === PTPRM_VERSION ) {
            return;
        }
        self::repair_site();
        update_option( 'ptprm_site_repair_version', PTPRM_VERSION, false );
    }

    public static function activate(): void {
        $saved = get_option( PTPRM_OPTION );
        if ( false === $saved ) {
            add_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( ptprm_demo_options() ) );
        } else {
            update_option( PTPRM_OPTION, ptprm_normalize_option_for_storage( (array) $saved ), true );
        }
        if ( class_exists( 'PTPRM_Members' ) ) {
            PTPRM_Members::install();
        }
        if ( class_exists( 'PTPRM_Login_Portal' ) ) {
            PTPRM_Login_Portal::ensure_pages();
            update_option( 'ptprm_unified_login_v1', 1, false );
        }
        if ( class_exists( 'PTPRM_Member_Portal' ) ) {
            PTPRM_Member_Portal::ensure_pages();
        }
        if ( class_exists( 'PTPRM_Admin_Portal' ) ) {
            PTPRM_Admin_Portal::ensure_pages();
        }
        if ( class_exists( 'PTPRM_Board_Registry' ) ) {
            PTPRM_Board_Registry::maybe_seed_defaults();
            PTPRM_Board_Registry::ensure_region_pages();
        }
        if ( class_exists( 'PTPRM_Bidang_Registry' ) ) {
            PTPRM_Bidang_Registry::ensure_categories();
            PTPRM_Bidang_Registry::ensure_pages();
        }
        PTPRM_Access::ensure_capabilities();
        if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
            PTPRM_Default_Accounts::ensure_all();
            update_option( PTPRM_Default_Accounts::OPTION_VERSION, PTPRM_Default_Accounts::ACCOUNTS_VERSION, false );
        }
        flush_rewrite_rules();
    }

    public static function admin_actions(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['ptprm_sync_wp'] ) && '1' === $_GET['ptprm_sync_wp'] ) {
            check_admin_referer( 'ptprm_sync_wp' );
            update_option( PTPRM_OPTION, self::seed_for_current_site( true ), true );
            wp_cache_delete( PTPRM_OPTION, 'options' );
            set_transient( 'ptprm_admin_notice', __( 'Identitas disesuaikan dengan situs WordPress ini.', 'ptsbi-premium' ), 30 );
            wp_safe_redirect( self::settings_admin_url( [ 'ptprm_synced' => '1' ] ) );
            exit;
        }

        if ( isset( $_GET['ptprm_export_backup'] ) && '1' === $_GET['ptprm_export_backup'] ) {
            check_admin_referer( 'ptprm_export_backup' );
            $data = ptprm_normalize_option_for_storage( (array) get_option( PTPRM_OPTION, [] ) );
            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="ptprm-backup-' . gmdate( 'Y-m-d' ) . '.json"' );
            echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
            exit;
        }

        if ( isset( $_GET['ptprm_apply_demo'] ) && '1' === $_GET['ptprm_apply_demo'] ) {
            check_admin_referer( 'ptprm_apply_demo' );
            update_option( PTPRM_OPTION, ptprm_apply_demo_copywriting(), true );
            wp_cache_delete( PTPRM_OPTION, 'options' );
            wp_cache_delete( 'alloptions', 'options' );
            delete_transient( 'ptprm_storage_repaired_' . PTPRM_VERSION );
            set_transient( 'ptprm_admin_notice', __( 'Contoh copywriting diterapkan ke semua field teks. Gambar/logo yang sudah ada tidak dihapus.', 'ptsbi-premium' ), 30 );
            wp_safe_redirect( self::settings_admin_url( [ 'ptprm-demo-applied' => '1' ] ) );
            exit;
        }

        if ( isset( $_GET['ptprm_default_accounts'] ) && '1' === $_GET['ptprm_default_accounts'] ) {
            check_admin_referer( 'ptprm_default_accounts' );
            if ( class_exists( 'PTPRM_Default_Accounts' ) ) {
                PTPRM_Default_Accounts::restore_all_accounts( true );
                update_option( PTPRM_Default_Accounts::OPTION_VERSION, PTPRM_Default_Accounts::ACCOUNTS_VERSION, false );
                PTPRM_Default_Accounts::maybe_lock_demo_anggota();
            }
            set_transient( 'ptprm_admin_notice', __( 'Akun demo dibuat/diperbarui (ptprm-local, bidang, admin). Password sementara: 12345678.', 'ptsbi-premium' ), 30 );
            wp_safe_redirect( self::settings_admin_url( [ 'ptprm-default-accounts' => '1' ] ) );
            exit;
        }

        if ( isset( $_GET['ptprm_repair_site'] ) && '1' === $_GET['ptprm_repair_site'] ) {
            check_admin_referer( 'ptprm_repair_site' );
            self::repair_site();
            update_option( 'ptprm_site_repair_version', PTPRM_VERSION, false );
            set_transient(
                'ptprm_admin_notice',
                __( 'Perbaikan selesai: akun ptprm-local & bidang, halaman panel bidang, tombol Purge All, dan portal diperbarui. Password demo bidang: 12345678.', 'ptsbi-premium' ),
                45
            );
            wp_safe_redirect( self::settings_admin_url( [ 'ptprm-repair-site' => '1' ] ) );
            exit;
        }

        if ( isset( $_GET['ptprm_member_pages'] ) && '1' === $_GET['ptprm_member_pages'] ) {
            check_admin_referer( 'ptprm_member_pages' );
            if ( class_exists( 'PTPRM_Login_Portal' ) ) {
                PTPRM_Login_Portal::ensure_pages();
            }
            if ( class_exists( 'PTPRM_Member_Portal' ) ) {
                PTPRM_Member_Portal::ensure_pages();
            }
            if ( class_exists( 'PTPRM_Admin_Portal' ) ) {
                PTPRM_Admin_Portal::ensure_pages();
            }
            delete_option( 'ptprm_member_pages_v1' );
            delete_option( 'ptprm_admin_pages_v1' );
            delete_option( 'ptprm_unified_login_v1' );
            flush_rewrite_rules( false );
            set_transient( 'ptprm_admin_notice', __( 'Halaman portal anggota & pengurus siap dipakai.', 'ptsbi-premium' ), 30 );
            wp_safe_redirect( self::settings_admin_url( [ 'ptprm-member-pages' => '1' ] ) );
            exit;
        }

        if ( isset( $_GET['ptprm_create_pages'] ) && '1' === $_GET['ptprm_create_pages'] ) {
            check_admin_referer( 'ptprm_create_pages' );
            $stats = PTPRM_Pages::create_standard_pages( false );
            $msg   = sprintf(
                /* translators: 1: created count, 2: menu id */
                __( 'Halaman standar: %1$d baru dibuat. Menu WordPress ID %2$d (lokasi: Menu Premium Plugin).', 'ptsbi-premium' ),
                (int) $stats['created'],
                (int) $stats['menu_id']
            );
            if ( $stats['created'] === 0 ) {
                $msg = __( 'Halaman standar sudah ada. Menu utama disinkronkan.', 'ptsbi-premium' );
            }
            set_transient( 'ptprm_admin_notice', $msg, 30 );
            wp_safe_redirect( self::settings_admin_url( [ 'ptprm-pages-created' => '1' ] ) );
            exit;
        }

        $msg = get_transient( 'ptprm_admin_notice' );
        if ( $msg ) {
            delete_transient( 'ptprm_admin_notice' );
            add_action( 'admin_notices', static function () use ( $msg ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
            } );
        }
    }

    public static function site_mismatch_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'toplevel_page_ptprm-settings' !== $screen->id ) {
            return;
        }
        $o = ptprm_options();
        $fp = $o['_site_fingerprint'] ?? '';
        if ( ! $fp || $fp === self::site_fingerprint() ) {
            return;
        }
        $sync = wp_nonce_url( admin_url( 'admin.php?page=ptprm-settings&ptprm_sync_wp=1' ), 'ptprm_sync_wp' );
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'Pengaturan ini mungkin berasal dari situs/backup lain. Klik "Sesuaikan dengan situs ini" agar nama organisasi dan teks default mengikuti website saat ini.', 'ptsbi-premium' );
        echo ' <a class="button button-secondary" href="' . esc_url( $sync ) . '">' . esc_html__( 'Sesuaikan dengan situs ini', 'ptsbi-premium' ) . '</a>';
        echo '</p></div>';
    }

    /** Deteksi teks default PTSBI yang tertinggal setelah migrasi DB. */
    public static function has_legacy_ptsbi_copy( array $o ): bool {
        $needles = [ 'PTSBI', 'ptsbi.org', 'tarombo.ptsbi.org', 'PUNGUAN TOGA SAMOSIR', 'Horas. Saya minta' ];
        $hay     = wp_json_encode( $o );
        foreach ( $needles as $n ) {
            if ( stripos( $hay, $n ) !== false ) {
                return true;
            }
        }
        return false;
    }
}
