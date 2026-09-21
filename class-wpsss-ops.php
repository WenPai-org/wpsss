<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 运营排障：体检、诊断地址、抽检远端、解除标记。
 */
final class Wpsss_Ops
{
    /** @var Wpsss_Plugin */
    private $plugin;

    public function __construct(Wpsss_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public static function host_of(string $url): string
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * @param array<string, string> $opts
     */
    public static function expected_host(array $opts = []): string
    {
        return self::host_of(Wpsss_Options::public_base($opts));
    }

    /**
     * @return array{kind: string, id: int, url: string, host: string}
     */
    public static function parse_input(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['kind' => 'empty', 'id' => 0, 'url' => '', 'host' => ''];
        }
        if (preg_match('/^\d+$/', $input)) {
            return ['kind' => 'id', 'id' => (int) $input, 'url' => '', 'host' => ''];
        }
        if (preg_match('#^https?://#i', $input)) {
            return ['kind' => 'url', 'id' => 0, 'url' => $input, 'host' => self::host_of($input)];
        }
        return ['kind' => 'unknown', 'id' => 0, 'url' => $input, 'host' => ''];
    }

    /**
     * @param array{
     *   id: int,
     *   offloaded: bool,
     *   key: string,
     *   local_exists: bool,
     *   url: string,
     *   error: string
     * } $row
     * @return array<string, mixed>
     */
    public static function classify_row(array $row, string $expected_host, array $opts = []): array
    {
        $url = (string) ($row['url'] ?? '');
        $host = self::host_of($url);
        $key = (string) ($row['key'] ?? '');
        $flags = [];
        if (!empty($row['offloaded']) && empty($row['local_exists'])) {
            $flags[] = 'local_missing';
        }
        if ($expected_host !== '' && $host !== '' && $host !== $expected_host) {
            $flags[] = 'host_mismatch';
        }
        if ($url !== '' && strpos($url, 'http://') === 0) {
            $flags[] = 'http';
        }
        if ((string) ($row['error'] ?? '') !== '') {
            $flags[] = 'failed';
        }
        if ($key !== '' && !empty($row['offloaded']) && class_exists('Wpsss_Offload') && method_exists('Wpsss_Offload', 'key_fits_current_prefix') && !Wpsss_Offload::key_fits_current_prefix($key, $opts)) {
            $flags[] = 'prefix_mismatch';
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'key' => (string) ($row['key'] ?? ''),
            'url' => $url,
            'host' => $host,
            'expected_host' => $expected_host,
            'flags' => $flags,
        ];
    }

    /**
     * @return array{
     *   checked: int,
     *   expected_host: string,
     *   local_missing: array<int, array<string, mixed>>,
     *   host_mismatch: array<int, array<string, mixed>>,
     *   http: array<int, array<string, mixed>>,
     *   failed: array<int, array<string, mixed>>,
     *   prefix_mismatch: array<int, array<string, mixed>>
     * }
     */
    public function scan(int $limit = 40): array
    {
        $opts = Wpsss_Options::get();
        $expected = self::expected_host($opts);
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit > 0 ? min($limit, 80) : 40,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'DESC',
        ]);
        $out = [
            'checked' => 0,
            'expected_host' => $expected,
            'local_missing' => [],
            'host_mismatch' => [],
            'http' => [],
            'failed' => [],
            'prefix_mismatch' => [],
        ];
        foreach (array_map('intval', $ids) as $id) {
            $file = get_attached_file($id);
            $url = (string) wp_get_attachment_url($id);
            if (Wpsss_Library::is_offloaded($id) && method_exists($this->plugin->offload(), 'rewrite_url')) {
                $url = $this->plugin->offload()->rewrite_url($url, $id);
            }
            $row = self::classify_row([
                'id' => $id,
                'offloaded' => Wpsss_Library::is_offloaded($id),
                'key' => (string) get_post_meta($id, Wpsss_Library::META_KEY, true),
                'local_exists' => is_string($file) && $file !== '' && file_exists($file),
                'url' => $url,
                'error' => Wpsss_Library::last_error($id),
            ], $expected, $opts);
            $out['checked']++;
            foreach ($row['flags'] as $flag) {
                if (isset($out[$flag]) && count($out[$flag]) < 12) {
                    $out[$flag][] = $row;
                }
            }
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnose(string $input): array
    {
        $parsed = self::parse_input($input);
        $opts = Wpsss_Options::get();
        $expected = self::expected_host($opts);
        $id = $parsed['id'];
        $url = $parsed['url'];
        if ($parsed['kind'] === 'url' && function_exists('attachment_url_to_postid')) {
            $id = (int) attachment_url_to_postid($url);
        }
        $found = $id > 0 && function_exists('get_post_type') && get_post_type($id) === 'attachment';
        $file = $found ? get_attached_file($id) : '';
        if ($found && $url === '') {
            $url = (string) wp_get_attachment_url($id);
            if (Wpsss_Library::is_offloaded($id) && method_exists($this->plugin->offload(), 'rewrite_url')) {
                $url = $this->plugin->offload()->rewrite_url($url, $id);
            }
        }
        $host = self::host_of($url);
        return [
            'input' => $input,
            'kind' => $parsed['kind'],
            'id' => $id,
            'found' => $found,
            'offloaded' => $found && Wpsss_Library::is_offloaded($id),
            'key' => $found ? (string) get_post_meta($id, Wpsss_Library::META_KEY, true) : '',
            'error' => $found ? Wpsss_Library::last_error($id) : '',
            'local_exists' => is_string($file) && $file !== '' && file_exists($file),
            'url' => $url,
            'host' => $host,
            'expected_host' => $expected,
            'host_ok' => $expected === '' || $host === '' || $host === $expected,
            'http' => strpos($url, 'http://') === 0,
            'notes' => self::diagnose_notes(
                $found,
                $parsed['kind'],
                $host,
                $expected,
                is_string($file) && $file !== '' && file_exists($file),
                $found && Wpsss_Library::is_offloaded($id),
                $found ? (string) get_post_meta($id, Wpsss_Library::META_KEY, true) : '',
                $opts
            ),
        ];
    }

    /**
     * @param array<string, string> $opts
     * @return string[]
     */
    public static function diagnose_notes(bool $found, string $kind, string $host, string $expected, bool $local_exists = true, bool $offloaded = false, string $key = '', array $opts = []): array
    {
        $notes = [];
        if ($kind === 'empty') {
            $notes[] = '请填写附件 ID 或完整 URL。';
        } elseif ($kind === 'unknown') {
            $notes[] = '无法识别。请用数字 ID，或 http(s) 开头的地址。';
        } elseif (!$found) {
            $notes[] = '媒体库里没有对应附件。请输入媒体库编号，或粘贴一张图片的完整地址再试。可能是主题直传、旧 CDN 或已删条目。';
        }
        if ($found && $expected !== '' && $host !== '' && $host !== $expected) {
            $notes[] = '访问域名与当前设置不一致。文章若仍是旧地址，用工具页改写。';
        }
        if ($found && $host === '' && $expected === '') {
            $notes[] = '当前没有对外地址。检查自定义域名或桶设置。';
        }
        if ($found && $offloaded && !$local_exists) {
            $notes[] = '本地文件缺失。到「工具 → 迁移」选「只补缺失」拉回。';
        }
        if ($found && $offloaded && $local_exists) {
            $notes[] = '拉回只把文件写回服务器，前台地址仍指向存储桶。要改回本地地址，请解除进桶标记，或拉回并删除桶内对象。本地已有同名文件时会先改名保留。';
        }
        if ($found && $offloaded && $key !== '' && class_exists('Wpsss_Offload') && method_exists('Wpsss_Offload', 'key_fits_current_prefix') && !Wpsss_Offload::key_fits_current_prefix($key, $opts)) {
            $notes[] = '对象键对不上当前前缀。换过桶或前缀后不要直接拉回。';
        }
        return $notes;
    }

    /**
     * @return array{checked: int, ok: int, missing: array<int, array{id: int, key: string, message: string}>}
     */
    public function head_check(int $limit = 10): array
    {
        $opts = Wpsss_Options::get();
        $driver = $this->plugin->active_driver();
        $ids = $this->plugin->library()->offloaded_ids($limit > 0 ? min($limit, 20) : 10);
        $ok = 0;
        $missing = [];
        foreach ($ids as $id) {
            $key = (string) get_post_meta($id, Wpsss_Library::META_KEY, true);
            if ($key === '') {
                $missing[] = ['id' => $id, 'key' => '', 'message' => '没有对象键'];
                continue;
            }
            $head = $driver->head_object($opts, $key);
            if (is_wp_error($head)) {
                $missing[] = ['id' => $id, 'key' => $key, 'message' => $head->get_error_message()];
                continue;
            }
            $ok++;
        }
        return [
            'checked' => count($ids),
            'ok' => $ok,
            'missing' => $missing,
        ];
    }

    /**
     * @return array{ok: int}
     */
    public function clear_errors(int $limit = 50): array
    {
        $ids = $this->plugin->library()->failed_ids($limit > 0 ? min($limit, 80) : 50);
        $ok = 0;
        foreach ($ids as $id) {
            delete_post_meta($id, Wpsss_Library::META_ERROR);
            $ok++;
        }
        return ['ok' => $ok];
    }

    /**
     * 只去掉进桶标记，不删桶内对象。
     *
     * @return array{ok: int, skip: int, errors: string[]}
     */
    public function release_flags(int $limit, bool $confirm): array
    {
        if (!$confirm) {
            return ['ok' => 0, 'skip' => 0, 'errors' => ['解除标记前请先勾选确认项。']];
        }
        $ids = $this->plugin->library()->offloaded_ids($limit > 0 ? min($limit, 40) : 20);
        $ok = 0;
        foreach ($ids as $id) {
            delete_post_meta($id, Wpsss_Library::META_OFFLOADED);
            delete_post_meta($id, Wpsss_Library::META_KEY);
            delete_post_meta($id, Wpsss_Library::META_ERROR);
            $ok++;
        }
        return ['ok' => $ok, 'skip' => 0, 'errors' => []];
    }
}
