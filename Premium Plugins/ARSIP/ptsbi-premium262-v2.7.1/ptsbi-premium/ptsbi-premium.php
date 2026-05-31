<?php
/**
 * Plugin Name:       Premium Organization
 * Plugin URI:        https://wordpress.org/
 * Description:       Plugin premium untuk situs organisasi: Beranda lengkap (Hero, CTA, section, footer) dan sub-halaman seragam. Universal — pengaturan per website (tidak terkunci domain tertentu).
 * Version:           2.7.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Premium Plugins
 * License:           GPL-2.0-or-later
 * Text Domain:       ptsbi-premium
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PTPRM_VERSION', '2.7.1' );
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
require_once PTPRM_DIR . 'includes/class-address-regions.php';
require_once PTPRM_DIR . 'includes/class-access.php';
require_once PTPRM_DIR . 'includes/class-default-accounts.php';
require_once PTPRM_DIR . 'includes/class-login-portal.php';
require_once PTPRM_DIR . 'includes/class-members.php';
require_once PTPRM_DIR . 'includes/class-member-portal.php';
require_once PTPRM_DIR . 'includes/class-wilayah-board.php';
require_once PTPRM_DIR . 'includes/class-admin-portal.php';

register_activation_hook( __FILE__, [ 'PTPRM_Bootstrap', 'activate' ] );

add_action( 'plugins_loaded', function () {
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
    new PTPRM_Wilayah_Board();
    new PTPRM_Admin_Portal();
    if ( is_admin() ) {
        new PTPRM_Settings();
    }
} );
