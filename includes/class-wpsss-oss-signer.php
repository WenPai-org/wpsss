<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * Aliyun OSS Signature Version 1 (HMAC-SHA1).
 */
class Wpsss_Oss_Signer
{
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
        array $oss_headers = []
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
        array $oss_headers = []
    ): string {
        $canonical = self::canonical_string(
            $method,
            $content_md5,
            $content_type,
            $date,
            $bucket,
            $object,
            $oss_headers
        );
        return 'OSS ' . $access_key_id . ':' . self::signature($secret, $canonical);
    }

    public static function object_url(string $host, string $object): string
    {
        $parts = array_map('rawurlencode', explode('/', ltrim($object, '/')));
        return rtrim($host, '/') . '/' . implode('/', $parts);
    }
}
