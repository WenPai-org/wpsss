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
        add_filter('wp_get_attachment_thumb_url', [$this, 'rewrite_url'], 30, 2);
        add_filter('wp_get_original_image_url', [$this, 'rewrite_url'], 30, 2);
        add_filter('image_get_intermediate_size', [$this, 'rewrite_intermediate'], 10, 3);
        add_filter('image_downsize', [$this, 'rewrite_image_downsize'], 10, 3);
        add_filter('wp_get_attachment_image_src', [$this, 'rewrite_image_src'], 30, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'rewrite_srcset'], 10, 5);
        add_filter('the_content', [$this, 'rewrite_content'], 100);
        add_filter('the_excerpt', [$this, 'rewrite_content'], 100);
        add_filter('post_thumbnail_html', [$this, 'rewrite_content'], 100);
        add_filter('widget_text', [$this, 'rewrite_content'], 100);
        add_filter('widget_block_content', [$this, 'rewrite_content'], 100);
        add_filter('term_description', [$this, 'rewrite_content'], 100);
        add_filter('the_content_feed', [$this, 'rewrite_content'], 100);
        add_filter('the_excerpt_rss', [$this, 'rewrite_content'], 100);
        add_filter('comment_text', [$this, 'rewrite_content'], 100);
        add_filter('rest_prepare_attachment', [$this, 'rewrite_rest_attachment'], 20, 3);
    }

    /**
     * @return string[]
     */
    public static function denied_extensions(): array
    {
        return ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phps', 'htaccess', 'asp', 'aspx', 'jsp', 'cgi'];
    }

    /**
     * @param array<string, string> $opts
     */
    public static function should_put_path(string $path, array $opts = []): bool
    {
        $base = strtolower(basename($path));
        if ($base === '.htaccess' || $base === 'htaccess') {
            return false;
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '') {
            return true;
        }
        if (in_array($ext, self::denied_extensions(), true)) {
            return false;
        }
        $skip = [];
        if (class_exists('Wpsss_Options') && method_exists('Wpsss_Options', 'sanitize_exclude_ext')) {
            $skip = explode(',', Wpsss_Options::sanitize_exclude_ext((string) ($opts['exclude_ext'] ?? '')));
            $skip = array_values(array_filter($skip, static function ($item) {
                return $item !== '';
            }));
        }
        return !in_array($ext, $skip, true);
    }

    /**
     * @param array<string, string> $opts
     */
    public static function is_denied_path(string $path): bool
    {
        $base = strtolower(basename($path));
        if ($base === '.htaccess' || $base === 'htaccess') {
            return true;
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        return $ext !== '' && in_array($ext, self::denied_extensions(), true);
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

        $uploads = $this->uploads();
        $basedir = (string) $uploads['basedir'];
        $primary = $basedir . '/' . ltrim((string) $metadata['file'], '/');
        $filesize = file_exists($primary) ? (string) filesize($primary) : '';
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
                if ($filesize !== '') {
                    update_post_meta((int) $attachment_id, '_wpsss_filesize', $filesize);
                }
                $this->mark_offloaded((int) $attachment_id, $this->object_key($primary));
            } elseif ($ok->get_error_code() !== 'wpsss_skip_type') {
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
            if ($put->get_error_code() === 'wpsss_skip_type') {
                return true;
            }
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
        $file = get_attached_file($post_id);
        if ($file && file_exists($file)) {
            update_post_meta($post_id, '_wpsss_filesize', (string) filesize($file));
        }
        Wpsss_History::add_offload($post_id, $key);
    }

    public function mark_failed(int $post_id, string $message): void
    {
        update_post_meta($post_id, Wpsss_Library::META_ERROR, $message);
        Wpsss_History::add_fail($post_id, $message);
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
        $delete = function () use ($post_id): void {
            if (!$this->should_offload()) {
                return;
            }

            $keys = $this->attachment_keys($post_id);
            if ($keys === []) {
                return;
            }

            $this->plugin->active_driver()->delete_objects(Wpsss_Options::get(), $keys);
            Wpsss_History::add_delete_attachment($post_id, count($keys));
        };

        if (class_exists('Wpsss_Site') && method_exists('Wpsss_Site', 'with_attachment_blog')) {
            Wpsss_Site::with_attachment_blog($post_id, $delete);
            return;
        }

        $delete();
    }

    /**
     * @param mixed $data
     * @param mixed $post_id
     * @param mixed $size
     * @return mixed
     */
    public function rewrite_intermediate($data, $post_id, $size)
    {
        unset($size);
        if (!is_array($data)) {
            return $data;
        }
        if (!Wpsss_Library::is_offloaded((int) $post_id) || Wpsss_Options::public_base() === '') {
            return $data;
        }
        if (isset($data['url'])) {
            $data['url'] = $this->rewrite_url((string) $data['url'], (int) $post_id);
        }
        return $data;
    }

    /**
     * WP image_downsize 用 wp_basename(全图 URL) 换尺寸文件名。
     * 签名读的 query 会进 basename，整段被换成 150x150 后签名丢失，私有桶 403。
     *
     * @param mixed $out
     * @param mixed $id
     * @param mixed $size
     * @return mixed
     */
    public function rewrite_image_downsize($out, $id, $size)
    {
        if ($out !== false) {
            return $out;
        }
        if (!Wpsss_Library::is_offloaded((int) $id) || Wpsss_Options::public_base() === '') {
            return false;
        }
        if (!function_exists('image_get_intermediate_size')) {
            return false;
        }
        $intermediate = image_get_intermediate_size((int) $id, $size);
        if (!is_array($intermediate) || empty($intermediate['url'])) {
            return false;
        }
        return [
            (string) $intermediate['url'],
            isset($intermediate['width']) ? (int) $intermediate['width'] : 0,
            isset($intermediate['height']) ? (int) $intermediate['height'] : 0,
            true,
        ];
    }

    /**
     * 媒体库列表 / wp_get_attachment_image 走这里。核心 mime 图标不改写。
     *
     * @param mixed $image
     * @param mixed $attachment_id
     * @param mixed $size
     * @param mixed $icon
     * @return mixed
     */
    public function rewrite_image_src($image, $attachment_id, $size, $icon)
    {
        unset($size, $icon);
        if (!is_array($image) || empty($image[0]) || !is_string($image[0])) {
            return $image;
        }
        if (!self::url_is_ours($image[0])) {
            return $image;
        }
        $image[0] = $this->rewrite_url($image[0], (int) $attachment_id);
        return $image;
    }

    public static function url_is_ours(string $url): bool
    {
        if ($url === '' || strpos($url, '/wp-includes/images/media/') !== false) {
            return false;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || !class_exists('Wpsss_Options')) {
            return false;
        }
        $base_host = (string) parse_url(Wpsss_Options::public_base(), PHP_URL_HOST);
        if ($base_host !== '' && strcasecmp($host, $base_host) === 0) {
            return true;
        }
        if (!function_exists('wp_upload_dir')) {
            return false;
        }
        $uploads = wp_upload_dir();
        $upload_base = rtrim((string) ($uploads['baseurl'] ?? ''), '/');
        return $upload_base !== '' && strpos($url, $upload_base) === 0;
    }

    public static function safe_upload_url_path(string $value): string
    {
        $value = trim($value);
        if ($value === '.' || $value === '') {
            return '';
        }
        return untrailingslashit($value);
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

        $primary = $this->attachment_primary_key($post_id);
        $style = method_exists('Wpsss_Url', 'style_suffix') ? Wpsss_Url::style_suffix($opts) : '';
        $key = self::object_key_for_url($url, $primary, $style);
        if ($key === '') {
            return $url;
        }
        $mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($post_id) : '';
        $next = method_exists('Wpsss_Url', 'serve_object_url')
            ? Wpsss_Url::serve_object_url($base, $key, $opts, $mime)
            : Wpsss_Url::sign_object_url($base, $key, $opts);
        if (!method_exists('Wpsss_Url', 'serve_object_url')) {
            $next = Wpsss_Url::apply_style($next, $opts, $mime);
        }
        return Wpsss_Url::apply_preview($next, $mime, $opts);
    }

    /**
     * 缩略图 / 原图 / 中间尺寸跟主文件同目录。空 URL 退回主对象键。
     */
    public static function object_key_for_url(string $url, string $primary_key, string $style = ''): string
    {
        if ($primary_key === '') {
            return '';
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        if ($path === '') {
            return $primary_key;
        }
        $file = basename($path);
        if ($file === '' || $file === '.' || $file === '/') {
            return $primary_key;
        }
        if ($style !== '' && $style[0] !== '?' && $style[0] !== '&' && substr($file, -strlen($style)) === $style) {
            $file = substr($file, 0, -strlen($style));
        }
        $dir = dirname(str_replace('\\', '/', $primary_key));
        $prefix = ($dir === '.' || $dir === '') ? '' : $dir . '/';
        return $prefix . $file;
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
            $key = self::object_key_for_url((string) $source['url'], $primary, Wpsss_Url::style_suffix($opts));
            $sources[$width]['url'] = method_exists('Wpsss_Url', 'serve_object_url')
                ? Wpsss_Url::serve_object_url($base, $key, $opts)
                : Wpsss_Url::sign_object_url($base, $key !== '' ? $key : $dir . basename((string) parse_url((string) $source['url'], PHP_URL_PATH)), $opts);
        }
        if (method_exists('Wpsss_Url', 'serve_object_url')) {
            return $sources;
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
        // 不要把整站 uploads 主机换成自定义域：未进桶的旧图会 404。
        // 新附件走 wp_get_attachment_*。工具页改写走 library，不走这条。
        $extra = (string) ($opts['rewrite_from'] ?? '');
        if ($extra === '') {
            return $content;
        }
        $style = Wpsss_Url::style_suffix($opts);
        $content = Wpsss_Url::rewrite_text($content, $extra, $to, $style);
        return Wpsss_Url::sign_rewritten_text($content, $to, $opts);
    }

    /**
     * @param mixed $response
     * @param mixed $post
     * @param mixed $request
     * @return mixed
     */
    public function rewrite_rest_attachment($response, $post, $request)
    {
        unset($request);
        if (!is_object($response) || !method_exists($response, 'get_data') || !method_exists($response, 'set_data')) {
            return $response;
        }
        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }
        $id = is_object($post) && isset($post->ID) ? (int) $post->ID : (int) ($data['id'] ?? 0);
        if ($id <= 0 || !Wpsss_Library::is_offloaded($id)) {
            return $response;
        }
        if (!empty($data['source_url'])) {
            $data['source_url'] = $this->rewrite_url((string) $data['source_url'], $id);
        }
        if (isset($data['guid']) && is_array($data['guid']) && !empty($data['guid']['rendered'])) {
            $data['guid']['rendered'] = $this->rewrite_url((string) $data['guid']['rendered'], $id);
        }
        $response->set_data($data);
        return $response;
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
        $uploads = $this->uploads();
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
        $opts = Wpsss_Options::get();
        if (!self::should_put_path($absolute_path, $opts)) {
            $code = self::is_denied_path($absolute_path) ? 'wpsss_denied' : 'wpsss_skip_type';
            return new WP_Error($code, '此类文件不进桶。');
        }
        if (($opts['skip_exist'] ?? '0') === '1') {
            $head = $this->plugin->active_driver()->head_object($opts, $key);
            if (!is_wp_error($head)) {
                return true;
            }
        }
        if ($mime === '') {
            $mime = (string) (wp_check_filetype($absolute_path)['type'] ?? 'application/octet-stream');
        }

        $result = $this->plugin->active_driver()->put_object(
            $opts,
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

    /**
     * @param array<string, string> $opts
     */
    public static function local_rel_from_object_key(string $key, array $opts = []): string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        $prefix = trim((string) ($opts['object_prefix'] ?? ''), '/');
        if ($prefix !== '' && strpos($key, $prefix . '/') === 0) {
            return substr($key, strlen($prefix) + 1);
        }
        return $key;
    }

    /**
     * @return true|WP_Error
     */
    public function pull_attachment(int $post_id, bool $delete_remote = false)
    {
        $opts = Wpsss_Options::get();
        $driver = $this->plugin->active_driver();
        if (!$driver->is_configured($opts)) {
            return new WP_Error('wpsss_not_configured', '先保存桶和密钥。');
        }
        $keys = $this->attachment_keys($post_id);
        if ($keys === []) {
            return new WP_Error('wpsss_no_key', '没有对象键，无法拉回。');
        }
        $uploads = $this->uploads();
        $basedir = (string) $uploads['basedir'];
        foreach ($keys as $key) {
            $rel = self::local_rel_from_object_key((string) $key, $opts);
            if ($rel === '' || strpos($rel, '..') !== false) {
                return new WP_Error('wpsss_bad_key', '对象键不安全。');
            }
            $dest = $basedir . '/' . $rel;
            if (!wp_mkdir_p(dirname($dest))) {
                return new WP_Error('wpsss_mkdir', '建不了本地目录。');
            }
            $body = $driver->get_object($opts, (string) $key);
            if (is_wp_error($body)) {
                return $body;
            }
            if (file_put_contents($dest, $body) === false) {
                return new WP_Error('wpsss_write', '写本地失败：' . $rel);
            }
        }
        if ($delete_remote) {
            $del = $driver->delete_objects($opts, $keys);
            if (is_wp_error($del)) {
                return $del;
            }
            delete_post_meta($post_id, Wpsss_Library::META_OFFLOADED);
            delete_post_meta($post_id, Wpsss_Library::META_KEY);
        }
        delete_post_meta($post_id, Wpsss_Library::META_ERROR);
        Wpsss_History::add_pull($post_id, $delete_remote);
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
        $uploads = $this->uploads();
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

    /** @return array<string, mixed> */
    private function uploads(): array
    {
        if (class_exists('Wpsss_Site') && method_exists('Wpsss_Site', 'uploads')) {
            return Wpsss_Site::uploads();
        }
        return function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => '', 'baseurl' => ''];
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
