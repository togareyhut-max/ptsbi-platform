<?php
/**
 * Pemuatan aman modul anggota (hindari bentrok dengan ptsbi-premium 2.7.x yang masih embed class yang sama).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Modul anggota sudah dimuat dari ptsbi-premium lama (2.7.x embed).
 */
function ptsmb_members_already_loaded(): bool {
    return class_exists( 'PTPRM_Members', false );
}

/**
 * @return string
 */
function ptsmb_embedded_premium_version(): string {
    return defined( 'PTPRM_VERSION' ) ? (string) PTPRM_VERSION : '';
}

/**
 * @return bool True jika class anggota siap dipakai.
 */
function ptsmb_members_ready(): bool {
    return class_exists( 'PTPRM_Members', false );
}

/**
 * Muat file include modul anggota (sekali). Return false jika premium lama sudah embed.
 *
 * @return bool
 */
function ptsmb_load_includes(): bool {
    static $loaded = false;

    if ( ptsmb_members_already_loaded() ) {
        return false;
    }

    if ( $loaded ) {
        return true;
    }

    require_once PTSMB_DIR . 'includes/helpers-compat.php';

    if ( ! class_exists( 'PTPRM_Access', false ) ) {
        require_once PTSMB_DIR . 'includes/class-access-fallback.php';
    }

    require_once PTSMB_DIR . 'includes/class-membership-api-client.php';
    require_once PTSMB_DIR . 'includes/class-address-regions.php';
    require_once PTSMB_DIR . 'includes/class-default-accounts.php';
    require_once PTSMB_DIR . 'includes/class-members.php';
    require_once PTSMB_DIR . 'includes/class-login-portal.php';
    require_once PTSMB_DIR . 'includes/class-member-portal.php';
    require_once PTSMB_DIR . 'includes/class-members-admin.php';
    require_once PTSMB_DIR . 'includes/class-access-members.php';
    require_once PTSMB_DIR . 'includes/class-assets.php';

    $loaded = true;
    return true;
}

/**
 * Inisialisasi hook modul anggota.
 */
function ptsmb_init_plugin(): void {
    if ( ! ptsmb_load_includes() ) {
        return;
    }

    if ( ! defined( 'PTPRM_VERSION' ) && class_exists( 'PTPRM_Access', false ) ) {
        PTPRM_Access::init();
    }

    PTPRM_Access_Members::ensure_capabilities();
    PTPRM_Address_Regions::init();
    PTPRM_Default_Accounts::init();
    new PTPRM_Login_Portal();
    new PTPRM_Members();
    new PTPRM_Member_Portal();
    new PTPRM_Members_Admin();
    new PTSMB_Assets();
}

/**
 * Aktivasi DB / rewrite — aman jika class sudah ada di premium embed.
 */
function ptsmb_activate_plugin(): void {
    if ( ptsmb_members_ready() ) {
        PTPRM_Members::install();
        flush_rewrite_rules();
        return;
    }

    if ( ! ptsmb_load_includes() || ! class_exists( 'PTPRM_Members', false ) ) {
        return;
    }

    PTPRM_Members::install();
    flush_rewrite_rules();
}

/**
 * Peringatan admin saat tidak bisa memuat (premium 2.7.x embed).
 */
function ptsmb_compat_admin_notice(): void {
    if ( ! is_admin() || ! ptsmb_members_already_loaded() ) {
        return;
    }

    $ver = ptsmb_embedded_premium_version();
    $msg = __( 'Plugin PTSBI Members tidak memuat ulang modul anggota karena class yang sama sudah ada di Premium Organization.', 'ptsbi-members' );

    if ( $ver !== '' && version_compare( $ver, '3.0', '<' ) ) {
        $msg = sprintf(
            /* translators: %s: ptsbi-premium version e.g. 2.7.1 */
            __( 'PTSBI Members tidak kompatibel dengan Premium Organization %s (modul anggota masih di dalam premium). Pilih salah satu: (1) gunakan hanya premium 2.7.x tanpa plugin ini, atau (2) upgrade premium ke 3.0.3+ lalu aktifkan PTSBI Members.', 'ptsbi-members' ),
            $ver
        );
    } elseif ( $ver === '' || version_compare( $ver, '3.0', '>=' ) ) {
        $msg = __( 'PTSBI Members: modul anggota sudah dimuat dari salinan ptsbi-premium lain. Pastikan hanya satu folder ptsbi-premium di wp-content/plugins dan premium versi 3.0.3+ tanpa file class-members.php di dalamnya.', 'ptsbi-members' );
    }

    echo '<div class="notice notice-warning"><p>' . esc_html( $msg ) . '</p></div>';
}
