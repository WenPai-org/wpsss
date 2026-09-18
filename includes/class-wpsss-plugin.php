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

    /** @var Wpsss_Offload|null */
    private $offload = null;

    /** @var Wpsss_Library|null */
    private $library = null;

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
        $this->register_driver(new Wpsss_Driver_Qcloud_Cos());
        $this->register_driver(new Wpsss_Driver_Qiniu_Kodo());
        $this->register_driver(new Wpsss_Driver_Huawei_Obs());
        $this->register_driver(new Wpsss_Driver_S3_Compat('volc-tos', '火山 TOS'));
        $this->register_driver(new Wpsss_Driver_S3_Compat());
        add_action('admin_init', [$this, 'register_settings']);
        $this->offload = new Wpsss_Offload($this);
        $this->library = new Wpsss_Library($this, $this->offload);
        (new Wpsss_Admin($this))->register();
        $this->offload->register();
        $this->library->register();
    }

    public function offload(): Wpsss_Offload
    {
        if ($this->offload === null) {
            $this->offload = new Wpsss_Offload($this);
        }
        return $this->offload;
    }

    public function library(): Wpsss_Library
    {
        if ($this->library === null) {
            $this->library = new Wpsss_Library($this, $this->offload());
        }
        return $this->library;
    }

    public function register_driver(Wpsss_Driver $driver): void
    {
        $this->drivers[$driver->id()] = $driver;
    }

    public function active_driver(): Wpsss_Driver
    {
        $id = Wpsss_Options::get()['driver'];
        return $this->drivers[$id] ?? $this->drivers[Wpsss_Options::DRIVER_ALIYUN];
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
