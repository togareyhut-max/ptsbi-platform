<?php
/**
 * Plugin Name:       Premium Organization
 * Plugin URI:        https://ptsbi.org/
 * Description:       Plugin organisasi PTSBI: beranda, portal anggota (pendaftaran, profil, import DAMI), panel pengurus, dan pengaturan situs — satu plugin lengkap.
 * Version:           3.2.21
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Premium Plugins
 * License:           GPL-2.0-or-later
 * Text Domain:       ptsbi-premium
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PTPRM_VERSION', '3.2.21' );
define( 'PTPRM_FILE',    __FILE__ );
define( 'PTPRM_DIR',     plugin_dir_path( __FILE__ ) );
define( 'PTPRM_URL',     plugin_dir_url( __FILE__ ) );
define( 'PTPRM_OPTION',  'ptprm_options' );

require_once PTPRM_DIR . 'includes/helpers.php';
require_once PTPRM_DIR . 'includes/class-bootstrap.php';
require_once PTPRM_DIR . 'includes/class-pages.php';
require_once PTPRM_DIR . 'includes/demo-content.php';
require_once PTPRM_DIR . 'includes/class-assets.php';
require_once PTPRM_DIR . 'includes/class-renderer.php';
require_once PTPRM_DIR . 'includes/class-home.php';
require_once PTPRM_DIR . 'includes/class-subpage.php';
require_once PTPRM_DIR . 'includes/class-site.php';
require_once PTPRM_DIR . 'includes/class-header.php';
require_once PTPRM_DIR . 'includes/class-settings.php';
require_once PTPRM_DIR . 'includes/class-membership-api-client.php';
require_once PTPRM_DIR . 'includes/class-membership-sync.php';
require_once PTPRM_DIR . 'includes/class-address-regions.php';
require_once PTPRM_DIR . 'includes/class-access.php';
require_once PTPRM_DIR . 'includes/class-portal-session.php';
require_once PTPRM_DIR . 'includes/class-default-accounts.php';
require_once PTPRM_DIR . 'includes/class-login-portal.php';
require_once PTPRM_DIR . 'includes/class-members.php';
require_once PTPRM_DIR . 'includes/class-member-portal.php';
require_once PTPRM_DIR . 'includes/class-members-admin.php';
require_once PTPRM_DIR . 'includes/class-admin-portal.php';
require_once PTPRM_DIR . 'includes/class-cache-purge.php';
require_once PTPRM_DIR . 'includes/class-pdf-portal.php';
require_once PTPRM_DIR . 'includes/class-pdf-lightbox.php';
require_once PTPRM_DIR . 'includes/class-board-registry.php';
require_once PTPRM_DIR . 'includes/class-bidang-registry.php';
require_once PTPRM_DIR . 'includes/class-board-display.php';
require_once PTPRM_DIR . 'includes/class-board-admin.php';
require_once PTPRM_DIR . 'includes/class-bidang-display.php';
require_once PTPRM_DIR . 'includes/class-bidang-portal.php';
require_once PTPRM_DIR . 'includes/class-popup-portal.php';

register_activation_hook( __FILE__, [ 'PTPRM_Bootstrap', 'activate' ] );

add_action(
    'plugins_loaded',
    static function (): void {
        PTPRM_Bootstrap::init();
        PTPRM_Access::init();
        PTPRM_Portal_Session::init();
        PTPRM_Board_Registry::init();
        PTPRM_Bidang_Registry::init();
        PTPRM_Address_Regions::init();
        PTPRM_Default_Accounts::init();
        new PTPRM_Login_Portal();
        new PTPRM_Assets();
        new PTPRM_Home();
        new PTPRM_Subpage();
        new PTPRM_Site();
        new PTPRM_Header();
        new PTPRM_Members();
        new PTPRM_Member_Portal();
        new PTPRM_Members_Admin();
        new PTPRM_Admin_Portal();
        new PTPRM_Cache_Purge();
        new PTPRM_Board_Display();
        new PTPRM_Board_Admin();
        new PTPRM_Bidang_Display();
        new PTPRM_Bidang_Portal();
        new PTPRM_Popup_Portal();
        new PTPRM_Pdf_Portal();
        new PTPRM_Pdf_Lightbox();
        add_action( 'init', 'ptprm_maybe_setup_publications_page', 25 );
        if ( is_admin() ) {
            new PTPRM_Settings();
        }
    }
);

/**
 * Sekali per versi: halaman Publikasi & Dokumentasi + item menu.
 */
function ptprm_maybe_setup_publications_page(): void {
    if ( ! class_exists( 'PTPRM_Pages' ) ) {
        return;
    }
    $flag = 'ptprm_publications_setup_' . PTPRM_VERSION;
    if ( get_option( $flag ) ) {
        return;
    }
    PTPRM_Pages::setup_publications_front();
    update_option( $flag, 1, false );
}
