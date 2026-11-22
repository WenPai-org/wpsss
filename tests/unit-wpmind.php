<?php
define('WPSSS_TEST', true);
if (!defined('WPSSS_VERSION')) {
    define('WPSSS_VERSION', '0.9.3');
}
require __DIR__ . '/wp-stubs.php';

/** @var array<string, array<int, array<int, array{cb: callable, args: int}>>> */
$GLOBALS['wpsss_test_filters'] = [];

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var string */
        public $code;
        /** @var string */
        public $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $cb, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['wpsss_test_filters'][$hook][$priority][] = [
            'cb' => $cb,
            'args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, callable $cb, int $priority = 10): bool
    {
        if (!isset($GLOBALS['wpsss_test_filters'][$hook][$priority])) {
            return false;
        }
        foreach ($GLOBALS['wpsss_test_filters'][$hook][$priority] as $idx => $entry) {
            if ($entry['cb'] === $cb) {
                unset($GLOBALS['wpsss_test_filters'][$hook][$priority][$idx]);
                if ($GLOBALS['wpsss_test_filters'][$hook][$priority] === []) {
                    unset($GLOBALS['wpsss_test_filters'][$hook][$priority]);
                }
                if ($GLOBALS['wpsss_test_filters'][$hook] === []) {
                    unset($GLOBALS['wpsss_test_filters'][$hook]);
                }
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('has_filter')) {
    function has_filter(string $hook, $cb = false)
    {
        if (empty($GLOBALS['wpsss_test_filters'][$hook])) {
            return false;
        }
        if ($cb === false) {
            return true;
        }
        foreach ($GLOBALS['wpsss_test_filters'][$hook] as $priority => $entries) {
            foreach ($entries as $entry) {
                if ($entry['cb'] === $cb) {
                    return $priority;
                }
            }
        }
        return false;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        if (empty($GLOBALS['wpsss_test_filters'][$hook])) {
            return $value;
        }
        $by_priority = $GLOBALS['wpsss_test_filters'][$hook];
        ksort($by_priority);
        foreach ($by_priority as $entries) {
            foreach ($entries as $entry) {
                $call_args = array_merge([$value], $args);
                $value = ($entry['cb'])(...array_slice($call_args, 0, $entry['args']));
            }
        }
        return $value;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-cos-signer.php';
require dirname(__DIR__) . '/includes/class-wpsss-options.php';
require dirname(__DIR__) . '/includes/class-wpsss-ci.php';
require dirname(__DIR__) . '/includes/class-wpsss-wpmind.php';

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

$cos_opts = [
    'driver' => 'qcloud-cos',
    'bucket' => 'demo-1250000000',
    'region' => 'ap-guangzhou',
    'access_key_id' => 'AKIDtest',
    'access_key_secret' => 'test-secret',
];

// 1. WPMind absent (no global functions, no filters registered yet).
wpsss_assert(!function_exists('wpmind_is_available') && !function_exists('wpmind_moderate'), 'wpmind functions genuinely absent in phase 1');
wpsss_assert(Wpsss_Wpmind::is_available() === false, 'unavailable when wpmind absent');
wpsss_assert(Wpsss_Wpmind::is_wired() === false, 'not wired before register');

$pre_items = Wpsss_Wpmind::filter_ecosystem_integrations([]);
wpsss_assert(count($pre_items) === 1 && $pre_items[0]['active'] === false, 'ecosystem inactive before register when wpmind absent');

// Register WPMind integration hooks.
(new Wpsss_Wpmind())->register();
wpsss_assert(has_filter('wpsss_ci_audit_text', [Wpsss_Wpmind::class, 'filter_audit_text']) !== false, 'hooks wpsss_ci_audit_text');
wpsss_assert(has_filter('wpmind_ecosystem_integrations', [Wpsss_Wpmind::class, 'filter_ecosystem_integrations']) !== false, 'hooks wpmind_ecosystem_integrations');
wpsss_assert(Wpsss_Wpmind::is_wired() === true, 'wired after register');

// Fallback when WPMind is absent: empty opts -> pass, COS transport -> COS verdict.
wpsss_assert(Wpsss_Ci::audit_text('hello', []) === 'pass', 'absent wpmind falls back to pass');

$cos_calls = 0;
Wpsss_Ci::$transport = static function (string $url, array $headers, string $xml) use (&$cos_calls) {
    unset($url, $headers, $xml);
    $cos_calls++;
    return '<Response><JobsDetail><Result>1</Result></JobsDetail></Response>';
};
wpsss_assert(Wpsss_Ci::audit_text('bad', $cos_opts) === 'block', 'absent wpmind falls back to cos http');
wpsss_assert($cos_calls === 1, 'cos transport called once when wpmind absent');

// 2. Filter-based weak-coupling path (apply_filters wpmind_is_available / wpmind_moderate).
$filter_calls = [];
$filter_avail_cb = static function () {
    return true;
};
$filter_mod_cb = static function ($fallback, $content, $options = []) use (&$filter_calls) {
    unset($fallback);
    $filter_calls[] = ['content' => $content, 'options' => $options];
    return [
        'safe' => false,
        'categories' => [
            'spam' => ['flagged' => true, 'score' => 0.91, 'reason' => 'ad'],
        ],
    ];
};
add_filter('wpmind_is_available', $filter_avail_cb, 10, 1);
add_filter('wpmind_moderate', $filter_mod_cb, 10, 3);

$cos_calls = 0;
wpsss_assert(Wpsss_Ci::audit_text('spam via filter', $cos_opts) === 'block', 'filter-based wpmind_moderate delegates block');
wpsss_assert($cos_calls === 0, 'filter-based wpmind skips cos transport');
wpsss_assert(
    count($filter_calls) === 1
        && $filter_calls[0]['content'] === 'spam via filter'
        && ($filter_calls[0]['options']['context'] ?? '') === 'wpsss_audit',
    'filter-based wpmind passes context wpsss_audit'
);

remove_filter('wpmind_is_available', $filter_avail_cb, 10);
remove_filter('wpmind_moderate', $filter_mod_cb, 10);

// 3. Global function path (wpmind_is_available & wpmind_moderate).
// Conditional definition avoids PHP compile-time function hoisting so phases 1-2 test genuine absence.
$GLOBALS['wpsss_test_wpmind_available'] = true;
$GLOBALS['wpsss_test_wpmind_result'] = null;
$GLOBALS['wpsss_test_wpmind_calls'] = [];

if (!function_exists('wpmind_is_available')) {
    function wpmind_is_available(): bool
    {
        return !empty($GLOBALS['wpsss_test_wpmind_available']);
    }
}

if (!function_exists('wpmind_moderate')) {
    function wpmind_moderate(string $content, array $options = [])
    {
        $GLOBALS['wpsss_test_wpmind_calls'][] = [
            'content' => $content,
            'options' => $options,
        ];
        return $GLOBALS['wpsss_test_wpmind_result'];
    }
}

// 3a. Pass case.
$GLOBALS['wpsss_test_wpmind_available'] = true;
$GLOBALS['wpsss_test_wpmind_calls'] = [];
$GLOBALS['wpsss_test_wpmind_result'] = [
    'safe' => true,
    'categories' => [
        'spam' => ['flagged' => false, 'score' => 0.04, 'reason' => ''],
    ],
    'summary' => 'ok',
];
$cos_calls = 0;
wpsss_assert(Wpsss_Ci::audit_text('normal comment', $cos_opts) === 'pass', 'wpmind_moderate pass verdict');
wpsss_assert($cos_calls === 0, 'wpmind pass skips cos transport');
wpsss_assert(
    count($GLOBALS['wpsss_test_wpmind_calls']) === 1
        && $GLOBALS['wpsss_test_wpmind_calls'][0]['content'] === 'normal comment'
        && ($GLOBALS['wpsss_test_wpmind_calls'][0]['options']['context'] ?? '') === 'wpsss_audit',
    'wpmind_moderate receives text and wpsss_audit context'
);

// 3b. Flagged block case (high score) + comment filter integration.
$GLOBALS['wpsss_test_wpmind_result'] = [
    'safe' => false,
    'categories' => [
        'spam' => ['flagged' => true, 'score' => 0.95, 'reason' => 'spam link'],
    ],
];
$cos_calls = 0;
wpsss_assert(Wpsss_Ci::audit_text('buy cheap links now', $cos_opts) === 'block', 'wpmind_moderate flagged high score -> block');
wpsss_assert($cos_calls === 0, 'wpmind block skips cos transport');

update_option('wpsss_options', array_merge($cos_opts, ['ci_audit' => '1']));
$ci = new Wpsss_Ci();
wpsss_assert($ci->filter_comment(1, ['comment_content' => 'buy cheap links now']) === 'spam', 'filter_comment maps wpmind block to spam');

// 3c. Flagged review case (moderate score < 0.7).
$GLOBALS['wpsss_test_wpmind_result'] = [
    'safe' => false,
    'categories' => [
        'spam' => ['flagged' => true, 'score' => 0.52, 'reason' => 'possible promo'],
    ],
];
wpsss_assert(Wpsss_Ci::audit_text('check my blog', $cos_opts) === 'review', 'wpmind_moderate moderate score -> review');
wpsss_assert($ci->filter_comment(1, ['comment_content' => 'check my blog']) === 0, 'filter_comment maps wpmind review to pending 0');

// 3d. Minimal unsafe shape (safe => false without categories) -> block.
$GLOBALS['wpsss_test_wpmind_result'] = ['safe' => false];
wpsss_assert(Wpsss_Ci::audit_text('bad minimal', $cos_opts) === 'block', 'wpmind_moderate minimal safe=false -> block');

// 3e. Mixed signal (safe => true but a category flagged below threshold) -> review.
$GLOBALS['wpsss_test_wpmind_result'] = [
    'safe' => true,
    'categories' => [
        'spam' => ['flagged' => true, 'score' => 0.4],
    ],
];
wpsss_assert(Wpsss_Ci::audit_text('mixed signal', $cos_opts) === 'review', 'wpmind_moderate mixed signal -> review');

// 3f. WPMind unavailable (wpmind_is_available() === false) -> fallback to COS / pass.
$GLOBALS['wpsss_test_wpmind_available'] = false;
$GLOBALS['wpsss_test_wpmind_calls'] = [];
$cos_calls = 0;
wpsss_assert(Wpsss_Ci::audit_text('bad when unavailable', $cos_opts) === 'block', 'unavailable wpmind falls back to cos transport');
wpsss_assert($cos_calls === 1, 'cos transport runs when wpmind unavailable');
wpsss_assert(count($GLOBALS['wpsss_test_wpmind_calls']) === 0, 'wpmind_moderate not called when unavailable');
wpsss_assert(Wpsss_Ci::audit_text('hello unconfigured', []) === 'pass', 'unavailable wpmind without cos falls back to pass');

// 3g. WP_Error from wpmind_moderate() -> graceful fallback to COS / pass.
$GLOBALS['wpsss_test_wpmind_available'] = true;
$GLOBALS['wpsss_test_wpmind_calls'] = [];
$GLOBALS['wpsss_test_wpmind_result'] = new WP_Error('wpmind_quota', 'quota exceeded');
$cos_calls = 0;
wpsss_assert(Wpsss_Ci::audit_text('text on wp_error', $cos_opts) === 'block', 'WP_Error falls back to cos transport');
wpsss_assert($cos_calls === 1, 'cos transport called after WP_Error');
wpsss_assert(count($GLOBALS['wpsss_test_wpmind_calls']) === 1, 'wpmind_moderate attempted before WP_Error fallback');
wpsss_assert(Wpsss_Ci::audit_text('text on wp_error no cos', []) === 'pass', 'WP_Error without cos falls back to pass');

// 3h. Malformed response (empty array) -> graceful fallback.
$GLOBALS['wpsss_test_wpmind_result'] = [];
$cos_calls = 0;
wpsss_assert(Wpsss_Ci::audit_text('malformed', $cos_opts) === 'block', 'malformed array falls back to cos transport');
wpsss_assert($cos_calls === 1, 'cos transport called after malformed result');

// 3i. Earlier higher-priority custom filter is respected without calling wpmind_moderate.
$GLOBALS['wpsss_test_wpmind_calls'] = [];
$GLOBALS['wpsss_test_wpmind_result'] = ['safe' => false];
$custom_cb = static function () {
    return 'review';
};
add_filter('wpsss_ci_audit_text', $custom_cb, 5, 3);
wpsss_assert(Wpsss_Ci::audit_text('custom override', $cos_opts) === 'review', 'higher-priority filter verdict preserved');
wpsss_assert(count($GLOBALS['wpsss_test_wpmind_calls']) === 0, 'wpmind_moderate skipped when earlier filter decided');
remove_filter('wpsss_ci_audit_text', $custom_cb, 5);

Wpsss_Ci::$transport = null;

// 4. wpmind_ecosystem_integrations filter output.
$GLOBALS['wpsss_test_wpmind_available'] = true;
$eco = apply_filters('wpmind_ecosystem_integrations', [], []);
wpsss_assert(is_array($eco) && count($eco) === 1, 'ecosystem filter appends wpsss entry');
$entry = $eco[0] ?? [];
wpsss_assert(($entry['slug'] ?? '') === 'wpsss', 'ecosystem slug is wpsss');
wpsss_assert(($entry['name'] ?? '') !== '', 'ecosystem name non-empty');
wpsss_assert(($entry['desc'] ?? '') !== '', 'ecosystem desc non-empty');
wpsss_assert(($entry['feature'] ?? '') !== '', 'ecosystem feature non-empty');
wpsss_assert(($entry['icon'] ?? '') === 'cloud', 'ecosystem icon is cloud');
wpsss_assert(($entry['version'] ?? '') === WPSSS_VERSION, 'ecosystem version matches WPSSS_VERSION');
wpsss_assert(strpos((string) ($entry['settings_url'] ?? ''), 'page=wpsss') !== false, 'ecosystem settings_url targets wpsss');
wpsss_assert(($entry['active'] ?? false) === true, 'ecosystem active is true when wired');

// 4b. Preserves other plugins & deduplicates pre-existing wpsss entry + aggregates stats.
$seed = [
    [
        'slug' => 'wpslug',
        'name' => '文派别名（WPSlug）',
        'active' => true,
    ],
    [
        'slug' => 'wpsss',
        'name' => 'old placeholder',
        'requests' => 1,
        'tokens' => 10,
        'active' => true,
    ],
];
$by_caller = [
    'wpsss' => ['requests' => 4, 'tokens' => 200],
    'wpsss_audit' => ['requests' => 3, 'tokens' => 150],
];
$merged = apply_filters('wpmind_ecosystem_integrations', $seed, $by_caller);
wpsss_assert(count($merged) === 2, 'ecosystem filter deduplicates existing wpsss entry');
wpsss_assert(($merged[0]['slug'] ?? '') === 'wpslug', 'ecosystem filter preserves sibling plugin');
wpsss_assert(($merged[1]['slug'] ?? '') === 'wpsss', 'ecosystem filter updates wpsss in place');
wpsss_assert(($merged[1]['requests'] ?? 0) === 7, 'ecosystem filter aggregates wpsss + wpsss_audit requests');
wpsss_assert(($merged[1]['tokens'] ?? 0) === 350, 'ecosystem filter aggregates wpsss + wpsss_audit tokens');
wpsss_assert(($merged[1]['active'] ?? false) === true, 'ecosystem merged entry active');

// 5. Static wiring assertions in main entry and plugin boot.
$main_src = (string) file_get_contents(dirname(__DIR__) . '/wpsss.php');
$boot_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-wpsss-plugin.php');
wpsss_assert(strpos($main_src, "includes/class-wpsss-wpmind.php") !== false, 'wpsss.php loads class-wpsss-wpmind.php');
wpsss_assert(strpos($boot_src, 'Wpsss_Wpmind') !== false, 'Wpsss_Plugin::boot wires Wpsss_Wpmind');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
