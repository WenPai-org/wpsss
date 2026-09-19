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

    public static function is_styleable(string $url, string $mime = ''): bool
    {
        if ($url === '') {
            return false;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['gif', 'svg'], true)) {
            return false;
        }
        if ($mime === 'image/gif' || $mime === 'image/svg+xml') {
            return false;
        }
        return true;
    }

    public static function apply_style(string $url, array $opts = [], string $mime = ''): string
    {
        $opts = $opts !== [] ? $opts : Wpsss_Options::get();
        $style = self::style_suffix($opts);
        if ($url === '' || $style === '') {
            return $url;
        }
        if (strpos($url, $style) !== false) {
            return $url;
        }
        if (!self::is_styleable($url, $mime)) {
            return $url;
        }
        if (($opts['driver'] ?? '') === 'qcloud-cos' && ($opts['ci_slim'] ?? '0') === '1') {
            $url = self::append_query($url, 'imageMogr2/format/webp');
        }
        if ($style[0] === '?' || $style[0] === '&') {
            return self::append_query($url, ltrim($style, '?&'));
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
            if (!self::is_styleable($item)) {
                continue;
            }
            $styled = ($style[0] === '?' || $style[0] === '&')
                ? self::append_query($item, ltrim($style, '?&'))
                : $item . $style;
            $text = str_replace($item, $styled, $text);
        }
        return str_replace($style . $style, $style, $text);
    }

    /**
     * 读地址：签名（如需）并按 mime 套样式。阿里签名读把 x-oss-process 写进签名串。
     *
     * @param array<string, mixed> $opts
     */
    public static function serve_object_url(string $base, string $key, array $opts, string $mime = '', ?int $now = null): string
    {
        $unsigned = Wpsss_Oss_Signer::object_url($base, $key);
        $style = self::is_styleable($unsigned, $mime) ? self::style_suffix($opts) : '';
        $process = self::oss_process_param($style);
        if (($opts['url_access'] ?? 'public') === 'signed'
            && ($opts['driver'] ?? '') === 'aliyun-oss'
            && $process !== ''
        ) {
            $ak = (string) ($opts['access_key_id'] ?? '');
            $sk = (string) ($opts['access_key_secret'] ?? '');
            if ($ak !== '' && $sk !== '') {
                return Wpsss_Oss_Signer::signed_get_url(
                    $base,
                    (string) ($opts['bucket'] ?? ''),
                    $key,
                    $ak,
                    $sk,
                    max(60, (int) ($opts['signed_ttl'] ?? 3600)),
                    $now,
                    $process
                );
            }
        }
        $url = self::sign_object_url($base, $key, $opts, $now);
        return $style === '' ? $url : self::apply_style($url, $opts, $mime);
    }

    public static function oss_process_param(string $style): string
    {
        if ($style === '') {
            return '';
        }
        if (preg_match('/(?:^|[?&])x-oss-process=([^&]+)/', $style, $m)) {
            return rawurldecode((string) $m[1]);
        }
        return '';
    }

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
        if ($driver === 'qcloud-cos') {
            return Wpsss_Cos_Signer::signed_get_url($base, (string) $opts['bucket'], $key, $ak, $sk, $ttl, $now);
        }
        if ($driver === 'qiniu-kodo') {
            return Wpsss_Kodo_Signer::private_download_url($ak, $sk, Wpsss_Oss_Signer::object_url($base, $key), $ttl, $now);
        }
        if ($driver === 'huawei-obs') {
            return Wpsss_Obs_Signer::signed_get_url($base, (string) $opts['bucket'], $key, $ak, $sk, $ttl, $now);
        }
        if ($driver === 's3-compat' || $driver === 'volc-tos' || $driver === 'gcs') {
            $req = Wpsss_Options::s3_object_request($opts, $key);
            if ($req['url'] === '' || $req['host'] === '') {
                return $url;
            }
            $qs = Wpsss_S3_Signer::sign_get_query(
                $ak,
                $sk,
                $req['host'],
                $req['canonical_uri'],
                Wpsss_S3_Signer::amz_date($now),
                Wpsss_Options::s3_signing_region($opts),
                $ttl
            );
            return $req['url'] . '?' . $qs;
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
            if (
                strpos($item, 'Signature=') !== false
                || strpos($item, 'X-Amz-Signature=') !== false
                || strpos($item, 'q-signature=') !== false
                || strpos($item, 'token=') !== false
            ) {
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
            $style = self::style_suffix($opts);
            if ($style !== '' && $style[0] !== '?' && $style[0] !== '&' && substr($object, -strlen($style)) === $style) {
                $object = substr($object, 0, -strlen($style));
            }
            $text = str_replace($item, self::serve_object_url($base, $object, $opts), $text);
        }
        return $text;
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
            if (!self::is_styleable($url)) {
                continue;
            }
            if (strpos($url, $style) === false) {
                $sources[$width]['url'] = ($style[0] === '?' || $style[0] === '&')
                    ? self::append_query($url, ltrim($style, '?&'))
                    : $url . $style;
            }
        }
        return $sources;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function rewrite_attachment_js(array $response, string $url): array
    {
        $response['url'] = $url;
        $dir = rtrim((string) dirname($url), '/');
        if (isset($response['sizes']) && is_array($response['sizes'])) {
            foreach ($response['sizes'] as $size => $meta) {
                if (!is_array($meta) || empty($meta['url'])) {
                    continue;
                }
                $response['sizes'][$size]['url'] = $dir . '/' . self::attachment_js_basename((string) $meta['url']);
            }
        }
        if (isset($response['icon']) && is_string($response['icon']) && $response['icon'] !== '') {
            if (self::icon_looks_like_attachment($response['icon'], $url)) {
                $response['icon'] = $dir . '/' . self::attachment_js_basename($response['icon']);
            }
        }
        return $response;
    }

    public static function rewrite_maybe_serialized(string $value, callable $rewriter): string
    {
        if (!is_serialized($value)) {
            return $rewriter($value);
        }
        $data = @unserialize($value);
        if ($data === false && $value !== serialize(false)) {
            return $value;
        }
        return serialize(self::rewrite_serialized_walk($data, $rewriter));
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private static function rewrite_serialized_walk($data, callable $rewriter)
    {
        if (is_string($data)) {
            return $rewriter($data);
        }
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                $data[$key] = self::rewrite_serialized_walk($item, $rewriter);
            }
            return $data;
        }
        if (is_object($data) && get_class($data) === 'stdClass') {
            foreach (get_object_vars($data) as $key => $item) {
                $data->{$key} = self::rewrite_serialized_walk($item, $rewriter);
            }
            return $data;
        }
        return $data;
    }

    private static function attachment_js_basename(string $item): string
    {
        if (function_exists('wp_basename')) {
            return wp_basename($item);
        }
        $path = (string) (parse_url($item, PHP_URL_PATH) ?: '');
        $name = basename($path !== '' ? $path : $item);
        $query = parse_url($item, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $name .= '?' . $query;
        }
        return $name;
    }

    private static function icon_looks_like_attachment(string $icon, string $url): bool
    {
        if (strpos($icon, '/uploads/') !== false) {
            return true;
        }
        $icon_host = parse_url($icon, PHP_URL_HOST);
        $url_host = parse_url($url, PHP_URL_HOST);
        return is_string($icon_host) && $icon_host !== '' && $icon_host === $url_host;
    }
}
