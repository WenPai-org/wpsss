<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * 阿里云 ECS 实例 RAM 角色。只在 oss_auth=ram 时问元数据，不把 STS 写入 options。
 */
class Wpsss_Oss_Ram
{
    public const IMDS = 'http://100.100.100.200/latest/meta-data/ram/security-credentials/';

    /** @var callable|null */
    public static $transport = null;

    /** @var array{id:string,secret:string,token:string,until:int}|null */
    private static $memo = null;

    /**
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    public static function hydrate(array $opts): array
    {
        if (($opts['oss_auth'] ?? 'ak') !== 'ram') {
            return $opts;
        }
        $driver = (string) ($opts['driver'] ?? 'aliyun-oss');
        if ($driver !== '' && $driver !== 'aliyun-oss') {
            return $opts;
        }
        $cred = self::credentials((string) ($opts['oss_ram_role'] ?? ''));
        if ($cred === null) {
            return $opts;
        }
        $opts['access_key_id'] = $cred['id'];
        $opts['access_key_secret'] = $cred['secret'];
        $opts['security_token'] = $cred['token'];
        return $opts;
    }

    /**
     * @return array{id:string,secret:string,token:string}|null
     */
    public static function credentials(string $role = ''): ?array
    {
        $now = time();
        if (self::$memo !== null && self::$memo['until'] > $now) {
            return [
                'id' => self::$memo['id'],
                'secret' => self::$memo['secret'],
                'token' => self::$memo['token'],
            ];
        }
        if ($role === '') {
            $list = self::get(self::IMDS);
            if ($list === null || trim($list) === '') {
                return null;
            }
            $role = trim((string) preg_split('/\s+/', trim($list))[0]);
        }
        if ($role === '' || strpos($role, '/') !== false || strpos($role, '..') !== false) {
            return null;
        }
        $body = self::get(self::IMDS . rawurlencode($role));
        if ($body === null) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['Code'] ?? '') !== 'Success') {
            return null;
        }
        $id = (string) ($data['AccessKeyId'] ?? '');
        $secret = (string) ($data['AccessKeySecret'] ?? '');
        $token = (string) ($data['SecurityToken'] ?? '');
        if ($id === '' || $secret === '' || $token === '') {
            return null;
        }
        self::$memo = [
            'id' => $id,
            'secret' => $secret,
            'token' => $token,
            'until' => $now + 240,
        ];
        return ['id' => $id, 'secret' => $secret, 'token' => $token];
    }

    public static function reset(): void
    {
        self::$memo = null;
        self::$transport = null;
    }

    private static function get(string $url): ?string
    {
        if (is_callable(self::$transport)) {
            $out = (self::$transport)($url);
            return is_string($out) ? $out : null;
        }
        if (!function_exists('wp_remote_get')) {
            return null;
        }
        $response = wp_remote_get($url, [
            'timeout' => 1,
            'redirection' => 0,
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        return (string) wp_remote_retrieve_body($response);
    }
}
