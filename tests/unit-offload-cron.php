<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

$GLOBALS['wpsss_transients'] = [];
$GLOBALS['wpsss_cron_scheduled'] = [];
$GLOBALS['wpsss_cron_cleared'] = 0;

function get_transient($key)
{
    return array_key_exists($key, $GLOBALS['wpsss_transients']) ? $GLOBALS['wpsss_transients'][$key] : false;
}

function set_transient($key, $value, $ttl = 0)
{
    $GLOBALS['wpsss_transients'][$key] = $value;
    return true;
}

function delete_transient($key)
{
    unset($GLOBALS['wpsss_transients'][$key]);
    return true;
}

function wp_schedule_event($ts, $recurrence, $hook)
{
    $GLOBALS['wpsss_cron_scheduled'][] = [$ts, $recurrence, $hook];
    return true;
}

function wp_clear_scheduled_hook($hook)
{
    $GLOBALS['wpsss_cron_cleared']++;
    return true;
}

function add_filter($tag, $cb, $prio = 10, $args = 1)
{
    if ($tag === 'posts_where') {
        $GLOBALS['wpsss_posts_where_filters'][] = $cb;
    }
    return true;
}

function remove_filter($tag, $cb, $prio = 10)
{
    if ($tag === 'posts_where') {
        $GLOBALS['wpsss_posts_where_filters'] = array_values(array_filter(
            $GLOBALS['wpsss_posts_where_filters'],
            static function ($f) use ($cb) {
                return $f !== $cb;
            }
        ));
    }
    return true;
}

if (!isset($GLOBALS['wpsss_posts_where_filters'])) {
    $GLOBALS['wpsss_posts_where_filters'] = [];
}

// 最小 $wpdb：让 posts_where 回调真的拼出带游标的 SQL。
class wpsss_cron_wpdb_stub
{
    public $posts = 'wp_posts';

    public function prepare($sql, ...$args)
    {
        foreach ($args as $a) {
            $sql = preg_replace('/%d/', (string) (int) $a, $sql, 1);
        }
        return $sql;
    }
}

$GLOBALS['wpdb'] = new wpsss_cron_wpdb_stub();

// 媒体库画像：全部附件 ID 与已进桶集合由全局变量给出。
$GLOBALS['wpsss_cron_all_ids'] = [];
$GLOBALS['wpsss_cron_offloaded_ids'] = [];
$GLOBALS['wpsss_cron_configured'] = true;

// stats() 走单往返 SQL：$wpdb 桩按 meta 集合计数。
class wpsss_cron_wpdb extends wpsss_cron_wpdb_base
{
}
class wpsss_cron_wpdb_base
{
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';

    public function prepare($sql, ...$args)
    {
        foreach ($args as $a) {
            $sql = preg_replace('/%s/', "'" . $a . "'", $sql, 1);
        }
        return $sql;
    }

    public function get_row($sql, $format = null)
    {
        $offloaded = count($GLOBALS['wpsss_cron_offloaded_ids']);
        $failed = (int) ($GLOBALS['wpsss_cron_failed'] ?? 0);
        $total = count($GLOBALS['wpsss_cron_all_ids']);
        return [
            'total' => (string) $total,
            'offloaded' => (string) $offloaded,
            'failed' => (string) $failed,
            'local_only' => '0',
            'offloaded_bytes' => '1048576',
        ];
    }
}

$GLOBALS['wpdb'] = null;
$GLOBALS['wpdb'] = new wpsss_cron_wpdb();

function get_posts($args = [])
{
    // 仿真 WP 真实行为：get_posts() 默认 suppress_filters=true，posts_where 不跑、
    // 游标不生效；只有查询显式传 false 时才应用过滤器。忘了传，这些测试就会红。
    $suppress = array_key_exists('suppress_filters', $args) ? (bool) $args['suppress_filters'] : true;
    $before = 0;
    if (!$suppress) {
        $sql = 'SELECT ID FROM wp_posts WHERE 1=1';
        foreach ($GLOBALS['wpsss_posts_where_filters'] as $cb) {
            $sql = (string) $cb($sql);
        }
        if (preg_match('/ID < (\d+)/', $sql, $m)) {
            $before = (int) $m[1];
        }
    }
    $limit = (int) ($args['posts_per_page'] ?? 20);
    if (isset($args['meta_key']) && $args['meta_key'] === '_wpsss_offloaded') {
        $ids = $GLOBALS['wpsss_cron_offloaded_ids'];
    } elseif (isset($args['meta_query'])) {
        $off = $GLOBALS['wpsss_cron_offloaded_ids'];
        $ids = array_values(array_diff($GLOBALS['wpsss_cron_all_ids'], $off));
    } else {
        $ids = $GLOBALS['wpsss_cron_all_ids'];
    }
    $ids = array_values(array_filter($ids, static function ($id) use ($before) {
        return $before <= 0 || $id < $before;
    }));
    rsort($ids);
    return array_slice($ids, 0, max(0, $limit));
}

function get_post_meta($post_id, $key = '', $single = false)
{
    if ($key === '_wpsss_offloaded') {
        return in_array((int) $post_id, $GLOBALS['wpsss_cron_offloaded_ids'], true) ? '1' : '';
    }
    return $single ? '' : [];
}

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

class Wpsss_Options
{
    public static function get()
    {
        return ['driver' => 'aliyun-oss'];
    }
}

class Wpsss_Driver_Cron_Stub
{
    public function is_configured($opts)
    {
        return (bool) $GLOBALS['wpsss_cron_configured'];
    }
}

class Wpsss_Plugin
{
    public function active_driver()
    {
        return new Wpsss_Driver_Cron_Stub();
    }
}

class Wpsss_Offload
{
    /** @var int[] */
    public $calls = [];

    public function offload_attachment($id)
    {
        $this->calls[] = (int) $id;
        $GLOBALS['wpsss_cron_offloaded_ids'][] = (int) $id;
        return true;
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-library.php';

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

function fresh_library()
{
    $GLOBALS['wpsss_transients'] = [];
    $GLOBALS['wpsss_cron_scheduled'] = [];
    $GLOBALS['wpsss_cron_cleared'] = 0;
    return new Wpsss_Library(new Wpsss_Plugin(), new Wpsss_Offload());
}

// 密钥失效：立即卸下 cron，不碰附件。
$GLOBALS['wpsss_cron_all_ids'] = [301, 300, 299];
$GLOBALS['wpsss_cron_offloaded_ids'] = [];
$GLOBALS['wpsss_cron_configured'] = false;
$lib = fresh_library();
$lib->cron_offload_tick();
wpsss_assert($GLOBALS['wpsss_cron_cleared'] === 1, 'cron disarms when driver not configured');

// 没有待传：卸下 cron。
$GLOBALS['wpsss_cron_configured'] = true;
$GLOBALS['wpsss_cron_all_ids'] = [301];
$GLOBALS['wpsss_cron_offloaded_ids'] = [301];
$lib = fresh_library();
$lib->cron_offload_tick();
wpsss_assert($GLOBALS['wpsss_cron_cleared'] === 1, 'cron disarms when nothing pending');

// 运行锁被浏览器 tick 占着：跳过本轮，不动 cron。
$GLOBALS['wpsss_cron_all_ids'] = [301, 300];
$GLOBALS['wpsss_cron_offloaded_ids'] = [];
$lib = fresh_library();
$GLOBALS['wpsss_transients']['wpsss_offload_runlock'] = 1;
$offload = new Wpsss_Offload();
$lib2 = new Wpsss_Library(new Wpsss_Plugin(), $offload);
$lib2->cron_offload_tick();
wpsss_assert($offload->calls === [], 'cron skips while run lock is held');
wpsss_assert($GLOBALS['wpsss_cron_cleared'] === 0, 'cron keeps schedule when only skipping');

// 正常一轮：最多补 2 个，游标前进，锁释放，队列未清空时保持 armed。
$GLOBALS['wpsss_cron_all_ids'] = [305, 304, 303, 302, 301];
$GLOBALS['wpsss_cron_offloaded_ids'] = [];
$GLOBALS['wpsss_transients'] = [];
$GLOBALS['wpsss_cron_cleared'] = 0;
$offload = new Wpsss_Offload();
$lib3 = new Wpsss_Library(new Wpsss_Plugin(), $offload);
$lib3->cron_offload_tick();
wpsss_assert($offload->calls === [305, 304], 'cron processes at most two per minute');
wpsss_assert((int) get_transient('wpsss_offload_cursor') === 304, 'cron advances shared cursor');
wpsss_assert(get_transient('wpsss_offload_runlock') === false, 'cron releases run lock');
wpsss_assert($GLOBALS['wpsss_cron_cleared'] === 0, 'cron stays armed while queue has work');

// 队列清空：处理完剩余两个后自动卸下。
$lib3->cron_offload_tick();
wpsss_assert($offload->calls === [305, 304, 303, 302], 'cron drains the rest');
$lib3->cron_offload_tick();
wpsss_assert($GLOBALS['wpsss_cron_cleared'] >= 1, 'cron disarms itself when drained');

// 卡住的队列（无进展）也会卸下，避免空转。
$GLOBALS['wpsss_cron_all_ids'] = [401, 400];
$GLOBALS['wpsss_cron_offloaded_ids'] = [401, 400];
$GLOBALS['wpsss_transients']['wpsss_offload_cursor'] = 401;
$offload2 = new Wpsss_Offload();
(new Wpsss_Library(new Wpsss_Plugin(), $offload2))->cron_offload_tick();
wpsss_assert($offload2->calls === [], 'stuck queue processes nothing');
wpsss_assert($GLOBALS['wpsss_cron_cleared'] >= 1, 'cron disarms when no progress');

// 停用钩子清的都是既有键名。
$src = (string) file_get_contents(dirname(__DIR__) . '/wpsss.php');
wpsss_assert(strpos($src, "wp_clear_scheduled_hook('wpsss_offload_cron_event')") !== false, 'deactivation clears cron hook');
wpsss_assert(strpos($src, "delete_transient('wpsss_offload_runlock')") !== false, 'deactivation clears run lock');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
