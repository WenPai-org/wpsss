<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';

if (!function_exists('is_serialized')) {
    function is_serialized($data, $strict = true): bool
    {
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ($data === 'N;') {
            return true;
        }
        if (strlen($data) < 4 || $data[1] !== ':') {
            return false;
        }
        $last = substr($data, -1);
        if ($strict && $last !== ';' && $last !== '}') {
            return false;
        }
        $token = $data[0];
        if ($token === 's' || $token === 'a' || $token === 'O') {
            return (bool) preg_match('/^' . $token . ':[0-9]+:/s', $data);
        }
        if ($token === 'b' || $token === 'i' || $token === 'd') {
            return (bool) preg_match('/^' . $token . ':[0-9.E+-]+;/', $data);
        }
        return false;
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-cos-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-kodo-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-obs-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-url.php';

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

$opts = ['image_style' => '?x-oss-process=style/blog', 'origin_protect' => '1', 'driver' => 'aliyun-oss'];
wpsss_assert(Wpsss_Url::style_suffix($opts) === '?x-oss-process=style/blog', 'style suffix');
wpsss_assert(Wpsss_Url::should_protect_origin($opts), 'origin protect on');
$url = Wpsss_Url::apply_style('https://cdn.example.com/a.jpg', $opts);
wpsss_assert($url === 'https://cdn.example.com/a.jpg?x-oss-process=style/blog', 'jpg append style');
wpsss_assert(Wpsss_Url::apply_style($url, $opts) === $url, 'do not double style');
wpsss_assert(
    Wpsss_Url::apply_style('https://cdn.example.com/a.gif', $opts) === 'https://cdn.example.com/a.gif',
    'gif url no style'
);
wpsss_assert(
    Wpsss_Url::apply_style('https://cdn.example.com/a.svg', $opts) === 'https://cdn.example.com/a.svg',
    'svg url no style'
);
wpsss_assert(!Wpsss_Url::is_styleable('https://cdn.example.com/a.jpg', 'image/gif'), 'image/gif mime not styleable');
wpsss_assert(!Wpsss_Url::is_styleable('', 'image/jpeg'), 'empty url not styleable');
wpsss_assert(
    Wpsss_Url::apply_style('https://cdn.example.com/a.bin', $opts, 'image/gif') === 'https://cdn.example.com/a.bin',
    'gif mime skips style even without .gif'
);
$signed_then_style = Wpsss_Url::apply_style(
    'https://cdn.example.com/a.jpg?OSSAccessKeyId=ak&Expires=1&Signature=x',
    $opts
);
wpsss_assert(strpos($signed_then_style, '??') === false, 'style after query does not use second ?');
wpsss_assert(strpos($signed_then_style, '&x-oss-process=style/blog') !== false, 'style joins signed query with &');
wpsss_assert(Wpsss_Url::oss_process_param('?x-oss-process=style/blog') === 'style/blog', 'oss process from style');

$cos_slim = ['image_style' => '!s', 'driver' => 'qcloud-cos', 'ci_slim' => '1'];
wpsss_assert(
    strpos(Wpsss_Url::apply_style('https://cdn.example.com/a.gif', $cos_slim), 'imageMogr2') === false,
    'gif skips imageMogr2'
);
wpsss_assert(
    strpos(Wpsss_Url::apply_style('https://cdn.example.com/a.jpg', $cos_slim), 'imageMogr2/format/webp') !== false,
    'jpg may ci_slim'
);

$text = Wpsss_Url::rewrite_text(
    '见 https://site.example/wp-content/uploads/2026/a.jpg 和 https://site.example/wp-content/uploads/2026/a.gif',
    'https://site.example/wp-content/uploads',
    'https://cdn.example.com',
    '!blog'
);
wpsss_assert(strpos($text, 'https://cdn.example.com/2026/a.jpg!blog') !== false, 'rewrite_text jpg + style');
wpsss_assert(strpos($text, 'https://cdn.example.com/2026/a.gif') !== false, 'rewrite_text keeps gif host');
wpsss_assert(strpos($text, 'a.gif!blog') === false, 'rewrite_text gif no style');

$srcset = Wpsss_Url::style_srcset(
    [
        150 => ['url' => 'https://cdn.example.com/a-150.jpg'],
        300 => ['url' => 'https://cdn.example.com/a.gif'],
    ],
    '!s'
);
wpsss_assert($srcset[150]['url'] === 'https://cdn.example.com/a-150.jpg!s', 'srcset style jpg');
wpsss_assert($srcset[300]['url'] === 'https://cdn.example.com/a.gif', 'srcset skip gif');

$preview = Wpsss_Url::apply_preview(
    'https://bucket.cos.ap-guangzhou.myqcloud.com/a.pdf',
    'application/pdf',
    ['ci_preview' => '1']
);
wpsss_assert(strpos($preview, 'ci-process=doc-preview') !== false, 'ci preview on pdf');

$js = Wpsss_Url::rewrite_attachment_js(
    [
        'url' => 'https://site.example/wp-content/uploads/2026/09/a.jpg',
        'title' => 'keep-me',
        'sizes' => [
            'thumbnail' => [
                'url' => 'https://site.example/wp-content/uploads/2026/09/a-150x150.jpg?ver=1',
                'width' => 150,
            ],
        ],
        'icon' => 'https://site.example/wp-includes/images/media/default.png',
    ],
    'https://cdn.example.com/2026/09/a.jpg'
);
wpsss_assert($js['url'] === 'https://cdn.example.com/2026/09/a.jpg', 'attachment js url');
wpsss_assert(
    $js['sizes']['thumbnail']['url'] === 'https://cdn.example.com/2026/09/a-150x150.jpg?ver=1',
    'attachment js sizes.url'
);
wpsss_assert($js['title'] === 'keep-me', 'attachment js keeps other keys');
wpsss_assert(
    $js['icon'] === 'https://site.example/wp-includes/images/media/default.png',
    'attachment js leaves uncertain icon'
);
$js_uploads_icon = Wpsss_Url::rewrite_attachment_js(
    [
        'url' => 'https://site.example/wp-content/uploads/2026/09/a.jpg',
        'icon' => 'https://site.example/wp-content/uploads/2026/09/a-150x150.jpg',
    ],
    'https://cdn.example.com/2026/09/a.jpg'
);
wpsss_assert(
    $js_uploads_icon['icon'] === 'https://cdn.example.com/2026/09/a-150x150.jpg',
    'attachment js rewrites uploads icon'
);

$kodo = Wpsss_Kodo_Signer::private_download_url(
    'ak',
    'sk',
    'https://cdn.example/2026/09/a.png',
    3600,
    1700000000
);
wpsss_assert(
    strpos($kodo, 'https://cdn.example/2026/09/a.png?e=1700003600&token=ak:') === 0,
    'kodo private download prefix'
);

$obs1 = Wpsss_Obs_Signer::signed_get_url(
    'https://cdn.example',
    'demo-bucket',
    '2026/09/a.png',
    'ak',
    'sk',
    3600,
    1700000000
);
$obs2 = Wpsss_Obs_Signer::signed_get_url(
    'https://cdn.example',
    'demo-bucket',
    '2026/09/a.png',
    'ak',
    'sk',
    3600,
    1700000000
);
wpsss_assert(strpos($obs1, 'Signature=') !== false, 'obs signed_get_url Signature');
wpsss_assert($obs1 === $obs2, 'obs signed_get_url repeatable');

$cos1 = Wpsss_Cos_Signer::signed_get_url(
    'https://demo-1250000000.cos.ap-guangzhou.myqcloud.com',
    'demo-1250000000',
    '2026/09/a.png',
    'AKIDtest',
    'test-secret',
    3600,
    1700000000
);
$cos2 = Wpsss_Cos_Signer::signed_get_url(
    'https://demo-1250000000.cos.ap-guangzhou.myqcloud.com',
    'demo-1250000000',
    '2026/09/a.png',
    'AKIDtest',
    'test-secret',
    3600,
    1700000000
);
wpsss_assert(strpos($cos1, 'q-signature=') !== false, 'cos signed_get_url q-signature');
wpsss_assert($cos1 === $cos2, 'cos signed_get_url repeatable');

$sign_base = [
    'url_access' => 'signed',
    'bucket' => 'demo-bucket',
    'access_key_id' => 'ak',
    'access_key_secret' => 'sk',
    'signed_ttl' => 3600,
];
$cos_signed = Wpsss_Url::sign_object_url(
    'https://demo-1250000000.cos.ap-guangzhou.myqcloud.com',
    '2026/09/a.png',
    $sign_base + ['driver' => 'qcloud-cos', 'access_key_id' => 'AKIDtest', 'access_key_secret' => 'test-secret'],
    1700000000
);
wpsss_assert(strpos($cos_signed, 'q-signature=') !== false, 'sign_object_url cos');
$kodo_signed = Wpsss_Url::sign_object_url(
    'https://cdn.example',
    '2026/09/a.png',
    $sign_base + ['driver' => 'qiniu-kodo'],
    1700000000
);
wpsss_assert(
    strpos($kodo_signed, 'https://cdn.example/2026/09/a.png?e=1700003600&token=ak:') === 0,
    'sign_object_url kodo'
);
$obs_signed = Wpsss_Url::sign_object_url(
    'https://cdn.example',
    '2026/09/a.png',
    $sign_base + ['driver' => 'huawei-obs'],
    1700000000
);
wpsss_assert(strpos($obs_signed, 'Signature=') !== false, 'sign_object_url obs');

$oss_styled = Wpsss_Url::serve_object_url(
    'https://cdn.example.com',
    '2026/09/a.png',
    $sign_base + [
        'driver' => 'aliyun-oss',
        'image_style' => '?x-oss-process=style/blog',
    ],
    'image/jpeg',
    1700000000
);
wpsss_assert(strpos($oss_styled, 'x-oss-process=style') !== false, 'serve_object_url oss process');
wpsss_assert(strpos($oss_styled, 'Signature=') !== false, 'serve_object_url oss still signed');
wpsss_assert(strpos($oss_styled, '??') === false, 'serve_object_url no double question');
$oss_plain = Wpsss_Oss_Signer::signed_get_url(
    'https://cdn.example.com',
    'demo-bucket',
    '2026/09/a.png',
    'ak',
    'sk',
    3600,
    1700000000,
    'style/blog'
);
$oss_plain2 = Wpsss_Oss_Signer::signed_get_url(
    'https://cdn.example.com',
    'demo-bucket',
    '2026/09/a.png',
    'ak',
    'sk',
    3600,
    1700000000,
    'style/blog'
);
wpsss_assert($oss_plain === $oss_plain2, 'oss signed process repeatable');
wpsss_assert(strpos($oss_plain, 'x-oss-process=style%2Fblog') !== false || strpos($oss_plain, 'x-oss-process=style/blog') !== false, 'oss signed process query');

$plain = Wpsss_Url::rewrite_maybe_serialized('see http://old/a.jpg', static function (string $text): string {
    return str_replace('http://old', 'http://new', $text);
});
wpsss_assert($plain === 'see http://new/a.jpg', 'rewrite_maybe_serialized plain');

$serialized = serialize(['u' => 'http://old/a.jpg']);
$rewritten = Wpsss_Url::rewrite_maybe_serialized($serialized, static function (string $text): string {
    return str_replace('http://old', 'http://new', $text);
});
$unpacked = unserialize($rewritten);
wpsss_assert(is_array($unpacked) && ($unpacked['u'] ?? '') === 'http://new/a.jpg', 'rewrite_maybe_serialized unserialize');
wpsss_assert($rewritten === serialize(['u' => 'http://new/a.jpg']), 'rewrite_maybe_serialized length');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
