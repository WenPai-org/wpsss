<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-ops.php';
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
$missing_local = Wpsss_Ops::diagnose_notes(true, 'id', 'cdn.example', 'cdn.example', false, true);
wpsss_assert(strpos(implode('', $missing_local), '只补缺失') !== false, 'diagnose missing local points to skip pull');
$keep_local = Wpsss_Ops::diagnose_notes(true, 'id', 'cdn.example', 'cdn.example', true, true);
wpsss_assert(strpos(implode('', $keep_local), '旁路改名') !== false, 'diagnose existing local points to rename');
wpsss_assert(strpos(implode('', $keep_local), '前台仍走桶') !== false, 'diagnose existing local says still on bucket');
$prefix_notes = Wpsss_Ops::diagnose_notes(true, 'id', 'cdn.example', 'cdn.example', true, true, '2026/09/a.jpg', ['object_prefix' => 'wp']);
wpsss_assert(strpos(implode('', $prefix_notes), '对不上当前前缀') !== false, 'diagnose prefix mismatch');
$prefix_row = Wpsss_Ops::classify_row([
    'id' => 5,
    'offloaded' => true,
    'key' => '2026/09/c.jpg',
    'local_exists' => true,
    'url' => 'https://cdn.example/2026/09/c.jpg',
    'error' => '',
], 'cdn.example', ['object_prefix' => 'wp']);
wpsss_assert(in_array('prefix_mismatch', $prefix_row['flags'], true), 'flag prefix_mismatch');

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
wpsss_assert(strpos($tools, '桶里没有回收站') !== false, 'tools delete has no-trash warning');
wpsss_assert(strpos($tools, '先做完这三步') !== false, 'tools delete has backup steps');
wpsss_assert(strpos($tools, '本地已经打开核对过') !== false, 'tools delete confirm is a backup claim');
wpsss_assert(strpos($tools, '有数据库备份') !== false, 'tools rewrite asks for database backup');
wpsss_assert(strpos($tools, 'data-wpsss-confirm') !== false, 'tools dialog confirm');
wpsss_assert(strpos($tools, '媒体体检') !== false, 'tools scan card');
wpsss_assert(strpos($tools, '诊断一条地址') !== false, 'tools diagnose card');
wpsss_assert(strpos($tools, '抽检远端对象') !== false, 'tools head card');
wpsss_assert(strpos($tools, '解除进桶标记') !== false, 'tools release card');
wpsss_assert(strpos($tools, '我确认在测试站执行拉回') === false, 'tools pull copy not test-only');
wpsss_assert(strpos($tools, 'wpsss_pull_exist') !== false, 'tools pull on-exist radios');
wpsss_assert(strpos($tools, 'wpsss-pull-seg') !== false, 'tools pull uses compact seg');
wpsss_assert(strpos((string) file_get_contents($toolsDir . 'part-tools-move.php'), 'choice-cards') === false, 'tools pull not choice cards');
wpsss_assert(strpos($tools, '只写磁盘') !== false, 'tools pull says disk only');
wpsss_assert(strpos($tools, '批量拉回') !== false, 'tools pull batch scope');
wpsss_assert(strpos($tools, '单个附件') !== false, 'tools pull one scope');
wpsss_assert(strpos($tools, '媒体库编号') !== false, 'tools pull id is media number');
wpsss_assert(strpos((string) file_get_contents($toolsDir . 'part-tools-move.php'), 'placeholder="<?php echo esc_attr__(\'全部\'') === false, 'tools pull no cryptic 全部 placeholder');
wpsss_assert(strpos($tools, '序列化字段不修改') === false, 'tools rewrite no longer lies about serialized');
wpsss_assert(strpos($tools, '含序列化') !== false, 'tools rewrite admits serialized meta');
wpsss_assert(strpos($tools, '请清页面缓存') !== false, 'tools asks to flush cache');
wpsss_assert(strpos($tools, 'Woo 付费下载') !== false, 'tools delete mentions Woo when class exists');
wpsss_assert(strpos($tools, '只补缺失') !== false, 'tools pull skip mode');
wpsss_assert(strpos($tools, 'wpsss_pull_reset') !== false, 'tools pull resume reset');
wpsss_assert(strpos($tools, 'wpsss_offload_reset') !== false, 'tools offload resume reset');
wpsss_assert(strpos($tools, 'js-wpsss-offload-form') !== false, 'tools offload ajax form');
wpsss_assert(strpos($tools, '开始补传') !== false, 'tools offload start label');
wpsss_assert(strpos($tools, '前缀不对') !== false, 'tools scan prefix mismatch');
wpsss_assert(strpos($tools, '旁路改名') !== false, 'tools pull rename mode');
wpsss_assert(strpos($tools, 'name="wpsss_pull_apply"') !== false, 'tools pull preview');
wpsss_assert(strpos($tools, '到迁移用「只补缺失」拉回') !== false, 'tools scan/diagnose cta');
$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');
wpsss_assert(strpos($css, '.wpsss-gate-extra[hidden]') !== false, 'css hides extra confirm');
wpsss_assert(strpos($css, '.wpsss-caution') !== false, 'css caution panel');
wpsss_assert(strpos($css, '.wpsss-backup-note') !== false, 'css backup note');

$js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/admin.js');
wpsss_assert(strpos($js, 'confirmPullDelete') !== false || strpos($js, 'wpsss_pull_delete_confirm') !== false, 'js pull delete gate');
wpsss_assert(strpos($js, 'js-wpsss-pull-exist') !== false, 'js pull exist mode');
wpsss_assert(strpos($js, 'js-wpsss-pull-scope') !== false, 'js pull scope');
wpsss_assert(strpos($js, 'posts-filter') !== false, 'js media bulk confirm');
wpsss_assert(strpos($js, 'confirmMediaDelete') !== false, 'js media delete confirm');
wpsss_assert(strpos($js, 'wpsss_offload_tick') !== false, 'js offload tick');

$admin = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-admin.php');
wpsss_assert(strpos($admin, '删除远端须再确认') !== false, 'admin rejects pull delete without second confirm');
wpsss_assert(strpos($admin, 'function redirect_tools') !== false, 'admin returns to tools panel');
wpsss_assert(strpos($admin, '删桶不能只补缺失') !== false, 'admin rejects delete+skip');
wpsss_assert(strpos($admin, '只写回了磁盘，前台仍走桶') !== false, 'admin pull message disk vs url');
wpsss_assert(strpos($admin, '选择「单个附件」时请填写媒体库编号') !== false, 'admin rejects empty one-scope');
wpsss_assert(strpos($admin, 'pull_cursor_key') !== false, 'admin pull cursor');
wpsss_assert(strpos($admin, 'handle_offload_tick') !== false, 'admin offload tick');
wpsss_assert(strpos($admin, 'run_offload_slice') !== false, 'admin offload budget slice');

$cli = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-cli.php');
wpsss_assert(strpos($cli, '删除远端须加 --yes') !== false, 'cli pull delete needs yes');
wpsss_assert(strpos($cli, '删除对象须加 --yes') !== false, 'cli delete needs yes');
wpsss_assert(strpos($cli, '--on-exist') !== false, 'cli pull on-exist');
wpsss_assert(strpos($cli, '删桶不能 --on-exist=skip') !== false, 'cli rejects delete+skip');
wpsss_assert(strpos($cli, '--before') !== false, 'cli pull/offload before cursor');
wpsss_assert(strpos($cli, '只写回磁盘，前台仍走桶') !== false, 'cli pull disk vs url');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
