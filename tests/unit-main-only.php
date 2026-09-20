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
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->code = (string) $code;
            $this->message = (string) $message;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
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

function get_attached_file($post_id)
{
    return '';
}

function wp_upload_dir()
{
    return ['basedir' => '/tmp/wpsss-mo-uploads', 'baseurl' => 'https://local.example.com/wp-content/uploads'];
}

class Wpsss_Library
{
    public const META_OFFLOADED = '_wpsss_offloaded';
    public const META_KEY = '_wpsss_key';
    public const META_ERROR = '_wpsss_error';
    public const META_LOCAL_ONLY = '_wpsss_local_only';
    public const META_MAIN_ONLY = '_wpsss_main_only';
    public const META_FAIL_COUNT = '_wpsss_fail_count';

    public static function is_offloaded(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_OFFLOADED) === '1';
    }

    public static function is_main_only(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_MAIN_ONLY) === '1';
    }

    public static function set_main_only(int $post_id, bool $on): void
    {
        update_post_meta($post_id, self::META_MAIN_ONLY, $on ? '1' : '');
    }

    public static function fail_count(int $post_id): int
    {
        return (int) get_post_meta($post_id, self::META_FAIL_COUNT);
    }
}

if (!class_exists('Wpsss_History')) {
    class Wpsss_History
    {
        public static function add_fail($id, $msg)
        {
        }

        public static function add_offload($id, $key = '')
        {
        }
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-offload.php';

$GLOBALS['wpsss_options_store']['wpsss_options'] = [
    'driver' => 'aliyun-oss',
    'bucket' => 'demo',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'ak',
    'access_key_secret' => 'sk',
    'keep_local' => '1',
    'custom_domain' => 'https://img.example.com',
    'url_access' => 'public',
];

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

class Wpsss_Driver_MO_Stub
{
    public function is_configured($o)
    {
        return true;
    }
}

class Wpsss_Plugin
{
    public function active_driver()
    {
        return new Wpsss_Driver_MO_Stub();
    }
}

$offload = new Wpsss_Offload(new Wpsss_Plugin());

// ===== main_only：主文件 URL 照常改写，尺寸 URL 保持本地 =====
$GLOBALS['wpsss_mo_meta'] = [
    201 => [
        '_wpsss_offloaded' => '1',
        '_wpsss_main_only' => '1',
        '_wpsss_key' => '2026/09/hero.jpg',
    ],
];
$url = $offload->rewrite_url('https://local.example.com/wp-content/uploads/2026/09/hero.jpg', 201);
wpsss_assert(strpos($url, 'img.example.com/2026/09/hero.jpg') !== false, 'main_only keeps primary rewrite');

$url = $offload->rewrite_url('https://local.example.com/wp-content/uploads/2026/09/hero-300x200.jpg', 201);
wpsss_assert(strpos($url, 'local.example.com') !== false, 'main_only keeps size url local');

$url = $offload->rewrite_url('https://local.example.com/wp-content/uploads/2026/09/hero-1024x576.jpg', 201);
wpsss_assert(strpos($url, 'local.example.com') !== false, 'main_only keeps large size url local');

// 无标记时尺寸照旧改写
$GLOBALS['wpsss_mo_meta'][202] = [
    '_wpsss_offloaded' => '1',
    '_wpsss_key' => '2026/09/full.jpg',
];
$url = $offload->rewrite_url('https://local.example.com/wp-content/uploads/2026/09/full-300x200.jpg', 202);
wpsss_assert(strpos($url, 'img.example.com/2026/09/full-300x200.jpg') !== false, 'without flag sizes still rewrite');

// ===== 失败计数退避 =====
$GLOBALS['wpsss_mo_meta'][203] = [];
$offload->mark_failed(203, '第一次失败');
$offload->mark_failed(203, '第二次失败');
wpsss_assert(Wpsss_Library::fail_count(203) === 2, 'mark_failed counts up');
$offload->mark_offloaded(203, '2026/09/ok.jpg');
wpsss_assert(Wpsss_Library::fail_count(203) === 0, 'mark_offloaded resets counter');

// ===== 接线断言 =====
$src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-offload.php');
wpsss_assert(strpos($src, 'unique_filename_in_bucket') !== false, 'bucket dedupe hooked');
wpsss_assert(strpos($src, "add_filter('wp_unique_filename'") !== false, 'unique filename filter registered');
wpsss_assert(strpos($src, 'set_main_only') !== false, 'main_only marking wired');
$lib_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-library.php');
wpsss_assert(strpos($lib_src, 'FAIL_BACKOFF_THRESHOLD') !== false, 'backoff threshold exists');
wpsss_assert(strpos($lib_src, 'wpsss_stalled_notice') !== false, 'stall notice written');
$cli_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-cli.php');
wpsss_assert(strpos($cli_src, '--source=<which>') !== false, 'cli source flag documented');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
