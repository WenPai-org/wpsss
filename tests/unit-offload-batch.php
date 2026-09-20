<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';

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

if (!function_exists('get_posts')) {
    function get_posts($args = [])
    {
        $limit = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 20;
        $ids = $GLOBALS['wpsss_pending_ids'] ?? [];
        return array_slice($ids, 0, $limit > 0 ? $limit : count($ids));
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        $off = $GLOBALS['wpsss_offloaded'] ?? [];
        if ($key === '_wpsss_offloaded') {
            return !empty($off[(int) $post_id]) ? '1' : '';
        }
        return $single ? '' : [];
    }
}

class Wpsss_Plugin
{
}

class Wpsss_Offload
{
    /** @var int */
    public $delay_us = 80000;

    /** @var int[] */
    public $calls = [];

    public function offload_attachment($id)
    {
        usleep($this->delay_us);
        $this->calls[] = (int) $id;
        return true;
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-library.php';

$GLOBALS['wpsss_pending_ids'] = [100, 99, 98, 97, 96];
$GLOBALS['wpsss_offloaded'] = [];
$offload = new Wpsss_Offload();
$lib = new Wpsss_Library(new Wpsss_Plugin(), $offload);

$all = $lib->offload_batch(5, false, 0, 'pending', 0, 0);
wpsss_assert($all['ok'] === 5 && $all['stopped'] === false, 'no budget processes the batch');
wpsss_assert($all['cursor'] === 96, 'cursor is last processed id');
wpsss_assert($offload->calls === [100, 99, 98, 97, 96], 'no budget order');

$offload->calls = [];
$sliced = $lib->offload_batch(5, false, 0, 'pending', 0, 0.12);
wpsss_assert($sliced['ok'] === 2 && $sliced['stopped'] === true, 'budget stops after first items');
wpsss_assert($sliced['cursor'] === 99, 'cursor does not jump to min of unused ids');
wpsss_assert($offload->calls === [100, 99], 'budget processed only two');

$dry = $lib->offload_batch(5, true, 0, 'pending', 0, 0);
wpsss_assert($dry['ok'] === 5 && $dry['stopped'] === false, 'dry run still counts the batch');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
