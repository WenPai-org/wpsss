<?php
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-s3-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';

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

// AWS docs: header-based auth GET Object, 2013-05-24.
$access = 'AKIAIOSFODNN7EXAMPLE';
$secret = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
$amz_date = '20130524T000000Z';
$headers = [
    'host' => 'examplebucket.s3.amazonaws.com',
    'range' => 'bytes=0-9',
    'x-amz-content-sha256' => Wpsss_S3_Signer::EMPTY_SHA256,
    'x-amz-date' => $amz_date,
];
$signed = Wpsss_S3_Signer::sign(
    $access,
    $secret,
    'GET',
    '/test.txt',
    '',
    $headers,
    Wpsss_S3_Signer::EMPTY_SHA256,
    $amz_date,
    'us-east-1'
);

wpsss_assert($signed['signature'] === 'f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41', 'aws get object signature');
wpsss_assert(strpos($signed['authorization'], 'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date') !== false, 'signed header order');
wpsss_assert(strpos($signed['string_to_sign'], '20130524/us-east-1/s3/aws4_request') !== false, 'credential scope');
wpsss_assert(Wpsss_S3_Signer::payload_hash('') === Wpsss_S3_Signer::EMPTY_SHA256, 'empty payload hash');
wpsss_assert(Wpsss_S3_Signer::encode_key('wpsss-probe/a b.txt') === 'wpsss-probe/a%20b.txt', 'encodes space in key');
wpsss_assert(Wpsss_S3_Signer::canonical_uri('folder/file.txt') === '/folder/file.txt', 'canonical uri adds slash');

$GLOBALS['wpsss_options_store'] = [];
$tos = Wpsss_Options::sanitize([
    'driver' => 'volc-tos',
    'bucket' => 'demo-tos',
    'region' => 'cn-beijing',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'keep_local' => '1',
]);
wpsss_assert($tos['driver'] === 'volc-tos', 'sanitize keeps tos card');
wpsss_assert($tos['s3_preset'] === 'tos', 'tos card locks preset');
wpsss_assert(Wpsss_Options::effective_s3_preset($tos) === 'tos', 'effective preset tos');
wpsss_assert(Wpsss_Options::s3_endpoint($tos) === 'https://tos-s3-cn-beijing.volces.com', 'tos default endpoint');
$tos_req = Wpsss_Options::s3_object_request($tos, '2026/a.png');
wpsss_assert($tos_req['host'] === 'demo-tos.tos-s3-cn-beijing.volces.com', 'tos virtual host');
wpsss_assert($tos_req['canonical_uri'] === '/2026/a.png', 'tos virtual path');

$r2 = Wpsss_Options::sanitize([
    'driver' => 's3-compat',
    's3_preset' => 'r2',
    'bucket' => 'photos',
    'region' => 'auto',
    'app_id' => 'acct123',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'keep_local' => '1',
]);
wpsss_assert($r2['s3_preset'] === 'r2', 'sanitize r2 preset');
wpsss_assert($r2['force_path_style'] === '1', 'r2 defaults path-style');
wpsss_assert(Wpsss_Options::s3_endpoint($r2) === 'https://acct123.r2.cloudflarestorage.com', 'r2 endpoint from app_id');
$r2_req = Wpsss_Options::s3_object_request($r2, 'raw/x.jpg');
wpsss_assert($r2_req['host'] === 'acct123.r2.cloudflarestorage.com', 'r2 path-style host');
wpsss_assert($r2_req['canonical_uri'] === '/photos/raw/x.jpg', 'r2 path includes bucket');

$aws = Wpsss_Options::sanitize([
    'driver' => 's3-compat',
    's3_preset' => 'aws',
    'bucket' => 'examplebucket',
    'region' => 'us-east-1',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'keep_local' => '1',
]);
wpsss_assert($aws['force_path_style'] === '0', 'aws defaults virtual host');
$aws_req = Wpsss_Options::s3_object_request($aws, 'test.txt');
wpsss_assert($aws_req['host'] === 'examplebucket.s3.us-east-1.amazonaws.com', 'aws virtual host');
wpsss_assert(Wpsss_Options::default_public_host($aws) === 'https://examplebucket.s3.us-east-1.amazonaws.com', 'aws public host');

$nobos = Wpsss_Options::sanitize([
    'driver' => 's3-compat',
    's3_preset' => 'bos',
    'bucket' => 'demo',
    'region' => 'us-east-1',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'keep_local' => '1',
]);
wpsss_assert($nobos['s3_preset'] === 'aws', 'bos preset rejected');
wpsss_assert(!isset(Wpsss_Options::s3_presets()['bos']), 'no bos preset');


$oss_signed = Wpsss_Oss_Signer::signed_get_url(
    'https://wenpai-wpsss-test.oss-cn-hangzhou.aliyuncs.com',
    'wenpai-wpsss-test',
    '2026/a.png',
    'id',
    'secret',
    3600,
    1700000000
);
wpsss_assert(strpos($oss_signed, 'OSSAccessKeyId=id') !== false, 'oss signed get query');
wpsss_assert(strpos($oss_signed, 'Expires=1700003600') !== false, 'oss signed expiry');

$s3q = Wpsss_S3_Signer::sign_get_query(
    'AKIAIOSFODNN7EXAMPLE',
    'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    'examplebucket.s3.us-east-1.amazonaws.com',
    '/test.txt',
    '20130524T000000Z',
    'us-east-1',
    86400
);
wpsss_assert(strpos($s3q, 'X-Amz-Algorithm=AWS4-HMAC-SHA256') !== false, 's3 query algo');
wpsss_assert(strpos($s3q, 'X-Amz-Signature=') !== false, 's3 query signature');


$tos_in = Wpsss_Options::sanitize([
    'driver' => 'volc-tos',
    'bucket' => 'demo-tos',
    'region' => 'ap-southeast-3',
    'access_key_id' => 'id',
    'access_key_secret' => 'secret',
    'keep_local' => '1',
    'use_internal' => '1',
]);
wpsss_assert($tos_in['s3_preset'] === 'tos', 'jakarta still tos');
wpsss_assert(Wpsss_Options::s3_endpoint($tos_in) === 'https://tos-s3-ap-southeast-3.ivolces.com', 'tos internal endpoint');
wpsss_assert(isset(Wpsss_Options::regions_for('volc-tos')['ap-southeast-3']), 'tos lists jakarta');
wpsss_assert(Wpsss_Options::region_is_overseas('volc-tos', 'ap-southeast-3'), 'jakarta overseas');
wpsss_assert(!Wpsss_Options::region_is_overseas('volc-tos', 'cn-beijing'), 'beijing domestic');

if ($failed > 0) {

    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
