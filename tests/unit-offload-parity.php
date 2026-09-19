<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
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

class FakeDriver
{
    public function is_configured($o)
    {
        return true;
    }
}

$meta = [
    'file' => '2026/09/a.jpg',
    'original_image' => 'a-original.jpg',
    'sizes' => [
        'medium' => ['file' => 'a-300x200.jpg'],
        'webp' => ['file' => 'image.webp'],
        'heic' => ['file' => 'image.heic'],
    ],
    'backup_sizes' => [
        'full-orig' => ['file' => 'a-backup.jpg'],
    ],
];
$keys = Wpsss_Offload::keys_from_metadata($meta);
wpsss_assert(in_array('2026/09/a.jpg', $keys, true), 'keys primary');
wpsss_assert(in_array('2026/09/a-original.jpg', $keys, true), 'keys original_image');
wpsss_assert(in_array('2026/09/a-300x200.jpg', $keys, true), 'keys intermediate jpg');
wpsss_assert(in_array('2026/09/image.webp', $keys, true), 'keys webp size');
wpsss_assert(in_array('2026/09/image.heic', $keys, true), 'keys heic size');
wpsss_assert(in_array('2026/09/a-backup.jpg', $keys, true), 'keys backup_sizes');

$flat = Wpsss_Offload::keys_from_metadata([
    'file' => 'justname.jpg',
    'original_image' => 'orig.jpg',
    'sizes' => [
        'webp' => ['file' => 'image.webp'],
        'heic' => ['file' => 'image.heic'],
    ],
]);
wpsss_assert(in_array('justname.jpg', $flat, true), 'flat primary');
wpsss_assert(in_array('orig.jpg', $flat, true), 'flat original');
wpsss_assert(in_array('image.webp', $flat, true), 'flat webp');
wpsss_assert(in_array('image.heic', $flat, true), 'flat heic');
wpsss_assert(strpos(implode("\n", $flat), './') === false, 'flat keys have no ./');

wpsss_assert(Wpsss_Offload::safe_upload_url_path('.') === '', 'safe path dot');
wpsss_assert(Wpsss_Offload::safe_upload_url_path('') === '', 'safe path empty');
wpsss_assert(Wpsss_Offload::safe_upload_url_path('https://cdn.example.com/') === 'https://cdn.example.com', 'safe path https');
wpsss_assert(Wpsss_Offload::safe_upload_url_path('https://cdn.example.com') === 'https://cdn.example.com', 'safe path https no slash');

wpsss_assert(
    Wpsss_Offload::object_key_for_url(
        'https://site.example/wp-content/uploads/2026/09/a-150x150.jpg',
        'wp/2026/09/a.jpg'
    ) === 'wp/2026/09/a-150x150.jpg',
    'thumb url keeps prefix and dir'
);
wpsss_assert(
    Wpsss_Offload::object_key_for_url(
        'https://site.example/wp-content/uploads/2026/09/a-original.jpg',
        '2026/09/a.jpg'
    ) === '2026/09/a-original.jpg',
    'original image sibling key'
);
wpsss_assert(
    Wpsss_Offload::object_key_for_url('', '2026/09/a.jpg') === '2026/09/a.jpg',
    'empty url falls back to primary'
);
wpsss_assert(
    Wpsss_Offload::object_key_for_url(
        'https://cdn.example.com/2026/09/a-150x150.jpg!s',
        '2026/09/a.jpg',
        '!s'
    ) === '2026/09/a-150x150.jpg',
    'strips cos style delimiter from basename'
);
wpsss_assert(Wpsss_Offload::object_key_for_url('https://x/a.jpg', '') === '', 'empty primary stays empty');

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false)
    {
        unset($post_id, $key, $single);
        return '';
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-library.php';
require dirname(__DIR__) . '/includes/class-wpsss-plugin.php';

$offload = new Wpsss_Offload(new Wpsss_Plugin());
wpsss_assert($offload->rewrite_intermediate('nope', 1, 'thumb') === 'nope', 'intermediate non-array');
$same = ['url' => 'http://local/a.jpg', 'file' => 'a.jpg'];
wpsss_assert($offload->rewrite_intermediate($same, 1, 'medium') === $same, 'intermediate not offloaded');
wpsss_assert($offload->rewrite_image_downsize(false, 1, 'thumbnail') === false, 'downsize not offloaded');
wpsss_assert($offload->rewrite_image_downsize(['keep'], 1, 'thumbnail') === ['keep'], 'downsize passthrough');
$icon = ['http://site.example/wp-includes/images/media/default.svg', 48, 64, false];
wpsss_assert($offload->rewrite_image_src($icon, 1, [60, 60], true) === $icon, 'image_src skips core icon');
wpsss_assert(Wpsss_Offload::url_is_ours('http://site.example/wp-includes/images/media/default.png') === false, 'core icon not ours');
wpsss_assert(Wpsss_Offload::url_is_ours('') === false, 'empty url not ours');
$signed_full = 'https://bucket.oss.example.com/2026/09/a.png?x-oss-signature=abc&x-oss-date=1';
$eaten = str_replace(basename($signed_full), 'a-150x150.png', $signed_full);
wpsss_assert($eaten === 'https://bucket.oss.example.com/2026/09/a-150x150.png', 'wp basename strip drops signature');

$offload_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-offload.php');
$media_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-media.php');
wpsss_assert((bool) preg_match('/wp_get_original_image_url/', $offload_src), 'original image hook in Offload');
wpsss_assert((bool) preg_match('/wp_get_attachment_thumb_url/', $offload_src), 'thumb url hook in Offload');
wpsss_assert((bool) preg_match('/image_get_intermediate_size/', $offload_src), 'intermediate hook in Offload');
wpsss_assert((bool) preg_match('/image_downsize/', $offload_src), 'image_downsize hook in Offload');
wpsss_assert((bool) preg_match('/wp_get_attachment_image_src/', $offload_src), 'image_src hook in Offload');
wpsss_assert((bool) preg_match('/wp_prepare_attachment_for_js/', $media_src), 'js hook in Media');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
