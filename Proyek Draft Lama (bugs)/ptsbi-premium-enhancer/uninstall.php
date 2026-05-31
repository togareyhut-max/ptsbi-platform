<?php
/**
 * Uninstall handler - remove plugin options when user deletes plugin.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'ptsbi_pe_options' );
