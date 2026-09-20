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
        // WP 给音视频短网址追加 ?_=时间戳 缓存戳；签名 URL 的 query 一变就 403，这里剥掉。
        add_filter('wp_audio_shortcode', [$this, 'strip_media_cache_buster'], 100);
        add_filter('wp_video_shortcode', [$this, 'strip_media_cache_buster'], 100);
        // keep_local=0 时本地文件不在：编辑/再生成等场景退回桶地址（AS3CF 同款语义）。
        add_filter('get_attached_file', [$this, 'filter_attached_file'], 10, 2);
        add_filter('wp_get_original_image_path', [$this, 'filter_attached_file'], 10, 2);
        // FSE 区块模板与 Customizer 背景/头图里的硬编码地址，也走按附件改写。
        add_filter('render_block', [$this, 'rewrite_content'], 100);
        add_filter('theme_mod_background_image', [$this, 'rewrite_content'], 100);
        add_filter('theme_mod_header_image', [$this, 'rewrite_content'], 100);
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
     * 本请求内已经进过桶的文件（wp_handle_upload 先于附件创建）。
     * 后续 add_attachment / metadata 钩子据此跳过重复 PUT，keep_local=0 删了本地也能靠它认账。
     *
     * @var array<string, string>
     */
    private static $just_put = [];

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

        $path = wp_normalize_path((string) $upload['file']);
        $put = $this->put_local_file($path, $mime);
        if (!is_wp_error($put)) {
            self::$just_put[$path] = $this->object_key($path);
        }
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
        // 主文件可能已被 wp_handle_upload 传过（非图片先于附件创建进桶），不重复 PUT。
        $primary_done = isset(self::$just_put[wp_normalize_path($primary)]);
        $filesize = file_exists($primary) ? (string) filesize($primary) : '';
        $ok = $primary_done ? true : $this->put_local_file($primary, (string) (wp_check_filetype($primary)['type'] ?? ''));

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
            } elseif ($ok->get_error_code() !== 'wpsss_skip_type' && $ok->get_error_code() !== 'wpsss_woo_exclude') {
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
            // 本地已不在：可能是 wp_handle_upload 传完即删（keep_local=0），或进程死在半路。
            // 先问一次桶，桶里有就补上标记；两边都没有才记失败，不再无声卡在待传里。
            if ($file) {
                $key = $this->object_key($file);
                $head = $this->plugin->active_driver()->head_object(Wpsss_Options::get(), $key);
                if (!is_wp_error($head)) {
                    $this->mark_offloaded($post_id, $key);
                    return true;
                }
            }
            $this->mark_failed($post_id, '本地文件不在，桶里也没有，无法进桶。');
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
        // wp_handle_upload 已经传过同一个文件（含第三方直传），不再重复 PUT。
        $normalized = wp_normalize_path($file);
        if (!isset(self::$just_put[$normalized])) {
            $put = $this->put_local_file($file, (string) (get_post_mime_type($post_id) ?: ''));
            if (is_wp_error($put)) {
                if ($put->get_error_code() === 'wpsss_skip_type') {
                    return true;
                }
                if ($put->get_error_code() === 'wpsss_woo_exclude') {
                    update_post_meta($post_id, Wpsss_Library::META_LOCAL_ONLY, '1');
                    return true;
                }
                $this->mark_failed($post_id, $put->get_error_message());
                return $put;
            }
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

    /**
     * @param mixed $override
     * @return mixed
     */
    public function on_save_image_editor($override)
    {
        // boot() 已用优先级 100 挂了 wp_update_attachment_metadata；
        // 这里再挂一条（默认优先级 10）会导致图像编辑保存时整套尺寸双倍上传。
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

    /**
     * 本地文件已删（keep_local=0）且附件已进桶时，get_attached_file 退回桶地址。
     * 本地仍在则原样返回；未进桶也原样返回（调用方自行处理缺文件）。
     *
     * @param string|mixed $file
     * @param int|mixed $attachment_id
     * @return string|mixed
     */
    public function filter_attached_file($file, $attachment_id = 0)
    {
        if (!is_string($file) || $file === '' || (int) $attachment_id <= 0) {
            return $file;
        }
        if (file_exists($file) || !Wpsss_Library::is_offloaded((int) $attachment_id)) {
            return $file;
        }
        $opts = Wpsss_Options::get();
        if (!$this->plugin->active_driver()->is_configured($opts)) {
            return $file;
        }
        $base = Wpsss_Options::public_base();
        if ($base === '') {
            return $file;
        }
        $key = (string) get_post_meta((int) $attachment_id, Wpsss_Library::META_KEY, true);
        if ($key === '') {
            $key = $this->object_key($file);
        }
        if ($key === '') {
            return $file;
        }
        return Wpsss_Url::sign_object_url($base, $key, $opts);
    }

    public function rewrite_content(string $content): string
    {
        $opts = Wpsss_Options::get();
        $to = Wpsss_Options::public_base();
        if ($to === '' || !$this->plugin->active_driver()->is_configured($opts)) {
            return $content;
        }
        // 先按附件改写：正文/区块里写死的本站 uploads 地址，只有对应附件确实
        // 进桶才换成桶地址；未进桶的旧图保持本地，不做整站换主机。
        $content = $this->rewrite_local_attachment_urls($content, $opts, $to);
        // 再做前缀级替换（迁移旧 CDN 域名时填写 rewrite_from）。
        $extra = (string) ($opts['rewrite_from'] ?? '');
        if ($extra !== '') {
            $style = Wpsss_Url::style_suffix($opts);
            $content = Wpsss_Url::rewrite_text($content, $extra, $to, $style);
        }
        return Wpsss_Url::sign_rewritten_text($content, $to, $opts);
    }

    /** @var array<string, bool> 相对路径 => 是否进桶（每请求缓存，避免重复查库） */
    private $offloaded_rel_cache = [];

    private function rewrite_local_attachment_urls(string $content, array $opts, string $to): string
    {
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : [];
        $baseurl = isset($uploads['baseurl']) ? untrailingslashit((string) $uploads['baseurl']) : '';
        if ($baseurl === '') {
            return $content;
        }
        $bases = [$baseurl];
        $extra = isset($opts['rewrite_from']) ? untrailingslashit((string) $opts['rewrite_from']) : '';
        if ($extra !== '' && $extra !== $baseurl) {
            $bases[] = $extra;
        }
        $pattern = '~(' . implode('|', array_map(static function ($b) {
            return preg_quote((string) $b, '~');
        }, $bases)) . ')/([^\s"\'<>)]+)~';
        $that = $this;
        return (string) preg_replace_callback($pattern, static function (array $m) use ($that, $to) {
            $rel = (string) $m[2];
            $path = preg_replace('/[?#].*$/', '', $rel);
            if ($path === '' || !$that->rel_is_offloaded((string) $path)) {
                return $m[0];
            }
            return $to . '/' . $rel;
        }, $content);
    }

    /**
     * 相对 uploads 的文件路径是否属于一个已进桶的附件。
     * 尺寸文件（name-300x200.jpg）查不到 meta 时回落到原图再查一次。
     */
    private function rel_is_offloaded(string $path): bool
    {
        if (isset($this->offloaded_rel_cache[$path])) {
            return $this->offloaded_rel_cache[$path];
        }
        $candidates = [$path];
        if (preg_match('/(-\d+x\d+)(\.[a-z0-9]{2,6})$/i', $path, $m)) {
            $candidates[] = substr($path, -(strlen($m[1]) + strlen($m[2]))) === '' ? '' : substr($path, 0, strlen($path) - strlen($m[1]) - strlen($m[2])) . $m[2];
        }
        $ok = false;
        foreach ($candidates as $rel) {
            $ids = get_posts([
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => '_wp_attached_file',
                'meta_value' => $rel,
                'suppress_filters' => true,
            ]);
            foreach ($ids as $id) {
                if ((int) $id > 0 && Wpsss_Library::is_offloaded((int) $id)) {
                    $ok = true;
                    break 2;
                }
            }
        }
        $this->offloaded_rel_cache[$path] = $ok;
        return $ok;
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
     * @param string|mixed $html
     * @return string|mixed
     */
    public function strip_media_cache_buster($html)
    {
        if (!is_string($html)) {
            return $html;
        }
        return preg_replace('/(&#038;|&)_=[0-9]+/', '', $html);
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
        if (self::key_is_excluded_dir(Wpsss_Offload::local_rel_from_object_key($key, $opts), $opts)) {
            return new WP_Error('wpsss_woo_exclude', '付费下载目录按设置不进桶。');
        }
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

        $acl = self::key_is_private_dir(Wpsss_Offload::local_rel_from_object_key($key, $opts), $opts) ? 'private' : 'public-read';
        $result = $this->plugin->active_driver()->put_object(
            $opts,
            $key,
            $absolute_path,
            $mime,
            $acl
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

    public static function sanitize_on_exist(string $mode): string
    {
        return in_array($mode, ['skip', 'rename', 'overwrite'], true) ? $mode : 'skip';
    }

    /** @var string[] 付费下载等只应留在服务器或私有的目录（相对 uploads） */
    public const PRIVATE_DIRS = ['woocommerce_uploads'];

    /**
     * 付费下载目录策略：exclude=不进桶（默认）；private=私有进桶、下载走签名；off=照常。
     */
    public static function key_is_excluded_dir(string $rel, array $opts = []): bool
    {
        if ((string) ($opts['woo_policy'] ?? 'exclude') !== 'exclude') {
            return false;
        }
        return self::rel_in_private_dirs($rel);
    }

    public static function key_is_private_dir(string $rel, array $opts = []): bool
    {
        if ((string) ($opts['woo_policy'] ?? 'exclude') !== 'private') {
            return false;
        }
        return self::rel_in_private_dirs($rel);
    }

    private static function rel_in_private_dirs(string $rel): bool
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        foreach (self::PRIVATE_DIRS as $dir) {
            if ($rel === $dir || strpos($rel, $dir . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    public static function key_fits_current_prefix(string $key, array $opts = []): bool
    {
        $prefix = trim((string) ($opts['object_prefix'] ?? ''), '/');
        $key = ltrim(str_replace('\\', '/', $key), '/');
        if ($key === '' || $prefix === '') {
            return true;
        }
        return $key === $prefix || strpos($key, $prefix . '/') === 0;
    }

    /**
     * @param array<int, array{0: string, 1: string}> $moved sidecar => dest
     */
    public static function restore_sidecars(array $moved): void
    {
        foreach (array_reverse($moved) as $pair) {
            $side = (string) ($pair[0] ?? '');
            $dest = (string) ($pair[1] ?? '');
            if ($side === '' || $dest === '' || !is_file($side) || is_file($dest)) {
                continue;
            }
            @rename($side, $dest);
        }
    }

    public static function sidecar_path(string $dest, string $stamp = ''): string
    {
        $info = pathinfo($dest);
        $dir = (string) ($info['dirname'] ?? '.');
        $name = (string) ($info['filename'] ?? 'file');
        $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
        $stamp = $stamp !== '' ? $stamp : gmdate('Ymd-His');
        $base = $dir . '/' . $name . '-local-' . $stamp . $ext;
        $n = 2;
        while (file_exists($base)) {
            $base = $dir . '/' . $name . '-local-' . $stamp . '-' . $n . $ext;
            $n++;
        }
        return $base;
    }

    public static function same_bytes(string $dest, string $body): bool
    {
        if (!is_file($dest)) {
            return false;
        }
        $size = filesize($dest);
        if ($size === false || $size !== strlen($body)) {
            return false;
        }
        return hash_file('sha256', $dest) === hash('sha256', $body);
    }

    private static function same_file_hash(string $a, string $b): bool
    {
        if (!is_file($a) || !is_file($b)) {
            return false;
        }
        $size_a = filesize($a);
        $size_b = filesize($b);
        if ($size_a === false || $size_b === false || $size_a !== $size_b) {
            return false;
        }
        return hash_file('sha256', $a) === hash_file('sha256', $b);
    }

    /**
     * @return array{keys: int, missing: int, exists: int, would_write: int, would_skip: int, would_rename: int, would_block: int}
     */
    public function plan_pull(int $post_id, string $on_exist = 'skip'): array
    {
        $on_exist = self::sanitize_on_exist($on_exist);
        $opts = Wpsss_Options::get();
        $keys = $this->attachment_keys($post_id);
        $basedir = (string) $this->uploads()['basedir'];
        $missing = 0;
        $exists = 0;
        $write = 0;
        $skip = 0;
        $rename = 0;
        $block = 0;
        foreach ($keys as $key) {
            if (!self::key_fits_current_prefix((string) $key, $opts)) {
                $block++;
                continue;
            }
            $rel = self::local_rel_from_object_key((string) $key, $opts);
            if ($rel === '' || strpos($rel, '..') !== false) {
                // 和执行路径对齐：坏键计入「前缀不符」类拦截，预览不再少报。
                $block++;
                continue;
            }
            $dest = $basedir . '/' . $rel;
            if (!is_file($dest)) {
                $missing++;
                $write++;
                continue;
            }
            $exists++;
            if ($on_exist === 'skip') {
                $skip++;
                continue;
            }
            $write++;
            if ($on_exist === 'rename') {
                $rename++;
            }
        }
        return [
            'keys' => count($keys),
            'missing' => $missing,
            'exists' => $exists,
            'would_write' => $write,
            'would_skip' => $skip,
            'would_rename' => $rename,
            'would_block' => $block,
        ];
    }

    /**
     * @return true|WP_Error
     */
    public function pull_attachment(int $post_id, bool $delete_remote = false, string $on_exist = 'skip')
    {
        $on_exist = self::sanitize_on_exist($on_exist);
        if ($delete_remote && $on_exist === 'skip') {
            return new WP_Error('wpsss_pull_delete_mode', '删桶不能只补缺失。请改用覆盖或旁路改名，并先核对本地。');
        }
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
        $moved = [];
        foreach ($keys as $key) {
            if (!self::key_fits_current_prefix((string) $key, $opts)) {
                self::restore_sidecars($moved);
                return new WP_Error('wpsss_prefix', '对象键对不上当前前缀，已停止，避免写到错误目录。');
            }
            $rel = self::local_rel_from_object_key((string) $key, $opts);
            if ($rel === '' || strpos($rel, '..') !== false) {
                self::restore_sidecars($moved);
                return new WP_Error('wpsss_bad_key', '对象键不安全。');
            }
            $dest = $basedir . '/' . $rel;
            if (!wp_mkdir_p(dirname($dest))) {
                self::restore_sidecars($moved);
                return new WP_Error('wpsss_mkdir', '建不了本地目录。');
            }
            $exists = is_file($dest);
            if ($exists && $on_exist === 'skip') {
                continue;
            }
            if (method_exists($driver, 'get_object_range')) {
                // 大文件路径：分段下载到临时文件，比对后再落到最终位置。
                $tmp = $dest . '.wpsss-pulling';
                $download = Wpsss_Stream::download(static function (int $start, int $end) use ($driver, $opts, $key) {
                    return $driver->get_object_range($opts, (string) $key, $start, $end);
                }, $tmp);
                if (is_wp_error($download)) {
                    @unlink($tmp);
                    self::restore_sidecars($moved);
                    return $download;
                }
                if ($exists && self::same_file_hash($dest, $tmp)) {
                    @unlink($tmp);
                    continue;
                }
                if ($exists && $on_exist === 'rename') {
                    $side = self::sidecar_path($dest);
                    if (!@rename($dest, $side)) {
                        @unlink($tmp);
                        self::restore_sidecars($moved);
                        return new WP_Error('wpsss_rename', '挪不开本地文件：' . basename($dest));
                    }
                    $moved[] = [$side, $dest];
                }
                if (!@rename($tmp, $dest)) {
                    @unlink($tmp);
                    self::restore_sidecars($moved);
                    return new WP_Error('wpsss_write', '写本地失败：' . $rel);
                }
                continue;
            }
            $body = $driver->get_object($opts, (string) $key);
            if (is_wp_error($body)) {
                self::restore_sidecars($moved);
                return $body;
            }
            if (!is_string($body)) {
                self::restore_sidecars($moved);
                return new WP_Error('wpsss_get', '读远端失败：' . $rel);
            }
            if ($exists && self::same_bytes($dest, $body)) {
                continue;
            }
            if ($exists && $on_exist === 'rename') {
                $side = self::sidecar_path($dest);
                if (!@rename($dest, $side)) {
                    self::restore_sidecars($moved);
                    return new WP_Error('wpsss_rename', '挪不开本地文件：' . basename($dest));
                }
                $moved[] = [$side, $dest];
            }
            if (file_put_contents($dest, $body) === false) {
                self::restore_sidecars($moved);
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
    /**
     * CLI 与搬运工具用：一个附件名下的全部对象键（主文件 + 原图 + 各尺寸 + 备份）。
     *
     * @return string[]
     */
    public function attachment_object_keys(int $post_id): array
    {
        return $this->attachment_keys($post_id);
    }

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
