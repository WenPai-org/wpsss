<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * 腾讯云 COS 签名 v5（q-sign-algorithm=sha1）。不粘官方 SDK。
 */
class Wpsss_Cos_Signer
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $params
     */
    public static function authorization(
        string $secret_id,
        string $secret_key,
        string $method,
        string $path,
        array $headers,
        array $params,
        int $start,
        int $end
    ): string {
        $key_time = $start . ';' . $end;
        $sign_key = hash_hmac('sha1', $key_time, $secret_key);
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = rawurlencode(trim((string) $value));
        }
        ksort($normalized);
        $header_list = array_keys($normalized);
        $parts = [];
        foreach ($normalized as $name => $value) {
            $parts[] = $name . '=' . $value;
        }
        $header_block = implode('&', $parts);
        $param_list = [];
        $param_pairs = [];
        foreach ($params as $name => $value) {
            $lower = strtolower($name);
            $param_list[] = $lower;
            $param_pairs[$lower] = rawurlencode($value);
        }
        ksort($param_pairs);
        sort($param_list);
        $query = [];
        foreach ($param_pairs as $name => $value) {
            $query[] = rawurlencode($name) . '=' . $value;
        }
        $uri = '/' . ltrim($path, '/');
        $http = strtolower($method) . "\n"
            . $uri . "\n"
            . implode('&', $query) . "\n"
            . $header_block . "\n";
        $string_to_sign = "sha1\n" . $key_time . "\n" . sha1($http) . "\n";
        $signature = hash_hmac('sha1', $string_to_sign, $sign_key);
        return 'q-sign-algorithm=sha1'
            . '&q-ak=' . $secret_id
            . '&q-sign-time=' . $key_time
            . '&q-key-time=' . $key_time
            . '&q-header-list=' . implode(';', $header_list)
            . '&q-url-param-list=' . implode(';', $param_list)
            . '&q-signature=' . $signature;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $params
     */
    public static function http_string(
        string $method,
        string $path,
        array $headers,
        array $params
    ): string {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = rawurlencode(trim((string) $value));
        }
        ksort($normalized);
        $parts = [];
        foreach ($normalized as $name => $value) {
            $parts[] = $name . '=' . $value;
        }
        $header_block = implode('&', $parts);
        $param_pairs = [];
        foreach ($params as $name => $value) {
            $param_pairs[strtolower($name)] = rawurlencode($value);
        }
        ksort($param_pairs);
        $query = [];
        foreach ($param_pairs as $name => $value) {
            $query[] = rawurlencode($name) . '=' . $value;
        }
        return strtolower($method) . "\n"
            . '/' . ltrim($path, '/') . "\n"
            . implode('&', $query) . "\n"
            . $header_block . "\n";
    }

    public static function signed_get_url(
        string $host,
        string $bucket,
        string $object,
        string $ak,
        string $sk,
        int $ttl = 3600,
        ?int $now = null
    ): string {
        $start = $now ?? time();
        $end = $start + max(60, $ttl);
        $path = '/' . ltrim($object, '/');
        $host_header = $host;
        if (stripos($host, 'http://') === 0 || stripos($host, 'https://') === 0) {
            $host_header = (string) (parse_url($host, PHP_URL_HOST) ?: '');
        } else {
            $host_header = rtrim($host, '/');
        }
        $auth = self::authorization($ak, $sk, 'GET', $path, ['Host' => $host_header], [], $start, $end);
        $parts = array_map('rawurlencode', explode('/', ltrim($object, '/')));
        return rtrim($host, '/') . '/' . implode('/', $parts) . '?' . $auth;
    }
}
