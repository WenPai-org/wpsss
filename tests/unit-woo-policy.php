<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-offload.php';

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

$GLOBALS['wpsss_woo_tmp'] = sys_get_temp_dir() . '/wpsss-woo-policy-uploads';
@mkdir($GLOBALS['wpsss_woo_tmp'] . '/woocommerce_uploads', 0777, true);

function wp_upload_dir()
{
    $base = $GLOBALS['wpsss_woo_tmp'];
    return ['basedir' => $base, 'baseurl' => 'https://img.example.com'];
}

class Wpsss_Driver_Woo_Recorder
{
    /** @var array<int, array{0: string, 1: string}> */
    public $calls = [];

    public function is_configured($opts)
    {
        return true;
    }

    public function put_object($settings, $object, $absolute_path, $mime, $acl = 'public-read')
    {
        $this->calls[] = [(string) $object, (string) $acl];
        return true;
    }
}

class Wpsss_Plugin
{
    /** @var Wpsss_Driver_Woo_Recorder */
    public $driver;

    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    public function active_driver()
    {
        return $this->driver;
    }
}

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

function woo_policy_opts(string $policy): array
{
    $GLOBALS['wpsss_options_store']['wpsss_options'] = [
        'driver' => 'aliyun-oss',
        'bucket' => 'demo',
        'region' => 'oss-cn-hangzhou',
        'access_key_id' => 'ak',
        'access_key_secret' => 'sk',
        'keep_local' => '1',
        'exclude_ext' => '',
        'woo_policy' => $policy,
    ];
    return Wpsss_Options::get();
}

// 策略判定
wpsss_assert(Wpsss_Offload::key_is_excluded_dir('woocommerce_uploads/guide.zip', ['woo_policy' => 'exclude']) === true, 'exclude policy flags woo dir');
wpsss_assert(Wpsss_Offload::key_is_excluded_dir('2026/09/pic.jpg', ['woo_policy' => 'exclude']) === false, 'exclude policy ignores normal media');
wpsss_assert(Wpsss_Offload::key_is_excluded_dir('woocommerce_uploads/guide.zip', ['woo_policy' => 'private']) === false, 'private policy is not exclusion');
wpsss_assert(Wpsss_Offload::key_is_private_dir('woocommerce_uploads/guide.zip', ['woo_policy' => 'private']) === true, 'private policy flags woo dir');
wpsss_assert(Wpsss_Offload::key_is_private_dir('woocommerce_uploads/guide.zip', ['woo_policy' => 'off']) === false, 'off policy keeps everything normal');
wpsss_assert(Wpsss_Offload::key_is_private_dir('woocommerce_uploads/guide.zip', []) === false, 'default policy is exclude not private');

// put_local_file：三档行为
$woo_file = $GLOBALS['wpsss_woo_tmp'] . '/woocommerce_uploads/guide.zip';
file_put_contents($woo_file, 'premium');
$media_file = $GLOBALS['wpsss_woo_tmp'] . '/2026/09/pic.jpg';
@mkdir(dirname($media_file), 0777, true);
file_put_contents($media_file, 'jpg');

woo_policy_opts('exclude');
$driver = new Wpsss_Driver_Woo_Recorder();
$offload = new Wpsss_Offload(new Wpsss_Plugin($driver));
$r = $offload->put_local_file($woo_file, 'application/zip');
wpsss_assert(is_wp_error($r) && $r->get_error_code() === 'wpsss_woo_exclude', 'exclude policy blocks woo upload');
wpsss_assert($driver->calls === [], 'exclude policy never reaches the driver');

woo_policy_opts('private');
$driver = new Wpsss_Driver_Woo_Recorder();
$offload = new Wpsss_Offload(new Wpsss_Plugin($driver));
$r = $offload->put_local_file($woo_file, 'application/zip');
wpsss_assert($r === true, 'private policy uploads woo file');
wpsss_assert($driver->calls === [['woocommerce_uploads/guide.zip', 'private']], 'private policy passes private acl to driver');

$r = $offload->put_local_file($media_file, 'image/jpeg');
wpsss_assert($r === true && end($driver->calls) === ['2026/09/pic.jpg', 'public-read'], 'normal media keeps public-read');

woo_policy_opts('off');
$driver = new Wpsss_Driver_Woo_Recorder();
$offload = new Wpsss_Offload(new Wpsss_Plugin($driver));
$r = $offload->put_local_file($woo_file, 'application/zip');
wpsss_assert($r === true && $driver->calls === [['woocommerce_uploads/guide.zip', 'public-read']], 'off policy uploads woo file publicly');

// 接线存在性
$opts_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-options.php');
wpsss_assert(strpos($opts_src, "woo_policy") !== false, 'options know woo_policy');
$lib_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-library.php');
wpsss_assert(strpos($lib_src, 'META_LOCAL_ONLY') !== false, 'library tracks local-only attachments');
$woo_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-woo.php');
wpsss_assert(strpos($woo_src, 'object_in_bucket') !== false, 'woo rewrite checks object existence');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
