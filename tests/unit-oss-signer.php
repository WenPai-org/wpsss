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

wpsss_assert(Wpsss_Oss_Signer::sign_region('oss-cn-hangzhou') === 'cn-hangzhou', 'sign region strips oss-');
wpsss_assert(Wpsss_Oss_Signer::sign_region('oss-accelerate') === 'cn-hangzhou', 'accelerate region fallback');
wpsss_assert(Wpsss_Oss_Signer::sign_region('cn-shanghai') === 'cn-shanghai', 'bare region stays');

$official_canonical = Wpsss_Oss_Signer::v4_canonical_request(
    'PUT',
    '/examplebucket/exampleobject',
    '',
    [
        'content-disposition' => 'attachment',
        'content-length' => '3',
        'content-md5' => 'ICy5YqxZB1uWSwcVLSNLcA==',
        'content-type' => 'text/plain',
        'x-oss-content-sha256' => 'UNSIGNED-PAYLOAD',
        'x-oss-date' => '20250411T064124Z',
    ],
    ['content-disposition', 'content-length']
);
wpsss_assert(
    hash('sha256', $official_canonical) === 'c46d96390bdbc2d739ac9363293ae9d710b14e48081fcb22cd8ad54b63136eca',
    'official v4 canonical hash'
);
$official_sts = Wpsss_Oss_Signer::v4_string_to_sign(
    '20250411T064124Z',
    '20250411/cn-hangzhou/oss/aliyun_v4_request',
    $official_canonical
);
wpsss_assert(
    strpos($official_sts, "OSS4-HMAC-SHA256\n20250411T064124Z\n") === 0,
    'official v4 string-to-sign prefix'
);

$iso = '20250411T064124Z';
$v4_headers = Wpsss_Oss_Signer::v4_sign_headers(
    'testid',
    'test-secret',
    'PUT',
    'demo-bucket',
    '2026/09/hello.txt',
    'cn-hangzhou',
    $iso,
    [
        'Host' => 'demo-bucket.oss-cn-hangzhou.aliyuncs.com',
        'Content-Type' => 'text/plain',
        'x-oss-object-acl' => 'public-read',
    ]
);
wpsss_assert(strpos($v4_headers['Authorization'], 'OSS4-HMAC-SHA256 Credential=testid/20250411/cn-hangzhou/oss/aliyun_v4_request') === 0, 'v4 auth prefix');
wpsss_assert(strpos($v4_headers['Authorization'], 'AdditionalHeaders=host') !== false, 'v4 additional host');
wpsss_assert(preg_match('/Signature=[0-9a-f]{64}$/', $v4_headers['Authorization']) === 1, 'v4 signature hex');
$v4_headers2 = Wpsss_Oss_Signer::v4_sign_headers(
    'testid',
    'test-secret',
    'PUT',
    'demo-bucket',
    '2026/09/hello.txt',
    'cn-hangzhou',
    $iso,
    [
        'Host' => 'demo-bucket.oss-cn-hangzhou.aliyuncs.com',
        'Content-Type' => 'text/plain',
        'x-oss-object-acl' => 'public-read',
    ]
);
wpsss_assert($v4_headers['Authorization'] === $v4_headers2['Authorization'], 'v4 auth repeatable');

$v4url = Wpsss_Oss_Signer::v4_signed_get_url(
    'https://cdn.example.com',
    'demo-bucket',
    '2026/09/a.png',
    'ak',
    'sk',
    'cn-hangzhou',
    3600,
    1700000000,
    'style/blog'
);
$v4url2 = Wpsss_Oss_Signer::v4_signed_get_url(
    'https://cdn.example.com',
    'demo-bucket',
    '2026/09/a.png',
    'ak',
    'sk',
    'cn-hangzhou',
    3600,
    1700000000,
    'style/blog'
);
wpsss_assert($v4url === $v4url2, 'v4 signed url repeatable');
wpsss_assert(strpos($v4url, 'x-oss-signature-version=OSS4-HMAC-SHA256') !== false, 'v4 signed url version');
wpsss_assert(strpos($v4url, 'x-oss-process=style') !== false, 'v4 signed url process');
wpsss_assert(strpos($v4url, 'x-oss-signature=') !== false, 'v4 signed url signature');
wpsss_assert(strpos($v4url, '??') === false, 'v4 signed url single ?');
wpsss_assert(Wpsss_Oss_Signer::v4_canonical_query(['acl' => '']) === 'acl', 'v4 query name-only');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
