<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 按扩展名给对象 Content-Type。mime_content_type() 常把 .css 标成 text/plain，
 * 浏览器不当样式表用；字体包同步必须用扩展名优先。
 */
final class Wpsss_Mime
{
    /**
     * @return array<string, string>
     */
    public static function extension_map(): array
    {
        return [
            'css'   => 'text/css',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'json'  => 'application/json',
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'js'    => 'application/javascript',
            'map'   => 'application/json',
            'txt'   => 'text/plain',
            'html'  => 'text/html',
            'htm'   => 'text/html',
            'xml'   => 'application/xml',
        ];
    }

    /**
     * 为本地文件选 MIME。扩展名命中则用之；否则尝试 WordPress / fileinfo；最后 octet-stream。
     */
    public static function for_file(string $absolute_path, string $object_key = ''): string
    {
        $name = $object_key !== '' ? $object_key : $absolute_path;
        $ext  = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $map  = self::extension_map();
        if ($ext !== '' && isset($map[$ext])) {
            return $map[$ext];
        }
        if (function_exists('wp_check_filetype')) {
            $type = (string) (wp_check_filetype($name)['type'] ?? '');
            if ($type !== '') {
                return $type;
            }
        }
        if (function_exists('mime_content_type') && is_readable($absolute_path)) {
            $detected = (string) (mime_content_type($absolute_path) ?: '');
            if ($detected !== '' && $detected !== 'text/plain') {
                return $detected;
            }
            // text/plain 对未知扩展才保留；.css 已在 map 里。
            if ($detected === 'text/plain' && ($ext === '' || $ext === 'txt')) {
                return 'text/plain';
            }
        }
        return 'application/octet-stream';
    }
}
