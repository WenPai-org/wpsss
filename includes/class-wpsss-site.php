<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

final class Wpsss_Site
{
    public static function blog_id(): int
    {
        return function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
    }

    /** @return array<string, mixed> */
    public static function uploads(): array
    {
        return function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => '', 'baseurl' => ''];
    }

    /**
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function with_blog(int $blog_id, callable $fn)
    {
        if (!function_exists('is_multisite') || !is_multisite() || !function_exists('switch_to_blog')) {
            return $fn();
        }
        $current = self::blog_id();
        if ($blog_id <= 0 || $blog_id === $current) {
            return $fn();
        }
        switch_to_blog($blog_id);
        try {
            return $fn();
        } finally {
            if (function_exists('restore_current_blog')) {
                restore_current_blog();
            }
        }
    }

    /**
     * 附件一般已在当前 blog。多站点下若能拿到 get_current_blog_id 就够。
     * 提供挂钩给 C：with_attachment_blog($post_id, $fn) 目前 = $fn()，
     * 除非 is_multisite 且函数 get_object_term 等——不要猜 blog_id。
     * 只包一层 with_blog(self::blog_id(), $fn) 即可。
     */
    public static function with_attachment_blog(int $post_id, callable $fn)
    {
        unset($post_id);
        return self::with_blog(self::blog_id(), $fn);
    }
}
