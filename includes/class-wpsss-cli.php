<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI：wp sss …
 *
 * 密钥建议用 --from-env 从环境文件读入，避免留在命令历史里。
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
     * : 从 env 文件读取桶与密钥（WPSSS_OSS_BUCKET / WPSSS_OSS_REGION / WPSSS_OSS_ACCESS_KEY_ID / WPSSS_OSS_ACCESS_KEY_SECRET）。
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
     * : 自定义域名（HTTPS）。请确认 CDN 已指向存储桶再填，填错会导致前台图片无法访问。
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
     * : aws / do / r2 / minio / wasabi / tos / custom。暂不支持百度 BOS。
     * [--force-path-style=<value>]
     * : 1 走 path-style。R2 / MinIO 默认 1。
     * [--site-mode=<mode>]
     * : 站点场景：domestic（国内）/ crossborder（跨境）/ mixed（混合），影响后台推荐厂商排序。
     * [--url-access=<mode>]
     * : public / signed。
     * [--signed-ttl=<seconds>]
     * : 签名有效期，默认 3600。
     * [--allow-no-local]
     * : 确认关闭本地备份（服务器不再保留副本）后再使用。
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
            'site-mode' => 'site_mode',
            'url-access' => 'url_access',
            'signed-ttl' => 'signed_ttl',
        ];
        foreach ($map as $flag => $key) {
            if (array_key_exists($flag, $assoc_args)) {
                $incoming[$key] = (string) $assoc_args[$flag];
            }
        }

        if (isset($incoming['keep_local']) && (string) $incoming['keep_local'] === '0'
            && !self::can_turn_off_keep_local($assoc_args)
        ) {
            WP_CLI::error('关闭本地备份须管理员批准。确认后加 --allow-no-local。');
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
     * [--before=<id>]
     * : 只处理 ID 小于该值的附件，用于接着上一批。
     *
     * ## EXAMPLES
     *
     *     wp sss offload --dry-run
     *     wp sss offload --limit=20
     *     wp sss offload --before=100
     *
     * @when after_wp_load
     */
    public function offload(array $args, array $assoc_args): void
    {
        unset($args);
        $id = isset($assoc_args['id']) ? (int) $assoc_args['id'] : 0;
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 20;
        $dry = !empty($assoc_args['dry-run']);
        $before = isset($assoc_args['before']) ? (int) $assoc_args['before'] : 0;
        $result = wpsss()->library()->offload_batch($limit, $dry, $id, 'pending', $before);
        WP_CLI::log('ok=' . $result['ok'] . ' fail=' . $result['fail'] . ' skip=' . $result['skip'] . ' cursor=' . (int) ($result['cursor'] ?? 0));
        foreach ($result['errors'] as $line) {
            WP_CLI::warning($line);
        }
        if ($result['fail'] > 0) {
            WP_CLI::error('仍有附件未上传成功。');
        }
        WP_CLI::success($dry ? '试运行结束，未做任何更改。' : '补传结束。');
    }

    /**
     * 把已进桶的附件拉回 uploads。默认不删远端。
     *
     * ## OPTIONS
     *
     * [--id=<id>]
     * : 只拉这一个附件。
     *
     * [--limit=<n>]
     * : 一批最多几个。默认 20。
     *
     * [--dry-run]
     * : 只数不写。
     *
     * [--on-exist=<mode>]
     * : 本地已有时：skip（默认，只补缺失）、rename（旧文件改名保留）、overwrite（覆盖）。
     *
     * [--delete-remote]
     * : 拉回后删桶里的副本，并清掉 offloaded 标记。须同时加 --yes，且不能与 skip 同用。
     *
     * [--before=<id>]
     * : 只处理 ID 小于该值的已进桶附件，用于接着上一批。
     *
     * ## EXAMPLES
     *
     *     wp sss pull --dry-run
     *     wp sss pull --id=12 --on-exist=rename
     *     wp sss pull --before=100
     *
     * @when after_wp_load
     */
    public function pull(array $args, array $assoc_args): void
    {
        unset($args);
        $id = isset($assoc_args['id']) ? (int) $assoc_args['id'] : 0;
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 20;
        $dry = !empty($assoc_args['dry-run']);
        $delete = !empty($assoc_args['delete-remote']);
        $on_exist = Wpsss_Offload::sanitize_on_exist(isset($assoc_args['on-exist']) ? (string) $assoc_args['on-exist'] : 'skip');
        $before = isset($assoc_args['before']) ? (int) $assoc_args['before'] : 0;
        if ($delete && empty($assoc_args['yes']) && empty($assoc_args['dry-run'])) {
            WP_CLI::error('删除远端须加 --yes。');
        }
        if ($delete && $on_exist === 'skip') {
            WP_CLI::error('--delete-remote 不能搭配 --on-exist=skip，请用 rename 或 overwrite，并加 --yes。');
        }
        $result = wpsss()->library()->pull_batch($limit, $dry, $id, $delete, $on_exist, $before);
        WP_CLI::log(
            'ok=' . $result['ok']
            . ' fail=' . $result['fail']
            . ' skip=' . $result['skip']
            . ' renamed=' . $result['renamed']
            . ' missing=' . $result['missing']
            . ' exists=' . $result['exists']
            . ' blocked=' . (int) ($result['blocked'] ?? 0)
            . ' cursor=' . (int) ($result['cursor'] ?? 0)
            . ' on-exist=' . $on_exist
        );
        if (!$delete && !$dry) {
            WP_CLI::log('文件已写回本地，前台地址仍指向存储桶。要改回本地地址，请解除标记或使用 --delete-remote。');
        }
        foreach ($result['errors'] as $line) {
            WP_CLI::warning($line);
        }
        if ($result['fail'] > 0) {
            WP_CLI::error('仍有附件未拉回成功。');
        }
        WP_CLI::success($dry ? '试运行结束，未做任何更改。' : '拉回结束。');
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
            WP_CLI::error('请提供附件 ID。');
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
            $access = (string) ($opts['url_access'] ?? 'public');
            if (self::classify_anonymous_http($code, $access) === 'ok') {
                if ($access === 'signed' && ($code === 403 || $code === 401)) {
                    $report['oks'][] = '匿名 ' . $code . ' 属预期（签名访问）';
                } else {
                    $report['oks'][] = '匿名可读 HTTP ' . $code;
                }
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
            WP_CLI::success('体检完成：无阻断项。');
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
            $warnings[] = 'bucket 不是测试桶 wenpai-wpsss-test。生产须另行审批。';
        }
        if (($opts['keep_local'] ?? '') !== '1') {
            $blockers[] = 'keep_local=0。关闭须管理员批准。';
        } else {
            $oks[] = 'keep_local=1';
        }
        $ram = (($opts['driver'] ?? '') === 'aliyun-oss' || ($opts['driver'] ?? '') === '')
            && (($opts['oss_auth'] ?? 'ak') === 'ram');
        if ($ram) {
            $oks[] = 'oss_auth=ram';
        } elseif (($opts['access_key_id'] ?? '') === '' || ($opts['access_key_secret'] ?? '') === '') {
            $blockers[] = '密钥未齐。wp sss set --from-env';
        } else {
            $oks[] = '密钥已配置（不回显）';
        }
        $mode = (string) ($opts['site_mode'] ?? 'domestic');
        if (!in_array($mode, ['domestic', 'crossborder', 'mixed'], true)) {
            $mode = 'domestic';
        }
        $oks[] = 'site_mode=' . $mode;
        if (($opts['url_access'] ?? 'public') === 'signed') {
            $oks[] = 'url_access=signed';
        }
        if (method_exists('Wpsss_Options', 'runtime_warnings')) {
            foreach (Wpsss_Options::runtime_warnings($opts) as $line) {
                if ($line !== '' && !in_array($line, $warnings, true) && !in_array($line, $blockers, true)) {
                    $warnings[] = $line;
                }
            }
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
    public static function classify_anonymous_http(int $code, string $url_access = 'public'): string
    {
        if ($url_access === 'signed') {
            return $code === 200 || $code === 403 || $code === 401 ? 'ok' : 'warn';
        }
        return $code === 200 ? 'ok' : 'warn';
    }

    public static function anonymous_read_message(int $code): string
    {
        if ($code === 403 || $code === 401) {
            return '匿名无法读取对象（HTTP ' . $code . '）。公开读模式下前台图片可能无法显示。可改用「签名访问」，或开启桶公共读 / 配置自定义域名。';
        }
        if ($code === 0) {
            return '匿名探测没返回状态。不能当可读。';
        }
        return '匿名读对象 HTTP ' . $code . '。200 才是访客能看。';
    }

    /**
     * 后台概览用的体检摘要（不回显密钥）。
     *
     * @param array<string, string> $opts
     * @return array{
     *   healthy: bool,
     *   tone: string,
     *   label: string,
     *   blockers: string[],
     *   warnings: string[],
     *   oks: string[]
     * }
     */
    public static function doctor_report(array $opts): array
    {
        $raw = self::doctor_findings(
            $opts,
            (string) get_option('upload_url_path', ''),
            (string) get_option(Wpsss_Options::OWN_URL_FLAG, '')
        );
        $blockers = array_map([self::class, 'doctor_line_for_admin'], $raw['blockers']);
        $warnings = array_map([self::class, 'doctor_line_for_admin'], $raw['warnings']);
        $oks = array_map([self::class, 'doctor_line_for_admin'], $raw['oks']);

        $sample = self::sample_offloaded_attachment_url();
        if ($sample !== '') {
            $code = self::anonymous_http_status($sample);
            $access = (string) ($opts['url_access'] ?? 'public');
            if (self::classify_anonymous_http($code, $access) === 'ok') {
                if ($access === 'signed' && ($code === 403 || $code === 401)) {
                    $oks[] = '匿名访问受限，符合签名访问设置';
                } else {
                    $oks[] = '样本对象可匿名访问（HTTP ' . $code . '）';
                }
            } else {
                $warnings[] = self::anonymous_read_message_for_admin($code, $access);
            }
        }

        $healthy = $blockers === [];
        $tone = $healthy ? ($warnings === [] ? 'ok' : 'warn') : 'block';
        if (!$healthy) {
            $label = __('存在阻断项', 'wpsss');
        } elseif ($warnings !== []) {
            $label = __('有警告', 'wpsss');
        } else {
            $label = __('运行正常', 'wpsss');
        }

        return [
            'healthy' => $healthy,
            'tone' => $tone,
            'label' => $label,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'oks' => $oks,
        ];
    }

    public static function sample_offloaded_attachment_url(): string
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

    private static function doctor_line_for_admin(string $line): string
    {
        $map = [
            'bucket 空。先 wp sss set --from-env' => '尚未填写存储桶名称',
            'bucket=wenpai-wpsss-test' => '当前为测试桶 wenpai-wpsss-test',
            'bucket 不是测试桶 wenpai-wpsss-test。生产须另行审批。' => '当前存储桶不是测试桶，生产环境须另行审批',
            'keep_local=0。关闭须管理员批准。' => '本地备份已关闭，须管理员批准',
            'keep_local=1' => '本地备份已开启',
            '密钥未齐。wp sss set --from-env' => '访问密钥尚未配置完整',
            '密钥已配置（不回显）' => '访问密钥已保存',
            'site_mode=domestic' => '站点场景：国内站',
            'site_mode=crossborder' => '站点场景：跨境 · 外贸站',
            'site_mode=mixed' => '站点场景：混合站',
            'url_access=signed' => '对象访问：签名 URL',
            '七牛必须填写 HTTPS 自定义域名，默认域不能给访客用。' => '七牛须填写 HTTPS 自定义域名，否则前台没有对外地址',
            '阿里默认域名 2019 年后常强制下载不预览。请配自定义域或改签名访问。' => '阿里默认域名常强制下载，请配自定义域或改签名访问',
            'COS 的 APPID 是桶创建者的，不是子账户的。' => 'COS 的 APPID 须填桶主账号，不是子账户',
            'upload_url_path 不能是「.」，删除远端会失败。' => '媒体地址前缀不能是「.」，否则删除远端会失败',
            '实例 RAM 角色名为空时，会向 ECS 元数据询问；失败则无法进桶。' => 'RAM 角色未填写，插件会向服务器元数据接口取临时密钥；取不到就无法进桶',
        ];
        if (isset($map[$line])) {
            return $map[$line];
        }
        if (strpos($line, 'site_mode=') === 0) {
            return $line;
        }
        return $line;
    }

    private static function anonymous_read_message_for_admin(int $code, string $url_access): string
    {
        if ($url_access === 'signed') {
            if ($code === 0) {
                return '未能探测样本对象，请稍后在工具页测试连通';
            }
            return '样本对象匿名访问 HTTP ' . $code . '，请检查签名设置';
        }
        if ($code === 403 || $code === 401) {
            return '样本对象禁止匿名访问（HTTP ' . $code . '）；公开读模式下前台图片可能无法显示';
        }
        if ($code === 0) {
            return '未能探测样本对象，请稍后在工具页测试连通';
        }
        return '样本对象匿名访问 HTTP ' . $code . '，公开读通常应为 200';
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
        return self::sample_offloaded_attachment_url();
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
        Wpsss_History::add_object('upload', $key);
        WP_CLI::success('已上传 ' . $key);
    }

    /**
     * 只读扫描最近一批附件。
     *
     * ## OPTIONS
     *
     * [--limit=<n>]
     * : 默认 40。
     *
     * @when after_wp_load
     */
    public function scan(array $args, array $assoc_args): void
    {
        unset($args);
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 40;
        $report = (new Wpsss_Ops(wpsss()))->scan($limit);
        WP_CLI::log('checked=' . $report['checked'] . ' host=' . $report['expected_host']);
        WP_CLI::log('local_missing=' . count($report['local_missing']));
        WP_CLI::log('host_mismatch=' . count($report['host_mismatch']));
        WP_CLI::log('http=' . count($report['http']));
        WP_CLI::log('failed=' . count($report['failed']));
        WP_CLI::success('扫描结束。');
    }

    /**
     * 诊断一条附件 ID 或 URL。
     *
     * ## OPTIONS
     *
     * <input>
     * : 附件 ID 或完整 URL。
     *
     * @when after_wp_load
     */
    public function diagnose(array $args, array $assoc_args): void
    {
        unset($assoc_args);
        $input = isset($args[0]) ? (string) $args[0] : '';
        $row = (new Wpsss_Ops(wpsss()))->diagnose($input);
        WP_CLI::log(wp_json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        WP_CLI::success($row['found'] ? '已定位附件。' : '没有对应附件。');
    }

    /**
     * 按对象键删除。
     *
     * ## OPTIONS
     *
     * <key>
     * : 对象键。
     *
     * [--yes]
     * : 确认删除。
     *
     * ## EXAMPLES
     *
     *     wp sss delete manual/a.png --yes
     *
     * @when after_wp_load
     */
    public function delete(array $args, array $assoc_args): void
    {
        $key = isset($args[0]) ? ltrim((string) $args[0], '/') : '';
        if ($key === '') {
            WP_CLI::error('请提供对象键。');
        }
        if (empty($assoc_args['yes'])) {
            WP_CLI::error('删除对象须加 --yes。');
        }
        $result = wpsss()->active_driver()->delete_objects(Wpsss_Options::get(), [$key]);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        Wpsss_History::add_object('delete', $key);
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
