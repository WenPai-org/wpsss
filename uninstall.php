<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$owns = get_option('wpsss_owns_upload_url_path');
if ($owns === '1') {
    delete_option('upload_url_path');
    delete_option('wpsss_owns_upload_url_path');
}
delete_option('wpsss_options');
