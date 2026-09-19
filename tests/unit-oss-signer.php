<?php

define('WPSSS_TEST', true);
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';

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

$md5 = Wpsss_Oss_Signer::content_md5('hello');
wpsss_assert($md5 === 'XUFAKrxLKna5cZ2REBfFkg==', 'content_md5 hello');

$canonical = Wpsss_Oss_Signer::canonical_string(
    'PUT',
    $md5,
    'text/plain',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '2026/09/hello.txt'
);
$expected_canonical = "PUT\nXUFAKrxLKna5cZ2REBfFkg==\ntext/plain\nFri, 18 Sep 2026 08:00:00 GMT\n/demo-bucket/2026/09/hello.txt";
wpsss_assert($canonical === $expected_canonical, 'canonical string');

$sig = Wpsss_Oss_Signer::signature('test-secret', $canonical);
wpsss_assert($sig === '6gzjr5JR3rsI38e5BJcIRiRkI1o=', 'hmac signature');

$auth = Wpsss_Oss_Signer::authorization(
    'testid',
    'test-secret',
    'PUT',
    $md5,
    'text/plain',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '2026/09/hello.txt'
);
wpsss_assert($auth === 'OSS testid:6gzjr5JR3rsI38e5BJcIRiRkI1o=', 'authorization header');

$url = Wpsss_Oss_Signer::object_url('https://cdn.example.com', '2026/09/图片.png');
wpsss_assert(strpos($url, 'https://cdn.example.com/2026/09/') === 0, 'object url host');
wpsss_assert(substr_count($url, ' ') === 0, 'object url encoded');

$canonical_acl = Wpsss_Oss_Signer::canonical_string(
    'PUT',
    $md5,
    'text/plain',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '2026/09/hello.txt',
    ['x-oss-object-acl' => 'public-read']
);
$expected_acl = "PUT\nXUFAKrxLKna5cZ2REBfFkg==\ntext/plain\nFri, 18 Sep 2026 08:00:00 GMT\nx-oss-object-acl:public-read\n/demo-bucket/2026/09/hello.txt";
wpsss_assert($canonical_acl === $expected_acl, 'canonical includes object acl');
$auth_acl = Wpsss_Oss_Signer::authorization(
    'testid',
    'test-secret',
    'PUT',
    $md5,
    'text/plain',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '2026/09/hello.txt',
    ['X-OSS-Object-Acl' => 'public-read']
);
wpsss_assert($auth_acl === 'OSS testid:e6Zvqd+dZVmCKJmymanLVPurcro=', 'authorization with acl header');

$canonical_loc = Wpsss_Oss_Signer::canonical_string(
    'GET',
    '',
    '',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '',
    [],
    'location'
);
wpsss_assert($canonical_loc === "GET\n\n\nFri, 18 Sep 2026 08:00:00 GMT\n/demo-bucket/?location", 'canonical location');

$signed_style = Wpsss_Oss_Signer::signed_get_url(
    'https://cdn.example.com',
    'demo-bucket',
    '2026/09/a.png',
    'ak',
    'sk',
    3600,
    1700000000,
    'style/blog'
);
wpsss_assert(strpos($signed_style, 'Signature=') !== false, 'signed get with process has signature');
wpsss_assert(strpos($signed_style, 'x-oss-process=') !== false, 'signed get with process query');
wpsss_assert(strpos($signed_style, '??') === false, 'signed get with process single ?');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
