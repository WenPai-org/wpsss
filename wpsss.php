<?php
/**
 * Plugin Name: WPSSS
 * Plugin URI: https://wpcy.com/document/start-guide
 * Description: 文派智储。媒体进桶，网页仍从自己的服务器打开。支持国内云、S3 兼容与自定义 CDN 域名。
 * Version: 0.9.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: 文派
 * Author URI: https://wpcy.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpsss
 * Update URI: https://updates.wenpai.net
 */

if (!defined('ABSPATH')) {
    exit;
}

// 声明支持 PHP 7.4，str_* 三兄弟是 PHP 8 才有的；缺了它们媒体库行操作在 7.4 上致命。
if (PHP_VERSION_ID < 80000) {
    if (!function_exists('str_starts_with')) {
        function str_starts_with(string $haystack, string $needle): bool
        {
            return $needle === '' || strpos($haystack, $needle) === 0;
        }
    }
    if (!function_exists('str_ends_with')) {
        function str_ends_with(string $haystack, string $needle): bool
        {
            return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
        }
    }
    if (!function_exists('str_contains')) {
        function str_contains(string $haystack, string $needle): bool
        {
            return $needle === '' || strpos($haystack, $needle) !== false;
        }
    }
}

define('WPSSS_VERSION', '0.9.1');
define('WPSSS_FILE', __FILE__);
define('WPSSS_DIR', plugin_dir_path(__FILE__));
define('WPSSS_PRODUCT_URL', 'https://wpcy.com/');
define('WPSSS_DOCS_URL', 'https://wpcy.com/document');
define('WPSSS_SUPPORT_URL', 'https://wpcy.com/support/');

require_once WPSSS_DIR . 'lib/wenpai-admin-ui/php/load.php';
require_once WPSSS_DIR . 'includes/class-wpsss-oss-signer.php';
require_once WPSSS_DIR . 'includes/class-wpsss-oss-ram.php';
require_once WPSSS_DIR . 'includes/class-wpsss-cos-signer.php';
require_once WPSSS_DIR . 'includes/class-wpsss-kodo-signer.php';
require_once WPSSS_DIR . 'includes/class-wpsss-obs-signer.php';
require_once WPSSS_DIR . 'includes/class-wpsss-s3-signer.php';
require_once WPSSS_DIR . 'includes/class-wpsss-url.php';
require_once WPSSS_DIR . 'includes/class-wpsss-stream.php';
require_once WPSSS_DIR . 'includes/class-wpsss-copy.php';
require_once WPSSS_DIR . 'includes/class-wpsss-history.php';
require_once WPSSS_DIR . 'includes/class-wpsss-options.php';
require_once WPSSS_DIR . 'includes/class-wpsss-site.php';
require_once WPSSS_DIR . 'includes/class-wpsss-ci.php';
require_once WPSSS_DIR . 'includes/class-wpsss-bucket-info.php';
require_once WPSSS_DIR . 'includes/class-wpsss-driver.php';
require_once WPSSS_DIR . 'includes/drivers/class-wpsss-driver-aliyun-oss.php';
require_once WPSSS_DIR . 'includes/drivers/class-wpsss-driver-qcloud-cos.php';
require_once WPSSS_DIR . 'includes/drivers/class-wpsss-driver-qiniu-kodo.php';
require_once WPSSS_DIR . 'includes/drivers/class-wpsss-driver-huawei-obs.php';
require_once WPSSS_DIR . 'includes/drivers/class-wpsss-driver-s3-compat.php';
require_once WPSSS_DIR . 'includes/class-wpsss-offload.php';
require_once WPSSS_DIR . 'includes/class-wpsss-woo.php';
require_once WPSSS_DIR . 'includes/class-wpsss-library.php';
require_once WPSSS_DIR . 'includes/class-wpsss-ops.php';
require_once WPSSS_DIR . 'includes/class-wpsss-media.php';
require_once WPSSS_DIR . 'includes/class-wpsss-icons.php';
require_once WPSSS_DIR . 'includes/class-wpsss-admin.php';
require_once WPSSS_DIR . 'includes/class-wpsss-plugin.php';
require_once WPSSS_DIR . 'includes/class-wpsss-cli.php';

function wpsss(): Wpsss_Plugin
{
    return Wpsss_Plugin::instance();
}

/**
 * 第三方把文件放进 uploads 后，可调此函数进桶。
 *
 * @return true|WP_Error
 */
function wpsss_file_upload(string $absolute_path, string $mime = '')
{
    return wpsss()->offload()->put_local_file($absolute_path, $mime);
}

add_action('plugins_loaded', static function () {
    wpsss()->boot();
});

register_deactivation_hook(__FILE__, static function () {
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('wpsss_offload_cron_event');
    }
    if (function_exists('delete_transient')) {
        delete_transient('wpsss_offload_runlock');
        delete_transient('wpsss_offload_cursor');
    }
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sss', 'Wpsss_CLI');
}
