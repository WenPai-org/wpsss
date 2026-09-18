<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Wpsss_Plugin
{
    private static ?Wpsss_Plugin $instance = null;

    /** @var array<string, Wpsss_Driver> */
    private array $drivers = [];

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
        add_action('admin_menu', [$this, 'register_menu']);
        add_filter('plugin_action_links_' . plugin_basename(WPSSS_FILE), [$this, 'action_links']);
    }

    public function register_driver(Wpsss_Driver $driver): void
    {
        $this->drivers[$driver->id()] = $driver;
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

    public function render_settings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>WPSSS</h1>';
        echo '<p>文派对象存储骨架。阿里云 OSS driver 已登记，尚未接上传。</p><ul>';
        foreach ($this->drivers as $driver) {
            echo '<li>' . esc_html($driver->label()) . ' <code>' . esc_html($driver->id()) . '</code></li>';
        }
        echo '</ul></div>';
    }
}
