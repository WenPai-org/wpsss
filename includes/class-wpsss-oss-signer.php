<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * Aliyun OSS Signature Version 1 (HMAC-SHA1) 与 Version 4 (OSS4-HMAC-SHA256)。
 */
class Wpsss_Oss_Signer
{
    public const UNSIGNED_PAYLOAD = 'UNSIGNED-PAYLOAD';

    public static function content_md5(string $body): string
    {
        return base64_encode(md5($body, true));
    }

    public static function gmt_date(?int $timestamp = null): string
    {
        return gmdate('D, d M Y H:i:s', $timestamp ?? time()) . ' GMT';
    }

    /**
     * @param array<string, string> $oss_headers headers that start with x-oss-
     */
    public static function canonical_string(
        string $method,
        string $content_md5,
        string $content_type,
        string $date,
        string $bucket,
        string $object,
        array $oss_headers = [],
        string $subresource = ''
    ): string {
        $header_block = '';
        if ($oss_headers !== []) {
            $normalized = [];
            foreach ($oss_headers as $name => $value) {
                $normalized[strtolower($name)] = trim($value);
            }
            ksort($normalized);
            foreach ($normalized as $name => $value) {
                $header_block .= $name . ':' . $value . "\n";
            }
        }

        $resource = '/' . $bucket . '/' . ltrim($object, '/');
        if ($subresource !== '') {
            $resource .= '?' . $subresource;
        }
        return strtoupper($method) . "\n"
            . $content_md5 . "\n"
            . $content_type . "\n"
            . $date . "\n"
            . $header_block
            . $resource;
    }

    public static function signature(string $secret, string $canonical): string
    {
        return base64_encode(hash_hmac('sha1', $canonical, $secret, true));
    }

    /**
     * @param array<string, string> $oss_headers
     */
    public static function authorization(
        string $access_key_id,
        string $secret,
        string $method,
        string $content_md5,
        string $content_type,
        string $date,
        string $bucket,
        string $object,
        array $oss_headers = [],
        string $subresource = ''
    ): string {
        $canonical = self::canonical_string(
            $method,
            $content_md5,
            $content_type,
            $date,
            $bucket,
            $object,
            $oss_headers,
            $subresource
        );
        return 'OSS ' . $access_key_id . ':' . self::signature($secret, $canonical);
    }

    public static function signed_get_url(
        string $host,
        string $bucket,
        string $object,
        string $access_key,
        string $secret,
        int $ttl = 3600,
        ?int $now = null,
        string $process = ''
    ): string {
        $object = ltrim($object, '/');
        $expires = ($now ?? time()) + max(60, $ttl);
        $resource = '/' . $bucket . '/' . $object;
        if ($process !== '') {
            $resource .= '?x-oss-process=' . $process;
        }
        $canonical = "GET\n\n\n" . $expires . "\n" . $resource;
        $parts = [
            'OSSAccessKeyId=' . rawurlencode($access_key),
            'Expires=' . $expires,
        ];
        if ($process !== '') {
            $parts[] = 'x-oss-process=' . rawurlencode($process);
        }
        $parts[] = 'Signature=' . rawurlencode(self::signature($secret, $canonical));
        return self::object_url($host, $object) . '?' . implode('&', $parts);
    }

    public static function object_url(string $host, string $object): string
    {
        $parts = array_map('rawurlencode', explode('/', ltrim($object, '/')));
        return rtrim($host, '/') . '/' . implode('/', $parts);
    }

    public static function sign_region(string $region): string
    {
        $region = trim($region);
        if ($region === '' || strpos($region, 'oss-accelerate') === 0) {
            return 'cn-hangzhou';
        }
        if (strpos($region, 'oss-') === 0) {
            return substr($region, 4);
        }
        return $region;
    }

    public static function iso_date(?int $timestamp = null): string
    {
        return gmdate('Ymd\THis\Z', $timestamp ?? time());
    }

    public static function uri_encode(string $value, bool $encode_slash = true): string
    {
        $encoded = rawurlencode($value);
        if (!$encode_slash) {
            return str_replace('%2F', '/', $encoded);
        }
        return $encoded;
    }

    public static function v4_canonical_uri(string $bucket, string $object): string
    {
        $object = ltrim($object, '/');
        $path = $object === '' ? '/' . $bucket . '/' : '/' . $bucket . '/' . $object;
        return self::uri_encode($path, false);
    }

    /**
     * @param array<string, string> $params
     */
    public static function v4_canonical_query(array $params): string
    {
        $pairs = [];
        foreach ($params as $name => $value) {
            $key = self::uri_encode((string) $name);
            if ($value === '') {
                $pairs[$key] = $key;
            } else {
                $pairs[$key] = $key . '=' . self::uri_encode((string) $value);
            }
        }
        ksort($pairs);
        return implode('&', $pairs);
    }

    /**
     * @param array<string, string> $headers
     * @param string[] $additional
     */
    public static function v4_canonical_request(
        string $method,
        string $canonical_uri,
        string $canonical_query,
        array $headers,
        array $additional = [],
        string $payload_hash = self::UNSIGNED_PAYLOAD
    ): string {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = trim((string) $value);
        }
        ksort($normalized);
        $header_block = '';
        foreach ($normalized as $name => $value) {
            $header_block .= $name . ':' . $value . "\n";
        }
        $extra = array_values(array_unique(array_map('strtolower', $additional)));
        sort($extra);
        return strtoupper($method) . "\n"
            . $canonical_uri . "\n"
            . $canonical_query . "\n"
            . $header_block . "\n"
            . implode(';', $extra) . "\n"
            . $payload_hash;
    }

    public static function v4_string_to_sign(string $timestamp, string $scope, string $canonical): string
    {
        return "OSS4-HMAC-SHA256\n"
            . $timestamp . "\n"
            . $scope . "\n"
            . hash('sha256', $canonical);
    }

    public static function v4_signing_key(string $secret, string $date, string $region): string
    {
        $date_key = hash_hmac('sha256', $date, 'aliyun_v4' . $secret, true);
        $region_key = hash_hmac('sha256', $region, $date_key, true);
        $service_key = hash_hmac('sha256', 'oss', $region_key, true);
        return hash_hmac('sha256', 'aliyun_v4_request', $service_key, true);
    }

    public static function v4_scope(string $date, string $region): string
    {
        return $date . '/' . $region . '/oss/aliyun_v4_request';
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $query
     * @param string[] $additional
     * @return array<string, string>
     */
    public static function v4_sign_headers(
        string $access_key,
        string $secret,
        string $method,
        string $bucket,
        string $object,
        string $region,
        string $iso,
        array $headers,
        array $query = [],
        array $additional = ['host']
    ): array {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = trim((string) $value);
        }
        $normalized['x-oss-date'] = $normalized['x-oss-date'] ?? $iso;
        $normalized['x-oss-content-sha256'] = $normalized['x-oss-content-sha256'] ?? self::UNSIGNED_PAYLOAD;

        $additional = array_values(array_unique(array_map('strtolower', $additional)));
        sort($additional);

        $signed = [];
        foreach ($normalized as $name => $value) {
            if (self::v4_is_default_signed_header($name) || in_array($name, $additional, true)) {
                $signed[$name] = $value;
            }
        }
        ksort($signed);

        $date = substr($iso, 0, 8);
        $scope = self::v4_scope($date, $region);
        $canonical = self::v4_canonical_request(
            $method,
            self::v4_canonical_uri($bucket, $object),
            self::v4_canonical_query($query),
            $signed,
            $additional
        );
        $signature = hash_hmac(
            'sha256',
            self::v4_string_to_sign($iso, $scope, $canonical),
            self::v4_signing_key($secret, $date, $region)
        );
        $auth = 'OSS4-HMAC-SHA256 Credential=' . $access_key . '/' . $scope;
        if ($additional !== []) {
            $auth .= ',AdditionalHeaders=' . implode(';', $additional);
        }
        $auth .= ',Signature=' . $signature;

        $headers['x-oss-date'] = $normalized['x-oss-date'];
        $headers['x-oss-content-sha256'] = $normalized['x-oss-content-sha256'];
        $headers['Authorization'] = $auth;
        return $headers;
    }

    public static function v4_signed_get_url(
        string $host,
        string $bucket,
        string $object,
        string $access_key,
        string $secret,
        string $region,
        int $ttl = 3600,
        ?int $now = null,
        string $process = '',
        string $security_token = ''
    ): string {
        $now = $now ?? time();
        $iso = self::iso_date($now);
        $date = substr($iso, 0, 8);
        $ttl = min(604800, max(60, $ttl));
        $scope = self::v4_scope($date, $region);
        $host_header = (string) (parse_url($host, PHP_URL_HOST) ?: '');
        $query = [
            'x-oss-additional-headers' => 'host',
            'x-oss-credential' => $access_key . '/' . $scope,
            'x-oss-date' => $iso,
            'x-oss-expires' => (string) $ttl,
            'x-oss-signature-version' => 'OSS4-HMAC-SHA256',
        ];
        if ($process !== '') {
            $query['x-oss-process'] = $process;
        }
        if ($security_token !== '') {
            $query['x-oss-security-token'] = $security_token;
        }
        $canonical = self::v4_canonical_request(
            'GET',
            self::v4_canonical_uri($bucket, $object),
            self::v4_canonical_query($query),
            ['host' => $host_header],
            ['host']
        );
        $query['x-oss-signature'] = hash_hmac(
            'sha256',
            self::v4_string_to_sign($iso, $scope, $canonical),
            self::v4_signing_key($secret, $date, $region)
        );
        $parts = [];
        foreach ($query as $name => $value) {
            $parts[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
        }
        return self::object_url($host, $object) . '?' . implode('&', $parts);
    }

    private static function v4_is_default_signed_header(string $name): bool
    {
        return $name === 'content-type' || $name === 'content-md5' || strpos($name, 'x-oss-') === 0;
    }
}
