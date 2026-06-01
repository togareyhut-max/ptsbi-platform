<?php
/**
 * Plugin Name:       Premium Organization
 * Plugin URI:        https://ptsbi.org/
 * Description:       Plugin organisasi PTSBI: beranda, portal anggota (pendaftaran, profil, import DAMI), panel pengurus, dan pengaturan situs — satu plugin lengkap.
 * Version:           3.0.8
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Premium Plugins
 * License:           GPL-2.0-or-later
 * Text Domain:       ptsbi-premium
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PTPRM_VERSION', '3.0.8' );
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
require_once PTPRM_DIR . 'includes/class-pdf-portal.php';
require_once PTPRM_DIR . 'includes/class-address-regions.php';
require_once PTPRM_DIR . 'includes/class-access.php';
require_once PTPRM_DIR . 'includes/class-default-accounts.php';
require_once PTPRM_DIR . 'includes/class-login-portal.php';
require_once PTPRM_DIR . 'includes/class-members.php';
require_once PTPRM_DIR . 'includes/class-member-portal.php';
require_once PTPRM_DIR . 'includes/class-members-admin.php';
require_once PTPRM_DIR . 'includes/class-admin-portal.php';
require_once PTPRM_DIR . 'includes/class-pdf-lightbox.php';

register_activation_hook( __FILE__, [ 'PTPRM_Bootstrap', 'activate' ] );

add_action(
    'plugins_loaded',
    static function (): void {
        PTPRM_Bootstrap::init();
        PTPRM_Access::init();
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
        new PTPRM_Pdf_Portal();
        new PTPRM_Pdf_Lightbox();
        if ( is_admin() ) {
            new PTPRM_Settings();
        }
    }
);
