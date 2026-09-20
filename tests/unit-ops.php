<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-ops.php';

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

wpsss_assert(Wpsss_Ops::parse_input('')['kind'] === 'empty', 'parse empty');
wpsss_assert(Wpsss_Ops::parse_input('128')['id'] === 128, 'parse id');
wpsss_assert(Wpsss_Ops::parse_input('https://cdn.example/a.jpg')['host'] === 'cdn.example', 'parse url host');
wpsss_assert(Wpsss_Ops::parse_input('not-a-url')['kind'] === 'unknown', 'parse unknown');

$row = Wpsss_Ops::classify_row([
    'id' => 3,
    'offloaded' => true,
    'key' => '2026/09/a.jpg',
    'local_exists' => false,
    'url' => 'http://old.example/2026/09/a.jpg',
    'error' => 'timeout',
], 'cdn.example');
wpsss_assert(in_array('local_missing', $row['flags'], true), 'flag local_missing');
wpsss_assert(in_array('host_mismatch', $row['flags'], true), 'flag host_mismatch');
wpsss_assert(in_array('http', $row['flags'], true), 'flag http');
wpsss_assert(in_array('failed', $row['flags'], true), 'flag failed');

$ok = Wpsss_Ops::classify_row([
    'id' => 4,
    'offloaded' => true,
    'key' => '2026/09/b.jpg',
    'local_exists' => true,
    'url' => 'https://cdn.example/2026/09/b.jpg',
    'error' => '',
], 'cdn.example');
wpsss_assert($ok['flags'] === [], 'healthy row no flags');

$notes = Wpsss_Ops::diagnose_notes(false, 'url', 'old.example', 'cdn.example');
wpsss_assert(strpos(implode('', $notes), '媒体库里没有') !== false, 'diagnose missing attachment');

$toolsDir = dirname(__DIR__) . '/templates/admin/';
$tools = (string) file_get_contents($toolsDir . 'tab-tools.php')
    . (string) file_get_contents($toolsDir . 'part-tools-daily.php')
    . (string) file_get_contents($toolsDir . 'part-tools-check.php')
    . (string) file_get_contents($toolsDir . 'part-tools-move.php');
$shell = (string) file_get_contents($toolsDir . 'tab-tools.php');
wpsss_assert(strpos($shell, "'daily'") !== false && strpos($shell, 'part-tools-') !== false, 'tools has daily panel');
wpsss_assert(strpos($shell, "'check'") !== false, 'tools has check panel');
wpsss_assert(strpos($shell, "'move'") !== false, 'tools has move panel');
wpsss_assert(strpos($shell, '媒体体检') === false, 'tools index does not stack all cards');
wpsss_assert(strpos($tools, 'wpsss_pull_delete_confirm') !== false, 'tools second confirm for delete remote');
wpsss_assert(strpos($tools, 'data-wpsss-confirm') !== false, 'tools dialog confirm');
wpsss_assert(strpos($tools, '媒体体检') !== false, 'tools scan card');
wpsss_assert(strpos($tools, '诊断一条地址') !== false, 'tools diagnose card');
wpsss_assert(strpos($tools, '抽检远端对象') !== false, 'tools head card');
wpsss_assert(strpos($tools, '解除进桶标记') !== false, 'tools release card');
wpsss_assert(strpos($tools, '我确认在测试站执行拉回') === false, 'tools pull copy not test-only');
$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');
wpsss_assert(strpos($css, '.wpsss-gate-extra[hidden]') !== false, 'css hides extra confirm');

$js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/admin.js');
wpsss_assert(strpos($js, 'confirmPullDelete') !== false || strpos($js, 'wpsss_pull_delete_confirm') !== false, 'js pull delete gate');
wpsss_assert(strpos($js, 'posts-filter') !== false, 'js media bulk confirm');
wpsss_assert(strpos($js, 'confirmMediaDelete') !== false, 'js media delete confirm');

$admin = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-admin.php');
wpsss_assert(strpos($admin, '删除远端须再确认') !== false, 'admin rejects pull delete without second confirm');
wpsss_assert(strpos($admin, 'function redirect_tools') !== false, 'admin returns to tools panel');

$cli = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-cli.php');
wpsss_assert(strpos($cli, '删除远端须加 --yes') !== false, 'cli pull delete needs yes');
wpsss_assert(strpos($cli, '删除对象须加 --yes') !== false, 'cli delete needs yes');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
