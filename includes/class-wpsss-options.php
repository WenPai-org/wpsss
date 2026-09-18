<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Options
{
    public const OPTION_KEY = 'wpsss_options';
    public const OWN_URL_FLAG = 'wpsss_owns_upload_url_path';

    public const DRIVER_ALIYUN = 'aliyun-oss';
    public const DRIVER_COS = 'qcloud-cos';
    public const DRIVER_KODO = 'qiniu-kodo';
    public const DRIVER_OBS = 'huawei-obs';

    /**
     * @return array<string, string>
     */
    public static function driver_ids(): array
    {
        return [
            self::DRIVER_ALIYUN => '阿里云 OSS',
            self::DRIVER_COS => '腾讯云 COS',
            self::DRIVER_KODO => '七牛云 KODO',
            self::DRIVER_OBS => '华为云 OBS',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'driver' => self::DRIVER_ALIYUN,
            'bucket' => '',
            'region' => 'oss-cn-hangzhou',
            'access_key_id' => '',
            'access_key_secret' => '',
            'app_id' => '',
            'custom_domain' => '',
            'keep_local' => '1',
            'upload_thumbs' => '1',
            'use_internal' => '0',
            'object_prefix' => '',
            'auto_rename' => 'off',
            'image_style' => '',
            'origin_protect' => '0',
            'rewrite_from' => '',
            'ci_slim' => '0',
            'ci_preview' => '0',
            'ci_audit' => '0',
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
        $custom = $custom !== '' ? untrailingslashit(esc_url_raw($custom)) : '';

        $rewrite_from = isset($input['rewrite_from']) ? trim((string) $input['rewrite_from']) : '';
        $rewrite_from = $rewrite_from !== '' ? untrailingslashit(esc_url_raw($rewrite_from)) : '';

        $keep = (isset($input['keep_local']) && (string) $input['keep_local'] === '1') ? '1' : '0';
        $allow_off = isset($input['_allow_no_local']) && (string) $input['_allow_no_local'] === '1';
        if ($keep === '0' && $current['keep_local'] === '1' && !$allow_off) {
            $keep = '1';
        }

        $drivers = array_keys(self::driver_ids());
        $driver = isset($input['driver']) ? sanitize_key((string) $input['driver']) : $current['driver'];
        if (!in_array($driver, $drivers, true)) {
            $driver = self::DRIVER_ALIYUN;
        }

        $rename = isset($input['auto_rename']) ? sanitize_key((string) $input['auto_rename']) : 'off';
        if (!in_array($rename, ['off', 'md5', 'time'], true)) {
            $rename = 'off';
        }

        $style = isset($input['image_style']) ? trim((string) $input['image_style']) : '';
        $style = preg_replace('/[\r\n]/', '', $style) ?? '';

        $prefix = isset($input['object_prefix']) ? trim((string) $input['object_prefix'], " \t/") : '';
        $prefix = sanitize_text_field($prefix);

        $out = [
            'driver' => $driver,
            'bucket' => sanitize_text_field($input['bucket'] ?? ''),
            'region' => sanitize_text_field($input['region'] ?? self::default_region($driver)),
            'access_key_id' => sanitize_text_field($input['access_key_id'] ?? ''),
            'access_key_secret' => $secret,
            'app_id' => sanitize_text_field($input['app_id'] ?? ''),
            'custom_domain' => $custom,
            'keep_local' => $keep,
            'upload_thumbs' => (isset($input['upload_thumbs']) && (string) $input['upload_thumbs'] === '1') ? '1' : '0',
            'use_internal' => (isset($input['use_internal']) && (string) $input['use_internal'] === '1') ? '1' : '0',
            'object_prefix' => $prefix,
            'auto_rename' => $rename,
            'image_style' => $style,
            'origin_protect' => (isset($input['origin_protect']) && (string) $input['origin_protect'] === '1') ? '1' : '0',
            'rewrite_from' => $rewrite_from,
            'ci_slim' => (isset($input['ci_slim']) && (string) $input['ci_slim'] === '1') ? '1' : '0',
            'ci_preview' => (isset($input['ci_preview']) && (string) $input['ci_preview'] === '1') ? '1' : '0',
            'ci_audit' => (isset($input['ci_audit']) && (string) $input['ci_audit'] === '1') ? '1' : '0',
        ];

        self::sync_upload_url_path($out['custom_domain']);
        return $out;
    }

    public static function default_region(string $driver): string
    {
        $map = [
            self::DRIVER_ALIYUN => 'oss-cn-hangzhou',
            self::DRIVER_COS => 'ap-guangzhou',
            self::DRIVER_KODO => 'z0',
            self::DRIVER_OBS => 'cn-north-4',
        ];
        return $map[$driver] ?? 'oss-cn-hangzhou';
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
        return self::default_public_host($opts);
    }

    /**
     * @param array<string, string> $opts
     */
    public static function default_public_host(array $opts): string
    {
        $bucket = $opts['bucket'];
        $region = $opts['region'];
        switch ($opts['driver'] ?? self::DRIVER_ALIYUN) {
            case self::DRIVER_COS:
                return 'https://' . $bucket . '.cos.' . $region . '.myqcloud.com';
            case self::DRIVER_OBS:
                return 'https://' . $bucket . '.obs.' . $region . '.myhuaweicloud.com';
            case self::DRIVER_KODO:
                return '';
            default:
                return 'https://' . $bucket . '.' . $region . '.aliyuncs.com';
        }
    }

    /**
     * @param array<string, string> $opts
     */
    public static function api_host(array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : self::get();
        $bucket = $opts['bucket'];
        $region = $opts['region'];
        $internal = ($opts['use_internal'] ?? '0') === '1';
        switch ($opts['driver'] ?? self::DRIVER_ALIYUN) {
            case self::DRIVER_COS:
                $host = $internal ? 'cos-internal.' : 'cos.';
                return 'https://' . $bucket . '.' . $host . $region . '.myqcloud.com';
            case self::DRIVER_OBS:
                $suffix = $internal ? '.internal' : '';
                return 'https://' . $bucket . '.obs.' . $region . $suffix . '.myhuaweicloud.com';
            case self::DRIVER_KODO:
                return self::kodo_upload_host($region);
            default:
                $suffix = $internal ? '-internal.aliyuncs.com' : '.aliyuncs.com';
                return 'https://' . $bucket . '.' . $region . $suffix;
        }
    }

    public static function kodo_upload_host(string $region): string
    {
        $map = [
            'z0' => 'https://up.qiniup.com',
            'z1' => 'https://up-z1.qiniup.com',
            'z2' => 'https://up-z2.qiniup.com',
            'na0' => 'https://up-na0.qiniup.com',
            'as0' => 'https://up-as0.qiniup.com',
        ];
        return $map[$region] ?? 'https://up.qiniup.com';
    }

    public static function kodo_rs_host(string $region): string
    {
        $map = [
            'z0' => 'https://rs.qiniu.com',
            'z1' => 'https://rs-z1.qiniu.com',
            'z2' => 'https://rs-z2.qiniu.com',
            'na0' => 'https://rs-na0.qiniu.com',
            'as0' => 'https://rs-as0.qiniu.com',
        ];
        return $map[$region] ?? 'https://rs.qiniu.com';
    }

    /**
     * @return array<string, string>
     */
    public static function regions(?string $driver = null): array
    {
        $driver = $driver ?? self::get()['driver'];
        return self::regions_for($driver);
    }

    /**
     * @return array<string, string>
     */
    public static function regions_for(string $driver): array
    {
        switch ($driver) {
            case self::DRIVER_COS:
                return [
                    'ap-beijing' => '北京',
                    'ap-nanjing' => '南京',
                    'ap-shanghai' => '上海',
                    'ap-guangzhou' => '广州',
                    'ap-chengdu' => '成都',
                    'ap-chongqing' => '重庆',
                    'ap-hongkong' => '中国香港',
                    'ap-singapore' => '新加坡',
                    'ap-tokyo' => '东京',
                    'na-siliconvalley' => '硅谷',
                    'eu-frankfurt' => '法兰克福',
                ];
            case self::DRIVER_KODO:
                return [
                    'z0' => '华东',
                    'z1' => '华北',
                    'z2' => '华南',
                    'na0' => '北美',
                    'as0' => '东南亚',
                ];
            case self::DRIVER_OBS:
                return [
                    'cn-north-1' => '华北-北京一',
                    'cn-north-4' => '华北-北京四',
                    'cn-east-2' => '华东-上海二',
                    'cn-east-3' => '华东-上海一',
                    'cn-south-1' => '华南-广州',
                    'cn-southwest-2' => '西南-贵阳一',
                    'ap-southeast-1' => '中国-香港',
                ];
            default:
                return [
                    'oss-cn-hangzhou' => '华东1 杭州',
                    'oss-cn-shanghai' => '华东2 上海',
                    'oss-cn-qingdao' => '华北1 青岛',
                    'oss-cn-beijing' => '华北2 北京',
                    'oss-cn-zhangjiakou' => '华北3 张家口',
                    'oss-cn-huhehaote' => '华北5 呼和浩特',
                    'oss-cn-wulanchabu' => '华北6 乌兰察布',
                    'oss-cn-shenzhen' => '华南1 深圳',
                    'oss-cn-heyuan' => '华南2 河源',
                    'oss-cn-guangzhou' => '华南3 广州',
                    'oss-cn-chengdu' => '西南1 成都',
                    'oss-cn-hongkong' => '中国香港',
                    'oss-us-west-1' => '美国西部1 硅谷',
                    'oss-us-east-1' => '美国东部1 弗吉尼亚',
                    'oss-ap-southeast-1' => '新加坡',
                    'oss-ap-northeast-1' => '日本 东京',
                    'oss-eu-central-1' => '德国 法兰克福',
                    'oss-accelerate' => '全球加速',
                    'oss-accelerate-overseas' => '海外加速',
                ];
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function all_regions(): array
    {
        $out = [];
        foreach (array_keys(self::driver_ids()) as $id) {
            $out[$id] = self::regions_for($id);
        }
        return $out;
    }

    /**
     * @param array<string, string> $opts
     */
    public static function apply_object_prefix(string $key, array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : self::get();
        $prefix = trim((string) ($opts['object_prefix'] ?? ''), '/');
        $key = ltrim($key, '/');
        if ($prefix === '' || $key === '') {
            return $key;
        }
        if (strpos($key, $prefix . '/') === 0) {
            return $key;
        }
        return $prefix . '/' . $key;
    }
}
