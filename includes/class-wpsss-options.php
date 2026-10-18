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
            'origin_region' => '',
            'access_key_id' => '',
            'access_key_secret' => '',
            'app_id' => '',
            'custom_domain' => '',
            'keep_local' => '1',
            'upload_thumbs' => '1',
            'woo_policy' => 'exclude',
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
            'oss_signature' => 'v4',
            'oss_auth' => 'ak',
            'oss_ram_role' => '',
            'setup_completed_at' => '',
            'exclude_ext' => '',
            'skip_exist' => '0',
            'cache_control' => '',
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

        $access_key_id = isset($input['access_key_id']) ? sanitize_text_field((string) $input['access_key_id']) : '';
        if ($access_key_id === '') {
            $access_key_id = (string) $current['access_key_id'];
        }

        $custom = isset($input['custom_domain']) ? trim((string) $input['custom_domain']) : '';
        $custom = $custom !== '' ? untrailingslashit(esc_url_raw($custom)) : '';

        $rewrite_from = isset($input['rewrite_from']) ? trim((string) $input['rewrite_from']) : '';
        $rewrite_from = $rewrite_from !== '' ? untrailingslashit(esc_url_raw($rewrite_from)) : '';

        $endpoint = isset($input['endpoint']) ? trim((string) $input['endpoint']) : '';
        $endpoint = $endpoint !== '' ? untrailingslashit(esc_url_raw($endpoint)) : '';
        if ($endpoint !== '' && stripos($endpoint, 'http://') === 0) {
            $endpoint = 'https://' . substr($endpoint, 7);
        }

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

        $region = sanitize_text_field($input['region'] ?? self::default_region($driver));
        $origin_region = sanitize_text_field($input['origin_region'] ?? $current['origin_region'] ?? '');
        if (in_array($region, ['oss-accelerate', 'oss-accelerate-overseas'], true) && $origin_region === '') {
            $origin_region = 'oss-cn-hangzhou';
        }

        $out = [
            'driver' => $driver,
            'bucket' => sanitize_text_field($input['bucket'] ?? ''),
            'region' => $region,
            'origin_region' => $origin_region,
            'access_key_id' => $access_key_id,
            'access_key_secret' => $secret,
            'app_id' => sanitize_text_field($input['app_id'] ?? ''),
            'custom_domain' => $custom,
            'keep_local' => $keep,
            'upload_thumbs' => (isset($input['upload_thumbs']) && (string) $input['upload_thumbs'] === '1') ? '1' : '0',
            'woo_policy' => in_array((string) ($input['woo_policy'] ?? ''), ['exclude', 'private', 'off'], true) ? (string) $input['woo_policy'] : 'exclude',
            'use_internal' => (isset($input['use_internal']) && (string) $input['use_internal'] === '1') ? '1' : '0',
            'object_prefix' => $prefix,
            'auto_rename' => $rename,
            'image_style' => $style,
            'origin_protect' => ($style !== '' && isset($input['origin_protect']) && (string) $input['origin_protect'] === '1') ? '1' : '0',
            'rewrite_from' => $rewrite_from,
            'ci_slim' => (isset($input['ci_slim']) && (string) $input['ci_slim'] === '1') ? '1' : '0',
            'ci_preview' => (isset($input['ci_preview']) && (string) $input['ci_preview'] === '1') ? '1' : '0',
            'ci_audit' => (isset($input['ci_audit']) && (string) $input['ci_audit'] === '1') ? '1' : '0',
            'endpoint' => $endpoint,
            's3_preset' => $preset,
            'force_path_style' => $path_style,
            'site_mode' => self::sanitize_site_mode($input['site_mode'] ?? $current['site_mode'] ?? 'domestic'),
            'url_access' => self::sanitize_url_access($input['url_access'] ?? $current['url_access'] ?? 'public', $input['driver'] ?? $current['driver'] ?? ''),
            'signed_ttl' => self::sanitize_signed_ttl($input['signed_ttl'] ?? $current['signed_ttl'] ?? '3600'),
            'oss_signature' => self::sanitize_oss_signature($input['oss_signature'] ?? $current['oss_signature'] ?? 'v4'),
            'oss_auth' => self::sanitize_oss_auth($input['oss_auth'] ?? $current['oss_auth'] ?? 'ak'),
            'oss_ram_role' => sanitize_text_field($input['oss_ram_role'] ?? $current['oss_ram_role'] ?? ''),
            'setup_completed_at' => sanitize_text_field($input['setup_completed_at'] ?? $current['setup_completed_at'] ?? ''),
            'exclude_ext' => self::sanitize_exclude_ext($input['exclude_ext'] ?? $current['exclude_ext'] ?? ''),
            'skip_exist' => array_key_exists('skip_exist', $input)
                ? (((string) $input['skip_exist'] === '1') ? '1' : '0')
                : (((string) ($current['skip_exist'] ?? '0') === '1') ? '1' : '0'),
            'cache_control' => self::sanitize_cache_control($input['cache_control'] ?? $current['cache_control'] ?? ''),
        ];

        self::sync_upload_url_path($out['custom_domain']);
        return $out;
    }

    /**
     * @return array<int, array{t: string, d: string}>
     */
    public static function setup_done_rows(string $site_mode, array $opts): array
    {
        $rows = [
            [
                't' => '本地备份',
                'd' => '上传后仍在服务器保留副本，便于回滚和拉回。',
            ],
            [
                't' => '缩略图同步',
                'd' => '各尺寸的附件会一并上传到存储桶。',
            ],
        ];

        if ($site_mode === 'domestic') {
            $rows[] = [
                't' => '推荐国内云厂商',
                'd' => '设置向导会优先展示阿里云 OSS、腾讯云 COS、七牛云、华为云 OBS 和火山引擎 TOS。',
            ];
        } elseif ($site_mode === 'crossborder') {
            $rows[] = [
                't' => '推荐国际与兼容云',
                'd' => 'S3 兼容存储和 Google Cloud Storage 排在前面，国内云厂商仍可选择。',
            ];
        } else {
            $rows[] = [
                't' => '国内外厂商均可选',
                'd' => '请按存储桶实际所在区域选择云厂商。',
            ];
        }

        $access = ($opts['url_access'] ?? 'public') === 'signed' ? 'signed' : 'public';
        if ($access === 'signed') {
            $rows[] = [
                't' => '签名访问',
                'd' => '私有存储桶通过带有效期的地址对外提供媒体。',
            ];
        } else {
            $rows[] = [
                't' => '公开读',
                'd' => '附件地址可以直接访问。存储桶策略须允许匿名读取。',
            ];
        }

        return $rows;
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
        if ($current_url === '.' && $owns === '1') {
            $current_url = '';
        }

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
                $api_region = (string) $region;
                if (strpos($api_region, 'oss-accelerate') === 0) {
                    $api_region = (string) ($opts['origin_region'] ?? '');
                    if ($api_region === '') {
                        $api_region = 'oss-cn-hangzhou';
                    }
                }
                $suffix = $internal ? '-internal.aliyuncs.com' : '.aliyuncs.com';
                return 'https://' . $bucket . '.' . $api_region . $suffix;
        }
    }

    /**
     * @param array<string, mixed> $opts
     * @return string[]
     */
    public static function runtime_warnings(array $opts): array
    {
        $warnings = [];
        $driver = (string) ($opts['driver'] ?? '');
        $custom = (string) ($opts['custom_domain'] ?? '');
        $url_access = (string) ($opts['url_access'] ?? 'public');
        $bucket = (string) ($opts['bucket'] ?? '');
        $app_id = (string) ($opts['app_id'] ?? '');

        if ($driver === self::DRIVER_KODO && $custom === '') {
            $warnings[] = '七牛的默认域名不能给访客使用。请填写 HTTPS 自定义域名，否则前台没有可访问的地址。';
        }
        if ($driver === self::DRIVER_ALIYUN && $custom === '' && $url_access !== 'signed') {
            $warnings[] = '自 2019 年起，阿里云存储桶的默认域名常把图片当作文件下载。请填写 HTTPS 自定义域名，或改用签名访问。';
        }
        if ($driver === self::DRIVER_COS && $app_id === '' && !preg_match('/-\d+/', $bucket)) {
            $warnings[] = '腾讯云 COS 的 APPID 属于创建存储桶的主账号，不是子账号。请填写主账号 APPID，或使用已带 APPID 后缀的桶名。';
        }
        if ((string) get_option('upload_url_path') === '.') {
            $warnings[] = '媒体地址前缀不能是「.」。为「.」时，删除存储桶中的对象会失败。';
        }
        if ($driver === self::DRIVER_ALIYUN && ($opts['oss_auth'] ?? 'ak') === 'ram' && (string) ($opts['oss_ram_role'] ?? '') === '') {
            $warnings[] = '未填写 RAM 角色名时，插件会向服务器获取临时密钥。获取失败时，附件无法进入存储桶。';
        }

        return $warnings;
    }

    public static function sanitize_oss_signature($value): string
    {
        $value = sanitize_key((string) $value);
        return $value === 'v1' ? 'v1' : 'v4';
    }

    public static function sanitize_oss_auth($value): string
    {
        $value = sanitize_key((string) $value);
        return $value === 'ram' ? 'ram' : 'ak';
    }

    public static function oss_sign_version(array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : self::get();
        return self::sanitize_oss_signature($opts['oss_signature'] ?? 'v4');
    }

    public static function oss_sign_region(array $opts = []): string
    {
        $opts = $opts !== [] ? $opts : self::get();
        $region = (string) ($opts['region'] ?? '');
        if (strpos($region, 'oss-accelerate') === 0) {
            $region = (string) ($opts['origin_region'] ?? 'oss-cn-hangzhou');
        }
        return Wpsss_Oss_Signer::sign_region($region);
    }

    public static function uninstall_cleanup(): void
    {
        $clean = static function (): void {
            if ((string) get_option(self::OWN_URL_FLAG, '') === '1') {
                delete_option('upload_url_path');
                delete_option(self::OWN_URL_FLAG);
            }
            delete_option(self::OPTION_KEY);
            delete_option('wpsss_history');
            if (function_exists('delete_metadata')) {
                delete_metadata('post', 0, '_wpsss_offloaded', '', true);
                delete_metadata('post', 0, '_wpsss_key', '', true);
                delete_metadata('post', 0, '_wpsss_error', '', true);
                delete_metadata('post', 0, '_wpsss_filesize', '', true);
                delete_metadata('post', 0, '_wpsss_main_only', '', true);
                delete_metadata('post', 0, '_wpsss_fail_count', '', true);
                delete_metadata('post', 0, '_wpsss_thumbs_pending', '', true);
            }
        };
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_sites')) {
            foreach (get_sites(['number' => 0]) as $site) {
                $id = (int) (is_object($site) ? ($site->blog_id ?? 0) : 0);
                if ($id <= 0) {
                    continue;
                }
                Wpsss_Site::with_blog($id, $clean);
            }
            return;
        }
        $clean();
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
                'help' => '站点与访客主要位于中国大陆。',
            ],
            'crossborder' => [
                'label' => '跨境 · 外贸站',
                'help' => '站点在海外，管理方在国内。',
            ],
            'mixed' => [
                'label' => '混合站',
                'help' => '访客来自境内和境外。',
            ],
        ];
    }

    public static function sanitize_site_mode($value): string
    {
        $mode = sanitize_key((string) $value);
        return isset(self::site_modes()[$mode]) ? $mode : 'domestic';
    }

    public static function sanitize_url_access($value, ?string $driver = null): string
    {
        if ((string) $value !== 'signed') {
            return 'public';
        }
        if ($driver !== null && !self::supports_signed_get($driver)) {
            return 'public';
        }
        return 'signed';
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

    public static function sanitize_exclude_ext($value): string
    {
        $parts = preg_split('/[\s,;]+/', strtolower((string) $value)) ?: [];
        $out = [];
        foreach ($parts as $ext) {
            $ext = ltrim(trim($ext), '.');
            if ($ext !== '' && preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
                $out[] = $ext;
            }
        }
        return implode(',', array_values(array_unique($out)));
    }

    public static function sanitize_cache_control($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^[A-Za-z0-9,=\s\-]+$/', $value)) {
            return '';
        }
        return substr($value, 0, 120);
    }

    /**
     * @param array<string, string> $opts
     */
    public static function object_cache_control(array $opts): string
    {
        $raw = trim((string) ($opts['cache_control'] ?? ''));
        if ($raw !== '') {
            return $raw;
        }
        return (($opts['url_access'] ?? 'public') === 'signed')
            ? 'private, max-age=3600'
            : 'public, max-age=2592000';
    }

    /**
     * @param array<string, string> $opts
     * @return array<string, mixed>
     */
    public static function export_public(array $opts = []): array
    {
        $opts = $opts === [] ? self::get() : array_merge(self::defaults(), $opts);
        unset($opts['access_key_id'], $opts['access_key_secret'], $opts['oss_ram_role']);
        return [
            'plugin' => 'wpsss',
            'version' => defined('WPSSS_VERSION') ? WPSSS_VERSION : '',
            'exported_at' => gmdate('c'),
            'options' => $opts,
        ];
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

    /**
     * @return array<string, string>
     */
    public static function driver_help(): array
    {
        return [
            self::DRIVER_ALIYUN => '适合国内站点。',
            self::DRIVER_COS => '适合已有腾讯云 COS 存储桶。',
            self::DRIVER_KODO => '对外访问需要填写 HTTPS 自定义域名。',
            self::DRIVER_OBS => '适合已有华为云 OBS 存储桶。',
            self::DRIVER_TOS => '适合已有火山引擎 TOS 存储桶。',
            self::DRIVER_S3 => '兼容 AWS、Cloudflare R2 和 MinIO。',
            self::DRIVER_GCS => '适合 Google Cloud 存储桶。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bucket_help(): array
    {
        return [
            self::DRIVER_ALIYUN => '只填写存储桶名称，不要带域名。',
            self::DRIVER_COS => '桶名须带 APPID 后缀，例如 demo-1250000000。',
            self::DRIVER_KODO => '只填写空间名称，不要带域名。',
            self::DRIVER_OBS => '只填写存储桶名称，不要带域名。',
            self::DRIVER_TOS => '只填写存储桶名称，不要带域名。',
            self::DRIVER_S3 => '只填写存储桶名称。Endpoint 请在高级设置填写。',
            self::DRIVER_GCS => '只填写存储桶名称。Endpoint 请在高级设置填写。',
        ];
    }

    /**
     * 设置页点选后的就地披露，不整页重载。
     *
     * @param array<string, string> $opts
     * @return array<string, mixed>
     */
    public static function admin_ui(array $opts): array
    {
        $regions = [];
        $defaults = [];
        foreach (array_keys(self::driver_ids()) as $id) {
            $regions[$id] = self::grouped_regions($id);
            $defaults[$id] = self::default_region($id);
        }
        $s3_family = [];
        foreach (array_keys(self::driver_ids()) as $id) {
            if (self::is_s3_family($id)) {
                $s3_family[] = $id;
            }
        }

        return [
            'savedDriver' => (string) ($opts['driver'] ?? self::DRIVER_ALIYUN),
            'savedSiteMode' => self::sanitize_site_mode($opts['site_mode'] ?? 'domestic'),
            'savedRegion' => (string) ($opts['region'] ?? ''),
            'configured' => ($opts['bucket'] ?? '') !== '',
            'drivers' => self::driver_ids(),
            'driverHelp' => self::driver_help(),
            'bucketHelp' => self::bucket_help(),
            'siteModes' => self::site_modes(),
            'regions' => $regions,
            'defaults' => $defaults,
            's3Family' => $s3_family,
            's3Presets' => self::s3_presets(),
            'lockedPreset' => [
                self::DRIVER_TOS => 'tos',
                self::DRIVER_GCS => 'gcs',
            ],
            'groupOrder' => [
                'domestic' => ['cn'],
                'crossborder' => ['intl', 'cn'],
                'mixed' => ['cn', 'intl'],
            ],
            'copy' => [
                'preview' => '正在预览，尚未保存。',
                'savePreview' => '预览中的更改需保存后才会生效。',
                'saveDefault' => '密钥留空保存时将保留已保存的值。',
                'appIdCos' => 'APPID（COS）',
                'appIdCosHelp' => '请填写创建存储桶的主账号 APPID。子账号的 APPID 无效。桶名已包含 APPID 时可以留空。',
                'appIdR2' => 'R2 Account ID',
                'appIdR2Help' => '用于拼接服务 Endpoint。',
                's3Help' => '将自动填入默认 Endpoint。',
                'tosHelp' => '火山引擎使用此预设，不能更换。',
                'gcsHelp' => 'Google Cloud Storage 使用此预设，不能更换。',
                'bucketFallback' => '只填写存储桶名称，不要带域名。',
            ],
        ];
    }
}
