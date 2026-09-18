<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
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
    Wpsss_Offload::key_from_uploads('/var/www/uploads/2026/09/a.png', '/var/www/uploads') === '2026/09/a.png',
    'key under uploads'
);
wpsss_assert(
    Wpsss_Offload::key_from_uploads('/var/www/uploads/2026/09/a.png', '/var/www/uploads/') === '2026/09/a.png',
    'key with trailing slash basedir'
);
wpsss_assert(
    Wpsss_Offload::key_from_uploads('/tmp/a.png', '/var/www/uploads') === '',
    'key outside uploads empty'
);
wpsss_assert(
    Wpsss_Offload::key_from_uploads('/var/www/uploads-other/a.png', '/var/www/uploads') === '',
    'key does not prefix-match sibling dir'
);

$keys = Wpsss_Offload::keys_from_metadata([
    'file' => '2026/09/pic.jpg',
    'original_image' => 'pic.jpg',
    'sizes' => [
        'thumbnail' => ['file' => 'pic-150x150.jpg'],
        'medium' => ['file' => 'pic-300x200.jpg'],
    ],
    'backup_sizes' => [
        ['file' => 'pic-backup.jpg'],
    ],
]);
wpsss_assert($keys[0] === '2026/09/pic.jpg', 'primary key first');
wpsss_assert(in_array('2026/09/pic.jpg', $keys, true), 'includes original_image with prefix');
wpsss_assert(in_array('2026/09/pic-150x150.jpg', $keys, true), 'includes thumb');
wpsss_assert(in_array('2026/09/pic-backup.jpg', $keys, true), 'includes backup size');
wpsss_assert(Wpsss_Offload::keys_from_metadata([]) === [], 'empty meta');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
