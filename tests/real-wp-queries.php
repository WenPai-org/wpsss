<?php
/**
 * 真 WordPress 查询验证（wp eval-file 运行，不用桩）。
 *
 * 背景：2026-09-21 生产事故——退避 meta_query 值比较是 INNER JOIN，
 * 没有失败计数的正常附件全被排出待传队列；单测桩忽略 meta_query 语义。
 * 从此凡动 class-wpsss-library.php 的查询，本脚本是必跑门。
 *
 * 用法（沙箱或任意装有本插件的 WP）：
 *   wp eval-file tests/real-wp-queries.php
 * 前提：插件已激活（不必配置桶——本脚本不碰网络，只验证查询语义）。
 * 结束时清理全部测试附件。
 */

wpsss();
$l = wpsss()->library();
$GLOBALS['wpsss_q_fail'] = 0;

function q_assert(bool $ok, string $label): void
{
    if ($ok) {
        WP_CLI::log("ok  {$label}");
        return;
    }
    $GLOBALS['wpsss_q_fail']++;
    WP_CLI::log("FAIL  {$label}");
}

// ---- 造 6 个一次性附件，覆盖全部 meta 状态 ----
$ids = [];
foreach (range(1, 6) as $i) {
    $file = wp_upload_dir()['basedir'] . "/wpsss-query-check-{$i}.txt";
    file_put_contents($file, "check-{$i}");
    $id = wp_insert_attachment([
        'post_mime_type' => 'text/plain',
        'post_title' => "wpsss-query-check-{$i}",
        'post_status' => 'inherit',
    ], $file);
    if (!is_int($id) || $id <= 0) {
        WP_CLI::error('造附件失败：wp_insert_attachment 返回 ' . var_export($id, true));
    }
    $ids[$i] = $id;
    // 插件在 add_attachment 时已自动进桶（沙箱配了桶）——剥掉自动标记，状态由下面的矩阵铺。
    delete_post_meta($id, '_wpsss_offloaded');
    delete_post_meta($id, '_wpsss_key');
    delete_post_meta($id, '_wpsss_error');
    delete_post_meta($id, '_wpsss_fail_count');
    delete_post_meta($id, '_wpsss_filesize');
}
$cleanup = function () use ($ids): void {
    foreach ($ids as $id) {
        wp_delete_attachment($id, true);
    }
};

try {
    // 状态矩阵：1 正常待传；2 已进桶；3 已进桶+本地专属；4 失败1次；5 失败3次(退避)；6 已进桶+失败计数2
    update_post_meta($ids[2], '_wpsss_offloaded', '1');
    update_post_meta($ids[2], '_wpsss_key', 'wpsss-query-check-2.txt');
    update_post_meta($ids[3], '_wpsss_offloaded', '1');
    update_post_meta($ids[3], '_wpsss_local_only', '1');
    update_post_meta($ids[4], '_wpsss_error', '测试失败');
    update_post_meta($ids[4], '_wpsss_fail_count', '1');
    update_post_meta($ids[5], '_wpsss_error', '连续失败');
    update_post_meta($ids[5], '_wpsss_fail_count', '3');
    update_post_meta($ids[6], '_wpsss_offloaded', '1');
    update_post_meta($ids[6], '_wpsss_error', '曾失败');
    update_post_meta($ids[6], '_wpsss_fail_count', '2');

    $in = static function (array $haystack, int $id): bool {
        return in_array($id, $haystack, true);
    };

    // pending：应含 1、4（失败1次未到退避），不含 2/3/5/6
    $pending = $l->pending_ids(50, 0);
    q_assert($in($pending, $ids[1]), 'pending 含正常待传');
    q_assert($in($pending, $ids[4]), 'pending 含失败1次（未退避）');
    q_assert(!$in($pending, $ids[5]), 'pending 排除连续失败3次（退避）');
    q_assert(!$in($pending, $ids[2]) && !$in($pending, $ids[6]), 'pending 排除已进桶');
    q_assert(!$in($pending, $ids[3]), 'pending 排除本地专属');

    // offloaded：应含 2、6，不含 3（标记冲突时以进桶标记为准？3 有 offloaded=1——按标记应含）
    $offloaded = $l->offloaded_ids(50, 0);
    q_assert($in($offloaded, $ids[2]) && $in($offloaded, $ids[6]) && $in($offloaded, $ids[3]), 'offloaded 按进桶标记');
    q_assert(!$in($offloaded, $ids[1]), 'offloaded 不含待传');

    // failed：应含 4、5、6
    $failed_ids = $l->failed_ids(50, 0);
    q_assert($in($failed_ids, $ids[4]) && $in($failed_ids, $ids[5]) && $in($failed_ids, $ids[6]), 'failed 按错误标记（不受退避影响）');

    // 游标：before 语义（只取更小编号）
    $desc = $l->pending_ids(50, 0);
    $mid = (int) $desc[0];
    $below = $l->pending_ids(50, $mid);
    foreach ($below as $id) {
        if ((int) $id >= $mid) {
            q_assert(false, "游标泄漏：{$id} >= {$mid}");
        }
    }
    q_assert(true, '游标 before 语义无泄漏');

    // stats 计数一致
    $s = $l->stats();
    q_assert((int) $s['offloaded'] >= 3, 'stats offloaded 计数含测试件');
    q_assert((int) $s['failed'] >= 3, 'stats failed 计数含测试件');
    q_assert(array_key_exists('offloaded_bytes', $s), 'stats 含字节统计键');

    // 队列快照
    $snap = $l->queue_snapshot();
    q_assert(isset($snap['pending'], $snap['failed'], $snap['cron'], $snap['lock']), '队列快照四字段齐');
} finally {
    $cleanup();
}

if ($GLOBALS['wpsss_q_fail'] > 0) {
    WP_CLI::error("{$GLOBALS['wpsss_q_fail']} 项失败——查询语义回退，禁止发布。");
}
WP_CLI::success('真 WP 查询验证全部通过（测试附件已清理）。');
