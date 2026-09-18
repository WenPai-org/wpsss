<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Offload
{
    /** @var Wpsss_Plugin */
    private $plugin;

    public function __construct(Wpsss_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        add_filter('sanitize_file_name', [$this, 'rename_file'], 10, 1);
        add_filter('wp_handle_upload', [$this, 'on_handle_upload'], 50);
        add_filter('wp_generate_attachment_metadata', [$this, 'on_generate_metadata'], 100, 2);
        add_filter('wp_update_attachment_metadata', [$this, 'on_generate_metadata'], 100, 2);
        add_filter('wp_save_image_editor_file', [$this, 'on_save_image_editor'], 101);
        add_action('delete_attachment', [$this, 'on_delete_attachment']);
        add_filter('wp_get_attachment_url', [$this, 'rewrite_url'], 30, 2);
        add_filter('wp_calculate_image_srcset', [$this, 'rewrite_srcset'], 10, 5);
        add_filter('the_content', [$this, 'rewrite_content'], 100);
        add_filter('the_excerpt', [$this, 'rewrite_content'], 100);
        add_filter('post_thumbnail_html', [$this, 'rewrite_content'], 100);
    }

    public function rename_file(string $filename): string
    {
        $opts = Wpsss_Options::get();
        $mode = $opts['auto_rename'] ?? 'off';
        if ($mode === 'off' || $filename === '') {
            return $filename;
        }
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $suffix = $ext !== '' ? '.' . $ext : '';
        if ($mode === 'md5') {
            return md5($filename . microtime(true)) . $suffix;
        }
        return gmdate('YmdHis', time()) . wp_rand(100, 999) . $suffix;
    }

    /**
     * @param array<string, string> $upload
     * @return array<string, string>
     */
    public function on_handle_upload(array $upload): array
    {
        if (!$this->should_offload() || empty($upload['file'])) {
            return $upload;
        }
        if (!$this->is_under_uploads((string) $upload['file'])) {
            return $upload;
        }

        $mime = isset($upload['type']) ? (string) $upload['type'] : '';
        if ($this->is_image_mime($mime)) {
            return $upload;
        }

        $this->put_local_file((string) $upload['file'], $mime);
        return $upload;
    }

    /**
     * @param array<string, mixed>|mixed $metadata
     * @return array<string, mixed>|mixed
     */
    public function on_generate_metadata($metadata, $attachment_id = 0)
    {
        if (!is_array($metadata) || empty($metadata['file']) || !$this->should_offload()) {
            return $metadata;
        }

        $uploads = wp_upload_dir();
        $basedir = (string) $uploads['basedir'];
        $primary = $basedir . '/' . ltrim((string) $metadata['file'], '/');
        $ok = $this->put_local_file($primary, (string) (wp_check_filetype($primary)['type'] ?? ''));

        $dir = dirname((string) $metadata['file']);
        $dir_abs = $dir !== '.' ? $basedir . '/' . $dir . '/' : $basedir . '/';

        if (!empty($metadata['original_image'])) {
            $this->put_local_file($dir_abs . $metadata['original_image'], '');
        }

        $opts = Wpsss_Options::get();
        if ($opts['upload_thumbs'] === '1' && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                if (empty($size['file'])) {
                    continue;
                }
                $this->put_local_file($dir_abs . $size['file'], (string) ($size['mime-type'] ?? ''));
            }
        }

        if ((int) $attachment_id > 0) {
            if (!is_wp_error($ok)) {
                $this->mark_offloaded((int) $attachment_id, $this->object_key($primary));
            } else {
                $this->mark_failed((int) $attachment_id, $ok->get_error_message());
            }
        }

        return $metadata;
    }

    /**
     * @return true|WP_Error
     */
    public function offload_attachment(int $post_id)
    {
        if (!$this->should_offload()) {
            return new WP_Error('wpsss_not_ready', '先保存桶和密钥。');
        }
        $file = get_attached_file($post_id);
        if (!$file || !file_exists($file)) {
            return new WP_Error('wpsss_missing_local', '本地文件不在，无法进桶。');
        }
        $meta = wp_get_attachment_metadata($post_id);
        if (is_array($meta) && !empty($meta['file'])) {
            $this->on_generate_metadata($meta, $post_id);
            if (!Wpsss_Library::is_offloaded($post_id)) {
                $this->mark_offloaded($post_id, $this->object_key($file));
            }
            return true;
        }
        $put = $this->put_local_file($file, (string) (get_post_mime_type($post_id) ?: ''));
        if (is_wp_error($put)) {
            $this->mark_failed($post_id, $put->get_error_message());
            return $put;
        }
        $this->mark_offloaded($post_id, $this->object_key($file));
        return true;
    }

    public function mark_offloaded(int $post_id, string $key = ''): void
    {
        update_post_meta($post_id, Wpsss_Library::META_OFFLOADED, '1');
        delete_post_meta($post_id, Wpsss_Library::META_ERROR);
        if ($key !== '') {
            update_post_meta($post_id, Wpsss_Library::META_KEY, $key);
        }
        Wpsss_History::add('offload', '附件 ' . $post_id . ($key !== '' ? ' → ' . $key : ''));
    }

    public function mark_failed(int $post_id, string $message): void
    {
        update_post_meta($post_id, Wpsss_Library::META_ERROR, $message);
        Wpsss_History::add('fail', '附件 ' . $post_id . '：' . $message);
    }

    public static function rewrite_urls_in_text(string $text, string $from_base, string $to_base): string
    {
        return Wpsss_Url::rewrite_text($text, $from_base, $to_base, '');
    }

    /** @var bool */
    private static $editor_hooked = false;

    /**
     * @param mixed $override
     * @return mixed
     */
    public function on_save_image_editor($override)
    {
        if (!self::$editor_hooked) {
            add_filter('wp_update_attachment_metadata', [$this, 'on_generate_metadata']);
            self::$editor_hooked = true;
        }
        return $override;
    }

    public function on_delete_attachment(int $post_id): void
    {
        if (!$this->should_offload()) {
            return;
        }

        $keys = $this->attachment_keys($post_id);
        if ($keys === []) {
            return;
        }

        $this->plugin->active_driver()->delete_objects(Wpsss_Options::get(), $keys);
        Wpsss_History::add('delete', '附件 ' . $post_id . ' ×' . count($keys));
    }

    public function rewrite_url(string $url, int $post_id): string
    {
        $base = Wpsss_Options::public_base();
        $opts = Wpsss_Options::get();
        if ($base === '' || !$this->plugin->active_driver()->is_configured($opts)) {
            return $url;
        }
        if (!Wpsss_Library::is_offloaded($post_id)) {
            return $url;
        }

        $key = $this->attachment_primary_key($post_id);
        if ($key === '') {
            return $url;
        }
        $next = Wpsss_Url::sign_object_url($base, $key, $opts);
        $next = Wpsss_Url::apply_style($next, $opts);
        $mime = (string) get_post_mime_type($post_id);
        return Wpsss_Url::apply_preview($next, $mime, $opts);
    }

    /**
     * @param array<int|string, array<string, mixed>> $sources
     * @param array<int, int> $size_array
     * @param array<string, mixed> $image_meta
     * @return array<int|string, array<string, mixed>>
     */
    public function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        $opts = Wpsss_Options::get();
        $base = Wpsss_Options::public_base();
        if ($base === '' || !is_array($sources) || !$this->plugin->active_driver()->is_configured($opts)) {
            return $sources;
        }
        if (!Wpsss_Library::is_offloaded((int) $attachment_id)) {
            return $sources;
        }

        $primary = $this->attachment_primary_key((int) $attachment_id);
        if ($primary === '') {
            return $sources;
        }
        $dir = dirname($primary);
        $dir = $dir === '.' ? '' : $dir . '/';

        foreach ($sources as $width => $source) {
            if (empty($source['url'])) {
                continue;
            }
            $file = wp_basename((string) $source['url']);
            $sources[$width]['url'] = Wpsss_Url::sign_object_url($base, $dir . $file, $opts);
        }
        $style = Wpsss_Url::should_protect_origin($opts) || Wpsss_Url::style_suffix($opts) !== ''
            ? Wpsss_Url::style_suffix($opts)
            : '';
        return Wpsss_Url::style_srcset($sources, $style);
    }

    public function rewrite_content(string $content): string
    {
        $opts = Wpsss_Options::get();
        $to = Wpsss_Options::public_base();
        if ($to === '' || !$this->plugin->active_driver()->is_configured($opts)) {
            return $content;
        }
        $uploads = wp_upload_dir();
        $from = untrailingslashit((string) $uploads['baseurl']);
        $style = Wpsss_Url::style_suffix($opts);
        $content = Wpsss_Url::rewrite_text($content, $from, $to, $style);
        $extra = (string) ($opts['rewrite_from'] ?? '');
        if ($extra !== '') {
            $content = Wpsss_Url::rewrite_text($content, $extra, $to, $style);
        }
        return Wpsss_Url::sign_rewritten_text($content, $to, $opts);
    }

    private function should_offload(): bool
    {
        if (!empty($_SERVER['REQUEST_URI']) && substr_count((string) $_SERVER['REQUEST_URI'], '/update.php') > 0) {
            return false;
        }
        $settings = Wpsss_Options::get();
        return $this->plugin->active_driver()->is_configured($settings);
    }

    private function is_under_uploads(string $path): bool
    {
        $uploads = wp_upload_dir();
        $basedir = wp_normalize_path((string) $uploads['basedir']);
        return strpos(wp_normalize_path($path), $basedir) === 0;
    }

    private function is_image_mime(string $mime): bool
    {
        return strpos($mime, 'image/') === 0;
    }

    /**
     * @return true|WP_Error
     */
    public function put_local_file(string $absolute_path, string $mime)
    {
        $key = $this->object_key($absolute_path);
        if ($key === '') {
            return new WP_Error('wpsss_bad_key', '不在 uploads 下，跳过。');
        }
        if ($mime === '') {
            $mime = (string) (wp_check_filetype($absolute_path)['type'] ?? 'application/octet-stream');
        }

        $result = $this->plugin->active_driver()->put_object(
            Wpsss_Options::get(),
            $key,
            $absolute_path,
            $mime
        );
        if (is_wp_error($result)) {
            error_log('WPSSS put failed: ' . $result->get_error_message());
            return $result;
        }

        if (Wpsss_Options::get()['keep_local'] !== '1' && file_exists($absolute_path)) {
            @unlink($absolute_path);
        }
        return true;
    }

    public static function key_from_uploads(string $absolute_path, string $basedir): string
    {
        $basedir = str_replace('\\', '/', $basedir);
        $basedir = rtrim($basedir, '/') . '/';
        $path = str_replace('\\', '/', $absolute_path);
        if ($basedir === '/' || strpos($path, $basedir) !== 0) {
            return '';
        }
        return ltrim(substr($path, strlen($basedir)), '/');
    }

    /**
     * @param array<string, mixed> $meta
     * @return string[]
     */
    public static function keys_from_metadata(array $meta): array
    {
        if (empty($meta['file'])) {
            return [];
        }

        $dir = dirname((string) $meta['file']);
        $prefix = $dir === '.' ? '' : $dir . '/';
        $keys = [(string) $meta['file']];
        if (!empty($meta['original_image'])) {
            $keys[] = $prefix . $meta['original_image'];
        }
        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            foreach ($meta['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $keys[] = $prefix . $size['file'];
                }
            }
        }
        if (!empty($meta['backup_sizes']) && is_array($meta['backup_sizes'])) {
            foreach ($meta['backup_sizes'] as $size) {
                if (!empty($size['file'])) {
                    $keys[] = $prefix . $size['file'];
                }
            }
        }
        return array_values(array_unique($keys));
    }

    private function object_key(string $absolute_path): string
    {
        $uploads = wp_upload_dir();
        $key = self::key_from_uploads($absolute_path, (string) $uploads['basedir']);
        if ($key === '') {
            return '';
        }
        return Wpsss_Options::apply_object_prefix($key);
    }

    /**
     * @return string[]
     */
    private function attachment_keys(int $post_id): array
    {
        $meta = wp_get_attachment_metadata($post_id);
        if (!is_array($meta) || empty($meta['file'])) {
            $file = get_attached_file($post_id);
            $key = $file ? $this->object_key($file) : '';
            return $key !== '' ? [$key] : [];
        }

        $keys = self::keys_from_metadata($meta);
        $dir = dirname((string) $meta['file']);
        $prefix = $dir === '.' ? '' : $dir . '/';
        $backups = get_post_meta($post_id, '_wp_attachment_backup_sizes', true);
        if (is_array($backups)) {
            foreach ($backups as $size) {
                if (!empty($size['file'])) {
                    $keys[] = $prefix . $size['file'];
                }
            }
        }
        $stored = (string) get_post_meta($post_id, Wpsss_Library::META_KEY, true);
        if ($stored !== '') {
            $keys[] = $stored;
        }
        $opts = Wpsss_Options::get();
        $keys = array_map(static function ($key) use ($opts) {
            return Wpsss_Options::apply_object_prefix((string) $key, $opts);
        }, $keys);
        return array_values(array_unique($keys));
    }

    private function attachment_primary_key(int $post_id): string
    {
        $stored = (string) get_post_meta($post_id, Wpsss_Library::META_KEY, true);
        if ($stored !== '') {
            return $stored;
        }
        $meta = wp_get_attachment_metadata($post_id);
        if (is_array($meta) && !empty($meta['file'])) {
            return Wpsss_Options::apply_object_prefix((string) $meta['file']);
        }
        $file = get_attached_file($post_id);
        return $file ? $this->object_key($file) : '';
    }
}
