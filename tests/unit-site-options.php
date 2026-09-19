<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';

$GLOBALS['wpsss_ms'] = false;
$GLOBALS['wpsss_sites'] = [];
$GLOBALS['wpsss_switch_log'] = [];
$GLOBALS['wpsss_restore_count'] = 0;
$GLOBALS['wpsss_blog_id'] = 1;
$GLOBALS['wpsss_upload_dir'] = [
    'basedir' => '/var/www/uploads',
    'baseurl' => 'https://ex.test/uploads',
];

if (!function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return !empty($GLOBALS['wpsss_ms']);
    }
}
if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id(): int
    {
        return (int) $GLOBALS['wpsss_blog_id'];
    }
}
if (!function_exists('switch_to_blog')) {
    function switch_to_blog($id): bool
    {
        $GLOBALS['wpsss_switch_log'][] = (int) $id;
        $GLOBALS['wpsss_blog_id'] = (int) $id;
        return true;
    }
}
if (!function_exists('restore_current_blog')) {
    function restore_current_blog(): bool
    {
        $GLOBALS['wpsss_restore_count']++;
        $GLOBALS['wpsss_blog_id'] = 1;
        return true;
    }
}
if (!function_exists('get_sites')) {
    function get_sites($args = []): array
    {
        return $GLOBALS['wpsss_sites'];
    }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false): array
    {
        return $GLOBALS['wpsss_upload_dir'];
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-site.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';

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

$GLOBALS['wpsss_options_store'] = [];
$https = Wpsss_Options::sanitize([
    'driver' => 's3-compat',
    'bucket' => 'b',
    'region' => 'us-east-1',
    'endpoint' => 'http://tos.example',
    'keep_local' => '1',
]);
wpsss_assert($https['endpoint'] === 'https://tos.example', 'sanitize endpoint http → https');

$GLOBALS['wpsss_options_store'] = [];
$accel = Wpsss_Options::sanitize([
    'driver' => 'aliyun-oss',
    'bucket' => 'mybucket',
    'region' => 'oss-accelerate',
    'keep_local' => '1',
]);
wpsss_assert($accel['origin_region'] === 'oss-cn-hangzhou', 'accelerate empty origin_region → hangzhou');
wpsss_assert(strpos(Wpsss_Options::api_host($accel), 'oss-cn-hangzhou') !== false, 'api_host uses origin_region');
wpsss_assert(strpos(Wpsss_Options::public_base($accel), 'oss-accelerate') !== false, 'public_base keeps oss-accelerate');

$GLOBALS['wpsss_options_store'] = [];
$kept = Wpsss_Options::sanitize([
    'driver' => 'aliyun-oss',
    'bucket' => 'mybucket',
    'region' => 'oss-cn-shanghai',
    'origin_region' => 'oss-cn-beijing',
    'keep_local' => '1',
]);
wpsss_assert($kept['origin_region'] === 'oss-cn-beijing', 'keeps origin_region when region is not accelerate');

$GLOBALS['wpsss_options_store'] = [];
$sig = Wpsss_Options::sanitize([
    'driver' => 'aliyun-oss',
    'bucket' => 'mybucket',
    'region' => 'oss-cn-hangzhou',
    'keep_local' => '1',
    'oss_signature' => 'v1',
    'oss_auth' => 'ram',
    'oss_ram_role' => 'wpsss-role',
]);
wpsss_assert($sig['oss_signature'] === 'v1', 'sanitize keeps oss v1');
wpsss_assert($sig['oss_auth'] === 'ram', 'sanitize keeps ram auth');
wpsss_assert($sig['oss_ram_role'] === 'wpsss-role', 'sanitize ram role');
wpsss_assert(Wpsss_Options::oss_sign_version($sig) === 'v1', 'oss_sign_version v1');
wpsss_assert(Wpsss_Options::oss_sign_region($sig) === 'cn-hangzhou', 'oss_sign_region strips prefix');
$bad = Wpsss_Options::sanitize([
    'driver' => 'aliyun-oss',
    'bucket' => 'mybucket',
    'region' => 'oss-cn-hangzhou',
    'keep_local' => '1',
    'oss_signature' => 'v9',
    'oss_auth' => 'oops',
]);
wpsss_assert($bad['oss_signature'] === 'v4', 'bad signature falls to v4');
wpsss_assert($bad['oss_auth'] === 'ak', 'bad auth falls to ak');
wpsss_assert(
    in_array('实例 RAM 角色名为空时，会向 ECS 元数据询问；失败则无法进桶。', Wpsss_Options::runtime_warnings([
        'driver' => 'aliyun-oss',
        'oss_auth' => 'ram',
        'oss_ram_role' => '',
    ]), true),
    'runtime_warnings ram empty role'
);

$GLOBALS['wpsss_options_store'] = [];
$kodo_w = Wpsss_Options::runtime_warnings([
    'driver' => 'qiniu-kodo',
    'custom_domain' => '',
    'access_key_secret' => 'do-not-echo',
]);
wpsss_assert(
    in_array('七牛必须填写 HTTPS 自定义域名，默认域不能给访客用。', $kodo_w, true),
    'runtime_warnings kodo needs custom domain'
);
wpsss_assert(strpos(implode("\n", $kodo_w), 'do-not-echo') === false, 'runtime_warnings does not echo secret');

$ali_w = Wpsss_Options::runtime_warnings([
    'driver' => 'aliyun-oss',
    'custom_domain' => '',
    'url_access' => 'public',
]);
wpsss_assert(
    in_array('阿里默认域名 2019 年后常强制下载不预览。请配自定义域或改签名访问。', $ali_w, true),
    'runtime_warnings aliyun default domain'
);

$GLOBALS['wpsss_options_store']['upload_url_path'] = '.';
$dot_w = Wpsss_Options::runtime_warnings([
    'driver' => 'aliyun-oss',
    'custom_domain' => 'https://cdn.example',
    'url_access' => 'public',
]);
wpsss_assert(
    in_array('upload_url_path 不能是「.」，删除远端会失败。', $dot_w, true),
    'runtime_warnings upload_url_path is dot'
);

$GLOBALS['wpsss_options_store'] = [
    Wpsss_Options::OWN_URL_FLAG => '1',
    'upload_url_path' => '.',
];
Wpsss_Options::sync_upload_url_path('');
wpsss_assert((string) get_option('upload_url_path', '') === '', 'sync deletes owned dot path');
wpsss_assert((string) get_option(Wpsss_Options::OWN_URL_FLAG, '') === '', 'sync deletes own flag');

$GLOBALS['wpsss_options_store'] = [
    'upload_url_path' => '.',
];
Wpsss_Options::sync_upload_url_path('');
wpsss_assert(get_option('upload_url_path') === '.', 'sync does not delete foreign dot path');

$GLOBALS['wpsss_ms'] = false;
$GLOBALS['wpsss_options_store'] = [
    Wpsss_Options::OWN_URL_FLAG => '1',
    'upload_url_path' => 'https://cdn.example.com',
    Wpsss_Options::OPTION_KEY => ['bucket' => 'x'],
];
Wpsss_Options::uninstall_cleanup();
wpsss_assert(!array_key_exists('upload_url_path', $GLOBALS['wpsss_options_store']), 'uninstall drops owned path');
wpsss_assert(!array_key_exists(Wpsss_Options::OWN_URL_FLAG, $GLOBALS['wpsss_options_store']), 'uninstall drops flag');
wpsss_assert(!array_key_exists(Wpsss_Options::OPTION_KEY, $GLOBALS['wpsss_options_store']), 'uninstall drops options');

$GLOBALS['wpsss_options_store'] = [
    'upload_url_path' => 'https://other.example.com',
    Wpsss_Options::OPTION_KEY => ['bucket' => 'x'],
];
Wpsss_Options::uninstall_cleanup();
wpsss_assert(get_option('upload_url_path') === 'https://other.example.com', 'uninstall keeps foreign upload_url_path');
wpsss_assert(get_option(Wpsss_Options::OPTION_KEY, '__missing__') === '__missing__', 'uninstall still drops wpsss_options');

$first = Wpsss_Site::uploads();
$GLOBALS['wpsss_upload_dir']['basedir'] = '/other/uploads';
$second = Wpsss_Site::uploads();
wpsss_assert($first['basedir'] === '/var/www/uploads' && $second['basedir'] === '/other/uploads', 'uploads not statically cached');

$GLOBALS['wpsss_ms'] = true;
$GLOBALS['wpsss_switch_log'] = [];
$GLOBALS['wpsss_restore_count'] = 0;
$GLOBALS['wpsss_blog_id'] = 1;
$seen = 0;
$ret = Wpsss_Site::with_blog(9, static function () use (&$seen) {
    $seen = Wpsss_Site::blog_id();
    return 'ok';
});
wpsss_assert($ret === 'ok', 'with_blog returns fn result');
wpsss_assert($GLOBALS['wpsss_switch_log'] === [9], 'with_blog calls switch_to_blog');
wpsss_assert($GLOBALS['wpsss_restore_count'] === 1, 'with_blog restores');
wpsss_assert($seen === 9, 'with_blog runs on target blog');

$GLOBALS['wpsss_switch_log'] = [];
$GLOBALS['wpsss_restore_count'] = 0;
Wpsss_Site::with_blog(1, static function () {
    return true;
});
wpsss_assert($GLOBALS['wpsss_switch_log'] === [] && $GLOBALS['wpsss_restore_count'] === 0, 'with_blog same blog skips switch');

$GLOBALS['wpsss_switch_log'] = [];
$GLOBALS['wpsss_restore_count'] = 0;
$threw = false;
try {
    Wpsss_Site::with_blog(4, static function () {
        throw new RuntimeException('x');
    });
} catch (RuntimeException $e) {
    $threw = true;
}
wpsss_assert($threw && $GLOBALS['wpsss_restore_count'] === 1, 'with_blog finally restores after throw');

$ran = false;
Wpsss_Site::with_attachment_blog(12, static function () use (&$ran) {
    $ran = true;
});
wpsss_assert($ran, 'with_attachment_blog runs fn');

$GLOBALS['wpsss_sites'] = [
    (object) ['blog_id' => 2],
    (object) ['blog_id' => 3],
];
$GLOBALS['wpsss_switch_log'] = [];
$GLOBALS['wpsss_restore_count'] = 0;
$GLOBALS['wpsss_blog_id'] = 1;
$GLOBALS['wpsss_options_store'] = [
    Wpsss_Options::OWN_URL_FLAG => '1',
    'upload_url_path' => 'https://cdn.example.com',
    Wpsss_Options::OPTION_KEY => ['bucket' => 'x'],
];
Wpsss_Options::uninstall_cleanup();
wpsss_assert($GLOBALS['wpsss_switch_log'] === [2, 3], 'uninstall multisite switches each site');
wpsss_assert($GLOBALS['wpsss_restore_count'] === 2, 'uninstall multisite restores each site');
wpsss_assert(!array_key_exists(Wpsss_Options::OPTION_KEY, $GLOBALS['wpsss_options_store']), 'uninstall multisite drops options');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
