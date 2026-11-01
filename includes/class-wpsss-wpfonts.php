<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 监听 WPFonts 字体包生命周期，把 uploads/wpfonts/packages/* 同步进当前桶。
 *
 * 钩子由 WPFonts ≥2.0.0-rc.9 触发（Foundry 锁定）。本类在 WPFonts 未装时无操作。
 */
final class Wpsss_Wpfonts
{
    public const HOOK_INSTALLED = 'wpfonts_package_installed';
    public const HOOK_DELETED   = 'wpfonts_package_deleted';

    /** @var Wpsss_Plugin */
    private $plugin;

    public function __construct(Wpsss_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        add_action(self::HOOK_INSTALLED, [$this, 'on_installed'], 10, 1);
        add_action(self::HOOK_DELETED, [$this, 'on_deleted'], 10, 1);
    }

    /**
     * @param mixed $payload
     */
    public function on_installed($payload): void
    {
        $data = self::normalize_payload($payload);
        if ($data === null) {
            return;
        }
        if (!$this->ready()) {
            return;
        }
        $files = self::resolve_files($data);
        if ($files === []) {
            return;
        }
        $opts   = Wpsss_Options::get();
        $driver = $this->plugin->active_driver();
        $ok     = 0;
        $fail   = 0;
        foreach ($files as $rel) {
            $rel = self::clean_rel($rel);
            if ($rel === '') {
                continue;
            }
            $abs = trailingslashit($data['dir']) . $rel;
            if (!is_readable($abs) || !is_file($abs)) {
                $fail++;
                continue;
            }
            $key  = Wpsss_Options::apply_object_prefix(trailingslashit($data['uploads_rel']) . $rel);
            $mime = Wpsss_Mime::for_file($abs, $key);
            $put  = $driver->put_object($opts, $key, $abs, $mime);
            if (is_wp_error($put) || $put === false) {
                $fail++;
                continue;
            }
            $ok++;
            if (class_exists('Wpsss_History', false)) {
                Wpsss_History::add_object('wpfonts_upload', $key);
            }
        }
        /**
         * 字体包同步结果（可选观测）。
         *
         * @param array $data Normalized payload.
         * @param int   $ok   Uploaded count.
         * @param int   $fail Failed count.
         */
        do_action('wpsss_wpfonts_package_synced', $data, $ok, $fail);
    }

    /**
     * @param mixed $payload
     */
    public function on_deleted($payload): void
    {
        $data = self::normalize_payload($payload);
        if ($data === null) {
            return;
        }
        if (!$this->ready()) {
            return;
        }
        $files = self::resolve_files($data);
        // 删后 dir 可能已不存在：至少删 local.css 与已知 files；再尝试 uploads_rel 下常见名。
        if ($files === [] && $data['local_css'] !== '') {
            $base = basename($data['local_css']);
            if ($base !== '') {
                $files = [$base];
            }
        }
        if ($files === []) {
            return;
        }
        $keys = [];
        foreach ($files as $rel) {
            $rel = self::clean_rel($rel);
            if ($rel === '') {
                continue;
            }
            $keys[] = Wpsss_Options::apply_object_prefix(trailingslashit($data['uploads_rel']) . $rel);
        }
        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            return;
        }
        $result = $this->plugin->active_driver()->delete_objects(Wpsss_Options::get(), $keys);
        if (!is_wp_error($result) && class_exists('Wpsss_History', false)) {
            foreach ($keys as $key) {
                Wpsss_History::add_object('wpfonts_delete', $key);
            }
        }
        do_action('wpsss_wpfonts_package_removed', $data, $keys, $result);
    }

    private function ready(): bool
    {
        $opts = Wpsss_Options::get();
        if (($opts['bucket'] ?? '') === '') {
            return false;
        }
        if (($opts['access_key_id'] ?? '') === '' || ($opts['access_key_secret'] ?? '') === '') {
            return false;
        }
        return true;
    }

    /**
     * @param mixed $payload
     * @return array{action:string,slug:string,package_id:string,dir:string,uploads_rel:string,local_css:string,files:array<int,string>}|null
     */
    public static function normalize_payload($payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }
        $slug = isset($payload['slug']) ? (string) $payload['slug'] : '';
        $id   = isset($payload['package_id']) ? (string) $payload['package_id'] : $slug;
        if ($slug === '' && $id !== '') {
            $slug = $id;
        }
        if ($slug !== '' && (strpos($slug, '..') !== false || strpos($slug, '/') !== false || strpos($slug, '\\') !== false)) {
            return null;
        }
        $uploads_rel = isset($payload['uploads_rel']) ? (string) $payload['uploads_rel'] : '';
        $uploads_rel = trim(str_replace('\\', '/', $uploads_rel), '/');
        if ($uploads_rel === '' && $slug !== '') {
            $uploads_rel = 'wpfonts/packages/' . $slug;
        }
        if ($uploads_rel === '' || strpos($uploads_rel, '..') !== false) {
            return null;
        }
        $dir = isset($payload['dir']) ? (string) $payload['dir'] : '';
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        $files = [];
        if (isset($payload['files']) && is_array($payload['files'])) {
            foreach ($payload['files'] as $f) {
                if (is_string($f) && $f !== '') {
                    $files[] = $f;
                }
            }
        }
        return [
            'action'      => isset($payload['action']) ? (string) $payload['action'] : '',
            'slug'        => $slug,
            'package_id'  => $id !== '' ? $id : $slug,
            'dir'         => $dir,
            'uploads_rel' => $uploads_rel,
            'local_css'   => isset($payload['local_css']) ? (string) $payload['local_css'] : '',
            'files'       => $files,
        ];
    }

    /**
     * @param array{dir:string,files:array<int,string>,local_css:string} $data
     * @return array<int, string>
     */
    public static function resolve_files(array $data): array
    {
        if ($data['files'] !== []) {
            $out = [];
            foreach ($data['files'] as $f) {
                $c = self::clean_rel($f);
                if ($c !== '') {
                    $out[] = $c;
                }
            }
            return array_values(array_unique($out));
        }
        $dir = $data['dir'];
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }
        $out = [];
        $it  = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        $root_len = strlen($dir) + 1;
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = substr($file->getPathname(), $root_len);
            $rel = self::clean_rel(str_replace('\\', '/', (string) $rel));
            if ($rel === '' || strpos($rel, '.incoming-') !== false) {
                continue;
            }
            $out[] = $rel;
        }
        sort($out);
        return $out;
    }

    public static function clean_rel(string $rel): string
    {
        $rel = str_replace('\\', '/', $rel);
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        if (preg_match('#^(?:[a-z]+:)?//#i', $rel)) {
            return '';
        }
        return $rel;
    }
}
