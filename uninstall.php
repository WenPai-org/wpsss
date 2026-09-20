<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-wpsss-site.php';
require_once __DIR__ . '/includes/class-wpsss-options.php';

if (function_exists('wp_clear_scheduled_hook')) {
    wp_clear_scheduled_hook('wpsss_offload_cron_event');
}
if (function_exists('delete_transient')) {
    delete_transient('wpsss_offload_runlock');
    delete_transient('wpsss_offload_cursor');
}

Wpsss_Options::uninstall_cleanup();
