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
        add_action('admin_post_wpsss_quick', [$this, 'handle_quick']);
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
        }
        return $classes;
    }

    public function enqueue(string $_hook): void
    {
        unset($_hook);
        if (!$this->is_screen()) {
            return;
        }
        $base = plugin_dir_url(WPSSS_FILE);
        wp_enqueue_style('wpsss-e', $base . 'assets/css/e.css', [], WPSSS_VERSION);
        wp_enqueue_style('wpsss-admin', $base . 'assets/css/admin.css', ['wpsss-e'], WPSSS_VERSION);
        wp_enqueue_script('wpsss-admin', $base . 'assets/js/admin.js', [], WPSSS_VERSION, true);
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
            $notice = $notice !== '' ? $notice : '已保存 ✓';
            $level = $level !== '' ? $level : 'ok';
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
            'url' => [$this, 'url'],
        ];

        echo '<div class="wpcy-app">';
        $this->include_template('shell-head', $view);
        echo '<main class="wpcy-main wpcy-wrap">';
        $this->include_template('tab-' . $tab, $view);
        echo '<footer class="foot"><span>文派对象存储 ' . esc_html(WPSSS_VERSION) . '</span></footer>';
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
        if (is_wp_error($result)) {
            $this->redirect('tools', 'error', $result->get_error_message());
        }
        $this->redirect('tools', 'ok', '已写入并删除探测对象。');
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
        foreach (['keep_local', 'upload_thumbs'] as $key) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $opts[$key] = (string) wp_unslash($_POST[$key]) === '1' ? '1' : '0';
        }
        update_option(Wpsss_Options::OPTION_KEY, $opts);
        wp_safe_redirect($this->url('settings', ['mode' => 'simple', 'saved' => '1']));
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
        return in_array($tab, ['overview', 'settings', 'tools'], true) ? $tab : 'overview';
    }

    private function mode(): string
    {
        $mode = isset($_GET['mode']) ? sanitize_key((string) $_GET['mode']) : 'simple';
        return $mode === 'advanced' ? 'advanced' : 'simple';
    }

    private function redirect(string $tab, string $level, string $notice): void
    {
        wp_safe_redirect($this->url($tab, ['wpsss_level' => $level, 'wpsss_notice' => $notice]));
        exit;
    }
}
