<?php
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';
require dirname(__DIR__) . '/includes/class-wpsss-oss-ram.php';

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

Wpsss_Oss_Ram::reset();
$untouched = Wpsss_Oss_Ram::hydrate([
    'oss_auth' => 'ak',
    'access_key_id' => 'keep',
    'driver' => 'aliyun-oss',
]);
wpsss_assert($untouched['access_key_id'] === 'keep', 'ak mode does not hydrate');

Wpsss_Oss_Ram::$transport = static function (string $url) {
    if (substr($url, -1) === '/') {
        return 'wpsss-role';
    }
    return json_encode([
        'Code' => 'Success',
        'AccessKeyId' => 'STS.ak',
        'AccessKeySecret' => 'sts-secret',
        'SecurityToken' => 'sts-token',
    ]);
};

$hydrated = Wpsss_Oss_Ram::hydrate([
    'oss_auth' => 'ram',
    'driver' => 'aliyun-oss',
    'access_key_id' => '',
    'oss_ram_role' => '',
]);
wpsss_assert($hydrated['access_key_id'] === 'STS.ak', 'ram hydrates access key');
wpsss_assert($hydrated['access_key_secret'] === 'sts-secret', 'ram hydrates secret');
wpsss_assert($hydrated['security_token'] === 'sts-token', 'ram hydrates token');

$again = Wpsss_Oss_Ram::hydrate([
    'oss_auth' => 'ram',
    'driver' => 'aliyun-oss',
]);
wpsss_assert($again['access_key_id'] === 'STS.ak', 'ram memo reused');

Wpsss_Oss_Ram::reset();
Wpsss_Oss_Ram::$transport = static function (string $url) {
    unset($url);
    return '{"Code":"Failed"}';
};
$miss = Wpsss_Oss_Ram::hydrate([
    'oss_auth' => 'ram',
    'driver' => 'aliyun-oss',
    'oss_ram_role' => 'nope',
    'access_key_id' => 'fallback',
]);
wpsss_assert($miss['access_key_id'] === 'fallback', 'failed imds keeps existing key');

Wpsss_Oss_Ram::reset();
Wpsss_Oss_Ram::$transport = static function (string $url) {
    unset($url);
    return 'ok';
};
wpsss_assert(Wpsss_Oss_Ram::credentials('../etc') === null, 'rejects dotted role');
wpsss_assert(Wpsss_Oss_Ram::credentials('a/b') === null, 'rejects slashed role');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
