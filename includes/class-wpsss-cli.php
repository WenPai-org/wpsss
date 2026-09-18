<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI：wp wpsss …
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
     *     wp wpsss status
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
            WP_CLI::warning('未配置。wp wpsss set --from-env');
        }
    }

    /**
     * 写探测对象再立刻删。
     *
     * ## EXAMPLES
     *
     *     wp wpsss probe
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
     * [--keep-local=<0|1>]
     * [--upload-thumbs=<0|1>]
     * [--use-internal=<0|1>]
     *
     * ## EXAMPLES
     *
     *     wp wpsss set --from-env
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

        $saved = Wpsss_Options::sanitize(array_merge($current, $incoming));
        update_option(Wpsss_Options::OPTION_KEY, $saved);
        WP_CLI::success('已保存。密钥不回显。再跑 wp wpsss status / probe。');
    }

    /**
     * 体检：桶、本地备份、upload_url_path。
     *
     * @when after_wp_load
     */
    public function doctor(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        $opts = Wpsss_Options::get();
        $ok = true;
        if ($opts['bucket'] === 'wenpai-wpsss-test') {
            WP_CLI::log('bucket=test ok');
        } elseif ($opts['bucket'] === '') {
            WP_CLI::warning('bucket empty');
            $ok = false;
        } else {
            WP_CLI::warning('bucket 不是测试桶。生产须人批。');
        }
        if ($opts['keep_local'] !== '1') {
            WP_CLI::warning('keep_local=0。关掉须人批。');
            $ok = false;
        } else {
            WP_CLI::log('keep_local=1 ok');
        }
        $url = (string) get_option('upload_url_path', '');
        $owns = (string) get_option(Wpsss_Options::OWN_URL_FLAG, '');
        WP_CLI::log('upload_url_path=' . $url);
        WP_CLI::log('owns=' . $owns);
        if ($ok) {
            WP_CLI::success('doctor 无阻断。');
        } else {
            WP_CLI::error('doctor 有警告。', false);
        }
    }

    /**
     * @return array<string, string>
     */
    private function read_env_file(string $path): array
    {
        if (!is_readable($path)) {
            WP_CLI::error('读不到 env：' . $path);
        }
        $out = [];
        $map = [
            'WPSSS_OSS_BUCKET' => 'bucket',
            'WPSSS_OSS_REGION' => 'region',
            'WPSSS_OSS_ACCESS_KEY_ID' => 'access_key_id',
            'WPSSS_OSS_ACCESS_KEY_SECRET' => 'access_key_secret',
        ];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
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
        if ($out === []) {
            WP_CLI::error('env 里没有 WPSSS_OSS_*。');
        }
        return $out;
    }

    private function mask(string $value): string
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
}
