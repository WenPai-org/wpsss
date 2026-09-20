<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

$GLOBALS['wpsss_copy_transients'] = [];

function get_transient($key)
{
    return array_key_exists($key, $GLOBALS['wpsss_copy_transients']) ? $GLOBALS['wpsss_copy_transients'][$key] : false;
}

function set_transient($key, $value, $ttl = 0)
{
    $GLOBALS['wpsss_copy_transients'][$key] = $value;
    return true;
}

function delete_transient($key)
{
    unset($GLOBALS['wpsss_copy_transients'][$key]);
    return true;
}

function wp_tempnam($suffix = '')
{
    return tempnam(sys_get_temp_dir(), 'wpsss-copy-test');
}

function wp_check_filetype($file, $types = null)
{
    return ['type' => 'application/octet-stream'];
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $code;
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->code = (string) $code;
            $this->message = (string) $message;
        }

        public function get_error_code()
        {
            return $this->code;
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

if (!function_exists('esc_xml')) {
    function esc_xml($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_XML1);
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-stream.php';
require dirname(__DIR__) . '/includes/class-wpsss-copy.php';

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

// 同厂商：只调 copy_object，不落临时文件
class SameVendorDriver
{
    /** @var array<int, array{0: string, 1: string, 2: string}> */
    public $copies = [];
    public $puts = [];

    public function copy_object($to, $to_key, $from, $from_key)
    {
        $this->copies[] = [$to['bucket'], $to_key, $from_key];
        return true;
    }

    public function put_object($to, $key, $path, $mime, $acl = 'public-read')
    {
        $this->puts[] = [$key, $path];
        return true;
    }

    public function get_object_range($opts, $key, $start, $end)
    {
        return ['code' => 200, 'body' => 'x', 'headers' => []];
    }
}

$src = new SameVendorDriver();
$dst = new SameVendorDriver();
$r = Wpsss_Copy::copy_key($src, ['bucket' => 'b1'], '2026/09/a.jpg', $dst, ['bucket' => 'b2'], 'moved/2026/09/a.jpg');
wpsss_assert($r === true, 'same-vendor copy succeeds');
wpsss_assert($dst->copies === [['b2', 'moved/2026/09/a.jpg', '2026/09/a.jpg']], 'same-vendor uses server-side copy');
wpsss_assert($dst->puts === [], 'same-vendor never falls back to upload');

// 跨厂商：copy_object 不存在语义（这里用不同类）→ 临时文件中转
class CrossVendorSource
{
    public $copies = [];
    public $puts = [];

    public function copy_object($to, $to_key, $from, $from_key)
    {
        return new WP_Error('wpsss_cross', '跨厂商。');
    }

    public function get_object_range($opts, $key, $start, $end)
    {
        $body = substr('CROSS-VENDOR-PAYLOAD', $start, $end - $start + 1);
        return ['code' => 206, 'body' => $body, 'headers' => ['Content-Range' => sprintf('bytes %d-%d/20', $start, $start + strlen($body) - 1)]];
    }
}

class CrossVendorDest
{
    public $copies = [];
    /** @var array<int, string> */
    public $puts = [];

    public function copy_object($to, $to_key, $from, $from_key)
    {
        return new WP_Error('wpsss_cross', '跨厂商。');
    }

    public function put_object($to, $key, $path, $mime, $acl = 'public-read')
    {
        $this->puts[] = (string) file_get_contents($path);
        return true;
    }
}

$src2 = new CrossVendorSource();
$dst2 = new CrossVendorDest();
$r = Wpsss_Copy::copy_key($src2, ['bucket' => 'b1'], 'a.bin', $dst2, ['bucket' => 'b2'], 'a.bin');
wpsss_assert($r === true, 'cross-vendor copy succeeds');
wpsss_assert($dst2->puts === ['CROSS-VENDOR-PAYLOAD'], 'cross-vendor streams through temp file');

// 跨厂商源失败透传
class BrokenSource extends CrossVendorSource
{
    public function get_object_range($opts, $key, $start, $end)
    {
        return new WP_Error('wpsss_src_down', '源桶读不了。');
    }
}
$r = Wpsss_Copy::copy_key(new BrokenSource(), ['bucket' => 'b1'], 'a.bin', $dst2, ['bucket' => 'b2'], 'a.bin');
wpsss_assert(is_wp_error($r) && $r->get_error_code() === 'wpsss_src_down', 'source failure propagates');

// CLI 与访问器接线
$cli_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-cli.php');
wpsss_assert(strpos($cli_src, 'function copy(') !== false, 'cli has copy command');
wpsss_assert(strpos($cli_src, 'Wpsss_Copy::copy_key') !== false, 'cli uses copy orchestrator');
wpsss_assert(strpos($cli_src, '--update-keys') !== false, 'cli documents update-keys');
$offload_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-offload.php');
wpsss_assert(strpos($offload_src, 'attachment_object_keys') !== false, 'offload exposes attachment keys');
wpsss_assert(strpos($offload_src, 'filter_attached_file') !== false, 'get_attached_file takeover present');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
