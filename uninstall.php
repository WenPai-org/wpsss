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
    // 带用户 ID 的 wpsss_pull_* / wpsss_rewrite_* / wpsss_ops_* transient 无法按前缀枚举，
    // 有意留给过期兜底（最长 1 天）；不为此全表扫 _transient_ 行。
}

Wpsss_Options::uninstall_cleanup();
