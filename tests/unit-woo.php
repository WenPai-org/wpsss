<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-s3-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-url.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-offload.php';
require dirname(__DIR__) . '/includes/class-wpsss-woo.php';

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

function wp_upload_dir(): array
{
    return [
        'basedir' => '/var/www/uploads',
        'baseurl' => 'https://shop.example/wp-content/uploads',
    ];
}

$abs = Wpsss_Woo::absolute_uploads_path(
    'https://shop.example/wp-content/uploads/2026/09/book.pdf',
    '/var/www/uploads',
    'https://shop.example/wp-content/uploads'
);
wpsss_assert($abs === '/var/www/uploads/2026/09/book.pdf', 'url to uploads path');
wpsss_assert(
    Wpsss_Woo::absolute_uploads_path('2026/09/book.pdf', '/var/www/uploads', 'https://shop.example/wp-content/uploads') === '/var/www/uploads/2026/09/book.pdf',
    'relative download path'
);
wpsss_assert(
    Wpsss_Woo::absolute_uploads_path('https://other.example/a.pdf', '/var/www/uploads', 'https://shop.example/wp-content/uploads') === '',
    'foreign url ignored'
);

$opts = Wpsss_Options::sanitize([
    'driver' => 'aliyun-oss',
    'bucket' => 'wenpai-wpsss-test',
    'region' => 'oss-cn-hangzhou',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'keep_local' => '1',
    'url_access' => 'public',
]);
$out = Wpsss_Woo::rewrite_file('/var/www/uploads/2026/09/book.pdf', $opts);
wpsss_assert(strpos($out, 'wenpai-wpsss-test.oss-cn-hangzhou.aliyuncs.com/2026/09/book.pdf') !== false, 'woo file to oss url');

$opts['url_access'] = 'signed';
$signed = Wpsss_Woo::rewrite_file('https://shop.example/wp-content/uploads/2026/09/book.pdf', $opts);
wpsss_assert(strpos($signed, 'Expires=') !== false || strpos($signed, 'x-oss-') !== false, 'woo signed get');

wpsss_assert(Wpsss_Woo::download_method('force', 'https://bucket.example/a.pdf') === 'redirect', 'remote uses redirect');
wpsss_assert(Wpsss_Woo::download_method('force', '/var/www/uploads/a.pdf') === 'force', 'local keeps method');
wpsss_assert(Wpsss_Woo::is_active() === false, 'woo absent in unit');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
