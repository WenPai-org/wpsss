<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-cos-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-ci.php';

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

$help = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/tab-help.php');
wpsss_assert(strpos($help, '强制下载') !== false, 'help 强制下载');
wpsss_assert(strpos($help, '镜像回源') !== false, 'help 镜像回源');
wpsss_assert(strpos($help, '主账号 APPID') !== false, 'help 主账号 APPID');
wpsss_assert(strpos($help, '七牛') !== false, 'help 七牛');
wpsss_assert(strpos($help, '默认 V4') !== false, 'help oss v4');
wpsss_assert(strpos($help, 'RAM 角色') !== false, 'help ecs ram');

$settings = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/tab-settings.php');
wpsss_assert(strpos($settings, '创建桶的主账号 APPID') !== false, 'settings COS AppId help');
wpsss_assert(strpos($settings, '仅记录功能开关') === false, 'settings no 仅记录功能开关');
wpsss_assert(strpos($settings, 'oss_signature') !== false, 'settings oss signature');
wpsss_assert(strpos($settings, 'ECS 实例 RAM 角色') !== false, 'settings ram option');

wpsss_assert(Wpsss_Ci::audit_text('hello', []) === 'pass', 'audit_text default pass');
wpsss_assert(
    strpos(Wpsss_Ci::request_url([
        'bucket' => 'demo',
        'region' => 'ap-guangzhou',
        'app_id' => '1250000000',
    ]), 'https://demo-1250000000.ci.ap-guangzhou.myqcloud.com/text/auditing') === 0,
    'ci host appends appid'
);
wpsss_assert(
    Wpsss_Ci::request_url(['bucket' => 'demo-1250000000', 'region' => 'ap-guangzhou']) === 'https://demo-1250000000.ci.ap-guangzhou.myqcloud.com/text/auditing',
    'ci host keeps bucket appid'
);
$body = Wpsss_Ci::request_body('hello');
wpsss_assert(strpos($body, '<Content>' . base64_encode('hello') . '</Content>') !== false, 'ci body base64');
wpsss_assert(Wpsss_Ci::parse_verdict('<Response><JobsDetail><Result>0</Result></JobsDetail></Response>') === 'pass', 'parse pass');
wpsss_assert(Wpsss_Ci::parse_verdict('<Response><JobsDetail><Result>1</Result></JobsDetail></Response>') === 'block', 'parse block');
wpsss_assert(Wpsss_Ci::parse_verdict('<Response><JobsDetail><Result>2</Result></JobsDetail></Response>') === 'review', 'parse review');
wpsss_assert(Wpsss_Ci::parse_verdict('<Response><JobsDetail><State>Failed</State></JobsDetail></Response>') === 'fail', 'parse failed state');

$opts = [
    'driver' => 'qcloud-cos',
    'bucket' => 'demo-1250000000',
    'region' => 'ap-guangzhou',
    'access_key_id' => 'AKIDtest',
    'access_key_secret' => 'test-secret',
];
Wpsss_Ci::$transport = static function (string $url, array $headers, string $xml) {
    unset($url, $xml);
    return isset($headers['Authorization']) && strpos($headers['Authorization'], 'q-signature=') !== false
        ? '<Response><JobsDetail><Result>1</Result></JobsDetail></Response>'
        : null;
};
wpsss_assert(Wpsss_Ci::audit_text('bad', $opts) === 'block', 'http block');
Wpsss_Ci::$transport = static function () {
    return null;
};
wpsss_assert(Wpsss_Ci::audit_text('bad', $opts) === 'pass', 'http fail open');
Wpsss_Ci::$transport = null;

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        if ($hook === 'wpsss_ci_audit_text' && array_key_exists('wpsss_ci_filter', $GLOBALS)) {
            return $GLOBALS['wpsss_ci_filter'];
        }
        return $value;
    }
}

$GLOBALS['wpsss_ci_filter'] = 'block';
wpsss_assert(Wpsss_Ci::audit_text('hello', []) === 'block', 'audit_text filter block');

update_option('wpsss_options', [
    'ci_audit' => '0',
    'driver' => 'qcloud-cos',
]);
$ci = new Wpsss_Ci();
wpsss_assert($ci->filter_comment(1, ['comment_content' => 'bad']) === 1, 'filter_comment ci_audit off');

update_option('wpsss_options', [
    'ci_audit' => '1',
    'driver' => 'qcloud-cos',
]);
wpsss_assert($ci->filter_comment(1, ['comment_content' => 'bad']) === 'spam', 'filter_comment block spam');
$GLOBALS['wpsss_ci_filter'] = 'review';
wpsss_assert($ci->filter_comment(1, ['comment_content' => 'maybe']) === 0, 'filter_comment review pending');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
