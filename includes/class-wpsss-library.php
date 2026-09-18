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

    public static function last_error(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::META_ERROR, true);
    }

    /**
     * @return array{total: int, offloaded: int, pending: int, failed: int}
     */
    public function stats(): array
    {
        $total_obj = wp_count_posts('attachment');
        $total = isset($total_obj->inherit) ? (int) $total_obj->inherit : 0;
        $done = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::META_OFFLOADED,
            'meta_value' => '1',
            'no_found_rows' => false,
        ]);
        $failed = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::META_ERROR,
            'meta_compare' => 'EXISTS',
            'no_found_rows' => false,
        ]);
        $offloaded = (int) $done->found_posts;
        return [
            'total' => $total,
            'offloaded' => $offloaded,
            'pending' => max(0, $total - $offloaded),
            'failed' => (int) $failed->found_posts,
        ];
    }

    /**
     * @return int[]
     */
    /**
     * @return int[]
     */
    public function offloaded_ids(int $limit = 20): array
    {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit > 0 ? $limit : 20,
            'fields' => 'ids',
            'meta_key' => self::META_OFFLOADED,
            'meta_value' => '1',
        ]);
        return array_map('intval', $ids);
    }

    /**
     * @return array{ok: int, fail: int, skip: int, errors: string[]}
     */
    public function pull_batch(int $limit = 20, bool $dry_run = false, int $only_id = 0, bool $delete_remote = false): array
    {
        $ids = $only_id > 0 ? [$only_id] : $this->offloaded_ids($limit);
        $ok = 0;
        $fail = 0;
        $skip = 0;
        $errors = [];
        foreach ($ids as $id) {
            if (!self::is_offloaded($id) && $only_id === 0) {
                $skip++;
                continue;
            }
            if ($dry_run) {
                $ok++;
                continue;
            }
            $result = $this->offload->pull_attachment($id, $delete_remote);
            if (is_wp_error($result)) {
                $fail++;
                $errors[] = $id . ': ' . $result->get_error_message();
                continue;
            }
            $ok++;
        }
        return compact('ok', 'fail', 'skip', 'errors');
    }

    public function pending_ids(int $limit = 20): array
    {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit > 0 ? $limit : 20,
            'fields' => 'ids',
            'meta_query' => [
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
            ],
        ]);
        return array_map('intval', $ids);
    }

    /**
     * @return array{ok: int, fail: int, skip: int, errors: string[]}
     */
    public function offload_batch(int $limit = 20, bool $dry_run = false, int $only_id = 0): array
    {
        $ids = $only_id > 0 ? [$only_id] : $this->pending_ids($limit);
        $ok = 0;
        $fail = 0;
        $skip = 0;
        $errors = [];
        foreach ($ids as $id) {
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
        return compact('ok', 'fail', 'skip', 'errors');
    }

    /**
     * @return array{scanned: int, changed: int, samples: string[], meta_scanned: int, meta_changed: int}
     */
    public function rewrite_posts(bool $apply, string $from = '', string $to = ''): array
    {
        $uploads = wp_upload_dir();
        $from = $from !== '' ? untrailingslashit($from) : untrailingslashit((string) $uploads['baseurl']);
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
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status != %s AND (post_content LIKE %s OR post_excerpt LIKE %s) LIMIT 200",
            'trash',
            $like,
            $like
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

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s LIMIT 500",
            $like
        ));
        foreach ($rows as $row) {
            $value = (string) $row->meta_value;
            if ($value === '' || is_serialized($value)) {
                continue;
            }
            $meta_scanned++;
            $next = $value;
            foreach ($pairs as [$src, $dst]) {
                $next = Wpsss_Offload::rewrite_urls_in_text($next, $src, $dst);
            }
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
        }

        return compact('scanned', 'changed', 'samples', 'meta_scanned', 'meta_changed');
    }
}
