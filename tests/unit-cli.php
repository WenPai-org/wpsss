<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require dirname(__DIR__) . '/includes/class-wpsss-cli.php';

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

wpsss_assert(Wpsss_CLI::mask_key('') === 'empty', 'mask empty');
wpsss_assert(Wpsss_CLI::mask_key('abc') === 'set', 'mask short');
$masked = Wpsss_CLI::mask_key('LTAI1234567890');
wpsss_assert(strpos($masked, 'LTAI') === 0 && substr($masked, -2) === '90', 'mask keeps ends');
wpsss_assert(strpos($masked, '123456') === false, 'mask hides middle');

$parsed = Wpsss_CLI::parse_env_text(
    "# comment\nWPSSS_OSS_BUCKET=wenpai-wpsss-test\nWPSSS_OSS_REGION=oss-cn-hangzhou\nWPSSS_OSS_ACCESS_KEY_ID=\"testid\"\nWPSSS_OSS_ACCESS_KEY_SECRET=sekrit\nOTHER=nope\n"
);
wpsss_assert($parsed['bucket'] === 'wenpai-wpsss-test', 'env bucket');
wpsss_assert($parsed['region'] === 'oss-cn-hangzhou', 'env region');
wpsss_assert($parsed['access_key_id'] === 'testid', 'env id strips quotes');
wpsss_assert($parsed['access_key_secret'] === 'sekrit', 'env secret');
wpsss_assert(!isset($parsed['OTHER']), 'env ignores unknown');

$ok = Wpsss_CLI::doctor_findings(
    [
        'bucket' => 'wenpai-wpsss-test',
        'keep_local' => '1',
        'access_key_id' => 'id',
        'access_key_secret' => 'secret',
    ],
    'https://wenpai-wpsss-test.oss-cn-hangzhou.aliyuncs.com',
    '0'
);
wpsss_assert($ok['blockers'] === [], 'doctor no blockers on test bucket');
wpsss_assert($ok['warnings'] === [], 'doctor no warnings on test bucket');

$bad = Wpsss_CLI::doctor_findings(
    [
        'bucket' => 'motucloud-icons-site',
        'keep_local' => '0',
        'access_key_id' => '',
        'access_key_secret' => '',
    ],
    'https://other.example.com',
    ''
);
wpsss_assert(count($bad['blockers']) >= 2, 'doctor blocks keep_local and keys');
wpsss_assert(count($bad['warnings']) >= 1, 'doctor warns non-test bucket');
$joined = implode(' ', $bad['blockers']);
wpsss_assert(strpos($joined, 'keep_local') !== false, 'blocker names keep_local');
wpsss_assert(strpos(implode(' ', $bad['warnings']), 'wenpai-wpsss-test') !== false, 'warning names test bucket');

wpsss_assert(Wpsss_CLI::can_turn_off_keep_local([]) === false, 'set refuses off by default');
wpsss_assert(Wpsss_CLI::can_turn_off_keep_local(['allow-no-local' => true]) === true, 'set allows with flag');

wpsss_assert(Wpsss_CLI::classify_anonymous_http(200) === 'ok', 'anon 200 is ok');
wpsss_assert(Wpsss_CLI::classify_anonymous_http(403) === 'warn', 'anon 403 is warn');
wpsss_assert(Wpsss_CLI::classify_anonymous_http(0) === 'warn', 'anon 0 is warn');
wpsss_assert(strpos(Wpsss_CLI::anonymous_read_message(403), '403') !== false, 'anon message names 403');
wpsss_assert(strpos(Wpsss_CLI::anonymous_read_message(403), '裂图') !== false, 'anon message says broken image');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
