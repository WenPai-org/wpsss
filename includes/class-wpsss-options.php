<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Options
{
    public const OPTION_KEY = 'wpsss_options';
    public const OWN_URL_FLAG = 'wpsss_owns_upload_url_path';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'driver' => 'aliyun-oss',
            'bucket' => '',
            'region' => 'oss-cn-hangzhou',
            'access_key_id' => '',
            'access_key_secret' => '',
            'custom_domain' => '',
            'keep_local' => '1',
            'upload_thumbs' => '1',
            'use_internal' => '0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function get(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge(self::defaults(), $stored);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function sanitize($input): array
    {
        $current = self::get();
        $input = is_array($input) ? $input : [];

        $secret = isset($input['access_key_secret']) ? (string) $input['access_key_secret'] : '';
        if ($secret === '') {
            $secret = $current['access_key_secret'];
        }

        $custom = isset($input['custom_domain']) ? trim((string) $input['custom_domain']) : '';
        $custom = untrailingslashit(esc_url_raw($custom));

        $keep = (isset($input['keep_local']) && (string) $input['keep_local'] === '1') ? '1' : '0';
        $allow_off = isset($input['_allow_no_local']) && (string) $input['_allow_no_local'] === '1';
        if ($keep === '0' && $current['keep_local'] === '1' && !$allow_off) {
            $keep = '1';
        }

        $out = [
            'driver' => 'aliyun-oss',
            'bucket' => sanitize_text_field($input['bucket'] ?? ''),
            'region' => sanitize_text_field($input['region'] ?? 'oss-cn-hangzhou'),
            'access_key_id' => sanitize_text_field($input['access_key_id'] ?? ''),
            'access_key_secret' => $secret,
            'custom_domain' => $custom,
            'keep_local' => $keep,
            'upload_thumbs' => (isset($input['upload_thumbs']) && (string) $input['upload_thumbs'] === '1') ? '1' : '0',
            'use_internal' => (isset($input['use_internal']) && (string) $input['use_internal'] === '1') ? '1' : '0',
        ];

        self::sync_upload_url_path($out['custom_domain']);
        return $out;
    }

    public static function sync_upload_url_path(string $custom_domain): void
    {
        $owns = (string) get_option(self::OWN_URL_FLAG, '');
        $current_url = (string) get_option('upload_url_path', '');

        if ($custom_domain !== '') {
            if ($current_url === '' || $owns === '1' || $current_url === $custom_domain) {
                update_option('upload_url_path', $custom_domain);
                update_option(self::OWN_URL_FLAG, '1');
            }
            return;
        }

        if ($owns === '1') {
            delete_option('upload_url_path');
            delete_option(self::OWN_URL_FLAG);
        }
    }

    public static function public_base(): string
    {
        $opts = self::get();
        if ($opts['custom_domain'] !== '') {
            return rtrim($opts['custom_domain'], '/');
        }
        if ($opts['bucket'] === '' || $opts['region'] === '') {
            return '';
        }
        return 'https://' . $opts['bucket'] . '.' . $opts['region'] . '.aliyuncs.com';
    }

    public static function api_host(array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : self::get();
        $region = $opts['region'];
        $suffix = $opts['use_internal'] === '1' ? '-internal.aliyuncs.com' : '.aliyuncs.com';
        return 'https://' . $opts['bucket'] . '.' . $region . $suffix;
    }

    /**
     * @return array<string, string>
     */
    public static function regions(): array
    {
        return [
            'oss-cn-hangzhou' => '华东1 杭州',
            'oss-cn-shanghai' => '华东2 上海',
            'oss-cn-beijing' => '华北2 北京',
            'oss-cn-qingdao' => '华北1 青岛',
            'oss-cn-zhangjiakou' => '华北3 张家口',
            'oss-cn-shenzhen' => '华南1 深圳',
            'oss-cn-chengdu' => '西南1 成都',
            'oss-cn-hongkong' => '中国香港',
            'oss-accelerate' => '全球加速',
        ];
    }
}
