<?php
/**
 * Plugin Name: KontenKit Core
 * Plugin URI: https://example.com/kontenkit-core
 * Description: Plugin premium pendamping tema blog & magazine — CPT, shortcode, SEO, performa artikel. Tanpa license key.
 * Version: 2.1.0
 * Author: Togaa
 * License: GPL v2 or later
 * Text Domain: kontenkit-core
 */

defined( 'ABSPATH' ) || exit;

define( 'KONTENKIT_CORE_VERSION', '2.1.0' );
define( 'KONTENKIT_CORE_FILE', __FILE__ );
define( 'KONTENKIT_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'KONTENKIT_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once KONTENKIT_CORE_DIR . 'includes/class-settings.php';
require_once KONTENKIT_CORE_DIR . 'includes/class-universal.php';
require_once KONTENKIT_CORE_DIR . 'includes/class-cpt.php';
require_once KONTENKIT_CORE_DIR . 'includes/class-post-meta.php';
require_once KONTENKIT_CORE_DIR . 'includes/class-shortcodes.php';
require_once KONTENKIT_CORE_DIR . 'includes/class-frontend.php';
require_once KONTENKIT_CORE_DIR . 'includes/class-seo.php';

final class KontenKit_Core {
	public static function init(): void {
		KontenKit_Settings::init();
		KontenKit_Universal::init();
		KontenKit_CPT::init();
		KontenKit_Post_Meta::init();
		KontenKit_Shortcodes::init();
		KontenKit_Frontend::init();
		KontenKit_SEO::init();
	}
}

add_action( 'plugins_loaded', [ 'KontenKit_Core', 'init' ] );

register_activation_hook( __FILE__, [ 'KontenKit_Settings', 'activate' ] );
