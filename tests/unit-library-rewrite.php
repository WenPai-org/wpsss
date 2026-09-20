<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('WPSSS_TEST', true);
require __DIR__ . '/wp-stubs.php';

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public $ID;
        public $post_content = '';
        public $post_excerpt = '';
        public $post_status = 'publish';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var string */
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->message = (string) $message;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

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
        return (bool) preg_match('/^[aOsidbC]:/', $data);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return [
            'baseurl' => 'http://old.example/uploads',
            'basedir' => '/tmp/uploads',
        ];
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id)
    {
        global $wpdb;
        $id = (int) $post_id;
        if (!isset($wpdb->post_rows[$id])) {
            return null;
        }
        $row = $wpdb->post_rows[$id];
        $post = new WP_Post();
        $post->ID = $id;
        $post->post_content = (string) $row['post_content'];
        $post->post_excerpt = (string) $row['post_excerpt'];
        $post->post_status = (string) $row['post_status'];
        return $post;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post(array $data)
    {
        global $wpdb;
        $id = (int) ($data['ID'] ?? 0);
        if (!isset($wpdb->post_rows[$id])) {
            return 0;
        }
        if (isset($data['post_content'])) {
            $wpdb->post_rows[$id]['post_content'] = $data['post_content'];
        }
        if (isset($data['post_excerpt'])) {
            $wpdb->post_rows[$id]['post_excerpt'] = $data['post_excerpt'];
        }
        return $id;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = [])
    {
        global $wpsss_get_posts_calls, $wpsss_offloaded_ids;
        $wpsss_get_posts_calls[] = $args;
        $limit = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 20;
        if ($limit <= 0) {
            $limit = 20;
        }
        if (($args['meta_key'] ?? '') === '_wpsss_offloaded' && (string) ($args['meta_value'] ?? '') === '1') {
            return array_slice($wpsss_offloaded_ids, 0, $limit);
        }
        return [];
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        global $wpsss_postmeta;
        $val = $wpsss_postmeta[(int) $post_id][$key] ?? '';
        return $single ? $val : (array) $val;
    }
}

if (!class_exists('Wpsss_Wpdb_Stub')) {
    class Wpsss_Wpdb_Stub
    {
        public $posts = 'wptests_posts';
        public $postmeta = 'wptests_postmeta';

        /** @var array<int, array{post_status: string, post_content: string, post_excerpt: string}> */
        public $post_rows = [];

        /** @var array<int, array{meta_id: int, post_id: int, meta_value: string}> */
        public $meta_rows = [];

        public function esc_like($text): string
        {
            return addcslashes((string) $text, '_%\\');
        }

        public function prepare($query, ...$args): string
        {
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }
            $i = 0;
            return (string) preg_replace_callback('/%[sd]/', function ($m) use ($args, &$i) {
                if (!array_key_exists($i, $args)) {
                    return $m[0];
                }
                $arg = $args[$i++];
                if ($m[0] === '%d') {
                    return (string) (int) $arg;
                }
                return "'" . addslashes((string) $arg) . "'";
            }, (string) $query);
        }

        public function get_col($query): array
        {
            [$limit, $offset] = $this->parse_limit_offset((string) $query);
            $after_id = $this->keyset_floor((string) $query, 'ID');
            $needle = $this->like_needle((string) $query);
            $ids = [];
            foreach ($this->post_rows as $id => $row) {
                if (($row['post_status'] ?? '') === 'trash') {
                    continue;
                }
                if ($after_id > 0 && (int) $id <= $after_id) {
                    continue;
                }
                $hay = (string) ($row['post_content'] ?? '') . (string) ($row['post_excerpt'] ?? '');
                if ($needle !== '' && strpos($hay, $needle) === false) {
                    continue;
                }
                $ids[] = $id;
            }
            if ($after_id > 0) {
                sort($ids, SORT_NUMERIC);
                return array_slice($ids, 0, $limit);
            }
            return array_slice($ids, $offset, $limit);
        }

        public function get_results($query): array
        {
            [$limit, $offset] = $this->parse_limit_offset((string) $query);
            $after_id = $this->keyset_floor((string) $query, 'meta_id');
            $needle = $this->like_needle((string) $query);
            $rows = [];
            foreach ($this->meta_rows as $row) {
                if ($after_id > 0 && (int) ($row['meta_id'] ?? 0) <= $after_id) {
                    continue;
                }
                $value = (string) ($row['meta_value'] ?? '');
                if ($needle !== '' && strpos($value, $needle) === false) {
                    continue;
                }
                $rows[] = (object) $row;
            }
            if ($after_id > 0) {
                usort($rows, static function ($a, $b) {
                    return (int) $a->meta_id <=> (int) $b->meta_id;
                });
                return array_slice($rows, 0, $limit);
            }
            return array_slice($rows, $offset, $limit);
        }

        public function update($table, $data, $where, $format = null, $where_format = null): int
        {
            if ($table !== $this->postmeta || !isset($where['meta_id']) || !isset($data['meta_value'])) {
                return 0;
            }
            $id = (int) $where['meta_id'];
            if (!isset($this->meta_rows[$id])) {
                return 0;
            }
            $this->meta_rows[$id]['meta_value'] = $data['meta_value'];
            return 1;
        }

        /**
         * @return array{0: int, 1: int}
         */
        private function parse_limit_offset(string $query): array
        {
            $limit = PHP_INT_MAX;
            $offset = 0;
            if (preg_match('/LIMIT\s+(\d+)/i', $query, $m)) {
                $limit = (int) $m[1];
            }
            if (preg_match('/OFFSET\s+(\d+)/i', $query, $m)) {
                $offset = (int) $m[1];
            }
            return [$limit, $offset];
        }

        /**
         * 键集分页下限：解析「AND <column> > %d」并把 prepare 已代入的数字取出来。
         */
        private function keyset_floor(string $query, string $column): int
        {
            if (preg_match('/' . $column . '\s*>\s*(\d+)/i', $query, $m)) {
                return (int) $m[1];
            }
            return 0;
        }

        private function like_needle(string $query): string
        {
            if (preg_match("/LIKE\\s+'((?:\\\\'|[^'])*)'/i", $query, $m)) {
                return trim(stripcslashes($m[1]), '%');
            }
            return '';
        }
    }
}

if (!class_exists('Wpsss_Plugin')) {
    class Wpsss_Plugin
    {
    }
}

if (!class_exists('Wpsss_Offload')) {
    class Wpsss_Offload
    {
        public static function rewrite_urls_in_text(string $text, string $from_base, string $to_base): string
        {
            $from = rtrim($from_base, '/');
            $to = rtrim($to_base, '/');
            if ($text === '' || $from === '' || $to === '' || $from === $to) {
                return $text;
            }
            return str_replace($from, $to, $text);
        }

        public static function sanitize_on_exist(string $mode): string
        {
            return in_array($mode, ['skip', 'rename', 'overwrite'], true) ? $mode : 'skip';
        }

        public function plan_pull(int $post_id, string $on_exist = 'skip'): array
        {
            unset($post_id, $on_exist);
            return [
                'keys' => 1,
                'missing' => 1,
                'exists' => 0,
                'would_write' => 1,
                'would_skip' => 0,
                'would_rename' => 0,
            ];
        }

        public function pull_attachment(int $post_id, bool $delete_remote = false, string $on_exist = 'skip')
        {
            unset($post_id, $delete_remote, $on_exist);
            return true;
        }
    }
}

if (!class_exists('Wpsss_Options')) {
    class Wpsss_Options
    {
        public static function get(): array
        {
            return ['rewrite_from' => ''];
        }

        public static function public_base(): string
        {
            return 'https://cdn.example/uploads';
        }
    }
}

$url_file = dirname(__DIR__) . '/includes/class-wpsss-url.php';
if (!class_exists('Wpsss_Url') && is_file($url_file)) {
    require $url_file;
}
if (!class_exists('Wpsss_Url')) {
    class Wpsss_Url
    {
    }
}

require dirname(__DIR__) . '/includes/class-wpsss-library.php';

$GLOBALS['wpdb'] = new Wpsss_Wpdb_Stub();
$GLOBALS['wpsss_get_posts_calls'] = [];
$GLOBALS['wpsss_offloaded_ids'] = [];
$GLOBALS['wpsss_postmeta'] = [];

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

function wpsss_reset_store(): void
{
    global $wpdb, $wpsss_get_posts_calls, $wpsss_offloaded_ids, $wpsss_postmeta;
    $wpdb->post_rows = [];
    $wpdb->meta_rows = [];
    $wpsss_get_posts_calls = [];
    $wpsss_offloaded_ids = [];
    $wpsss_postmeta = [];
}

$from = 'http://old.example/uploads';
$to = 'https://cdn.example/uploads';
$lib = new Wpsss_Library(new Wpsss_Plugin(), new Wpsss_Offload());

wpsss_reset_store();
$GLOBALS['wpdb']->post_rows[1] = [
    'post_status' => 'publish',
    'post_content' => '<img src="' . $from . '/x.jpg">',
    'post_excerpt' => '',
];
$empty_from = $lib->rewrite_posts(false, '', $to);
wpsss_assert($empty_from['scanned'] === 0 && $empty_from['changed'] === 0, 'empty from does not default to uploads');

wpsss_reset_store();
for ($i = 1; $i <= 250; $i++) {
    $GLOBALS['wpdb']->post_rows[$i] = [
        'post_status' => 'publish',
        'post_content' => '<img src="' . $from . '/p' . $i . '.jpg">',
        'post_excerpt' => '',
    ];
}
$paged = $lib->rewrite_posts(false, $from, $to);
wpsss_assert($paged['scanned'] >= 250, 'rewrite_posts paginates past 200');
wpsss_assert($paged['changed'] === 250, 'rewrite_posts changes all matching posts');
wpsss_assert(count($paged['samples']) === 5, 'rewrite_posts samples capped at 5');

wpsss_reset_store();
$GLOBALS['wpdb']->meta_rows[1] = [
    'meta_id' => 1,
    'post_id' => 1,
    'meta_value' => $from . '/plain.jpg',
];
$GLOBALS['wpdb']->meta_rows[2] = [
    'meta_id' => 2,
    'post_id' => 1,
    'meta_value' => '',
];
$plain = $lib->rewrite_posts(true, $from, $to);
wpsss_assert($plain['meta_scanned'] === 1, 'plaintext meta scanned');
wpsss_assert($plain['meta_changed'] === 1, 'plaintext meta changed');
wpsss_assert($GLOBALS['wpdb']->meta_rows[1]['meta_value'] === $to . '/plain.jpg', 'plaintext meta rewritten');
wpsss_assert($GLOBALS['wpdb']->meta_rows[2]['meta_value'] === '', 'empty meta skipped');

wpsss_reset_store();
$serialized = serialize(['src' => $from . '/a.jpg']);
$GLOBALS['wpdb']->meta_rows[3] = [
    'meta_id' => 3,
    'post_id' => 2,
    'meta_value' => $serialized,
];
$ser = $lib->rewrite_posts(true, $from, $to);
if (method_exists('Wpsss_Url', 'rewrite_maybe_serialized')) {
    $stored = (string) $GLOBALS['wpdb']->meta_rows[3]['meta_value'];
    $data = @unserialize($stored);
    wpsss_assert(is_array($data) && ($data['src'] ?? '') === $to . '/a.jpg', 'serialized src rewritten');
    wpsss_assert(is_serialized($stored), 'serialized value stays serialized');
    wpsss_assert($ser['meta_changed'] === 1, 'serialized meta_changed');
} else {
    wpsss_assert($ser['meta_changed'] === 0, 'serialized skipped without walker');
    wpsss_assert($GLOBALS['wpdb']->meta_rows[3]['meta_value'] === $serialized, 'serialized meta unchanged');
    echo "NEED_A\n";
}

wpsss_reset_store();
$GLOBALS['wpsss_offloaded_ids'] = [11];
$GLOBALS['wpsss_postmeta'][11][Wpsss_Library::META_OFFLOADED] = '1';
$pull = $lib->pull_batch(20, true);
wpsss_assert($pull['ok'] === 1, 'pull_batch dry_run ok');
wpsss_assert($pull['fail'] === 0 && $pull['skip'] === 0, 'pull_batch dry_run no fail/skip');
wpsss_assert(($pull['cursor'] ?? 0) === 11, 'pull_batch cursor is min id');
wpsss_assert(isset($pull['blocked']), 'pull_batch reports blocked');

$ids = $lib->offloaded_ids(20);
wpsss_assert($ids === [11], 'offloaded_ids returns stub ids');
wpsss_assert(
    $GLOBALS['wpsss_get_posts_calls'] !== []
    && ($GLOBALS['wpsss_get_posts_calls'][0]['meta_key'] ?? '') === Wpsss_Library::META_OFFLOADED
    && ($GLOBALS['wpsss_get_posts_calls'][0]['meta_value'] ?? '') === '1',
    'offloaded_ids uses get_posts'
);

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
