<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-kodo-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
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

wpsss_assert(
    Wpsss_Offload::local_rel_from_object_key('media/2026/09/a.png', ['object_prefix' => 'media']) === '2026/09/a.png',
    'strip prefix'
);
wpsss_assert(
    Wpsss_Offload::local_rel_from_object_key('2026/09/a.png', ['object_prefix' => '']) === '2026/09/a.png',
    'no prefix'
);
wpsss_assert(
    Wpsss_Offload::local_rel_from_object_key('media/2026/09/a.png', ['object_prefix' => 'other']) === 'media/2026/09/a.png',
    'other prefix stays'
);

$url = Wpsss_Kodo_Signer::private_download_url(
    'ak',
    'sk',
    'https://cdn.example/2026/09/a.png',
    3600,
    1700000000
);
wpsss_assert(strpos($url, 'https://cdn.example/2026/09/a.png?e=1700003600&token=ak:') === 0, 'kodo private download');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
