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
    public const DRIVER_TOS = 'volc-tos';
    public const DRIVER_S3 = 's3-compat';
    public const DRIVER_GCS = 'gcs';

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
            self::DRIVER_TOS => '火山 TOS',
            self::DRIVER_S3 => 'Amazon S3 / 兼容',
            self::DRIVER_GCS => 'Google Cloud Storage',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function driver_groups(): array
    {
        $ids = self::driver_ids();
        return [
            'cn' => [
                self::DRIVER_ALIYUN => $ids[self::DRIVER_ALIYUN],
                self::DRIVER_COS => $ids[self::DRIVER_COS],
                self::DRIVER_KODO => $ids[self::DRIVER_KODO],
                self::DRIVER_OBS => $ids[self::DRIVER_OBS],
                self::DRIVER_TOS => $ids[self::DRIVER_TOS],
            ],
            'intl' => [
                self::DRIVER_S3 => $ids[self::DRIVER_S3],
                self::DRIVER_GCS => $ids[self::DRIVER_GCS],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function s3_presets(): array
    {
        return [
            'aws' => 'Amazon S3',
            'do' => 'DigitalOcean Spaces',
            'r2' => 'Cloudflare R2',
            'minio' => 'MinIO',
            'wasabi' => 'Wasabi',
            'tos' => '火山 TOS',
            'gcs' => 'Google Cloud Storage',
            'custom' => '自定义 endpoint',
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
            'endpoint' => '',
            's3_preset' => 'aws',
            'force_path_style' => '0',
            'site_mode' => 'domestic',
            'url_access' => 'public',
            'signed_ttl' => '3600',
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

        $endpoint = isset($input['endpoint']) ? trim((string) $input['endpoint']) : '';
        $endpoint = $endpoint !== '' ? untrailingslashit(esc_url_raw($endpoint)) : '';

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

        $presets = array_keys(self::s3_presets());
        $preset = isset($input['s3_preset']) ? sanitize_key((string) $input['s3_preset']) : ($current['s3_preset'] ?? 'aws');
        if (!in_array($preset, $presets, true)) {
            $preset = 'aws';
        }
        if ($driver === self::DRIVER_TOS) {
            $preset = 'tos';
        }
        if ($driver === self::DRIVER_GCS) {
            $preset = 'gcs';
        }

        $path_style = self::default_path_style($preset);
        if (array_key_exists('force_path_style', $input)) {
            $path_style = ((string) $input['force_path_style'] === '1') ? '1' : '0';
        } elseif (self::is_s3_family($driver)) {
            $path_style = self::default_path_style($preset);
        } else {
            $path_style = (string) ($current['force_path_style'] ?? $path_style);
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
            'endpoint' => $endpoint,
            's3_preset' => $preset,
            'force_path_style' => $path_style,
            'site_mode' => self::sanitize_site_mode($input['site_mode'] ?? $current['site_mode'] ?? 'domestic'),
            'url_access' => self::sanitize_url_access($input['url_access'] ?? $current['url_access'] ?? 'public'),
            'signed_ttl' => self::sanitize_signed_ttl($input['signed_ttl'] ?? $current['signed_ttl'] ?? '3600'),
        ];

        self::sync_upload_url_path($out['custom_domain']);
        return $out;
    }

    public static function default_path_style(string $preset): string
    {
        return in_array($preset, ['r2', 'minio', 'gcs'], true) ? '1' : '0';
    }

    public static function default_region(string $driver): string
    {
        $map = [
            self::DRIVER_ALIYUN => 'oss-cn-hangzhou',
            self::DRIVER_COS => 'ap-guangzhou',
            self::DRIVER_KODO => 'z0',
            self::DRIVER_OBS => 'cn-north-4',
            self::DRIVER_TOS => 'cn-beijing',
            self::DRIVER_GCS => 'auto',
            self::DRIVER_S3 => 'us-east-1',
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

    /**
     * @param array<string, string> $opts
     */
    public static function public_base(array $opts = []): string
    {
        $opts = $opts !== [] ? array_merge(self::defaults(), $opts) : self::get();
        if ($opts['custom_domain'] !== '') {
            return rtrim($opts['custom_domain'], '/');
        }
        $preset = self::effective_s3_preset($opts);
        if ($opts['bucket'] === '' || ($opts['region'] === '' && !in_array($preset, ['r2', 'gcs'], true))) {
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
            case self::DRIVER_TOS:
            case self::DRIVER_S3:
            case self::DRIVER_GCS:
                $req = self::s3_object_request($opts, '');
                return $req['public_base'];
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
            case self::DRIVER_TOS:
            case self::DRIVER_S3:
            case self::DRIVER_GCS:
                return self::s3_object_request($opts, '')['public_base'];
            default:
                $suffix = $internal ? '-internal.aliyuncs.com' : '.aliyuncs.com';
                return 'https://' . $bucket . '.' . $region . $suffix;
        }
    }

    /**
     * @param array<string, string> $opts
     */
    public static function effective_s3_preset(array $opts): string
    {
        if (($opts['driver'] ?? '') === self::DRIVER_TOS) {
            return 'tos';
        }
        if (($opts['driver'] ?? '') === self::DRIVER_GCS) {
            return 'gcs';
        }
        $preset = sanitize_key((string) ($opts['s3_preset'] ?? 'aws'));
        return $preset !== '' ? $preset : 'aws';
    }

    /**
     * @param array<string, string> $opts
     */
    public static function s3_endpoint(array $opts, bool $public = false): string
    {
        $endpoint = trim((string) ($opts['endpoint'] ?? ''));
        if ($endpoint !== '') {
            return untrailingslashit($endpoint);
        }
        $preset = self::effective_s3_preset($opts);
        $region = trim((string) ($opts['region'] ?? ''));
        switch ($preset) {
            case 'do':
                return $region !== '' ? 'https://' . $region . '.digitaloceanspaces.com' : '';
            case 'r2':
                $account = trim((string) ($opts['app_id'] ?? ''));
                return $account !== '' ? 'https://' . $account . '.r2.cloudflarestorage.com' : '';
            case 'gcs':
                return 'https://storage.googleapis.com';
            case 'tos':
                if ($region === '') {
                    return '';
                }
                $internal = !$public && (($opts['use_internal'] ?? '0') === '1');
                $suffix = $internal ? '.ivolces.com' : '.volces.com';
                return 'https://tos-s3-' . $region . $suffix;
            default:
                return '';
        }
    }

    /**
     * @param array<string, string> $opts
     */
    public static function s3_force_path_style(array $opts): bool
    {
        return ((string) ($opts['force_path_style'] ?? self::default_path_style(self::effective_s3_preset($opts)))) === '1';
    }

    /**
     * @param array<string, string> $opts
     */
    public static function s3_signing_region(array $opts): string
    {
        $preset = self::effective_s3_preset($opts);
        $region = trim((string) ($opts['region'] ?? ''));
        if ($preset === 'r2' || $preset === 'gcs') {
            return $region !== '' ? $region : 'auto';
        }
        return $region !== '' ? $region : 'us-east-1';
    }

    /**
     * @param array<string, string> $opts
     */
    public static function s3_request_host(array $opts): string
    {
        return self::s3_object_request($opts, '')['host'];
    }

    /**
     * @param array<string, string> $opts
     * @return array{url: string, host: string, canonical_uri: string, public_base: string, public_url: string, public_host: string}
     */
    public static function s3_object_request(array $opts, string $object): array
    {
        $api = self::s3_build_request($opts, $object, false);
        $pub = self::s3_build_request($opts, $object, true);
        $api['public_base'] = $pub['public_base'];
        $api['public_url'] = $pub['url'];
        $api['public_host'] = $pub['host'];
        return $api;
    }

    /**
     * @param array<string, string> $opts
     * @return array{url: string, host: string, canonical_uri: string, public_base: string}
     */
    private static function s3_build_request(array $opts, string $object, bool $public): array
    {
        $bucket = trim((string) ($opts['bucket'] ?? ''));
        $object = ltrim($object, '/');
        $encoded = $object !== '' ? Wpsss_S3_Signer::encode_key($object) : '';
        $preset = self::effective_s3_preset($opts);
        $path_style = self::s3_force_path_style($opts);
        $endpoint = self::s3_endpoint($opts, $public);
        $region = self::s3_signing_region($opts);

        $base_host = '';
        if ($endpoint !== '') {
            $base_host = (string) (wp_parse_url($endpoint, PHP_URL_HOST) ?: '');
        } elseif ($preset === 'aws' && $region !== '') {
            $base_host = 's3.' . $region . '.amazonaws.com';
        }

        if ($base_host === '' || $bucket === '') {
            return [
                'url' => '',
                'host' => '',
                'canonical_uri' => $object !== '' ? '/' . $encoded : '/',
                'public_base' => '',
            ];
        }

        if ($path_style) {
            $host = $base_host;
            $path = '/' . rawurlencode($bucket);
            if ($encoded !== '') {
                $path .= '/' . $encoded;
            }
            $canonical = '/' . $bucket;
            if ($object !== '') {
                $canonical .= '/' . $encoded;
            }
            return [
                'url' => 'https://' . $host . $path,
                'host' => $host,
                'canonical_uri' => $canonical,
                'public_base' => 'https://' . $host . '/' . $bucket,
            ];
        }

        $host = $bucket . '.' . $base_host;
        $path = $encoded !== '' ? '/' . $encoded : '';
        return [
            'url' => 'https://' . $host . $path,
            'host' => $host,
            'canonical_uri' => $encoded !== '' ? '/' . $encoded : '/',
            'public_base' => 'https://' . $host,
        ];
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
            case self::DRIVER_TOS:
                return [
                    'cn-beijing' => '华北2 北京',
                    'cn-shanghai' => '华东2 上海',
                    'cn-guangzhou' => '华南1 广州',
                    'cn-hongkong' => '中国香港',
                    'ap-southeast-1' => '亚太东南 柔佛',
                    'ap-southeast-3' => '亚太东南 雅加达',
                ];
            case self::DRIVER_GCS:
                return [
                    'auto' => '自动（HMAC）',
                    'us-central1' => '美中',
                    'europe-west1' => '欧洲西部',
                    'asia-east1' => '亚太 台湾',
                    'asia-northeast1' => '亚太 东京',
                ];
            case self::DRIVER_S3:
                return [
                    'us-east-1' => '美东 弗吉尼亚',
                    'us-west-2' => '美西 俄勒冈',
                    'eu-west-1' => '欧洲 爱尔兰',
                    'ap-southeast-1' => '亚太 新加坡',
                    'ap-northeast-1' => '亚太 东京',
                    'auto' => 'R2 auto',
                    'nyc3' => 'Spaces nyc3',
                    'cn-beijing' => 'TOS 北京',
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

    public static function is_s3_family(string $driver): bool
    {
        return $driver === self::DRIVER_S3 || $driver === self::DRIVER_TOS || $driver === self::DRIVER_GCS;
    }

    /**
     * @return array<string, array{label: string, help: string}>
     */
    public static function site_modes(): array
    {
        return [
            'domestic' => [
                'label' => '国内站',
                'help' => '服务器、访客、管理员都在中国大陆。推荐国内云。厂商若有海外区域，自己选即可。',
            ],
            'crossborder' => [
                'label' => '跨境 · 外贸站',
                'help' => '服务器与访客在海外，管理员在中国大陆。推荐 S3 兼容。国内云仍可选。',
            ],
            'mixed' => [
                'label' => '混合站',
                'help' => '服务器在海外，访客中外都有。两组厂商都列出，自己挑。',
            ],
        ];
    }

    public static function sanitize_site_mode($value): string
    {
        $mode = sanitize_key((string) $value);
        return isset(self::site_modes()[$mode]) ? $mode : 'domestic';
    }

    public static function sanitize_url_access($value): string
    {
        return (string) $value === 'signed' ? 'signed' : 'public';
    }

    public static function sanitize_signed_ttl($value): string
    {
        $ttl = (int) $value;
        if ($ttl < 60) {
            $ttl = 3600;
        }
        if ($ttl > 604800) {
            $ttl = 604800;
        }
        return (string) $ttl;
    }

    public static function supports_signed_get(string $driver): bool
    {
        return $driver === self::DRIVER_ALIYUN || self::is_s3_family($driver);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function driver_groups_for_mode(string $mode, string $current = ''): array
    {
        $all = self::driver_groups();
        if ($mode === 'domestic') {
            $groups = ['cn' => $all['cn']];
        } elseif ($mode === 'crossborder') {
            $groups = [
                'intl' => $all['intl'],
                'cn' => $all['cn'],
            ];
        } else {
            $groups = $all;
        }
        if (self::is_s3_family($current) && $current !== self::DRIVER_TOS && !isset($groups['intl'])) {
            $groups['intl'] = $all['intl'];
        }
        return $groups;
    }

    public static function region_is_overseas(string $driver, string $region): bool
    {
        $r = strtolower($region);
        switch ($driver) {
            case self::DRIVER_ALIYUN:
                return (bool) preg_match('/^oss-(us|ap|eu|me)-|^oss-accelerate-overseas$|^oss-cn-hongkong$/', $r);
            case self::DRIVER_COS:
                return (bool) preg_match('/^(ap-hongkong|ap-singapore|ap-tokyo|na-|eu-)/', $r);
            case self::DRIVER_KODO:
                return in_array($r, ['na0', 'as0'], true);
            case self::DRIVER_OBS:
                return strpos($r, 'ap-') === 0;
            case self::DRIVER_TOS:
                return in_array($r, ['cn-hongkong', 'ap-southeast-1', 'ap-southeast-3'], true);
            case self::DRIVER_GCS:
                return true;
            case self::DRIVER_S3:
                return !in_array($r, ['cn-beijing'], true);
            default:
                return false;
        }
    }

    /**
     * @return array{cn: array<string, string>, overseas: array<string, string>}
     */
    public static function grouped_regions(string $driver): array
    {
        $cn = [];
        $overseas = [];
        foreach (self::regions_for($driver) as $id => $label) {
            if (self::region_is_overseas($driver, $id)) {
                $overseas[$id] = $label;
            } else {
                $cn[$id] = $label;
            }
        }
        return ['cn' => $cn, 'overseas' => $overseas];
    }
}
