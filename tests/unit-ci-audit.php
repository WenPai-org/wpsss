<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
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

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        unset($hook);
        unset($value);
        return 'block';
    }
}

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

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
