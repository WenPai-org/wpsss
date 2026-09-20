<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 桶间 / 厂商间搬运。同厂商走服务端复制；跨厂商先分段拉到临时文件再上传。
 * 只搬对象；要不要把插件切到新桶、改不改附件标记，由人决定（--update-keys 另说）。
 */
final class Wpsss_Copy
{
    /**
     * 复制一个对象。返回 true 或 WP_Error。
     *
     * @param Wpsss_Driver $from_driver
     * @param array<string, string> $from_opts
     * @param Wpsss_Driver $to_driver
     * @param array<string, string> $to_opts
     * @return true|WP_Error
     */
    public static function copy_key($from_driver, array $from_opts, string $from_key, $to_driver, array $to_opts, string $to_key)
    {
        $same_vendor = get_class($from_driver) === get_class($to_driver);
        if ($same_vendor && $to_driver->copy_object($to_opts, $to_key, $from_opts, $from_key) === true) {
            return true;
        }
        // 跨厂商（或服务端复制不被接受）：中转临时文件，全程流式。
        $tmp = wp_tempnam('wpsss-copy');
        if ($tmp === false) {
            return new WP_Error('wpsss_copy_tmp', '建不了临时文件。');
        }
        $download = Wpsss_Stream::download(static function (int $start, int $end) use ($from_driver, $from_opts, $from_key) {
            return $from_driver->get_object_range($from_opts, $from_key, $start, $end);
        }, $tmp);
        if (is_wp_error($download)) {
            @unlink($tmp);
            return $download;
        }
        $mime = (string) (wp_check_filetype($to_key)['type'] ?? 'application/octet-stream');
        $put = $to_driver->put_object($to_opts, $to_key, $tmp, $mime);
        @unlink($tmp);
        return $put;
    }
}
