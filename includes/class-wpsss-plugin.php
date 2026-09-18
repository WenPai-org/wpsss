<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Wpsss_Plugin
{
    /** @var self|null */
    private static $instance = null;

    /** @var array<string, Wpsss_Driver> */
    private $drivers = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        $this->register_driver(new Wpsss_Driver_Aliyun_Oss());
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_wpsss_probe', [$this, 'handle_probe']);
        add_filter('plugin_action_links_' . plugin_basename(WPSSS_FILE), [$this, 'action_links']);
        (new Wpsss_Offload($this))->register();
    }

    public function register_driver(Wpsss_Driver $driver): void
    {
        $this->drivers[$driver->id()] = $driver;
    }

    public function active_driver(): Wpsss_Driver
    {
        $id = Wpsss_Options::get()['driver'];
        return $this->drivers[$id] ?? $this->drivers['aliyun-oss'];
    }

    /**
     * @return array<string, Wpsss_Driver>
     */
    public function drivers(): array
    {
        return $this->drivers;
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function action_links(array $links): array
    {
        $url = admin_url('options-general.php?page=wpsss');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('设置', 'wpsss') . '</a>');
        return $links;
    }

    public function register_settings(): void
    {
        register_setting(
            'wpsss',
            Wpsss_Options::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [Wpsss_Options::class, 'sanitize'],
                'default' => Wpsss_Options::defaults(),
            ]
        );
    }

    public function register_menu(): void
    {
        add_options_page(
            'WPSSS',
            'WPSSS',
            'manage_options',
            'wpsss',
            [$this, 'render_settings']
        );
    }

    public function handle_probe(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('权限不足。', 'wpsss'));
        }
        check_admin_referer('wpsss_probe');

        $settings = Wpsss_Options::get();
        $driver = $this->active_driver();
        if (!$driver->is_configured($settings)) {
            $this->redirect_settings('empty', '先保存 Bucket 和密钥再测。');
        }

        $result = $driver->probe($settings);
        if (is_wp_error($result)) {
            $this->redirect_settings('error', $result->get_error_message());
        }
        $this->redirect_settings('ok', '连通：已写入并删除探测对象。');
    }

    public function render_settings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $opts = Wpsss_Options::get();
        $notice = isset($_GET['wpsss_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['wpsss_notice'])) : '';
        $level = isset($_GET['wpsss_level']) ? sanitize_key((string) $_GET['wpsss_level']) : '';
        $secret_set = $opts['access_key_secret'] !== '';

        echo '<div class="wrap">';
        echo '<h1>WPSSS</h1>';
        echo '<p>文派对象存储。先接阿里云 OSS；自定义域名给 CyberCDN。HTML 仍在源站。</p>';

        if ($notice !== '') {
            $class = $level === 'ok' ? 'notice-success' : ($level === 'error' ? 'notice-error' : 'notice-warning');
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }

        echo '<form method="post" action="options.php" class="card" style="max-width:40rem;padding:1.25rem 1.5rem;border:1px solid #dcdcde;box-shadow:none;">';
        settings_fields('wpsss');

        echo '<table class="form-table" role="presentation">';
        $this->row_text('Bucket', 'bucket', $opts['bucket'], '只填桶名，不要带域名。');
        echo '<tr><th scope="row"><label for="wpsss-region">区域</label></th><td><select name="' . esc_attr(Wpsss_Options::OPTION_KEY) . '[region]" id="wpsss-region">';
        foreach (Wpsss_Options::regions() as $id => $label) {
            echo '<option value="' . esc_attr($id) . '"' . selected($opts['region'], $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        $this->row_text('AccessKeyId', 'access_key_id', $opts['access_key_id'], '');
        echo '<tr><th scope="row"><label for="wpsss-sk">AccessKeySecret</label></th><td>';
        echo '<input name="' . esc_attr(Wpsss_Options::OPTION_KEY) . '[access_key_secret]" id="wpsss-sk" type="password" class="regular-text" value="" autocomplete="new-password" />';
        if ($secret_set) {
            echo '<p class="description">已保存，留空不改。</p>';
        }
        echo '</td></tr>';
        $this->row_text('自定义域名', 'custom_domain', $opts['custom_domain'], '例如 https://static.example.com 。空则用桶默认域名。会改 WordPress 的 upload_url_path（仅当该项原本为空或由本插件写入）。');
        $this->row_check('上传后保留本地文件', 'keep_local', $opts['keep_local'] === '1', '建议勾选。');
        $this->row_check('同时上传缩略图', 'upload_thumbs', $opts['upload_thumbs'] === '1', '');
        $this->row_check('走 OSS 内网', 'use_internal', $opts['use_internal'] === '1', '源站和桶在同一阿里云区域时再勾。');
        echo '</table>';
        submit_button('保存设置');
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:1rem;">';
        echo '<input type="hidden" name="action" value="wpsss_probe" />';
        wp_nonce_field('wpsss_probe');
        submit_button('测试连通', 'secondary', 'submit', false);
        echo '</form>';
        echo '</div>';
    }

    private function row_text(string $label, string $key, string $value, string $help): void
    {
        $id = 'wpsss-' . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input name="' . esc_attr(Wpsss_Options::OPTION_KEY) . '[' . esc_attr($key) . ']" id="' . esc_attr($id) . '" type="text" class="regular-text" value="' . esc_attr($value) . '" />';
        if ($help !== '') {
            echo '<p class="description">' . esc_html($help) . '</p>';
        }
        echo '</td></tr>';
    }

    private function row_check(string $label, string $key, bool $checked, string $help): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><label>';
        echo '<input type="hidden" name="' . esc_attr(Wpsss_Options::OPTION_KEY) . '[' . esc_attr($key) . ']" value="0" />';
        echo '<input name="' . esc_attr(Wpsss_Options::OPTION_KEY) . '[' . esc_attr($key) . ']" type="checkbox" value="1"' . checked($checked, true, false) . ' /> ';
        echo esc_html($label);
        echo '</label>';
        if ($help !== '') {
            echo '<p class="description">' . esc_html($help) . '</p>';
        }
        echo '</td></tr>';
    }

    private function redirect_settings(string $level, string $notice): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'wpsss',
                    'wpsss_level' => $level,
                    'wpsss_notice' => $notice,
                ],
                admin_url('options-general.php')
            )
        );
        exit;
    }
}
