<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * 七牛上传凭证 + 管理端 QBox 签名。不粘官方 SDK。
 */
class Wpsss_Kodo_Signer
{
    /**
     * @param array<string, mixed> $policy
     */
    public static function upload_token(string $access_key, string $secret, array $policy): string
    {
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($policy, JSON_UNESCAPED_SLASHES)
            : json_encode($policy, JSON_UNESCAPED_SLASHES);
        $encoded = self::urlsafe_b64(is_string($json) && $json !== '' ? $json : '{}');
        $sign = self::urlsafe_b64(hash_hmac('sha1', $encoded, $secret, true));
        return $access_key . ':' . $sign . ':' . $encoded;
    }

    public static function qbox(string $access_key, string $secret, string $path, string $body = ''): string
    {
        $data = $path . "\n" . $body;
        $sign = self::urlsafe_b64(hash_hmac('sha1', $data, $secret, true));
        return 'QBox ' . $access_key . ':' . $sign;
    }

    public static function private_download_url(string $access_key, string $secret, string $url, int $ttl = 3600, ?int $now = null): string
    {
        $now = $now ?? time();
        $deadline = $now + max(60, $ttl);
        $sep = strpos($url, '?') === false ? '?' : '&';
        $to_sign = $url . $sep . 'e=' . $deadline;
        $sign = self::urlsafe_b64(hash_hmac('sha1', $to_sign, $secret, true));
        return $to_sign . '&token=' . $access_key . ':' . $sign;
    }

    public static function encoded_entry(string $bucket, string $key): string
    {
        return self::urlsafe_b64($bucket . ':' . $key);
    }

    public static function urlsafe_b64(string $raw): string
    {
        return strtr(base64_encode($raw), '+/', '-_');
    }
}
