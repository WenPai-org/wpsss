<?php
require __DIR__ . '/wp-stubs.php';
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
$empty = Wpsss_Options::sanitize([
    'bucket' => 'wenpai-wpsss-test',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
]);
wpsss_assert($empty['keep_local'] === '1', 'sanitize default keep_local=1 when missing');
wpsss_assert($empty['bucket'] === 'wenpai-wpsss-test', 'sanitize bucket');

$GLOBALS['wpsss_options_store'][Wpsss_Options::OPTION_KEY] = $empty;
$off = Wpsss_Options::sanitize(array_merge($empty, ['keep_local' => '0']));
wpsss_assert($off['keep_local'] === '1', 'sanitize refuses keep_local 1->0 without allow');

$forced = Wpsss_Options::sanitize(array_merge($empty, [
    'keep_local' => '0',
    '_allow_no_local' => '1',
]));
wpsss_assert($forced['keep_local'] === '0', 'sanitize allows keep_local=0 with flag');
wpsss_assert(!isset($forced['_allow_no_local']), 'sanitize drops allow flag');

$GLOBALS['wpsss_options_store'] = [];
$with_custom = Wpsss_Options::sanitize([
    'bucket' => 'wenpai-wpsss-test',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'custom_domain' => 'https://cdn.example.com/',
    'keep_local' => '1',
]);
wpsss_assert($with_custom['custom_domain'] === 'https://cdn.example.com', 'custom domain trimmed');
wpsss_assert(get_option('upload_url_path') === 'https://cdn.example.com', 'owns empty url -> write');
wpsss_assert(get_option(Wpsss_Options::OWN_URL_FLAG) === '1', 'sets own flag');

$GLOBALS['wpsss_options_store'] = [
    'upload_url_path' => 'https://old.example.com',
];
Wpsss_Options::sanitize([
    'bucket' => 'wenpai-wpsss-test',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'custom_domain' => 'https://cdn.example.com',
    'keep_local' => '1',
]);
wpsss_assert(get_option('upload_url_path') === 'https://old.example.com', 'does not overwrite foreign upload_url_path');
wpsss_assert(get_option(Wpsss_Options::OWN_URL_FLAG, '') === '', 'foreign url leaves own flag empty');

$GLOBALS['wpsss_options_store'] = [
    Wpsss_Options::OPTION_KEY => $with_custom,
    'upload_url_path' => 'https://cdn.example.com',
    Wpsss_Options::OWN_URL_FLAG => '1',
];
$cleared = Wpsss_Options::sanitize([
    'bucket' => 'wenpai-wpsss-test',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'custom_domain' => '',
    'keep_local' => '1',
]);
wpsss_assert($cleared['custom_domain'] === '', 'clears custom domain');
wpsss_assert(get_option('upload_url_path', '') === '', 'owned url removed when custom empty');
wpsss_assert(get_option(Wpsss_Options::OWN_URL_FLAG, '') === '', 'own flag removed');

$GLOBALS['wpsss_options_store'][Wpsss_Options::OPTION_KEY] = [
    'bucket' => 'wenpai-wpsss-test',
    'region' => 'oss-cn-hangzhou',
    'custom_domain' => '',
];
wpsss_assert(
    Wpsss_Options::public_base() === 'https://wenpai-wpsss-test.oss-cn-hangzhou.aliyuncs.com',
    'public_base bucket host'
);


$GLOBALS['wpsss_options_store'] = [];
$cos = Wpsss_Options::sanitize([
    'driver' => 'qcloud-cos',
    'bucket' => 'demo-1250000000',
    'region' => 'ap-guangzhou',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'keep_local' => '1',
    'auto_rename' => 'md5',
    'object_prefix' => 'media/',
    'image_style' => '?imageMogr2/format/webp',
    'origin_protect' => '1',
]);
wpsss_assert($cos['driver'] === 'qcloud-cos', 'sanitize keeps cos driver');
wpsss_assert($cos['auto_rename'] === 'md5', 'sanitize auto_rename');
wpsss_assert($cos['object_prefix'] === 'media', 'sanitize trims prefix slash');
wpsss_assert($cos['origin_protect'] === '1', 'sanitize origin_protect');
$GLOBALS['wpsss_options_store'][Wpsss_Options::OPTION_KEY] = $cos;
wpsss_assert(
    Wpsss_Options::public_base() === 'https://demo-1250000000.cos.ap-guangzhou.myqcloud.com',
    'public_base cos host'
);
wpsss_assert(Wpsss_Options::apply_object_prefix('2026/a.png', $cos) === 'media/2026/a.png', 'prefix join');
$bad_driver = Wpsss_Options::sanitize(array_merge($cos, ['driver' => 'volcengine']));
wpsss_assert($bad_driver['driver'] === 'aliyun-oss', 'unknown driver falls back');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
