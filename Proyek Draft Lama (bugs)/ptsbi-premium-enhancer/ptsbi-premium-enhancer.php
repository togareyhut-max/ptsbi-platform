<?php
/**
 * Plugin Name:       Section Studio — Elementor Companion
 * Plugin URI:        https://ptsbi.org/
 * Description:       Companion Elementor dengan widget drag & drop (Nilai-Nilai, Kunjungi Kami, Footer Premium) plus panel Section Studio untuk warna, CTA, dan perbaikan otomatis beranda.
 * Version:           2.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Section Studio
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ptsbi-premium-enhancer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PTSBI_PE_VERSION', '2.2.0' );
define( 'PTSBI_PE_FILE', __FILE__ );
define( 'PTSBI_PE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTSBI_PE_URL', plugin_dir_url( __FILE__ ) );
define( 'PTSBI_PE_OPTION', 'ptsbi_pe_options' );

require_once PTSBI_PE_DIR . 'includes/helpers.php';
require_once PTSBI_PE_DIR . 'includes/class-css-builder.php';
require_once PTSBI_PE_DIR . 'includes/class-text-replacer.php';
require_once PTSBI_PE_DIR . 'includes/class-settings.php';
require_once PTSBI_PE_DIR . 'includes/class-assets.php';
require_once PTSBI_PE_DIR . 'includes/class-frontend.php';
require_once PTSBI_PE_DIR . 'includes/class-elementor.php';
require_once PTSBI_PE_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'PTSBI_PE_Plugin', 'on_activate' ] );
register_deactivation_hook( __FILE__, [ 'PTSBI_PE_Plugin', 'on_deactivate' ] );

add_action( 'plugins_loaded', function () {
    PTSBI_PE_Plugin::instance();
} );
