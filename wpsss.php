<?php
/**
 * Plugin Name: WPSSS
 * Plugin URI: https://feicode.com/WenPai-org/wpsss
 * Description: 文派对象存储。一个插件、多个 driver，先做阿里云 OSS；自定义域名接 CyberCDN。
 * Version: 0.3.0-dev
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: 文派
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: wpsss
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPSSS_VERSION', '0.3.0-dev');
define('WPSSS_FILE', __FILE__);
define('WPSSS_DIR', plugin_dir_path(__FILE__));

require_once WPSSS_DIR . 'includes/class-wpsss-oss-signer.php';
require_once WPSSS_DIR . 'includes/class-wpsss-options.php';
require_once WPSSS_DIR . 'includes/class-wpsss-driver.php';
require_once WPSSS_DIR . 'includes/drivers/class-wpsss-driver-aliyun-oss.php';
require_once WPSSS_DIR . 'includes/class-wpsss-offload.php';
require_once WPSSS_DIR . 'includes/class-wpsss-icons.php';
require_once WPSSS_DIR . 'includes/class-wpsss-admin.php';
require_once WPSSS_DIR . 'includes/class-wpsss-plugin.php';
require_once WPSSS_DIR . 'includes/class-wpsss-cli.php';

function wpsss(): Wpsss_Plugin
{
    return Wpsss_Plugin::instance();
}

add_action('plugins_loaded', static function () {
    wpsss()->boot();
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sss', 'Wpsss_CLI');
}
