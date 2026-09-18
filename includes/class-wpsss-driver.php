<?php

if (!defined('ABSPATH')) {
    exit;
}

interface Wpsss_Driver
{
    public function id(): string;

    public function label(): string;

    /**
     * @param array<string, mixed> $settings
     */
    public function is_configured(array $settings): bool;
}
