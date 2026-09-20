<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce 付费下载走桶地址。商品图已走附件 URL，这里只改 downloadable file。
 * Woo 没装就当空操作。不粘 Woo 源码。
 */
final class Wpsss_Woo
{
    public function register(): void
    {
        add_action('init', [$this, 'bind'], 20);
    }

    public function bind(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        add_filter('woocommerce_product_file_download_path', [self::class, 'download_path'], 20, 3);
        add_filter('woocommerce_file_download_method', [self::class, 'download_method'], 20, 3);
    }

    public static function is_active(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * @param mixed $file
     * @param mixed $product
     * @param mixed $download_id
     */
    public static function download_path($file, $product = null, $download_id = null)
    {
        unset($product, $download_id);
        if (!is_string($file) || $file === '') {
            return $file;
        }
        $rewritten = self::rewrite_file($file, Wpsss_Options::get());
        return $rewritten !== '' ? $rewritten : $file;
    }

    /**
     * @param mixed $method
     * @param mixed $file_path
     * @param mixed $filename
     */
    public static function download_method($method, $file_path = '', $filename = '')
    {
        unset($filename);
        if (is_string($file_path) && preg_match('#^https?://#i', $file_path)) {
            return 'redirect';
        }
        return $method;
    }

    /**
     * @param array<string, string> $opts
     */
    public static function rewrite_file(string $file, array $opts): string
    {
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => '', 'baseurl' => ''];
        $basedir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
        $baseurl = isset($uploads['baseurl']) ? untrailingslashit((string) $uploads['baseurl']) : '';
        $path = self::absolute_uploads_path($file, $basedir, $baseurl);
        if ($path === '' || $basedir === '') {
            return '';
        }
        $rel = Wpsss_Offload::key_from_uploads($path, $basedir);
        if ($rel === '') {
            return '';
        }
        $key = Wpsss_Options::apply_object_prefix($rel, $opts);
        $base = Wpsss_Options::public_base($opts);
        if ($base === '') {
            return '';
        }
        // 对象不在桶里（商家直传本地、已拉回删除等）就退回本地地址，别让订单下载 404。
        if (!self::object_in_bucket($key, $opts)) {
            return '';
        }
        // 私有策略的付费文件：无论全站是否签名访问，下载地址一律签名。
        if (Wpsss_Offload::key_is_private_dir($rel, $opts)) {
            $opts['url_access'] = 'signed';
        }
        return Wpsss_Url::sign_object_url($base, $key, $opts);
    }

    /** @var array<string, bool> 每请求内缓存存在性，产品列表页会反复问同一批文件 */
    private static $exists_cache = [];

    private static function object_in_bucket(string $key, array $opts): bool
    {
        if (isset(self::$exists_cache[$key])) {
            return self::$exists_cache[$key];
        }
        // 单测环境没有插件实例时沿用旧行为（视为存在）。
        if (!function_exists('wpsss')) {
            return true;
        }
        $driver = wpsss()->active_driver();
        if (!$driver->is_configured($opts)) {
            return false;
        }
        $ok = !is_wp_error($driver->head_object($opts, $key));
        self::$exists_cache[$key] = $ok;
        return $ok;
    }

    public static function absolute_uploads_path(string $file, string $basedir, string $baseurl): string
    {
        $file = trim($file);
        if ($file === '') {
            return '';
        }
        $basedir = rtrim(str_replace('\\', '/', $basedir), '/');
        $baseurl = untrailingslashit($baseurl);
        if (preg_match('#^https?://#i', $file)) {
            if ($baseurl !== '' && strpos($file, $baseurl . '/') === 0) {
                return $basedir . '/' . ltrim(substr($file, strlen($baseurl)), '/');
            }
            return '';
        }
        $file = str_replace('\\', '/', $file);
        if ($basedir !== '' && strpos($file, $basedir . '/') === 0) {
            return $file;
        }
        if ($file[0] !== '/' && $basedir !== '') {
            return $basedir . '/' . ltrim($file, '/');
        }
        return '';
    }
}
