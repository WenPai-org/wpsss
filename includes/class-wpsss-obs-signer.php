<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * 华为云 OBS 签名（OBS AccessKeyId:HMAC-SHA1）。资源串与 OSS V1 同形。
 */
class Wpsss_Obs_Signer
{
    /**
     * @param array<string, string> $obs_headers
     */
    public static function canonical_string(
        string $method,
        string $content_md5,
        string $content_type,
        string $date,
        string $bucket,
        string $object,
        array $obs_headers = [],
        string $subresource = ''
    ): string {
        $header_block = '';
        if ($obs_headers !== []) {
            $normalized = [];
            foreach ($obs_headers as $name => $value) {
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

    /**
     * @param array<string, string> $obs_headers
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
        array $obs_headers = [],
        string $subresource = ''
    ): string {
        $canonical = self::canonical_string(
            $method,
            $content_md5,
            $content_type,
            $date,
            $bucket,
            $object,
            $obs_headers,
            $subresource
        );
        $sig = base64_encode(hash_hmac('sha1', $canonical, $secret, true));
        return 'OBS ' . $access_key_id . ':' . $sig;
    }
}
