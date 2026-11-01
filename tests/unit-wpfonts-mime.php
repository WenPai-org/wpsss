<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require dirname(__DIR__) . '/includes/class-wpsss-mime.php';
require dirname(__DIR__) . '/includes/class-wpsss-wpfonts.php';

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

$tmp = sys_get_temp_dir() . '/wpsss-mime-' . getmypid();
@mkdir($tmp);
file_put_contents($tmp . '/x.css', 'body{}');
file_put_contents($tmp . '/x.woff2', 'w');
file_put_contents($tmp . '/x.json', '{}');
file_put_contents($tmp . '/x.bin', 'b');

wpsss_assert(Wpsss_Mime::for_file($tmp . '/x.css', 'wpfonts/packages/a/local.css') === 'text/css', 'css mime');
wpsss_assert(Wpsss_Mime::for_file($tmp . '/x.woff2', 'a/0.woff2') === 'font/woff2', 'woff2 mime');
wpsss_assert(Wpsss_Mime::for_file($tmp . '/x.json', 'a/catalog.json') === 'application/json', 'json mime');
wpsss_assert(Wpsss_Mime::for_file($tmp . '/x.bin', 'a/x.bin') === 'application/octet-stream' || Wpsss_Mime::for_file($tmp . '/x.bin', 'a/x.bin') !== 'text/css', 'bin not css');

wpsss_assert(Wpsss_Wpfonts::normalize_payload('nope') === null, 'reject non-array');
wpsss_assert(Wpsss_Wpfonts::normalize_payload(['slug' => 'x', 'uploads_rel' => '']) !== null, 'default uploads_rel from slug');
$bad = Wpsss_Wpfonts::normalize_payload(['slug' => 'good', 'uploads_rel' => 'wpfonts/../etc']);
wpsss_assert($bad === null, 'reject .. in uploads_rel');

$n = Wpsss_Wpfonts::normalize_payload([
    'action' => 'install',
    'slug' => 'wenfeng-syhtcjk',
    'package_id' => 'wenfeng-syhtcjk',
    'dir' => $tmp,
    'uploads_rel' => 'wpfonts/packages/wenfeng-syhtcjk',
    'local_css' => $tmp . '/local.css',
    'files' => ['local.css', '0.woff2', '../nope'],
]);
wpsss_assert(is_array($n), 'normalize ok');
wpsss_assert($n['uploads_rel'] === 'wpfonts/packages/wenfeng-syhtcjk', 'uploads_rel');
$files = Wpsss_Wpfonts::resolve_files($n);
wpsss_assert($files === ['0.woff2', 'local.css'] || $files === ['local.css', '0.woff2'], 'files cleaned ' . json_encode($files));
wpsss_assert(!in_array('../nope', $files, true) && !in_array('nope', $files, true), 'no traversal');

file_put_contents($tmp . '/local.css', '/*x*/');
file_put_contents($tmp . '/0.woff2', 'w');
$scan = Wpsss_Wpfonts::resolve_files([
    'dir' => $tmp,
    'files' => [],
    'local_css' => '',
]);
wpsss_assert(in_array('local.css', $scan, true) && in_array('0.woff2', $scan, true), 'scan dir');
wpsss_assert(Wpsss_Wpfonts::clean_rel('a/b.css') === 'a/b.css', 'clean ok');
wpsss_assert(Wpsss_Wpfonts::clean_rel('../x') === '', 'clean blocks ..');

@unlink($tmp . '/x.css');
@unlink($tmp . '/x.woff2');
@unlink($tmp . '/x.json');
@unlink($tmp . '/x.bin');
@unlink($tmp . '/local.css');
@unlink($tmp . '/0.woff2');
@rmdir($tmp);

exit($failed > 0 ? 1 : 0);
