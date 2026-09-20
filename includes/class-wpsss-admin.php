<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Wpsss_Admin
{
    /** @var Wpsss_Plugin */
    private $plugin;

    public function __construct(Wpsss_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('admin_body_class', [$this, 'body_class']);
        add_action('admin_post_wpsss_probe', [$this, 'handle_probe']);
        add_action('admin_post_wpsss_offload', [$this, 'handle_offload']);
        add_action('admin_post_wpsss_offload_one', [$this, 'handle_offload_one']);
        add_action('admin_post_wpsss_export', [$this, 'handle_export']);
        add_action('admin_post_wpsss_scan', [$this, 'handle_scan']);
        add_action('admin_post_wpsss_diagnose', [$this, 'handle_diagnose']);
        add_action('admin_post_wpsss_headcheck', [$this, 'handle_headcheck']);
        add_action('admin_post_wpsss_clear_errors', [$this, 'handle_clear_errors']);
        add_action('admin_post_wpsss_release', [$this, 'handle_release']);
        add_action('admin_post_wpsss_rewrite', [$this, 'handle_rewrite']);
        add_action('admin_post_wpsss_pull', [$this, 'handle_pull']);
        add_action('admin_post_wpsss_pull_one', [$this, 'handle_pull_one']);
        add_action('admin_post_wpsss_quick', [$this, 'handle_quick']);
        add_action('admin_post_wpsss_setup', [$this, 'handle_setup']);
        add_action('wp_ajax_wpsss_inspect', [$this, 'handle_inspect']);
        add_filter('wp_redirect', [$this, 'after_options_redirect']);
        add_filter('plugin_action_links_' . plugin_basename(WPSSS_FILE), [$this, 'action_links']);
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function action_links(array $links): array
    {
        $url = $this->url('settings');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('设置', 'wpsss') . '</a>');
        return $links;
    }

    public function body_class(string $classes): string
    {
        if ($this->is_screen()) {
            $classes .= ' wpsss-admin';
            if ($this->tab() === 'setup') {
                $classes .= ' wpsss-setup-wizard';
            }
        }
        return $classes;
    }

    public function enqueue(string $hook): void
    {
        $media = in_array($hook, ['upload.php', 'post.php', 'media-new.php'], true);
        if (!$this->is_screen() && !$media) {
            return;
        }
        $base = plugin_dir_url(WPSSS_FILE);
        wp_enqueue_style('wpsss-e', $base . 'assets/css/e.css', [], (string) filemtime(WPSSS_DIR . 'assets/css/e.css'));
        wp_enqueue_style('wpsss-admin', $base . 'assets/css/admin.css', ['wpsss-e'], (string) filemtime(WPSSS_DIR . 'assets/css/admin.css'));
        wp_enqueue_script('wpsss-admin', $base . 'assets/js/admin.js', [], (string) filemtime(WPSSS_DIR . 'assets/js/admin.js'), true);
        $ui = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'inspectNonce' => wp_create_nonce('wpsss_inspect'),
        ];
        if ($this->is_screen()) {
            $ui = array_merge($ui, Wpsss_Options::admin_ui(Wpsss_Options::get()));
        } else {
            $ui['copy'] = Wpsss_Options::admin_ui(Wpsss_Options::get())['copy'] ?? [];
        }
        wp_localize_script('wpsss-admin', 'wpsssAdmin', $ui);
    }

    public function register_menu(): void
    {
        add_menu_page(
            '文派对象存储',
            '文派对象存储',
            'manage_options',
            'wpsss',
            [$this, 'render'],
            'dashicons-cloud',
            81
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = $this->tab();
        $mode = $this->mode();
        $opts = Wpsss_Options::get();
        $driver = $this->plugin->active_driver();
        $configured = $driver->is_configured($opts);
        $notice = isset($_GET['wpsss_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['wpsss_notice'])) : '';
        $level = isset($_GET['wpsss_level']) ? sanitize_key((string) $_GET['wpsss_level']) : '';
        if (isset($_GET['settings-updated']) || isset($_GET['saved'])) {
            $notice = $notice !== '' ? $notice : __('已保存', 'wpsss');
            $level = $level !== '' ? $level : 'ok';
        }

        if ($tab === 'setup') {
            $step = $this->setup_step();
            if ($step > 2 && !$configured) {
                wp_safe_redirect($this->url('setup', [
                    'step' => '2',
                    'wpsss_level' => 'error',
                    'wpsss_notice' => __('请先完成桶与密钥。', 'wpsss'),
                ]));
                exit;
            }
        }

        $view = [
            'tab' => $tab,
            'mode' => $mode,
            'panel' => $this->tools_panel(),
            'opts' => $opts,
            'driver' => $driver,
            'configured' => $configured,
            'notice' => $notice,
            'level' => $level,
            'secret_set' => $opts['access_key_secret'] !== '',
            'public_base' => Wpsss_Options::public_base(),
            'stats' => $this->plugin->library()->stats(),
            'history' => Wpsss_History::all(),
            'rewrite' => get_transient($this->rewrite_transient_key()) ?: [],
            'pull' => get_transient($this->pull_transient_key()) ?: [],
            'pull_cursor' => (int) get_transient($this->pull_cursor_key()),
            'offload_cursor' => (int) get_transient($this->offload_cursor_key()),
            'ops' => get_transient($this->ops_transient_key()) ?: [],
            'doctor' => ($configured && in_array($tab, ['overview', 'setup'], true)) ? Wpsss_CLI::doctor_report($opts) : [],
            'url' => [$this, 'url'],
            'setup_step' => $tab === 'setup' ? $this->setup_step() : 0,
            'setup_done_rows' => Wpsss_Options::setup_done_rows(
                Wpsss_Options::sanitize_site_mode($opts['site_mode'] ?? 'domestic'),
                $opts
            ),
        ];

        echo '<div class="wpcy-app">';
        $this->include_template('shell-head', $view);
        echo '<main class="wpcy-main wpcy-wrap">';
        $this->include_template('tab-' . $tab, $view);
        echo '<footer class="foot">';
        echo '<span>文派对象存储 ' . esc_html(WPSSS_VERSION) . '</span>';
        echo '<span class="r">';
        echo '<a href="' . esc_url($this->url('help')) . '">' . Wpsss_Icons::svg('help') . esc_html__('帮助', 'wpsss') . '</a>';
        echo '</span></footer>';
        echo '</main></div>';
    }

    /**
     * @param string $location
     */
    public function after_options_redirect($location)
    {
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'wpsss') {
            return $this->url('settings', ['mode' => 'advanced', 'settings-updated' => 'true']);
        }
        return $location;
    }

    public function handle_probe(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_probe');

        $settings = Wpsss_Options::get();
        $driver = $this->plugin->active_driver();
        if (!$driver->is_configured($settings)) {
            $this->redirect_tools('empty', '先保存桶名和密钥再测。', 'daily');
        }

        $result = $driver->probe($settings);
        $return_tab = isset($_POST['wpsss_return_tab']) ? sanitize_key((string) wp_unslash($_POST['wpsss_return_tab'])) : 'tools';
        if (!in_array($return_tab, ['tools', 'setup'], true)) {
            $return_tab = 'tools';
        }
        $extra = $return_tab === 'setup' ? ['step' => '4'] : ['panel' => $this->tools_panel_from_request('daily')];
        if (is_wp_error($result)) {
            $this->redirect($return_tab, 'error', $result->get_error_message(), $extra);
        }
        $this->redirect($return_tab, 'ok', __('已写入并删除探测对象。', 'wpsss'), $extra);
    }

    public function handle_offload(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_offload');
        if (!$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
            $this->redirect_tools('empty', '先保存桶名和密钥再补传。', 'daily');
        }
        if (!empty($_POST['wpsss_offload_reset'])) {
            delete_transient($this->offload_cursor_key());
            $this->redirect_tools('ok', '已从头开始补传。', 'daily');
        }
        $source = !empty($_POST['wpsss_retry']) ? 'failed' : 'pending';
        $before = $source === 'failed' ? 0 : (int) get_transient($this->offload_cursor_key());
        $result = $this->plugin->library()->offload_batch(20, false, 0, $source, $before);
        if ($source !== 'failed') {
            $cursor = (int) ($result['cursor'] ?? 0);
            if ($cursor > 0) {
                set_transient($this->offload_cursor_key(), $cursor, DAY_IN_SECONDS);
            } else {
                delete_transient($this->offload_cursor_key());
            }
        }
        $verb = $source === 'failed' ? '重试' : '补传';
        $msg = sprintf('%s完成：成功 %d，失败 %d，跳过 %d。', $verb, $result['ok'], $result['fail'], $result['skip']);
        if ($source !== 'failed' && (int) ($result['cursor'] ?? 0) > 0) {
            $msg .= sprintf(' 下一批从 ID <%d 接着。', (int) $result['cursor']);
        }
        if ($result['errors'] !== []) {
            $msg .= ' ' . $result['errors'][0];
        }
        $this->redirect_tools($result['fail'] > 0 ? 'error' : 'ok', $msg, 'daily');
    }

    public function handle_offload_one(): void
    {
        if (!current_user_can('upload_files')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('wpsss_offload_one_' . $id);
        if ($id <= 0) {
            wp_safe_redirect(admin_url('upload.php'));
            exit;
        }
        $result = $this->plugin->library()->offload_batch(1, false, $id);
        $ok = $result['fail'] === 0 && $result['ok'] > 0;
        $this->redirect_media($id, 'wpsss_offload', $ok);
    }

    public function handle_pull(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_pull');
        if (!$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
            $this->redirect_tools('empty', '先保存桶名和密钥再拉回。', 'move');
        }
        if (!empty($_POST['wpsss_pull_reset'])) {
            delete_transient($this->pull_cursor_key());
            $this->redirect_tools('ok', '已从头开始拉回。', 'move');
        }
        $apply = !empty($_POST['wpsss_pull_apply']);
        $on_exist = Wpsss_Offload::sanitize_on_exist(isset($_POST['wpsss_pull_exist']) ? (string) wp_unslash($_POST['wpsss_pull_exist']) : 'skip');
        $delete = !empty($_POST['wpsss_pull_delete']);
        $scope = (isset($_POST['wpsss_pull_scope']) && (string) wp_unslash($_POST['wpsss_pull_scope']) === 'one') ? 'one' : 'batch';
        $only_id = ($scope === 'one' && isset($_POST['wpsss_pull_id'])) ? (int) $_POST['wpsss_pull_id'] : 0;
        if ($scope === 'one' && $only_id <= 0) {
            $this->redirect_tools('error', '指定一个时请填写媒体库编号。', 'move');
        }
        if ($apply && $on_exist !== 'skip' && empty($_POST['wpsss_pull_confirm'])) {
            $this->redirect_tools('error', '拉回须勾确认。', 'move');
        }
        if ($apply && $delete && empty($_POST['wpsss_pull_delete_confirm'])) {
            $this->redirect_tools('error', '删除远端须再确认。', 'move');
        }
        if ($delete && $on_exist === 'skip') {
            $this->redirect_tools('error', '删桶不能只补缺失。请改用覆盖或旁路改名。', 'move');
        }
        $before = $only_id > 0 ? 0 : (int) get_transient($this->pull_cursor_key());
        $result = $this->plugin->library()->pull_batch(20, !$apply, $only_id, $apply && $delete, $on_exist, $before);
        if ($apply && $only_id === 0) {
            $cursor = (int) ($result['cursor'] ?? 0);
            if ($cursor > 0) {
                set_transient($this->pull_cursor_key(), $cursor, DAY_IN_SECONDS);
            } else {
                delete_transient($this->pull_cursor_key());
            }
        }
        set_transient($this->pull_transient_key(), [
            'apply' => $apply,
            'on_exist' => $on_exist,
            'only_id' => $only_id,
            'delete' => $apply && $delete,
            'result' => $result,
        ], 10 * MINUTE_IN_SECONDS);
        $msg = $apply
            ? sprintf(
                '拉回完成：成功 %d 个附件，失败 %d；写入计划 %d，跳过 %d，旁路 %d。',
                $result['ok'],
                $result['fail'],
                $result['missing'] + ($on_exist === 'skip' ? 0 : $result['exists']),
                $result['skip'],
                $result['renamed']
            )
            : sprintf(
                '预览：将写入 %d 个文件，跳过 %d，旁路 %d；本地已有 %d，缺失 %d。',
                $result['ok'],
                $result['skip'],
                $result['renamed'],
                $result['exists'],
                $result['missing']
            );
        if ((int) ($result['blocked'] ?? 0) > 0) {
            $msg .= sprintf(' 前缀不符 %d。', (int) $result['blocked']);
        }
        if ($apply && !$delete) {
            $msg .= ' 只写回了磁盘，前台仍走桶。要改地址请解除标记，或拉回并删除。';
        }
        if ($apply && $only_id === 0 && (int) ($result['cursor'] ?? 0) > 0) {
            $msg .= sprintf(' 下一批从 ID <%d 接着。', (int) $result['cursor']);
        }
        if ($result['errors'] !== []) {
            $msg .= ' ' . $result['errors'][0];
        }
        $this->redirect_tools($result['fail'] > 0 ? 'error' : 'ok', $msg, 'move');
    }

    public function handle_pull_one(): void
    {
        if (!current_user_can('upload_files')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('wpsss_pull_one_' . $id);
        if ($id <= 0) {
            wp_safe_redirect(admin_url('upload.php'));
            exit;
        }
        $result = $this->plugin->library()->pull_batch(1, false, $id, false, 'rename');
        $ok = $result['fail'] === 0 && $result['ok'] > 0;
        $this->redirect_media($id, 'wpsss_pull', $ok);
    }

    public function handle_rewrite(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_rewrite');
        $apply = !empty($_POST['wpsss_rewrite_apply']);
        if ($apply && empty($_POST['wpsss_rewrite_confirm'])) {
            $this->redirect_tools('error', '写进文章须勾确认。', 'move');
        }
        $from = isset($_POST['wpsss_rewrite_from']) ? esc_url_raw(wp_unslash((string) $_POST['wpsss_rewrite_from'])) : '';
        $to = isset($_POST['wpsss_rewrite_to']) ? esc_url_raw(wp_unslash((string) $_POST['wpsss_rewrite_to'])) : '';
        if ($from === '') {
            $this->redirect_tools('error', '请填写要替换的来源地址。', 'move');
        }
        $result = $this->plugin->library()->rewrite_posts($apply, $from, $to);
        set_transient($this->rewrite_transient_key(), [
            'from' => $from,
            'to' => $to,
            'apply' => $apply,
            'result' => $result,
        ], 10 * MINUTE_IN_SECONDS);
        $verb = $apply ? '已改写' : '预览';
        $this->redirect_tools(
            'ok',
            sprintf(
                '%s：扫 %d 篇命中 %d，字段扫 %d 命中 %d。%s',
                $verb,
                $result['scanned'],
                $result['changed'],
                $result['meta_scanned'],
                $result['meta_changed'],
                $apply ? ' 会改正文、摘要和 post meta（含序列化）。不改 options / theme_mods / 磁盘 CSS。请清页面缓存和 CDN。' : ''
            ),
            'move'
        );
    }

    public function handle_scan(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_scan');
        $this->store_ops(['scan' => (new Wpsss_Ops($this->plugin))->scan(40)]);
        $this->redirect_tools('ok', '已扫描最近一批附件。', 'check');
    }

    public function handle_diagnose(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_diagnose');
        $input = isset($_POST['wpsss_diagnose']) ? sanitize_text_field(wp_unslash((string) $_POST['wpsss_diagnose'])) : '';
        $this->store_ops(['diagnose' => (new Wpsss_Ops($this->plugin))->diagnose($input)]);
        $this->redirect_tools('ok', '已诊断该地址。', 'check');
    }

    public function handle_headcheck(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_headcheck');
        if (!$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
            $this->redirect_tools('empty', '先保存桶名和密钥再抽检。', 'check');
        }
        $this->store_ops(['head' => (new Wpsss_Ops($this->plugin))->head_check(10)]);
        $this->redirect_tools('ok', '已抽检远端对象。', 'check');
    }

    public function handle_clear_errors(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_clear_errors');
        if (empty($_POST['wpsss_clear_confirm'])) {
            $this->redirect_tools('error', '清除失败标记须勾确认。', 'check');
        }
        $result = (new Wpsss_Ops($this->plugin))->clear_errors(50);
        $this->redirect_tools('ok', sprintf('已清除 %d 条失败标记。', $result['ok']), 'check');
    }

    public function handle_release(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_release');
        if (empty($_POST['wpsss_release_confirm'])) {
            $this->redirect_tools('error', '解除标记须勾确认。', 'move');
        }
        $result = (new Wpsss_Ops($this->plugin))->release_flags(20, true);
        if ($result['errors'] !== []) {
            $this->redirect_tools('error', $result['errors'][0], 'move');
        }
        $this->redirect_tools('ok', sprintf('已解除 %d 个进桶标记，桶内对象未删除。前台可能改回本地，请清页面缓存。', $result['ok']), 'move');
    }

    public function handle_export(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_export');
        $payload = wp_json_encode(Wpsss_Options::export_public(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($payload) || $payload === '') {
            $this->redirect_tools('error', '导出失败。', 'move');
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="wpsss-settings.json"');
        echo $payload;
        exit;
    }

    public function handle_inspect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('没有权限。', 'wpsss')], 403);
        }
        check_ajax_referer('wpsss_inspect', 'nonce');

        $saved = Wpsss_Options::get();
        $driver_id = isset($_POST['driver']) ? sanitize_key((string) wp_unslash($_POST['driver'])) : (string) $saved['driver'];
        $driver = $this->plugin->driver($driver_id);
        if ($driver === null) {
            wp_send_json_error(['message' => __('未知厂商。', 'wpsss')]);
        }

        $settings = $saved;
        $settings['driver'] = $driver_id;
        $text_keys = ['bucket', 'region', 'access_key_id', 'access_key_secret', 'app_id', 'endpoint', 's3_preset', 'force_path_style', 'use_internal', 'custom_domain'];
        foreach ($text_keys as $key) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $value = sanitize_text_field((string) wp_unslash($_POST[$key]));
            if ($value !== '') {
                $settings[$key] = $value;
            }
        }
        if (($settings['access_key_id'] ?? '') === '') {
            $settings['access_key_id'] = $saved['access_key_id'];
        }
        if (($settings['access_key_secret'] ?? '') === '') {
            $settings['access_key_secret'] = $saved['access_key_secret'];
        }
        if (($settings['bucket'] ?? '') === '' || ($settings['access_key_id'] ?? '') === '' || ($settings['access_key_secret'] ?? '') === '') {
            wp_send_json_error(['message' => __('请先填写桶名与密钥；已保存的密钥可留空。', 'wpsss')]);
        }

        $result = $driver->inspect_bucket($settings);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success($result);
    }

    public function handle_quick(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_quick');

        $opts = Wpsss_Options::get();
        if (isset($_POST['keep_local']) && (string) wp_unslash($_POST['keep_local']) !== '1') {
            wp_safe_redirect($this->url('settings', [
                'mode' => 'simple',
                'wpsss_level' => 'error',
                'wpsss_notice' => '关掉本地备份须人批。',
            ]));
            exit;
        }
        if (
            isset($_POST['origin_protect'])
            && (string) wp_unslash($_POST['origin_protect']) === '1'
            && trim((string) ($opts['image_style'] ?? '')) === ''
        ) {
            wp_safe_redirect($this->url('settings', [
                'mode' => 'simple',
                'wpsss_level' => 'error',
                'wpsss_notice' => '须先在高级设置填写图片样式。',
            ]));
            exit;
        }
        foreach (['keep_local', 'upload_thumbs', 'origin_protect'] as $key) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $opts[$key] = (string) wp_unslash($_POST[$key]) === '1' ? '1' : '0';
        }
        if (isset($_POST['site_mode'])) {
            $opts['site_mode'] = Wpsss_Options::sanitize_site_mode(wp_unslash($_POST['site_mode']));
        }
        if (isset($_POST['url_access'])) {
            $opts['url_access'] = Wpsss_Options::sanitize_url_access(wp_unslash($_POST['url_access']));
        }
        update_option(Wpsss_Options::OPTION_KEY, $opts);
        wp_safe_redirect($this->url('settings', ['mode' => 'simple', 'saved' => '1']));
        exit;
    }

    public function handle_setup(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_setup');

        $action = isset($_POST['wpsss_setup_action']) ? sanitize_key((string) wp_unslash($_POST['wpsss_setup_action'])) : '';
        $opts = Wpsss_Options::get();

        if ($action === 'step1') {
            $opts['site_mode'] = Wpsss_Options::sanitize_site_mode($_POST['site_mode'] ?? '');
            update_option(Wpsss_Options::OPTION_KEY, Wpsss_Options::sanitize($opts));
            $this->redirect('setup', 'ok', '', ['step' => '2']);
        }

        if ($action === 'step2') {
            $incoming = [
                'driver' => isset($_POST['driver']) ? wp_unslash($_POST['driver']) : $opts['driver'],
                'bucket' => isset($_POST['bucket']) ? wp_unslash($_POST['bucket']) : '',
                'region' => isset($_POST['region']) ? wp_unslash($_POST['region']) : '',
                'access_key_id' => isset($_POST['access_key_id']) ? wp_unslash($_POST['access_key_id']) : '',
                'access_key_secret' => isset($_POST['access_key_secret']) ? wp_unslash($_POST['access_key_secret']) : '',
                'app_id' => isset($_POST['app_id']) ? wp_unslash($_POST['app_id']) : '',
                'url_access' => isset($_POST['url_access']) ? wp_unslash($_POST['url_access']) : ($opts['url_access'] ?? 'public'),
                'site_mode' => $opts['site_mode'],
            ];
            if (isset($_POST['s3_preset'])) {
                $incoming['s3_preset'] = wp_unslash($_POST['s3_preset']);
            }
            $saved = Wpsss_Options::sanitize(array_merge($opts, $incoming));
            if ($saved['bucket'] === '' || $saved['access_key_id'] === '' || $saved['access_key_secret'] === '') {
                $this->redirect('setup', 'error', __('请填写桶名与访问密钥。', 'wpsss'), ['step' => '2']);
            }
            update_option(Wpsss_Options::OPTION_KEY, $saved);
            $this->redirect('setup', 'ok', __('已保存桶与密钥', 'wpsss'), ['step' => '3']);
        }

        if ($action === 'finish') {
            $opts['setup_completed_at'] = gmdate('c');
            update_option(Wpsss_Options::OPTION_KEY, Wpsss_Options::sanitize($opts));
            $this->redirect('overview', 'ok', __('设置向导已完成', 'wpsss'));
        }

        wp_safe_redirect($this->url('setup'));
        exit;
    }

    /**
     * @param array<string, string> $extra
     */
    public function url(string $tab, array $extra = []): string
    {
        $args = array_merge(['page' => 'wpsss', 'tab' => $tab], $extra);
        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @param array<string, mixed> $view
     */
    private function include_template(string $name, array $view): void
    {
        $path = WPSSS_DIR . 'templates/admin/' . $name . '.php';
        if (!is_readable($path)) {
            return;
        }
        include $path;
    }

    private function is_screen(): bool
    {
        return isset($_GET['page']) && sanitize_key((string) $_GET['page']) === 'wpsss';
    }

    private function tab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview';
        return in_array($tab, ['overview', 'settings', 'tools', 'help', 'setup'], true) ? $tab : 'overview';
    }

    private function setup_step(): int
    {
        $step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
        if ($step < 1 || $step > 4) {
            return 1;
        }
        return $step;
    }

    private function mode(): string
    {
        $mode = isset($_GET['mode']) ? sanitize_key((string) $_GET['mode']) : 'simple';
        return $mode === 'advanced' ? 'advanced' : 'simple';
    }

    private function sanitize_tools_panel(string $panel): string
    {
        return in_array($panel, ['daily', 'check', 'move'], true) ? $panel : 'daily';
    }

    private function tools_panel(): string
    {
        $panel = isset($_GET['panel']) ? sanitize_key((string) $_GET['panel']) : 'daily';
        return $this->sanitize_tools_panel($panel);
    }

    private function tools_panel_from_request(string $fallback): string
    {
        if (isset($_POST['wpsss_tools_panel'])) {
            return $this->sanitize_tools_panel(sanitize_key((string) wp_unslash($_POST['wpsss_tools_panel'])));
        }
        return $this->sanitize_tools_panel($fallback);
    }

    private function redirect_tools(string $level, string $notice, string $fallback = 'daily'): void
    {
        $this->redirect('tools', $level, $notice, ['panel' => $this->tools_panel_from_request($fallback)]);
    }

    private function rewrite_transient_key(): string
    {
        return 'wpsss_rewrite_' . get_current_user_id();
    }

    private function pull_transient_key(): string
    {
        return 'wpsss_pull_' . get_current_user_id();
    }

    private function pull_cursor_key(): string
    {
        return 'wpsss_pull_cursor_' . get_current_user_id();
    }

    private function offload_cursor_key(): string
    {
        return 'wpsss_offload_cursor_' . get_current_user_id();
    }

    private function ops_transient_key(): string
    {
        return 'wpsss_ops_' . get_current_user_id();
    }

    /**
     * @param array<string, mixed> $chunk
     */
    private function store_ops(array $chunk): void
    {
        $cur = get_transient($this->ops_transient_key());
        $cur = is_array($cur) ? $cur : [];
        set_transient($this->ops_transient_key(), array_merge($cur, $chunk), 10 * MINUTE_IN_SECONDS);
    }

    /**
     * @param array<string, string> $extra
     */
    private function redirect(string $tab, string $level, string $notice, array $extra = []): void
    {
        wp_safe_redirect($this->url($tab, array_merge([
            'wpsss_level' => $level,
            'wpsss_notice' => $notice,
        ], $extra)));
        exit;
    }

    private function redirect_media(int $id, string $flag, bool $ok): void
    {
        $target = admin_url('upload.php');
        $ref = wp_get_referer();
        if ($id > 0 && is_string($ref) && $ref !== '') {
            $path = (string) wp_parse_url($ref, PHP_URL_PATH);
            $query = wp_parse_url($ref, PHP_URL_QUERY);
            $args = [];
            if (is_string($query) && $query !== '') {
                parse_str($query, $args);
            }
            if (str_ends_with($path, 'post.php') && isset($args['post']) && (int) $args['post'] === $id) {
                $target = admin_url('post.php?post=' . $id . '&action=edit');
            }
        }
        wp_safe_redirect(add_query_arg($flag, $ok ? 'ok' : 'fail', $target));
        exit;
    }
}
