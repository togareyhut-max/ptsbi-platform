<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('ptsbi_premium_settings');
delete_transient('ptsbi_premium_activated');
