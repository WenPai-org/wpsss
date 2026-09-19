<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-wpsss-site.php';
require_once __DIR__ . '/includes/class-wpsss-options.php';

Wpsss_Options::uninstall_cleanup();
