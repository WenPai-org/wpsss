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
        return Wpsss_Url::sign_object_url($base, $key, $opts);
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
