<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-url.php';

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $code;

        public function __construct($code = '', $message = '')
        {
            $this->code = (string) $code;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return '';
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

$GLOBALS['wpsss_mo_meta'] = [];
$GLOBALS['wpsss_to_basedir'] = sys_get_temp_dir() . '/wpsss-takeover-uploads';
@mkdir($GLOBALS['wpsss_to_basedir'] . '/2026/09', 0777, true);

function get_post_meta($post_id, $key = '', $single = false)
{
    return $GLOBALS['wpsss_mo_meta'][(int) $post_id][$key] ?? '';
}

function update_post_meta($post_id, $key, $value)
{
    $GLOBALS['wpsss_mo_meta'][(int) $post_id][$key] = $value;
    return true;
}

function delete_post_meta($post_id, $key)
{
    unset($GLOBALS['wpsss_mo_meta'][(int) $post_id][$key]);
    return true;
}

function wp_upload_dir()
{
    return ['basedir' => $GLOBALS['wpsss_to_basedir'], 'baseurl' => 'https://local.example.com/wp-content/uploads'];
}

if (!class_exists('Wpsss_History')) {
    class Wpsss_History
    {
        public static function add_fail($id, $msg)
        {
        }
    }
}

class Wpsss_Library
{
    public const META_OFFLOADED = '_wpsss_offloaded';
    public const META_KEY = '_wpsss_key';

    public static function is_offloaded(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_OFFLOADED) === '1';
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-offload.php';

$failed = 0;
function wpsss_assert(bool $ok, string $label): void
{
    global $failed;
    if ($ok) {
        echo "ok  $label\n";
        return;
    }
    $failed++;
    echo "FAIL  $label\n";
}

/**
 * 桶里已有这些对象键（head 200 的白名单）；其它一律 404。
 */
$GLOBALS['wpsss_bucket_keys'] = [];
class Wpsss_Driver_Takeover_Stub
{
    public function is_configured($o)
    {
        return true;
    }

    public function head_object($opts, $key)
    {
        return in_array($key, $GLOBALS['wpsss_bucket_keys'], true) ? true : new WP_Error('404', 'NoSuchKey');
    }
}

class Wpsss_Plugin
{
    public function active_driver()
    {
        return new Wpsss_Driver_Takeover_Stub();
    }
}

function to_opts(array $over = []): array
{
    $GLOBALS['wpsss_options_store']['wpsss_options'] = array_merge([
        'driver' => 'aliyun-oss',
        'bucket' => 'demo',
        'region' => 'oss-cn-hangzhou',
        'access_key_id' => 'ak',
        'access_key_secret' => 'sk',
        'keep_local' => '0',
        'custom_domain' => 'https://img.example.com',
        'url_access' => 'public',
        'object_prefix' => '',
    ], $over);
    return Wpsss_Options::get();
}

$offload = new Wpsss_Offload(new Wpsss_Plugin());
$dir = $GLOBALS['wpsss_to_basedir'];

// ===== unique_filename_in_bucket =====
to_opts(['keep_local' => '0']);

// 桶里没有同名 → 原名返回
$GLOBALS['wpsss_bucket_keys'] = [];
wpsss_assert($offload->unique_filename_in_bucket('report.pdf', $dir . '/2026/09') === 'report.pdf', 'dedupe keeps name when bucket is free');

// 桶里有同名 → 加 -1
$GLOBALS['wpsss_bucket_keys'] = ['2026/09/report.pdf'];
wpsss_assert($offload->unique_filename_in_bucket('report.pdf', $dir . '/2026/09') === 'report-1.pdf', 'dedupe appends -1 on first clash');

// -1 也在 → -2
$GLOBALS['wpsss_bucket_keys'] = ['2026/09/report.pdf', '2026/09/report-1.pdf'];
wpsss_assert($offload->unique_filename_in_buffer_dummy ?? $offload->unique_filename_in_bucket('report.pdf', $dir . '/2026/09') === 'report-2.pdf', 'dedupe walks to -2');

// 本地文件还在 → 直接原名（本地查重有效，不问桶）
$local = $dir . '/2026/09/local-exists.txt';
file_put_contents($local, 'x');
$GLOBALS['wpsss_bucket_keys'] = ['2026/09/local-exists.txt'];
wpsss_assert($offload->unique_filename_in_bucket('local-exists.txt', $dir . '/2026/09') === 'local-exists.txt', 'local file short-circuits bucket check');
@unlink($local);

// keep_local=1 → 不问桶
to_opts(['keep_local' => '1']);
$GLOBALS['wpsss_bucket_keys'] = ['2026/09/report.pdf'];
wpsss_assert($offload->unique_filename_in_bucket('report.pdf', $dir . '/2026/09') === 'report.pdf', 'keep_local on skips bucket check');

// ===== filter_attached_file =====
to_opts(['keep_local' => '0', 'url_access' => 'public']);
$missing = $dir . '/2026/09/gone.jpg'; // 不创建 → file_exists false
$GLOBALS['wpsss_mo_meta'] = [
    301 => [
        '_wpsss_offloaded' => '1',
        '_wpsss_key' => '2026/09/gone.jpg',
    ],
];
$out = $offload->filter_attached_file($missing, 301);
wpsss_assert(strpos((string) $out, 'img.example.com/2026/09/gone.jpg') !== false, 'missing local + offloaded returns bucket url');

// 本地文件在 → 原路径
$present = $dir . '/2026/09/here.jpg';
file_put_contents($present, 'x');
$GLOBALS['wpsss_mo_meta'][302] = ['_wpsss_offloaded' => '1', '_wpsss_key' => '2026/09/here.jpg'];
wpsss_assert($offload->filter_attached_file($present, 302) === $present, 'local present returns path unchanged');
@unlink($present);

// 未进桶 → 原路径
$GLOBALS['wpsss_mo_meta'][303] = [];
wpsss_assert($offload->filter_attached_file($missing, 303) === $missing, 'not offloaded returns path unchanged');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
