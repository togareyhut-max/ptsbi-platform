<?php
/**
 * Plugin Name:       PTSBI Members (digabung)
 * Description:       Modul anggota sudah termasuk di Premium Organization v3.0.1. Hapus plugin ini dari server — cukup aktifkan ptsbi-premium saja.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Premium Plugins
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'admin_init',
    static function (): void {
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
);

add_action(
    'admin_notices',
    static function (): void {
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo esc_html__( 'PTSBI Members sudah digabung ke plugin Premium Organization v3.0.1. Hapus folder ptsbi-members dari wp-content/plugins dan gunakan hanya ptsbi-premium.', 'ptsbi-premium' );
        echo '</p></div>';
    }
);
