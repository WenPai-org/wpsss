<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $code;
        private $message;
        public function __construct($code = '', $message = '') { $this->code = (string) $code; $this->message = (string) $message; }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
}

$GLOBALS['wpsss_defer_meta'] = [];
$GLOBALS['wpsss_defer_puts'] = [];
$GLOBALS['wpsss_defer_fail_keys'] = [];

function get_post_meta($post_id, $key = '', $single = false)
{
    return $GLOBALS['wpsss_defer_meta'][(int) $post_id][$key] ?? '';
}
function update_post_meta($post_id, $key, $value)
{
    $GLOBALS['wpsss_defer_meta'][(int) $post_id][$key] = $value;
    return true;
}
function delete_post_meta($post_id, $key)
{
    unset($GLOBALS['wpsss_defer_meta'][(int) $post_id][$key]);
    return true;
}
function get_attached_file($post_id)
{
    return '';
}
function wp_upload_dir()
{
    return ['basedir' => '/tmp/wpsss-defer-uploads', 'baseurl' => 'https://local.example.com/wp-content/uploads'];
}
function wp_check_filetype($path)
{
    return ['type' => str_ends_with($path, '.webp') ? 'image/webp' : 'application/octet-stream'];
}
function wp_normalize_path($path)
{
    return str_replace('\\', '/', (string) $path);
}
if (!function_exists('error_log')) {
    function error_log($msg) {}
}

class Wpsss_Library
{
    public const META_OFFLOADED = '_wpsss_offloaded';
    public const META_KEY = '_wpsss_key';
    public const META_ERROR = '_wpsss_error';
    public const META_LOCAL_ONLY = '_wpsss_local_only';
    public const META_MAIN_ONLY = '_wpsss_main_only';
    public const META_FAIL_COUNT = '_wpsss_fail_count';
    public const META_THUMBS_PENDING = '_wpsss_thumbs_pending';

    public static function set_main_only(int $post_id, bool $on): void
    {
        update_post_meta($post_id, self::META_MAIN_ONLY, $on ? '1' : '');
    }

    public static function arm_offload_cron(): void {}
}

class Wpsss_History
{
    public static function add_offload($id, $key = '') {}
    public static function add_fail($id, $msg) {}
}

$GLOBALS['wpsss_options_store']['wpsss_options'] = [
    'driver' => 'aliyun-oss',
    'bucket' => 'demo',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'ak',
    'access_key_secret' => 'sk',
    'keep_local' => '1',
    'custom_domain' => '',
    'url_access' => 'public',
    'upload_thumbs' => '1',
];

class Wpsss_Driver_Defer
{
    public function is_configured($o) { return true; }
    public function put_object($opts, $key, $path, $mime, $acl = 'public-read')
    {
        if (in_array($key, $GLOBALS['wpsss_defer_fail_keys'], true)) {
            return new WP_Error('wpsss_probe_fail', 'simulated put failure');
        }
        $GLOBALS['wpsss_defer_puts'][] = $key;
        return true;
    }
    public function head_object($opts, $key) { return new WP_Error('wpsss_404', 'not found'); }
}

class Wpsss_Plugin
{
    public function active_driver() { return new Wpsss_Driver_Defer(); }
}

require dirname(__DIR__) . '/includes/class-wpsss-offload.php';

$failed = 0;
function wpsss_assert(bool $ok, string $label): void
{
    global $failed;
    if ($ok) { echo "ok  $label\n"; return; }
    $failed++;
    echo "FAIL  $label\n";
}

function defer_reset_meta(int $id): void
{
    $GLOBALS['wpsss_defer_meta'][$id] = [];
    $GLOBALS['wpsss_defer_puts'] = [];
}

// 准备本地文件：主图 + 一个尺寸文件（都在 uploads basedir 下）
@mkdir('/tmp/wpsss-defer-uploads/2026/09', 0777, true);
file_put_contents('/tmp/wpsss-defer-uploads/2026/09/photo.webp', 'primary');
file_put_contents('/tmp/wpsss-defer-uploads/2026/09/photo-150x150.webp', 'thumb');

$offload = new Wpsss_Offload(new Wpsss_Plugin());
$metadata = [
    'file' => '2026/09/photo.webp',
    'sizes' => ['thumbnail' => ['file' => 'photo-150x150.webp', 'mime-type' => 'image/webp']],
];

// ===== 新上传（keep_local=1）：只传主图，尺寸延后 =====
defer_reset_meta(501);
$offload->on_generate_metadata($metadata, 501);
$m = $GLOBALS['wpsss_defer_meta'][501];
$primary_key = '2026/09/photo.webp';
$thumb_key = '2026/09/photo-150x150.webp';
wpsss_assert(in_array($primary_key, $GLOBALS['wpsss_defer_puts'], true), 'fresh upload puts primary');
wpsss_assert(!in_array($thumb_key, $GLOBALS['wpsss_defer_puts'], true), 'fresh upload defers thumbnail');
wpsss_assert(($m['_wpsss_offloaded'] ?? '') === '1', 'fresh upload marks offloaded');
wpsss_assert(($m['_wpsss_thumbs_pending'] ?? '') === '1', 'fresh upload sets thumbs_pending');
wpsss_assert(($m['_wpsss_main_only'] ?? '') === '1', 'fresh upload sets main_only');

// ===== 队列补传：尺寸进桶，两个标记都摘掉 =====
$GLOBALS['wpsss_defer_puts'] = [];
$offload->on_generate_metadata($metadata, 501);
$m = $GLOBALS['wpsss_defer_meta'][501];
wpsss_assert(in_array($thumb_key, $GLOBALS['wpsss_defer_puts'], true), 'queue pass uploads thumbnail');
wpsss_assert(!isset($m['_wpsss_thumbs_pending']), 'queue pass clears thumbs_pending');
wpsss_assert(($m['_wpsss_main_only'] ?? '') !== '1', 'queue pass clears main_only');

// ===== 队列补传失败：标记保留，尺寸地址保持本地 =====
defer_reset_meta(502);
$GLOBALS['wpsss_defer_meta'][502] = ['_wpsss_offloaded' => '1', '_wpsss_main_only' => '1', '_wpsss_thumbs_pending' => '1'];
$GLOBALS['wpsss_defer_fail_keys'] = [$thumb_key];
$offload->on_generate_metadata($metadata, 502);
$m = $GLOBALS['wpsss_defer_meta'][502];
wpsss_assert(($m['_wpsss_thumbs_pending'] ?? '') === '1', 'failed queue pass keeps thumbs_pending');
wpsss_assert(($m['_wpsss_main_only'] ?? '') === '1', 'failed queue pass keeps main_only');

// ===== keep_local=0：全部同步进桶，不走延后 =====
$GLOBALS['wpsss_options_store']['wpsss_options']['keep_local'] = '0';
defer_reset_meta(503);
$GLOBALS['wpsss_defer_puts'] = [];
$GLOBALS['wpsss_defer_fail_keys'] = [];
$offload->on_generate_metadata($metadata, 503);
$m = $GLOBALS['wpsss_defer_meta'][503];
wpsss_assert(in_array($primary_key, $GLOBALS['wpsss_defer_puts'], true), 'keep_local=0 puts primary');
wpsss_assert(in_array($thumb_key, $GLOBALS['wpsss_defer_puts'], true), 'keep_local=0 puts thumbnail sync');
wpsss_assert(!isset($m['_wpsss_thumbs_pending']), 'keep_local=0 never defers');
$GLOBALS['wpsss_options_store']['wpsss_options']['keep_local'] = '1';

// ===== 接线断言 =====
$lib_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-library.php');
wpsss_assert(strpos($lib_src, 'META_THUMBS_PENDING') !== false, 'library defines thumbs_pending');
wpsss_assert(strpos($lib_src, 'thumbs_pending') !== false && strpos($lib_src, 'arm_offload_cron') !== false, 'cron gate counts deferred items');

if ($failed > 0) {
    echo "$failed failed\n";
    exit(1);
}
echo "all passed\n";
