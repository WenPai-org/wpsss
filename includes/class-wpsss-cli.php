<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI：wp sss …
 *
 * Agent 优先走这里，不要在对话里贴密钥。
 */
final class Wpsss_CLI
{
    /**
     * 看接通状态。不打印 Secret。
     *
     * ## EXAMPLES
     *
     *     wp sss status
     *
     * @when after_wp_load
     */
    public function status(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        $opts = Wpsss_Options::get();
        $driver = wpsss()->active_driver();
        $configured = $driver->is_configured($opts);
        WP_CLI::log('driver=' . $driver->id());
        WP_CLI::log('configured=' . ($configured ? 'yes' : 'no'));
        WP_CLI::log('bucket=' . $opts['bucket']);
        WP_CLI::log('region=' . $opts['region']);
        WP_CLI::log('access_key_id=' . $this->mask($opts['access_key_id']));
        WP_CLI::log('access_key_secret=' . ($opts['access_key_secret'] !== '' ? 'set' : 'empty'));
        WP_CLI::log('custom_domain=' . $opts['custom_domain']);
        WP_CLI::log('public_base=' . Wpsss_Options::public_base());
        WP_CLI::log('keep_local=' . $opts['keep_local']);
        WP_CLI::log('upload_thumbs=' . $opts['upload_thumbs']);
        WP_CLI::log('use_internal=' . $opts['use_internal']);
        WP_CLI::log('owns_upload_url_path=' . (string) get_option(Wpsss_Options::OWN_URL_FLAG, ''));
        if ($configured) {
            WP_CLI::success('已配置。');
        } else {
            WP_CLI::warning('未配置。wp sss set --from-env');
        }
    }

    /**
     * 写探测对象再立刻删。
     *
     * ## EXAMPLES
     *
     *     wp sss probe
     *
     * @when after_wp_load
     */
    public function probe(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        $opts = Wpsss_Options::get();
        $driver = wpsss()->active_driver();
        if (!$driver->is_configured($opts)) {
            WP_CLI::error('先保存桶和密钥。');
        }
        $result = $driver->probe($opts);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        WP_CLI::success('已写入并删除探测对象。');
    }

    /**
     * 写入设置。默认不回显密钥。
     *
     * ## OPTIONS
     *
     * [--from-env]
     * : 读 ~/.config/linuxjoy/wpsss-test.env（或 --env-file）。
     *
     * [--env-file=<path>]
     * : env 路径。
     *
     * [--bucket=<name>]
     * [--region=<id>]
     * [--access-key-id=<id>]
     * [--access-key-secret=<secret>]
     * [--custom-domain=<url>]
     * [--keep-local=<value>]
     * [--upload-thumbs=<value>]
     * [--use-internal=<value>]
     * [--allow-no-local]
     * : 允许把 keep_local 写成 0。无人批不要用。
     *
     * ## EXAMPLES
     *
     *     wp sss set --from-env
     *
     * @when after_wp_load
     */
    public function set(array $args, array $assoc_args): void
    {
        unset($args);
        $current = Wpsss_Options::get();
        $incoming = [];

        if (!empty($assoc_args['from-env'])) {
            $path = isset($assoc_args['env-file'])
                ? (string) $assoc_args['env-file']
                : (string) getenv('HOME') . '/.config/linuxjoy/wpsss-test.env';
            $incoming = array_merge($incoming, $this->read_env_file($path));
        }

        $map = [
            'bucket' => 'bucket',
            'region' => 'region',
            'access-key-id' => 'access_key_id',
            'access-key-secret' => 'access_key_secret',
            'custom-domain' => 'custom_domain',
            'keep-local' => 'keep_local',
            'upload-thumbs' => 'upload_thumbs',
            'use-internal' => 'use_internal',
        ];
        foreach ($map as $flag => $key) {
            if (array_key_exists($flag, $assoc_args)) {
                $incoming[$key] = (string) $assoc_args[$flag];
            }
        }

        if (isset($incoming['keep_local']) && (string) $incoming['keep_local'] === '0'
            && !self::can_turn_off_keep_local($assoc_args)
        ) {
            WP_CLI::error('关 keep_local 须人批。确认后加 --allow-no-local。');
        }
        if (self::can_turn_off_keep_local($assoc_args)) {
            $incoming['_allow_no_local'] = '1';
        }

        $saved = Wpsss_Options::sanitize(array_merge($current, $incoming));
        update_option(Wpsss_Options::OPTION_KEY, $saved);
        WP_CLI::success('已保存。密钥不回显。再跑 wp sss status / probe。');
    }

    /**
     * 体检：桶、本地备份、密钥、upload_url_path。
     *
     * BLOCK 才失败。WARN 不阻断（例如桶不是测试桶）。
     *
     * @when after_wp_load
     */
    public function doctor(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        $opts = Wpsss_Options::get();
        $report = self::doctor_findings(
            $opts,
            (string) get_option('upload_url_path', ''),
            (string) get_option(Wpsss_Options::OWN_URL_FLAG, '')
        );
        foreach ($report['oks'] as $line) {
            WP_CLI::log('OK    ' . $line);
        }
        foreach ($report['warnings'] as $line) {
            WP_CLI::warning('WARN  ' . $line);
        }
        foreach ($report['blockers'] as $line) {
            WP_CLI::log('BLOCK ' . $line);
        }
        WP_CLI::log('upload_url_path=' . $report['upload_url_path']);
        WP_CLI::log('owns=' . $report['owns']);
        if ($report['blockers'] === []) {
            WP_CLI::success('doctor 无阻断。');
            return;
        }
        WP_CLI::error('doctor 有阻断：' . implode(' / ', $report['blockers']));
    }

    /**
     * @return array<string, string>
     */
    private function read_env_file(string $path): array
    {
        if (!is_readable($path)) {
            WP_CLI::error('读不到 env：' . $path);
        }
        $out = self::parse_env_text((string) file_get_contents($path));
        if ($out === []) {
            WP_CLI::error('env 里没有 WPSSS_OSS_*。');
        }
        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function parse_env_text(string $text): array
    {
        $out = [];
        $map = [
            'WPSSS_OSS_BUCKET' => 'bucket',
            'WPSSS_OSS_REGION' => 'region',
            'WPSSS_OSS_ACCESS_KEY_ID' => 'access_key_id',
            'WPSSS_OSS_ACCESS_KEY_SECRET' => 'access_key_secret',
        ];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            if (isset($map[$k])) {
                $out[$map[$k]] = $v;
            }
        }
        return $out;
    }

    /**
     * @param array<string, string> $opts
     * @return array{blockers: string[], warnings: string[], oks: string[], upload_url_path: string, owns: string}
     */
    public static function doctor_findings(array $opts, string $upload_url_path, string $owns): array
    {
        $blockers = [];
        $warnings = [];
        $oks = [];
        $bucket = (string) ($opts['bucket'] ?? '');
        if ($bucket === '') {
            $blockers[] = 'bucket 空。先 wp sss set --from-env';
        } elseif ($bucket === 'wenpai-wpsss-test') {
            $oks[] = 'bucket=wenpai-wpsss-test';
        } else {
            $warnings[] = 'bucket 不是测试桶 wenpai-wpsss-test。生产须人批。';
        }
        if (($opts['keep_local'] ?? '') !== '1') {
            $blockers[] = 'keep_local=0。关掉须人批。';
        } else {
            $oks[] = 'keep_local=1';
        }
        if (($opts['access_key_id'] ?? '') === '' || ($opts['access_key_secret'] ?? '') === '') {
            $blockers[] = '密钥未齐。wp sss set --from-env';
        } else {
            $oks[] = '密钥已配置（不回显）';
        }
        return [
            'blockers' => $blockers,
            'warnings' => $warnings,
            'oks' => $oks,
            'upload_url_path' => $upload_url_path,
            'owns' => $owns,
        ];
    }

    /**
     * @param array<string, mixed> $assoc_args
     */
    public static function can_turn_off_keep_local(array $assoc_args): bool
    {
        return !empty($assoc_args['allow-no-local']);
    }

    public static function mask_key(string $value): string
    {
        if ($value === '') {
            return 'empty';
        }
        $len = strlen($value);
        if ($len <= 6) {
            return 'set';
        }
        return substr($value, 0, 4) . '…' . substr($value, -2);
    }

    private function mask(string $value): string
    {
        return self::mask_key($value);
    }
}
