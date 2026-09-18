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
        WP_CLI::log('object_prefix=' . $opts['object_prefix']);
        WP_CLI::log('auto_rename=' . $opts['auto_rename']);
        WP_CLI::log('image_style=' . $opts['image_style']);
        WP_CLI::log('origin_protect=' . $opts['origin_protect']);
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
     * : 桶名。
     * [--region=<id>]
     * : 区域，如 oss-cn-hangzhou。
     * [--access-key-id=<id>]
     * : RAM AccessKey ID。
     * [--access-key-secret=<secret>]
     * : RAM Secret。不回显。
     * [--custom-domain=<url>]
     * : 自定义域。无人批不要填。
     * [--keep-local=<value>]
     * : 1 留本地。0 须同时加 --allow-no-local。
     * [--upload-thumbs=<value>]
     * : 1 上传缩略图。
     * [--use-internal=<value>]
     * : 1 走内网。
     * [--driver=<id>]
     * : aliyun-oss / qcloud-cos / qiniu-kodo / huawei-obs / volc-tos / s3-compat。
     * [--app-id=<id>]
     * : COS APPID，可选。
     * [--object-prefix=<path>]
     * : 对象前缀 / 子目录。
     * [--auto-rename=<mode>]
     * : off / md5 / time。
     * [--image-style=<suffix>]
     * : 接到 URL 后的图片样式。
     * [--origin-protect=<value>]
     * : 1 原图保护。
     * [--rewrite-from=<url>]
     * : 额外改写来源。
     * [--endpoint=<url>]
     * : S3 兼容 endpoint。空着按预设拼。
     * [--s3-preset=<id>]
     * : aws / do / r2 / minio / wasabi / tos / bos / custom。
     * [--force-path-style=<value>]
     * : 1 走 path-style。R2 / MinIO 默认 1。
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
            'driver' => 'driver',
            'app-id' => 'app_id',
            'object-prefix' => 'object_prefix',
            'auto-rename' => 'auto_rename',
            'image-style' => 'image_style',
            'origin-protect' => 'origin_protect',
            'rewrite-from' => 'rewrite_from',
            'endpoint' => 'endpoint',
            's3-preset' => 's3_preset',
            'force-path-style' => 'force_path_style',
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
     * 把已有附件补传到桶。默认只处理未进桶的。
     *
     * ## OPTIONS
     *
     * [--id=<id>]
     * : 只处理这一个附件。
     *
     * [--limit=<n>]
     * : 一批最多几个。默认 20。
     *
     * [--dry-run]
     * : 只数不传。
     *
     * ## EXAMPLES
     *
     *     wp sss offload --dry-run
     *     wp sss offload --limit=20
     *
     * @when after_wp_load
     */
    public function offload(array $args, array $assoc_args): void
    {
        unset($args);
        $id = isset($assoc_args['id']) ? (int) $assoc_args['id'] : 0;
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 20;
        $dry = !empty($assoc_args['dry-run']);
        $result = wpsss()->library()->offload_batch($limit, $dry, $id);
        WP_CLI::log('ok=' . $result['ok'] . ' fail=' . $result['fail'] . ' skip=' . $result['skip']);
        foreach ($result['errors'] as $line) {
            WP_CLI::warning($line);
        }
        if ($result['fail'] > 0) {
            WP_CLI::error('有附件没传上去。');
        }
        WP_CLI::success($dry ? 'dry-run 结束。' : '补传结束。');
    }

    /**
     * 看改写后的附件 URL。
     *
     * ## OPTIONS
     *
     * <id>
     * : 附件 ID。
     *
     * ## EXAMPLES
     *
     *     wp sss url 12
     *
     * @when after_wp_load
     */
    public function url(array $args, array $assoc_args): void
    {
        unset($assoc_args);
        $id = isset($args[0]) ? (int) $args[0] : 0;
        if ($id <= 0) {
            WP_CLI::error('要附件 ID。');
        }
        $url = wp_get_attachment_url($id);
        WP_CLI::log('url=' . (is_string($url) ? $url : ''));
        WP_CLI::log('offloaded=' . (Wpsss_Library::is_offloaded($id) ? '1' : '0'));
        $key = (string) get_post_meta($id, Wpsss_Library::META_KEY, true);
        if ($key !== '') {
            WP_CLI::log('key=' . $key);
            $head = wpsss()->active_driver()->head_object(Wpsss_Options::get(), $key);
            if (is_wp_error($head)) {
                WP_CLI::log('head=' . $head->get_error_message());
            } else {
                WP_CLI::log('head=200');
            }
        }
    }

    /**
     * 把文章里的本地 uploads URL 换成桶域。默认 dry-run。
     *
     * ## OPTIONS
     *
     * [--apply]
     * : 真写库。不加就只预览。
     * [--from=<url>]
     * : 被替换的前缀。默认本站 uploads。
     * [--to=<url>]
     * : 换成这个。默认当前对外地址。
     *
     * ## EXAMPLES
     *
     *     wp sss rewrite
     *     wp sss rewrite --apply
     *
     * @when after_wp_load
     */
    public function rewrite(array $args, array $assoc_args): void
    {
        unset($args);
        $apply = !empty($assoc_args['apply']);
        $from = isset($assoc_args['from']) ? (string) $assoc_args['from'] : '';
        $to = isset($assoc_args['to']) ? (string) $assoc_args['to'] : '';
        $result = wpsss()->library()->rewrite_posts($apply, $from, $to);
        WP_CLI::log('scanned=' . $result['scanned'] . ' changed=' . $result['changed']);
        WP_CLI::log('meta_scanned=' . $result['meta_scanned'] . ' meta_changed=' . $result['meta_changed']);
        if ($result['samples'] !== []) {
            WP_CLI::log('ids=' . implode(',', $result['samples']));
        }
        WP_CLI::success($apply ? '已改写。' : '预览结束。要写库加 --apply。');
    }

    /**
     * 体检：桶、本地备份、密钥、upload_url_path、匿名可读。
     *
     * BLOCK 才失败。WARN 不阻断（例如桶不是测试桶、匿名 403）。
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
        $sample = $this->doctor_sample_public_url();
        if ($sample !== '') {
            $code = self::anonymous_http_status($sample);
            if (self::classify_anonymous_http($code) === 'ok') {
                $report['oks'][] = '匿名可读 HTTP ' . $code;
            } else {
                $report['warnings'][] = self::anonymous_read_message($code);
            }
        }
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
        $stats = wpsss()->library()->stats();
        WP_CLI::log('attachments=' . $stats['total'] . ' offloaded=' . $stats['offloaded'] . ' pending=' . $stats['pending']);
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
     * 200 才算访客能读。403 是 WARN，不阻断。
     */
    public static function classify_anonymous_http(int $code): string
    {
        return $code === 200 ? 'ok' : 'warn';
    }

    public static function anonymous_read_message(int $code): string
    {
        if ($code === 403 || $code === 401) {
            return '匿名读不到对象（HTTP ' . $code . '）。测试桶阻止公共访问时属预期，前台会裂图。';
        }
        if ($code === 0) {
            return '匿名探测没返回状态。不能当可读。';
        }
        return '匿名读对象 HTTP ' . $code . '。200 才是访客能看。';
    }

    public static function anonymous_http_status(string $url): int
    {
        if ($url === '' || !function_exists('wp_remote_head')) {
            return 0;
        }
        $response = wp_remote_head(
            $url,
            [
                'timeout' => 15,
                'redirection' => 0,
            ]
        );
        if (is_wp_error($response)) {
            return 0;
        }
        return (int) wp_remote_retrieve_response_code($response);
    }

    private function doctor_sample_public_url(): string
    {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => Wpsss_Library::META_OFFLOADED,
            'meta_value' => '1',
        ]);
        if ($ids === []) {
            return '';
        }
        $url = wp_get_attachment_url((int) $ids[0]);
        return is_string($url) ? $url : '';
    }

    /**
     * @param array<string, mixed> $assoc_args
     */

    /**
     * 把本地文件直接传到当前 driver。
     *
     * ## OPTIONS
     *
     * <file>
     * : 本地路径。
     *
     * [--key=<object>]
     * : 对象键。默认用文件名。
     *
     * ## EXAMPLES
     *
     *     wp sss upload ./a.png --key=manual/a.png
     *
     * @when after_wp_load
     */
    public function upload(array $args, array $assoc_args): void
    {
        $file = isset($args[0]) ? (string) $args[0] : '';
        if ($file === '' || !is_readable($file)) {
            WP_CLI::error('读不到文件。');
        }
        $key = isset($assoc_args['key']) ? ltrim((string) $assoc_args['key'], '/') : basename($file);
        $key = Wpsss_Options::apply_object_prefix($key);
        $mime = (string) (function_exists('mime_content_type') ? (mime_content_type($file) ?: '') : '');
        $result = wpsss()->active_driver()->put_object(Wpsss_Options::get(), $key, $file, $mime);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        Wpsss_History::add('upload', $key);
        WP_CLI::success('已上传 ' . $key);
    }

    /**
     * 按对象键删除。
     *
     * ## OPTIONS
     *
     * <key>
     * : 对象键。
     *
     * ## EXAMPLES
     *
     *     wp sss delete manual/a.png
     *
     * @when after_wp_load
     */
    public function delete(array $args, array $assoc_args): void
    {
        unset($assoc_args);
        $key = isset($args[0]) ? ltrim((string) $args[0], '/') : '';
        if ($key === '') {
            WP_CLI::error('要对象键。');
        }
        $result = wpsss()->active_driver()->delete_objects(Wpsss_Options::get(), [$key]);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        Wpsss_History::add('delete', $key);
        WP_CLI::success('已删除 ' . $key);
    }

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
