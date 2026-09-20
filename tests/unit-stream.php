<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';

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

function stream_temp(string $name): string
{
    return sys_get_temp_dir() . '/wpsss-stream-test-' . $name;
}

// ===== download：206 分段循环 =====
$payload = str_repeat('A', 5 * 1024 * 1024) . str_repeat('B', 3 * 1024 * 1024) . 'TAIL'; // 跨两段多一点
$dest = stream_temp('dl.bin');
$calls = [];
$range_fetch = function (int $start, int $end) use ($payload, &$calls) {
    $calls[] = [$start, $end];
    $body = substr($payload, $start, $end - $start + 1);
    $total = strlen($payload);
    return [
        'code' => 206,
        'body' => $body,
        'headers' => ['Content-Range' => sprintf('bytes %d-%d/%d', $start, $start + strlen($body) - 1, $total)],
    ];
};
$r = Wpsss_Stream::download($range_fetch, $dest);
wpsss_assert($r === true, 'download multi-chunk succeeds');
wpsss_assert(file_get_contents($dest) === $payload, 'download reassembles in order');
wpsss_assert(count($calls) === 2, 'download uses as many chunks as needed');
wpsss_assert($calls[1][1] - $calls[1][0] + 1 === 8388608 && strlen(substr($payload, 8388608)) === 4, 'last chunk covers the short tail');

// ===== download：服务器忽略 Range（200 整包） =====
$dest200 = stream_temp('dl200.bin');
$r = Wpsss_Stream::download(function ($start, $end) use ($payload) {
    return ['code' => 200, 'body' => $payload, 'headers' => []];
}, $dest200);
wpsss_assert($r === true && file_get_contents($dest200) === $payload, 'download tolerates Range-ignoring origin');

// ===== download：中途失败清理临时文件 =====
$destErr = stream_temp('dl-err.bin');
@unlink($destErr);
$r = Wpsss_Stream::download(function ($start, $end) {
    if ($start === 0) {
        return ['code' => 206, 'body' => str_repeat('X', 8 * 1024 * 1024), 'headers' => []];
    }
    return new WP_Error('wpsss_boom', '网断在第二段。');
}, $destErr);
wpsss_assert(is_wp_error($r), 'download surfaces mid-stream failure');
wpsss_assert(!file_exists($destErr), 'download cleans up partial file');

// ===== download：Content-Range 总量对不上 =====
$destMismatch = stream_temp('dl-mismatch.bin');
@unlink($destMismatch);
$r = Wpsss_Stream::download(function ($start, $end) {
    return ['code' => 206, 'body' => '0123456789', 'headers' => ['Content-Range' => 'bytes 0-4/6']];
}, $destMismatch);
wpsss_assert(is_wp_error($r) && !file_exists($destMismatch), 'download fails on size mismatch');

// ===== upload：分片循环 / ETag 收集 / 失败 abort =====
$src = stream_temp('up.bin');
file_put_contents($src, str_repeat('Z', 20 * 1024 * 1024 + 5)); // 8M + 8M + (4M+5B) → 3 片
$events = [];
$initiate = function () use (&$events) {
    $events[] = 'init';
    return 'upload-42';
};
$part = function (string $id, int $n, string $body) use (&$events) {
    $events[] = 'part' . $n . ':' . strlen($body);
    return 'etag-' . $n;
};
$complete = function (string $id, string $xml) use (&$events) {
    $events[] = 'complete:' . $id;
    $GLOBALS['wpsss_complete_xml'] = $xml;
    return true;
};
$abort = function (string $id) use (&$events) {
    $events[] = 'abort:' . $id;
};
$r = Wpsss_Stream::upload($initiate, $part, $complete, $abort, $src);
wpsss_assert($r === true, 'upload succeeds');
wpsss_assert(count(array_filter($events, static function ($e) {
    return strpos($e, 'part1:') === 0;
})) === 1, 'upload splits by part size');
wpsss_assert(in_array('part1:8388608', $events, true) && in_array('part2:8388608', $events, true) && in_array('part3:4194309', $events, true), 'upload part sizes are 8M+8M+tail');
wpsss_assert(in_array('complete:upload-42', $events, true), 'upload completes with upload id');
wpsss_assert(!in_array('abort:upload-42', $events, true), 'successful upload does not abort');
$xml = (string) $GLOBALS['wpsss_complete_xml'];
wpsss_assert(strpos($xml, '<PartNumber>1</PartNumber><ETag>etag-1</ETag>') !== false
    && strpos($xml, '<PartNumber>3</PartNumber><ETag>etag-3</ETag>') !== false, 'complete xml lists etags in order');

// 失败路径：第二片挂 → abort 被调、错误透传
$events2 = [];
$r = Wpsss_Stream::upload(
    static function () {
        return 'upload-77';
    },
    static function (string $id, int $n, string $body) {
        return $n === 2 ? new WP_Error('wpsss_part_fail', '第二片失败。') : 'etag-' . $n;
    },
    static function () {
        return true;
    },
    static function (string $id) use (&$events2) {
        $events2[] = 'abort:' . $id;
    },
    $src
);
wpsss_assert(is_wp_error($r) && $r->get_error_code() === 'wpsss_part_fail', 'part failure propagates');
wpsss_assert($events2 === ['abort:upload-77'], 'part failure aborts the upload');

// 小文件（低于一片）只有一片
$small = stream_temp('up-small.bin');
file_put_contents($small, 'tiny');
$count = 0;
$r = Wpsss_Stream::upload(
    static function () {
        return 'up-1';
    },
    static function (string $id, int $n, string $body) use (&$count) {
        $count++;
        return 'e';
    },
    static function () {
        return true;
    },
    static function () {
    },
    $small
);
wpsss_assert($r === true && $count === 1, 'small file uploads as a single part');

// ===== parse_upload_id / complete_xml / put_timeout =====
wpsss_assert(Wpsss_Stream::parse_upload_id('<InitiateMultipartUploadResult><Bucket>b</Bucket><UploadId>abc//123</UploadId></InitiateMultipartUploadResult>') === 'abc//123', 'upload id parsed');
wpsss_assert(Wpsss_Stream::parse_upload_id('<Error><Code>X</Code></Error>') === '', 'upload id absent on error body');
wpsss_assert(Wpsss_Stream::put_timeout(1024) === 60, 'small upload keeps base timeout');
wpsss_assert(Wpsss_Stream::put_timeout(200 * 1048576) === 130, 'large upload scales timeout');

// ===== 接线存在性 =====
$offload_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-offload.php');
wpsss_assert(strpos($offload_src, 'Wpsss_Stream::download') !== false, 'pull path streams via engine');
foreach (['aliyun-oss', 's3-compat', 'qcloud-cos', 'huawei-obs', 'qiniu-kodo'] as $slug) {
    $src = (string) file_get_contents(dirname(__DIR__) . '/includes/drivers/class-wpsss-driver-' . $slug . '.php');
    wpsss_assert(strpos($src, 'function get_object_range') !== false, $slug . ' implements range get');
}
$oss_src = (string) file_get_contents(dirname(__DIR__) . '/includes/drivers/class-wpsss-driver-aliyun-oss.php');
wpsss_assert(strpos($oss_src, 'put_object_multipart') !== false, 'oss driver has multipart upload');
$s3_src = (string) file_get_contents(dirname(__DIR__) . '/includes/drivers/class-wpsss-driver-s3-compat.php');
wpsss_assert(strpos($s_src_dummy ?? $s3_src, 'put_object_multipart') !== false, 's3 driver has multipart upload');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
