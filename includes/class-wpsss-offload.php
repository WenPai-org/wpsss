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
        add_filter('wp_handle_upload', [$this, 'on_handle_upload'], 50);
        add_filter('wp_generate_attachment_metadata', [$this, 'on_generate_metadata'], 100);
        add_filter('wp_save_image_editor_file', [$this, 'on_save_image_editor'], 101);
        add_action('delete_attachment', [$this, 'on_delete_attachment']);
        add_filter('wp_get_attachment_url', [$this, 'rewrite_url'], 30, 2);
        add_filter('wp_calculate_image_srcset', [$this, 'rewrite_srcset'], 10, 5);
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
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function on_generate_metadata($metadata)
    {
        if (!is_array($metadata) || empty($metadata['file']) || !$this->should_offload()) {
            return $metadata;
        }

        $uploads = wp_upload_dir();
        $basedir = (string) $uploads['basedir'];
        $primary = $basedir . '/' . ltrim((string) $metadata['file'], '/');
        $this->put_local_file($primary, (string) (wp_check_filetype($primary)['type'] ?? ''));

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

        return $metadata;
    }

    /**
     * @param mixed $override
     * @return mixed
     */
    public function on_save_image_editor($override)
    {
        add_filter('wp_update_attachment_metadata', [$this, 'on_generate_metadata']);
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
    }

    public function rewrite_url(string $url, int $post_id): string
    {
        $base = Wpsss_Options::public_base();
        if ($base === '' || !$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
            return $url;
        }

        $key = $this->attachment_primary_key($post_id);
        if ($key === '') {
            return $url;
        }
        return Wpsss_Oss_Signer::object_url($base, $key);
    }

    /**
     * @param array<int|string, array<string, mixed>> $sources
     * @param array<int, int> $size_array
     * @param array<string, mixed> $image_meta
     * @return array<int|string, array<string, mixed>>
     */
    public function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        $base = Wpsss_Options::public_base();
        if ($base === '' || !is_array($sources) || !$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
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
            $sources[$width]['url'] = Wpsss_Oss_Signer::object_url($base, $dir . $file);
        }
        return $sources;
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

    private function put_local_file(string $absolute_path, string $mime): void
    {
        $key = $this->object_key($absolute_path);
        if ($key === '') {
            return;
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
            return;
        }

        if (Wpsss_Options::get()['keep_local'] !== '1' && file_exists($absolute_path)) {
            @unlink($absolute_path);
        }
    }

    private function object_key(string $absolute_path): string
    {
        $uploads = wp_upload_dir();
        $basedir = wp_normalize_path(trailingslashit((string) $uploads['basedir']));
        $path = wp_normalize_path($absolute_path);
        if (strpos($path, $basedir) !== 0) {
            return '';
        }
        return ltrim(substr($path, strlen($basedir)), '/');
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
        $backups = get_post_meta($post_id, '_wp_attachment_backup_sizes', true);
        if (is_array($backups)) {
            foreach ($backups as $size) {
                if (!empty($size['file'])) {
                    $keys[] = $prefix . $size['file'];
                }
            }
        }
        return array_values(array_unique($keys));
    }

    private function attachment_primary_key(int $post_id): string
    {
        $meta = wp_get_attachment_metadata($post_id);
        if (is_array($meta) && !empty($meta['file'])) {
            return (string) $meta['file'];
        }
        $file = get_attached_file($post_id);
        return $file ? $this->object_key($file) : '';
    }
}
