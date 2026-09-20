<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-url.php';
require dirname(__DIR__) . '/includes/class-wpsss-offload.php';

if (!class_exists('WP_Error')) {
    class WP_Error
    {
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

// 媒体库画像：相对路径 => 附件 ID；offloaded 集合由 meta 桩给出。
$GLOBALS['wpsss_attached_file_map'] = [
    '2026/09/hero.jpg' => 101,
    '2024/11/old-pic.png' => 55,
];
$GLOBALS['wpsss_offloaded_ids'] = [101];

function get_posts($args = [])
{
    if (($args['meta_key'] ?? '') !== '_wp_attached_file') {
        return [];
    }
    $id = $GLOBALS['wpsss_attached_file_map'][$args['meta_value'] ?? ''] ?? 0;
    return $id > 0 ? [$id] : [];
}

function get_post_meta($post_id, $key = '', $single = false)
{
    if ($key === '_wpsss_offloaded') {
        return in_array((int) $post_id, $GLOBALS['wpsss_offloaded_ids'], true) ? '1' : '';
    }
    return $single ? '' : [];
}

function wp_upload_dir()
{
    return ['basedir' => '/tmp/wpsss-render-uploads', 'baseurl' => 'https://local.example.com/wp-content/uploads'];
}

class Wpsss_Driver_Render_Stub
{
    public function is_configured($opts)
    {
        return true;
    }
}

class Wpsss_Plugin
{
    public function active_driver()
    {
        return new Wpsss_Driver_Render_Stub();
    }
}

class Wpsss_Library
{
    public static function is_offloaded(int $post_id): bool
    {
        return in_array($post_id, $GLOBALS['wpsss_offloaded_ids'], true);
    }
}

$GLOBALS['wpsss_options_store']['wpsss_options'] = [
    'driver' => 'aliyun-oss',
    'bucket' => 'demo',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'ak',
    'access_key_secret' => 'sk',
    'keep_local' => '1',
    'custom_domain' => 'https://img.example.com',
    'url_access' => 'public',
    'rewrite_from' => '',
    'image_style' => '',
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

$offload = new Wpsss_Offload(new Wpsss_Plugin());

// 已进桶附件的硬编码本地地址 → 桶地址
$in = '<img src="https://local.example.com/wp-content/uploads/2026/09/hero.jpg" /> 外链文字';
$out = $offload->rewrite_content($in);
wpsss_assert(strpos($out, 'https://img.example.com/2026/09/hero.jpg') !== false, 'offloaded hardcoded url rewritten');
wpsss_assert(strpos($out, 'local.example.com') === false, 'no local host left for offloaded file');

// 未进桶附件 → 保持本地
$in = '<img src="https://local.example.com/wp-content/uploads/2024/11/old-pic.png" />';
$out = $offload->rewrite_content($in);
wpsss_assert(strpos($out, 'local.example.com') !== false, 'non-offloaded url stays local');

// 尺寸文件回落到原图判定
$in = '<img src="https://local.example.com/wp-content/uploads/2026/09/hero-1024x576.jpg" />';
$out = $offload->rewrite_content($in);
wpsss_assert(strpos($out, 'img.example.com/2026/09/hero-1024x576.jpg') !== false, 'size variant resolves via parent');

// 查询串保留
$in = '<img src="https://local.example.com/wp-content/uploads/2026/09/hero.jpg?v=3" />';
$out = $offload->rewrite_content($in);
wpsss_assert(strpos($out, 'img.example.com/2026/09/hero.jpg?v=3') !== false, 'query string preserved');

// srcset 多地址混合
$in = 'srcset="https://local.example.com/wp-content/uploads/2026/09/hero.jpg 1920w, https://local.example.com/wp-content/uploads/2024/11/old-pic.png 800w"';
$out = $offload->rewrite_content($in);
wpsss_assert(strpos($out, 'img.example.com/2026/09/hero.jpg 1920w') !== false, 'srcset offloaded entry rewritten');
wpsss_assert(strpos($out, 'local.example.com/wp-content/uploads/2024/11/old-pic.png 800w') !== false, 'srcset stale entry untouched');

// 挂点存在性
$src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-offload.php');
wpsss_assert(strpos($src, "add_filter('render_block'") !== false, 'render_block hooked');
wpsss_assert(strpos($src, "add_filter('theme_mod_background_image'") !== false, 'background image hooked');
wpsss_assert(strpos($src, "add_filter('theme_mod_header_image'") !== false, 'header image hooked');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
