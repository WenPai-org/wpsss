<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 同步历史。只留最近 50 条。
 */
class Wpsss_History
{
    public const OPTION_KEY = 'wpsss_history';

    /**
     * @return array<int, array<string, string>>
     */
    public static function all(): array
    {
        $rows = get_option(self::OPTION_KEY, []);
        return is_array($rows) ? $rows : [];
    }

    /**
     * 连续相同事件合并为一条，带 count。
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function collapsed(array $rows = []): array
    {
        if ($rows === []) {
            $rows = self::all();
        }
        $out = [];
        $prev = null;
        foreach ($rows as $row) {
            $event = (string) ($row['event'] ?? '');
            $detail = (string) ($row['detail'] ?? '');
            $key = $event . "\n" . $detail;
            if ($prev !== null && $prev['key'] === $key) {
                $out[$prev['i']]['count'] = (int) ($out[$prev['i']]['count'] ?? 1) + 1;
                continue;
            }
            $row['count'] = 1;
            $out[] = $row;
            $prev = ['key' => $key, 'i' => count($out) - 1];
        }

        return $out;
    }

    public static function add_offload(int $post_id, string $key = ''): void
    {
        self::add_row('offload', self::attachment_detail($post_id, $key !== '' ? self::short_key($key) : ''));
    }

    public static function add_fail(int $post_id, string $message): void
    {
        self::add_row('fail', self::attachment_detail($post_id, $message));
    }

    public static function add_delete_attachment(int $post_id, int $count): void
    {
        $suffix = $count > 1 ? sprintf(__('删 %d 个对象', 'wpsss'), $count) : __('删远端对象', 'wpsss');
        self::add_row('delete', self::attachment_detail($post_id, $suffix));
    }

    public static function add_pull(int $post_id, bool $delete_remote): void
    {
        $suffix = $delete_remote ? __('拉回并删远端', 'wpsss') : __('拉回本地', 'wpsss');
        self::add_row('pull', self::attachment_detail($post_id, $suffix));
    }

    public static function add_object(string $event, string $key): void
    {
        self::add_row($event, self::short_key($key));
    }

    private static function add_row(string $event, string $detail): void
    {
        $rows = self::all();
        array_unshift($rows, [
            't' => gmdate('Y-m-d H:i:s'),
            'event' => sanitize_key($event),
            'detail' => sanitize_text_field($detail),
        ]);
        $rows = array_slice($rows, 0, 50);
        update_option(self::OPTION_KEY, $rows, false);
    }

    public static function event_label(string $event): string
    {
        $labels = [
            'offload' => __('进桶', 'wpsss'),
            'upload' => __('上传', 'wpsss'),
            'pull' => __('拉回', 'wpsss'),
            'delete' => __('删远端', 'wpsss'),
            'fail' => __('失败', 'wpsss'),
        ];

        return $labels[$event] ?? $event;
    }

    public static function event_tone(string $event): string
    {
        $tones = [
            'offload' => 'ok',
            'upload' => 'ok',
            'pull' => 'ok',
            'delete' => 'warn',
            'fail' => 'bad',
        ];

        return $tones[$event] ?? '';
    }

    public static function format_time(string $gmt): string
    {
        if ($gmt === '') {
            return '';
        }
        $ts = strtotime($gmt . ' UTC');
        if ($ts === false) {
            return $gmt;
        }
        if (function_exists('wp_date')) {
            return wp_date('Y-m-d H:i', $ts);
        }

        return gmdate('Y-m-d H:i', $ts);
    }

    public static function format_detail(string $event, string $detail): string
    {
        $detail = trim($detail);
        if ($detail === '') {
            return '';
        }

        if (preg_match('/^附件\s+(\d+)\s*(?:→|[:：])\s*(.+)$/u', $detail, $m)) {
            return self::attachment_detail((int) $m[1], trim($m[2]));
        }
        if (preg_match('/^附件\s+(\d+)\s*×\s*(\d+)$/u', $detail, $m)) {
            $count = (int) $m[2];
            $suffix = $count > 1
                ? sprintf(__('删 %d 个对象', 'wpsss'), $count)
                : __('删远端对象', 'wpsss');

            return self::attachment_detail((int) $m[1], $suffix);
        }
        if (preg_match('/^attachment=(\d+)(?:\s+delete-remote)?$/', $detail, $m)) {
            $suffix = str_contains($detail, 'delete-remote')
                ? __('拉回并删远端', 'wpsss')
                : __('拉回本地', 'wpsss');

            return self::attachment_detail((int) $m[1], $suffix);
        }

        if ($event === 'upload' || $event === 'delete') {
            return self::short_key($detail);
        }

        return $detail;
    }

    public static function short_key(string $key): string
    {
        $key = trim(str_replace('\\', '/', $key));
        if ($key === '') {
            return '';
        }
        if (strlen($key) <= 48) {
            return $key;
        }

        return '…' . substr($key, -45);
    }

    private static function attachment_detail(int $post_id, string $suffix = ''): string
    {
        $title = '';
        if ($post_id > 0 && function_exists('get_post')) {
            $post = get_post($post_id);
            if (is_object($post) && isset($post->post_title)) {
                $title = trim((string) $post->post_title);
            }
        }
        $head = $post_id > 0
            ? sprintf(__('附件 #%d', 'wpsss'), $post_id)
            : __('附件', 'wpsss');
        if ($title !== '') {
            $head .= ' · ' . $title;
        }
        if ($suffix === '') {
            return $head;
        }

        return $head . ' · ' . $suffix;
    }
}
