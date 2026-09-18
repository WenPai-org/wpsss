<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Driver_Aliyun_Oss implements Wpsss_Driver
{
    public function id(): string
    {
        return 'aliyun-oss';
    }

    public function label(): string
    {
        return '阿里云 OSS';
    }

    public function is_configured(array $settings): bool
    {
        return false;
    }
}
