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
        $header_list = [];
        $normalized = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            $normalized[$lower] = trim($value);
            $header_list[] = $lower;
        }
        ksort($normalized);
        sort($header_list);
        $header_block = '';
        foreach ($normalized as $name => $value) {
            $header_block .= $name . ':' . $value . "\n";
        }
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
            $normalized[strtolower($name)] = trim($value);
        }
        ksort($normalized);
        $header_block = '';
        foreach ($normalized as $name => $value) {
            $header_block .= $name . ':' . $value . "\n";
        }
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
}
