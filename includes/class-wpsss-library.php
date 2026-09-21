<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 已有附件补传、统计、文章和字段里的本地上传 URL。
 */
final class Wpsss_Library
{
    public const META_OFFLOADED = '_wpsss_offloaded';
    public const META_KEY = '_wpsss_key';
    public const META_ERROR = '_wpsss_error';
    public const META_LOCAL_ONLY = '_wpsss_local_only';
    public const META_MAIN_ONLY = '_wpsss_main_only';
    public const META_THUMBS_PENDING = '_wpsss_thumbs_pending';
    public const META_FAIL_COUNT = '_wpsss_fail_count';
    public const FAIL_BACKOFF_THRESHOLD = 3;
    public const CRON_HOOK = 'wpsss_offload_cron_event';
    public const CRON_SLUG = 'wpsss_minute';
    public const CURSOR_KEY = 'wpsss_offload_cursor';
    public const RUN_LOCK_KEY = 'wpsss_offload_runlock';

    /** @var Wpsss_Plugin */
    private $plugin;

    /** @var Wpsss_Offload */
    private $offload;

    public function __construct(Wpsss_Plugin $plugin, Wpsss_Offload $offload)
    {
        $this->plugin = $plugin;
        $this->offload = $offload;
    }

    public function register(): void
    {
        add_action('add_attachment', [$this, 'on_add_attachment']);
        (new Wpsss_Media($this->plugin, $this->offload))->register();
    }

    public function on_add_attachment(int $post_id): void
    {
        if (self::is_offloaded($post_id)) {
            return;
        }
        $mime = (string) get_post_mime_type($post_id);
        if (strpos($mime, 'image/') === 0) {
            return;
        }
        $this->offload->offload_attachment($post_id);
    }

    public static function is_offloaded(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_OFFLOADED, true) === '1';
    }

    /**
     * 主文件标记：只有主文件（含原图）在桶，尺寸文件没传（thumbs 关闭 / PDF 预览）。
     * 此时尺寸 URL 保持本地，只有主文件地址改写，避免前台尺寸 404。
     */
    public static function is_main_only(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_MAIN_ONLY, true) === '1';
    }

    public static function set_main_only(int $post_id, bool $on): void
    {
        if ($on) {
            update_post_meta($post_id, self::META_MAIN_ONLY, '1');
        } else {
            delete_post_meta($post_id, self::META_MAIN_ONLY);
        }
    }

    public static function fail_count(int $post_id): int
    {
        return (int) get_post_meta($post_id, self::META_FAIL_COUNT, true);
    }

    public static function last_error(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::META_ERROR, true);
    }

    /**
     * 媒体库画像。一次 SQL 往返拿四个计数——补传 tick 每秒可能问多次，
     * 原先的四条 found_rows 查询在大库上是主要开销。
     *
     * @return array{total: int, offloaded: int, pending: int, failed: int, local_only: int}
     */
    public function stats(): array
    {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_row')) {
            return ['total' => 0, 'offloaded' => 0, 'pending' => 0, 'failed' => 0, 'local_only' => 0];
        }
        $posts = isset($wpdb->posts) ? (string) $wpdb->posts : 'wp_posts';
        $postmeta = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : 'wp_postmeta';
        $sql = "SELECT
            (SELECT COUNT(*) FROM {$posts} WHERE post_type = 'attachment' AND post_status = 'inherit') AS total,
            (SELECT COUNT(*) FROM {$posts} p JOIN {$postmeta} m ON m.post_id = p.ID
                AND m.meta_key = %s AND m.meta_value = '1'
                WHERE p.post_type = 'attachment' AND p.post_status = 'inherit') AS offloaded,
            (SELECT COUNT(*) FROM {$posts} p JOIN {$postmeta} m ON m.post_id = p.ID
                AND m.meta_key = %s
                WHERE p.post_type = 'attachment' AND p.post_status = 'inherit') AS failed,
            (SELECT COUNT(*) FROM {$posts} p JOIN {$postmeta} m ON m.post_id = p.ID
                AND m.meta_key = %s
                WHERE p.post_type = 'attachment' AND p.post_status = 'inherit') AS local_only,
            (SELECT COALESCE(SUM(CAST(fs.meta_value AS UNSIGNED)), 0) FROM {$postmeta} fs
                JOIN {$postmeta} od ON od.post_id = fs.post_id
                    AND od.meta_key = %s AND od.meta_value = '1'
                WHERE fs.meta_key = '_wpsss_filesize') AS offloaded_bytes";
        $row = $wpdb->get_row($wpdb->prepare($sql, self::META_OFFLOADED, self::META_ERROR, self::META_LOCAL_ONLY, self::META_OFFLOADED), 'ARRAY_A');
        $row = is_array($row) ? $row : [];
        $total = (int) ($row['total'] ?? 0);
        $offloaded = (int) ($row['offloaded'] ?? 0);
        $local_only = (int) ($row['local_only'] ?? 0);
        return [
            'total' => $total,
            'offloaded' => $offloaded,
            'pending' => max(0, $total - $offloaded - $local_only),
            'failed' => (int) ($row['failed'] ?? 0),
            'local_only' => $local_only,
            'offloaded_bytes' => (int) ($row['offloaded_bytes'] ?? 0),
        ];
    }

    /**
     * @return int[]
     */
    public function offloaded_ids(int $limit = 20, int $before_id = 0): array
    {
        return $this->query_ids([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit > 0 ? $limit : 20,
            'fields' => 'ids',
            'meta_key' => self::META_OFFLOADED,
            'meta_value' => '1',
        ], $before_id);
    }

    /**
     * @return array{ok: int, fail: int, skip: int, renamed: int, missing: int, exists: int, blocked: int, cursor: int, errors: string[], on_exist: string, dry_run: bool, stopped: bool}
     */
    public function pull_batch(int $limit = 20, bool $dry_run = false, int $only_id = 0, bool $delete_remote = false, string $on_exist = 'skip', int $before_id = 0, float $budget_seconds = 0): array
    {
        $on_exist = Wpsss_Offload::sanitize_on_exist($on_exist);
        $ids = $only_id > 0 ? [$only_id] : $this->offloaded_ids($limit, $before_id);
        $ok = 0;
        $fail = 0;
        $skip = 0;
        $renamed = 0;
        $missing = 0;
        $exists = 0;
        $blocked = 0;
        $errors = [];
        $started = microtime(true);
        $stopped = false;
        $last = 0;
        foreach ($ids as $id) {
            if (self::budget_hit($started, $budget_seconds) && ($ok + $fail + $skip) > 0) {
                $stopped = true;
                break;
            }
            $last = $id;
            if (!self::is_offloaded($id) && $only_id === 0) {
                $skip++;
                continue;
            }
            $plan = method_exists($this->offload, 'plan_pull')
                ? $this->offload->plan_pull($id, $on_exist)
                : ['would_write' => 1, 'would_skip' => 0, 'would_rename' => 0, 'would_block' => 0, 'missing' => 0, 'exists' => 0];
            $renamed += (int) ($plan['would_rename'] ?? 0);
            $missing += (int) ($plan['missing'] ?? 0);
            $exists += (int) ($plan['exists'] ?? 0);
            $blocked += (int) ($plan['would_block'] ?? 0);
            if ($dry_run) {
                $ok += (int) ($plan['would_write'] ?? 0);
                $skip += (int) ($plan['would_skip'] ?? 0);
                continue;
            }
            $result = $this->offload->pull_attachment($id, $delete_remote, $on_exist);
            if (is_wp_error($result)) {
                $fail++;
                $errors[] = $id . ': ' . $result->get_error_message();
                continue;
            }
            $ok++;
        }
        $cursor = $last;
        return compact('ok', 'fail', 'skip', 'renamed', 'missing', 'exists', 'blocked', 'cursor', 'errors', 'on_exist', 'dry_run', 'stopped');
    }

    /**
     * @return int[]
     */
    public function failed_ids(int $limit = 20, int $before_id = 0): array
    {
        return $this->query_ids([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit > 0 ? $limit : 20,
            'fields' => 'ids',
            'meta_key' => self::META_ERROR,
            'meta_compare' => 'EXISTS',
        ], $before_id);
    }

    public function pending_ids(int $limit = 20, int $before_id = 0): array
    {
        return $this->query_ids([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit > 0 ? $limit : 20,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => self::META_LOCAL_ONLY,
                    'compare' => 'NOT EXISTS',
                ],
                // 值比较是 INNER JOIN：没有失败计数的附件（绝大多数）必须用
                // NOT EXISTS 显式放行，否则待传队列整体为空（2026-09-21 生产事故）。
                [
                    'relation' => 'OR',
                    [
                        'key' => self::META_FAIL_COUNT,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => self::META_FAIL_COUNT,
                        'value' => self::FAIL_BACKOFF_THRESHOLD,
                        'compare' => '<',
                        'type' => 'NUMERIC',
                    ],
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => self::META_OFFLOADED,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => self::META_OFFLOADED,
                        'value' => '1',
                        'compare' => '!=',
                    ],
                    [
                        'key' => self::META_THUMBS_PENDING,
                        'value' => '1',
                    ],
                ],
            ],
        ], $before_id);
    }

    /**
     * @return array{ok: int, fail: int, skip: int, cursor: int, errors: string[], stopped: bool}
     */
    public function offload_batch(int $limit = 20, bool $dry_run = false, int $only_id = 0, string $source = 'pending', int $before_id = 0, float $budget_seconds = 0): array
    {
        if ($only_id > 0) {
            $ids = [$only_id];
        } elseif ($source === 'failed') {
            $ids = $this->failed_ids($limit);
        } else {
            $ids = $this->pending_ids($limit, $before_id);
        }
        $ok = 0;
        $fail = 0;
        $skip = 0;
        $errors = [];
        $started = microtime(true);
        $stopped = false;
        $last = 0;
        foreach ($ids as $id) {
            if (self::budget_hit($started, $budget_seconds) && ($ok + $fail + $skip) > 0) {
                $stopped = true;
                break;
            }
            $last = $id;
            if (self::is_offloaded($id) && $only_id === 0) {
                $skip++;
                continue;
            }
            if ($dry_run) {
                $ok++;
                continue;
            }
            $result = $this->offload->offload_attachment($id);
            if (is_wp_error($result)) {
                $fail++;
                $errors[] = $id . ': ' . $result->get_error_message();
                continue;
            }
            $ok++;
        }
        $cursor = $last;
        return compact('ok', 'fail', 'skip', 'cursor', 'errors', 'stopped');
    }

    /**
     * 队列观测一行话：待传/失败、后台任务下次触发、运行锁占用。
     *
     * @return array{pending: int, failed: int, cron: string, lock: string}
     */
    public function queue_snapshot(): array
    {
        $stats = $this->stats();
        $cron = '未排期';
        if (function_exists('wp_next_scheduled')) {
            $next = wp_next_scheduled(self::CRON_HOOK);
            if (is_int($next) && $next > 0) {
                $cron = human_time_diff(time(), $next) . '后';
            }
        }
        $lock = '空闲';
        $holder = (string) get_transient(self::RUN_LOCK_KEY);
        if ($holder !== '') {
            $lock = $holder === 'cron' ? '后台任务占用中' : '浏览器占用中';
        }
        return [
            'pending' => (int) $stats['pending'],
            'failed' => (int) $stats['failed'],
            'cron' => $cron,
            'lock' => $lock,
        ];
    }

    private static function budget_hit(float $started, float $budget_seconds): bool
    {
        return $budget_seconds > 0 && (microtime(true) - $started) >= $budget_seconds;
    }

    /**
     * 浏览器补传与 cron 托底共用的运行锁：同刻只允许一条队列在跑。
     * 锁值是持有者令牌（浏览器按用户、cron 用 'cron'），释放只放自己的；
     * TTL 覆盖单附件最坏临界区（主文件 + 多尺寸逐个 PUT），进程死掉也会自己过期。
     */
    public static function run_lock_acquire(string $token): bool
    {
        if (get_transient(self::RUN_LOCK_KEY) !== false) {
            return false;
        }
        set_transient(self::RUN_LOCK_KEY, $token, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    public static function run_lock_release(string $token): void
    {
        if ((string) get_transient(self::RUN_LOCK_KEY) === $token) {
            delete_transient(self::RUN_LOCK_KEY);
        }
    }

    public static function arm_offload_cron(): void
    {
        if (!function_exists('wp_schedule_event')) {
            return;
        }
        // 每个 tick 都 clear+schedule 会双写 autoload 的 cron option；已有近期事件就不再动。
        $next = function_exists('wp_next_scheduled') ? wp_next_scheduled(self::CRON_HOOK) : false;
        if (is_int($next) && $next > time() && $next - time() <= 110) {
            return;
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_schedule_event(time() + 60, self::CRON_SLUG, self::CRON_HOOK);
    }

    public static function disarm_offload_cron(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    /**
     * cron 托底：管理员关掉页面后，每分钟最多补 2 个，慢慢清完待传队列。
     * 队列清空、密钥失效或卡住时自动卸下自己；和浏览器 tick 抢同一把运行锁。
     */
    public function cron_offload_tick(): void
    {
        if (!$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
            self::disarm_offload_cron();
            return;
        }
        $stats = $this->stats();
        if ((int) $stats['pending'] <= 0) {
            self::disarm_offload_cron();
            return;
        }
        if (!self::run_lock_acquire('cron')) {
            return;
        }
        $before = (int) get_transient(self::CURSOR_KEY);
        $result = $this->offload_batch(2, false, 0, 'pending', $before, 20.0);
        $cursor = (int) ($result['cursor'] ?? 0);
        if ($cursor > 0) {
            set_transient(self::CURSOR_KEY, $cursor, DAY_IN_SECONDS);
        } else {
            delete_transient(self::CURSOR_KEY);
        }
        self::run_lock_release('cron');
        $worked = ((int) $result['ok'] + (int) $result['fail'] + (int) $result['skip']) > 0;
        if (!$worked || $this->pending_ids(1, $cursor) === []) {
            self::disarm_offload_cron();
            // 队列排干但统计上还有待传：余下全是连续失败退避项，别静默停车。
            if ($worked && (int) $stats['pending'] > 0) {
                set_transient('wpsss_stalled_notice', (int) $stats['pending'], DAY_IN_SECONDS);
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     * @return int[]
     */
    private function query_ids(array $args, int $before_id = 0): array
    {
        $args['orderby'] = 'ID';
        $args['order'] = 'DESC';
        // get_posts() 默认 suppress_filters=true，posts_where 根本不会跑，
        // 游标（ID < before）就静默失效。这里必须显式关掉，否则两个队列都会原地打转。
        $args['suppress_filters'] = false;
        $where = null;
        if ($before_id > 0 && function_exists('add_filter')) {
            $where = static function (string $sql) use ($before_id): string {
                global $wpdb;
                if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'prepare')) {
                    return $sql;
                }
                $posts = isset($wpdb->posts) ? (string) $wpdb->posts : 'posts';
                return $sql . $wpdb->prepare(' AND ' . $posts . '.ID < %d', $before_id);
            };
            add_filter('posts_where', $where);
        }
        $ids = get_posts($args);
        if ($where !== null && function_exists('remove_filter')) {
            remove_filter('posts_where', $where);
        }
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    /**
     * @return array{scanned: int, changed: int, samples: string[], meta_scanned: int, meta_changed: int}
     */
    public function rewrite_posts(bool $apply, string $from = '', string $to = ''): array
    {
        $from = $from !== '' ? untrailingslashit($from) : '';
        $extra = (string) Wpsss_Options::get()['rewrite_from'];
        $to = $to !== '' ? untrailingslashit($to) : untrailingslashit(Wpsss_Options::public_base());
        $scanned = 0;
        $changed = 0;
        $samples = [];
        $meta_scanned = 0;
        $meta_changed = 0;
        if ($from === '' || $to === '' || $from === $to) {
            return compact('scanned', 'changed', 'samples', 'meta_scanned', 'meta_changed');
        }

        $pairs = [[$from, $to]];
        if ($extra !== '' && untrailingslashit($extra) !== $to && untrailingslashit($extra) !== $from) {
            $pairs[] = [untrailingslashit($extra), $to];
        }

        global $wpdb;
        $like = '%' . $wpdb->esc_like($from) . '%';
        $post_batch = 200;
        // 键集分页：apply 会把命中行改写成不再命中 LIKE，OFFSET 翻页会整批跳过；
        // 修订等后台类型不参与改写，避免计数虚高。
        $last_post_id = 0;
        do {
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status != %s AND post_type NOT IN (%s, %s, %s) AND ID > %d AND (post_content LIKE %s OR post_excerpt LIKE %s) ORDER BY ID ASC LIMIT %d",
                'trash',
                'revision',
                'customize_changeset',
                'oembed_cache',
                $last_post_id,
                $like,
                $like,
                $post_batch
            ));
            foreach ($ids as $post_id) {
                $post = get_post((int) $post_id);
                if (!$post instanceof WP_Post) {
                    continue;
                }
                $scanned++;
                $content = (string) $post->post_content;
                $excerpt = (string) $post->post_excerpt;
                foreach ($pairs as [$src, $dst]) {
                    $content = Wpsss_Offload::rewrite_urls_in_text($content, $src, $dst);
                    $excerpt = Wpsss_Offload::rewrite_urls_in_text($excerpt, $src, $dst);
                }
                if ($content === $post->post_content && $excerpt === $post->post_excerpt) {
                    continue;
                }
                $changed++;
                if (count($samples) < 5) {
                    $samples[] = (string) $post->ID;
                }
                if ($apply) {
                    wp_update_post([
                        'ID' => $post->ID,
                        'post_content' => $content,
                        'post_excerpt' => $excerpt,
                    ]);
                }
            }
            foreach ($ids as $post_id) {
                $last_post_id = max($last_post_id, (int) $post_id);
            }
            $got_posts = count($ids);
        } while ($got_posts === $post_batch);

        $meta_batch = 500;
        $last_meta_id = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s AND meta_id > %d ORDER BY meta_id ASC LIMIT %d",
                $like,
                $last_meta_id,
                $meta_batch
            ));
            foreach ($rows as $row) {
                $value = (string) $row->meta_value;
                if ($value === '') {
                    continue;
                }
                $next = $this->rewrite_value($value, $pairs);
                if ($next === null) {
                    continue;
                }
                $meta_scanned++;
                if ($next === $value) {
                    continue;
                }
                $meta_changed++;
                if ($apply) {
                    $wpdb->update(
                        $wpdb->postmeta,
                        ['meta_value' => $next],
                        ['meta_id' => (int) $row->meta_id],
                        ['%s'],
                        ['%d']
                    );
                }
                $last_meta_id = max($last_meta_id, (int) $row->meta_id);
            }
            $got_meta = count($rows);
        } while ($got_meta === $meta_batch);

        return compact('scanned', 'changed', 'samples', 'meta_scanned', 'meta_changed');
    }

    /**
     * @param array<int, array{0: string, 1: string}> $pairs
     */
    private function rewrite_value(string $value, array $pairs): ?string
    {
        if (method_exists('Wpsss_Url', 'rewrite_maybe_serialized')) {
            return Wpsss_Url::rewrite_maybe_serialized($value, function ($text) use ($pairs) {
                foreach ($pairs as [$src, $dst]) {
                    $text = Wpsss_Offload::rewrite_urls_in_text($text, $src, $dst);
                }
                return $text;
            });
        }
        if (is_serialized($value)) {
            return null;
        }
        foreach ($pairs as [$src, $dst]) {
            $value = Wpsss_Offload::rewrite_urls_in_text($value, $src, $dst);
        }
        return $value;
    }
}
