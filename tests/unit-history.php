<?php
require __DIR__ . '/wp-stubs.php';

if (!function_exists('wp_date')) {
    function wp_date(string $format, int $timestamp): string
    {
        return gmdate($format, $timestamp);
    }
}

if (!function_exists('get_post')) {
    function get_post(int $id): ?object
    {
        if ($id === 42) {
            return (object) ['post_title' => 'hero.jpg'];
        }
        return null;
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-history.php';

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

wpsss_assert(
    Wpsss_History::format_detail('offload', '附件 42 → uploads/2026/a.png') === '附件 #42 · hero.jpg · uploads/2026/a.png',
    'legacy offload detail'
);
wpsss_assert(
    Wpsss_History::format_detail('fail', '附件 9：权限不足') === '附件 #9 · 权限不足',
    'legacy fail detail'
);
wpsss_assert(
    Wpsss_History::format_detail('delete', '附件 7 ×3') === '附件 #7 · 删 3 个对象',
    'legacy delete detail'
);
wpsss_assert(
    Wpsss_History::format_detail('pull', 'attachment=5 delete-remote') === '附件 #5 · 拉回并删除桶内对象',
    'legacy pull detail'
);
wpsss_assert(
    Wpsss_History::format_detail('upload', str_repeat('a', 60)) === '…' . str_repeat('a', 45),
    'short_key via upload detail'
);
wpsss_assert(
    Wpsss_History::format_time('2026-09-19 03:47:00') === '2026-09-19 03:47',
    'format_time gmt to local helper'
);
$collapsed = Wpsss_History::collapsed([
    ['event' => 'offload', 'detail' => '附件 #6 · 01-overview', 't' => '2026-09-19 03:58:01'],
    ['event' => 'offload', 'detail' => '附件 #6 · 01-overview', 't' => '2026-09-19 03:58:00'],
    ['event' => 'fail', 'detail' => '附件 #9 · 权限不足', 't' => '2026-09-19 03:48:00'],
]);
wpsss_assert(count($collapsed) === 2 && (int) $collapsed[0]['count'] === 2, 'collapsed merges consecutive same events');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
