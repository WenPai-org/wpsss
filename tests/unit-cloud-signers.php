<?php
define('WPSSS_TEST', true);
require dirname(__DIR__) . '/includes/class-wpsss-cos-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-kodo-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-obs-signer.php';

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

$http = Wpsss_Cos_Signer::http_string(
    'PUT',
    '2026/09/hello.txt',
    [
        'Host' => 'demo-1250000000.cos.ap-guangzhou.myqcloud.com',
        'Content-Type' => 'text/plain',
    ],
    []
);
wpsss_assert(strpos($http, "put\n/2026/09/hello.txt\n") === 0, 'cos http method path');
wpsss_assert(strpos($http, 'content-type=text%2Fplain') !== false, 'cos headers lower');
wpsss_assert(strpos($http, 'host=demo-1250000000.cos.ap-guangzhou.myqcloud.com') !== false, 'cos host signed');

$auth = Wpsss_Cos_Signer::authorization(
    'AKIDtest',
    'test-secret',
    'PUT',
    '2026/09/hello.txt',
    [
        'Host' => 'demo-1250000000.cos.ap-guangzhou.myqcloud.com',
        'Content-Type' => 'text/plain',
    ],
    [],
    1600000000,
    1600003600
);
wpsss_assert(strpos($auth, 'q-sign-algorithm=sha1') === 0, 'cos auth prefix');
wpsss_assert(strpos($auth, 'q-ak=AKIDtest') !== false, 'cos ak');
wpsss_assert(strpos($auth, 'q-sign-time=1600000000;1600003600') !== false, 'cos time');
wpsss_assert(preg_match('/q-signature=[0-9a-f]{40}/', $auth) === 1, 'cos sha1 hex');

$token = Wpsss_Kodo_Signer::upload_token('testid', 'test-secret', [
    'scope' => 'demo:hello.txt',
    'deadline' => 1600003600,
]);
$expected = 'testid:nXC0VUSUgWoSHMSmA9NALCDJ_qA=:eyJzY29wZSI6ImRlbW86aGVsbG8udHh0IiwiZGVhZGxpbmUiOjE2MDAwMDM2MDB9';
wpsss_assert($token === $expected, 'kodo upload token');
wpsss_assert(Wpsss_Kodo_Signer::encoded_entry('demo', 'a/b') === strtr(base64_encode('demo:a/b'), '+/', '-_'), 'kodo entry');
$qbox = Wpsss_Kodo_Signer::qbox('testid', 'test-secret', '/delete/xxx');
wpsss_assert(strpos($qbox, 'QBox testid:') === 0, 'kodo qbox prefix');

$canonical = Wpsss_Obs_Signer::canonical_string(
    'PUT',
    'XUFAKrxLKna5cZ2REBfFkg==',
    'text/plain',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '2026/09/hello.txt'
);
wpsss_assert(strpos($canonical, "PUT\n") === 0, 'obs verb');
$obs = Wpsss_Obs_Signer::authorization(
    'testid',
    'test-secret',
    'PUT',
    'XUFAKrxLKna5cZ2REBfFkg==',
    'text/plain',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '2026/09/hello.txt'
);
wpsss_assert($obs === 'OBS testid:6gzjr5JR3rsI38e5BJcIRiRkI1o=', 'obs authorization');
$obs_loc = Wpsss_Obs_Signer::canonical_string(
    'GET',
    '',
    '',
    'Fri, 18 Sep 2026 08:00:00 GMT',
    'demo-bucket',
    '',
    [],
    'acl'
);
wpsss_assert($obs_loc === "GET\n\n\nFri, 18 Sep 2026 08:00:00 GMT\n/demo-bucket/?acl", 'obs canonical acl');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
