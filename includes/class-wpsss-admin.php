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
        if ($this->is_screen()) {
            wp_enqueue_script('wpsss-admin', $base . 'assets/js/admin.js', [], (string) filemtime(WPSSS_DIR . 'assets/js/admin.js'), true);
            wp_localize_script('wpsss-admin', 'wpsssAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'inspectNonce' => wp_create_nonce('wpsss_inspect'),
            ]);
        }
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
            $this->redirect('tools', 'empty', '先保存桶名和密钥再测。');
        }

        $result = $driver->probe($settings);
        $return_tab = isset($_POST['wpsss_return_tab']) ? sanitize_key((string) wp_unslash($_POST['wpsss_return_tab'])) : 'tools';
        if (!in_array($return_tab, ['tools', 'setup'], true)) {
            $return_tab = 'tools';
        }
        $extra = $return_tab === 'setup' ? ['step' => '4'] : [];
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
            $this->redirect('tools', 'empty', '先保存桶名和密钥再补传。');
        }
        $result = $this->plugin->library()->offload_batch(20, false, 0);
        $msg = sprintf('补传完成：成功 %d，失败 %d，跳过 %d。', $result['ok'], $result['fail'], $result['skip']);
        if ($result['errors'] !== []) {
            $msg .= ' ' . $result['errors'][0];
        }
        $this->redirect('tools', $result['fail'] > 0 ? 'error' : 'ok', $msg);
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
        if (empty($_POST['wpsss_pull_confirm'])) {
            $this->redirect('tools', 'error', '拉回须勾确认。');
        }
        if (!$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
            $this->redirect('tools', 'empty', '先保存桶名和密钥再拉回。');
        }
        $delete = !empty($_POST['wpsss_pull_delete']);
        $result = $this->plugin->library()->pull_batch(20, false, 0, $delete);
        $msg = sprintf('拉回完成：成功 %d，失败 %d，跳过 %d。', $result['ok'], $result['fail'], $result['skip']);
        if ($result['errors'] !== []) {
            $msg .= ' ' . $result['errors'][0];
        }
        $this->redirect('tools', $result['fail'] > 0 ? 'error' : 'ok', $msg);
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
        $result = $this->plugin->library()->pull_batch(1, false, $id, false);
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
            $this->redirect('tools', 'error', '写进文章须勾确认。');
        }
        $from = isset($_POST['wpsss_rewrite_from']) ? esc_url_raw(wp_unslash((string) $_POST['wpsss_rewrite_from'])) : '';
        $to = isset($_POST['wpsss_rewrite_to']) ? esc_url_raw(wp_unslash((string) $_POST['wpsss_rewrite_to'])) : '';
        if ($from === '') {
            $this->redirect('tools', 'error', '请填写要替换的来源地址。');
        }
        $result = $this->plugin->library()->rewrite_posts($apply, $from, $to);
        set_transient($this->rewrite_transient_key(), [
            'from' => $from,
            'to' => $to,
            'apply' => $apply,
            'result' => $result,
        ], 10 * MINUTE_IN_SECONDS);
        $verb = $apply ? '已改写' : '预览';
        $this->redirect(
            'tools',
            'ok',
            sprintf(
                '%s：扫 %d 篇命中 %d，字段扫 %d 命中 %d。',
                $verb,
                $result['scanned'],
                $result['changed'],
                $result['meta_scanned'],
                $result['meta_changed']
            )
        );
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

    private function rewrite_transient_key(): string
    {
        return 'wpsss_rewrite_' . get_current_user_id();
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
