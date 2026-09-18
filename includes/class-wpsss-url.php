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
    /**
     * @param array<string, string> $opts
     */
    public static function sign_object_url(string $base, string $key, array $opts, ?int $now = null): string
    {
        $url = Wpsss_Oss_Signer::object_url($base, $key);
        if (($opts['url_access'] ?? 'public') !== 'signed') {
            return $url;
        }
        $driver = (string) ($opts['driver'] ?? '');
        $ttl = max(60, (int) ($opts['signed_ttl'] ?? 3600));
        $ak = (string) ($opts['access_key_id'] ?? '');
        $sk = (string) ($opts['access_key_secret'] ?? '');
        if ($ak === '' || $sk === '') {
            return $url;
        }
        if ($driver === 'aliyun-oss') {
            return Wpsss_Oss_Signer::signed_get_url($base, (string) $opts['bucket'], $key, $ak, $sk, $ttl, $now);
        }
        if ($driver === 's3-compat' || $driver === 'volc-tos' || $driver === 'gcs') {
            $req = Wpsss_Options::s3_object_request($opts, $key);
            if ($req['url'] === '' || $req['host'] === '') {
                return $url;
            }
            $qs = Wpsss_S3_Signer::sign_get_query(
                $ak,
                $sk,
                $req['public_host'] !== '' ? $req['public_host'] : $req['host'],
                $req['canonical_uri'],
                Wpsss_S3_Signer::amz_date($now),
                Wpsss_Options::s3_signing_region($opts),
                $ttl
            );
            $signed_url = $req['public_url'] !== '' ? $req['public_url'] : $req['url'];
            return $signed_url . '?' . $qs;
        }
        return $url;
    }

    /**
     * @param array<string, string> $opts
     */
    public static function sign_rewritten_text(string $text, string $base, array $opts): string
    {
        if (($opts['url_access'] ?? 'public') !== 'signed' || $base === '') {
            return $text;
        }
        $base = rtrim($base, '/');
        if (!preg_match_all('#https?://[^\s"\'<>]+#', $text, $matches)) {
            return $text;
        }
        foreach (array_unique($matches[0]) as $item) {
            if (strpos($item, $base) !== 0) {
                continue;
            }
            if (strpos($item, 'Signature=') !== false || strpos($item, 'X-Amz-Signature=') !== false) {
                continue;
            }
            $path = (string) (wp_parse_url($item, PHP_URL_PATH) ?: '');
            $object = ltrim(rawurldecode($path), '/');
            if ($object === '') {
                continue;
            }
            if (Wpsss_Options::is_s3_family((string) ($opts['driver'] ?? '')) && Wpsss_Options::s3_force_path_style($opts)) {
                $prefix = (string) ($opts['bucket'] ?? '') . '/';
                if (strpos($object, $prefix) === 0) {
                    $object = substr($object, strlen($prefix));
                }
            }
            $text = str_replace($item, self::sign_object_url($base, $object, $opts), $text);
        }
        return $text;
    }

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
