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
        (new Wpsss_Admin($this))->register();
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
}
