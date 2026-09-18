<?php

if (!defined('ABSPATH')) {
    exit;
}

interface Wpsss_Driver
{
    public function id(): string;

    public function label(): string;

    /**
     * @param array<string, string> $settings
     */
    public function is_configured(array $settings): bool;

    /**
     * @param array<string, string> $settings
     * @return true|WP_Error
     */
    public function put_object(array $settings, string $object, string $absolute_path, string $mime);

    /**
     * @param array<string, string> $settings
     * @param string[] $objects
     * @return true|WP_Error
     */
    public function delete_objects(array $settings, array $objects);

    /**
     * @param array<string, string> $settings
     * @return true|WP_Error
     */
    public function head_object(array $settings, string $object);

    /**
     * @param array<string, string> $settings
     * @return true|WP_Error
     */
    public function probe(array $settings);
}
