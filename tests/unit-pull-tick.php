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
$GLOBALS['wpsss_json'] = null;
$GLOBALS['wpsss_pull_pool'] = [];
$GLOBALS['wpsss_options_store'] = [Wpsss_Options_Fake_Key::KEY => []];

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

function wp_unslash($value)
{
    return $value;
}

function current_user_can($cap)
{
    return true;
}

function check_ajax_referer($action, $query_arg = false)
{
    return true;
}

class Wpsss_PullSentinel extends Exception
{
}

function wp_send_json_error($data, $status = 0)
{
    $GLOBALS['wpsss_json'] = ['success' => false, 'data' => $data];
    throw new Wpsss_PullSentinel('json');
}

function wp_send_json_success($data)
{
    $GLOBALS['wpsss_json'] = ['success' => true, 'data' => $data];
    throw new Wpsss_PullSentinel('json');
}

function get_current_user_id()
{
    return 7;
}

function get_posts($args = [])
{
    $before = (int) ($args['wpsss_before_id'] ?? 0);
    $limit = (int) ($args['posts_per_page'] ?? 20);
    $ids = array_values(array_filter($GLOBALS['wpsss_pull_pool'], static function ($id) use ($before) {
        return $before <= 0 || $id < $before;
    }));
    rsort($ids);
    return array_slice($ids, 0, max(0, $limit));
}

function get_post_meta($post_id, $key = '', $single = false)
{
    if ($key === '_wpsss_offloaded') {
        return in_array((int) $post_id, $GLOBALS['wpsss_pull_pool'], true) ? '1' : '';
    }
    return $single ? '' : [];
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->message = (string) $message;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

class Wpsss_Options_Fake_Key
{
    public const KEY = 'wpsss_options';
}

class Wpsss_Options
{
    public static function get()
    {
        return get_option(Wpsss_Options_Fake_Key::KEY, []);
    }
}

class Wpsss_Driver_Pull_Stub
{
    public function is_configured($opts)
    {
        return true;
    }
}

class Wpsss_Offload
{
    /** @var array<int, array{0: int, 1: bool, 2: string}> */
    public $pull_calls = [];

    /** @var array<int, array{0: int, 1: string}> */
    public $plan_calls = [];

    /** @var array<string, int> */
    public $plan = ['would_write' => 1, 'would_skip' => 0, 'would_rename' => 0, 'would_block' => 0, 'missing' => 0, 'exists' => 0];

    public static function sanitize_on_exist(string $mode): string
    {
        return in_array($mode, ['skip', 'rename', 'overwrite'], true) ? $mode : 'skip';
    }

    public function plan_pull($id, $on_exist)
    {
        $this->plan_calls[] = [(int) $id, $on_exist];
        return $this->plan;
    }

    public function pull_attachment($id, $delete_remote, $on_exist)
    {
        $this->pull_calls[] = [(int) $id, (bool) $delete_remote, $on_exist];
        return true;
    }
}

class Wpsss_Plugin
{
    /** @var Wpsss_Library|null */
    private $library;

    public function __construct(?Wpsss_Library $library = null)
    {
        $this->library = $library;
    }

    public function library()
    {
        return $this->library;
    }

    public function active_driver()
    {
        return new Wpsss_Driver_Pull_Stub();
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-library.php';
require dirname(__DIR__) . '/includes/class-wpsss-admin.php';

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

/**
 * @return array{0: Wpsss_Offload, 1: array<string,mixed>|null}
 */
function run_pull_tick(array $post)
{
    $_POST = $post;
    $GLOBALS['wpsss_json'] = null;
    $offload = new Wpsss_Offload();
    $holder = new Wpsss_Plugin();
    $library = new Wpsss_Library($holder, $offload);
    $admin = new Wpsss_Admin(new Wpsss_Plugin($library));
    try {
        $admin->handle_pull_tick();
    } catch (Wpsss_PullSentinel $e) {
        // wp_send_json_* 的等价物：响应已捕获。
    }
    $json = $GLOBALS['wpsss_json'];
    return [$offload, is_array($json) ? $json : null];
}

// 删桶 + 只补缺失：拒绝。
$GLOBALS['wpsss_pull_pool'] = [300, 299];
$GLOBALS['wpsss_transients'] = [];
[, $json] = run_pull_tick(['wpsss_pull_exist' => 'skip', 'wpsss_pull_delete' => '1']);
wpsss_assert($json !== null && $json['success'] === false, 'delete with skip mode rejected');
wpsss_assert(strpos((string) ($json['data']['message'] ?? ''), '只补缺失') !== false, 'delete with skip mode explains why');

// 旁路改名没勾确认：拒绝。
[, $json] = run_pull_tick(['wpsss_pull_exist' => 'rename']);
wpsss_assert($json !== null && $json['success'] === false, 'rename without confirm rejected');

// 删除没二次确认：拒绝。
[, $json] = run_pull_tick(['wpsss_pull_exist' => 'overwrite', 'wpsss_pull_confirm' => '1', 'wpsss_pull_delete' => '1']);
wpsss_assert($json !== null && $json['success'] === false, 'delete without second confirm rejected');

// 正常一档：一次拉一个，游标前进，还有剩余时 done=false。
$GLOBALS['wpsss_pull_pool'] = [300, 299];
$GLOBALS['wpsss_transients'] = [];
[$offload, $json] = run_pull_tick(['wpsss_pull_exist' => 'skip']);
wpsss_assert($json !== null && $json['success'] === true, 'happy path succeeds');
wpsss_assert($offload->pull_calls === [[300, false, 'skip']], 'tick pulls exactly one attachment');
wpsss_assert((int) get_transient('wpsss_pull_cursor_7') === 300, 'tick stores pull cursor');
wpsss_assert($json['data']['done'] === false && $json['data']['cursor'] === 300, 'tick reports more work left');

// 从游标继续：拉 299，之后无剩余 → done=true，游标清空。
[$offload, $json] = run_pull_tick(['wpsss_pull_exist' => 'skip']);
wpsss_assert($offload->pull_calls === [[299, false, 'skip']], 'tick continues from cursor');
wpsss_assert($json['data']['done'] === true, 'tick finishes when pool below cursor is empty');
wpsss_assert(get_transient('wpsss_pull_cursor_7') === false, 'tick clears cursor when done');

// 删除路径：确认齐全时 delete_remote 传给底层。
$GLOBALS['wpsss_pull_pool'] = [298];
$GLOBALS['wpsss_transients'] = [];
[$offload, $json] = run_pull_tick([
    'wpsss_pull_exist' => 'overwrite',
    'wpsss_pull_confirm' => '1',
    'wpsss_pull_delete' => '1',
    'wpsss_pull_delete_confirm' => '1',
]);
wpsss_assert($json !== null && $json['success'] === true, 'delete path allowed with both confirms');
wpsss_assert($offload->pull_calls === [[298, true, 'overwrite']], 'delete flag reaches pull_attachment');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
