<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * 图片样式、原图保护、正文运行时改写。对照 sy-records 四家插件的 URL 层。
 */
class Wpsss_Url
{
    public static function style_suffix(array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : Wpsss_Options::get();
        $style = trim((string) ($opts['image_style'] ?? ''));
        if ($style === '') {
            return '';
        }
        return $style;
    }

    public static function should_protect_origin(array $opts = []): bool
    {
        $opts = $opts !== [] ? $opts : Wpsss_Options::get();
        return ($opts['origin_protect'] ?? '0') === '1' && self::style_suffix($opts) !== '';
    }

    public static function apply_style(string $url, array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : Wpsss_Options::get();
        $style = self::style_suffix($opts);
        if ($url === '' || $style === '') {
            return $url;
        }
        if (strpos($url, $style) !== false) {
            return $url;
        }
        if (($opts['driver'] ?? '') === 'qcloud-cos' && ($opts['ci_slim'] ?? '0') === '1') {
            $url = self::append_query($url, 'imageMogr2/format/webp');
        }
        return $url . $style;
    }

    public static function apply_preview(string $url, string $mime, array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : Wpsss_Options::get();
        if (($opts['ci_preview'] ?? '0') !== '1') {
            return $url;
        }
        $previewable = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        if (!in_array($mime, $previewable, true)) {
            return $url;
        }
        return self::append_query($url, 'ci-process=doc-preview');
    }

    public static function append_query(string $url, string $extra): string
    {
        if ($extra === '' || strpos($url, $extra) !== false) {
            return $url;
        }
        $join = strpos($url, '?') === false ? '?' : '&';
        return $url . $join . $extra;
    }

    public static function rewrite_text(string $text, string $from_base, string $to_base, string $style = ''): string
    {
        $from = rtrim($from_base, '/');
        $to = rtrim($to_base, '/');
        if ($text === '') {
            return $text;
        }
        if ($from !== '' && $to !== '' && $from !== $to) {
            $text = str_replace($from, $to, $text);
        }
        if ($style === '' || $to === '') {
            return $text;
        }
        if (!preg_match_all('#https?://[^\s"\'<>]+#', $text, $matches)) {
            return $text;
        }
        foreach (array_unique($matches[0]) as $item) {
            if (strpos($item, $to) === false || strpos($item, $style) !== false) {
                continue;
            }
            $text = str_replace($item, $item . $style, $text);
        }
        return str_replace($style . $style, $style, $text);
    }

    /**
     * @param array<int|string, array<string, mixed>> $sources
     * @return array<int|string, array<string, mixed>>
     */
    public static function style_srcset(array $sources, string $style): array
    {
        if ($style === '') {
            return $sources;
        }
        foreach ($sources as $width => $source) {
            if (empty($source['url'])) {
                continue;
            }
            $url = (string) $source['url'];
            if (strpos($url, $style) === false) {
                $sources[$width]['url'] = $url . $style;
            }
        }
        return $sources;
    }
}
