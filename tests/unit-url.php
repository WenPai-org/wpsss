<?php
define('WPSSS_TEST', true);
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
wpsss_assert($url === 'https://cdn.example.com/a.jpg?x-oss-process=style/blog', 'append style once');
wpsss_assert(Wpsss_Url::apply_style($url, $opts) === $url, 'do not double style');

$text = Wpsss_Url::rewrite_text(
    '见 https://site.example/wp-content/uploads/2026/a.jpg 和别的',
    'https://site.example/wp-content/uploads',
    'https://cdn.example.com',
    '!blog'
);
wpsss_assert(strpos($text, 'https://cdn.example.com/2026/a.jpg!blog') !== false, 'runtime rewrite + style');
wpsss_assert(substr_count($text, '!blog') === 1, 'style once in content');

$preview = Wpsss_Url::apply_preview(
    'https://bucket.cos.ap-guangzhou.myqcloud.com/a.pdf',
    'application/pdf',
    ['ci_preview' => '1']
);
wpsss_assert(strpos($preview, 'ci-process=doc-preview') !== false, 'ci preview on pdf');
wpsss_assert(
    Wpsss_Url::apply_preview('https://x/a.jpg', 'image/jpeg', ['ci_preview' => '1']) === 'https://x/a.jpg',
    'ci preview skips image'
);

$srcset = Wpsss_Url::style_srcset(
    [150 => ['url' => 'https://cdn.example.com/a-150.jpg']],
    '!s'
);
wpsss_assert($srcset[150]['url'] === 'https://cdn.example.com/a-150.jpg!s', 'srcset style');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
