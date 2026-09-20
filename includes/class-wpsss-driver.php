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
     * @param string $acl 'public-read'（默认）或 'private'（付费下载等不公开直读）
     * @return true|WP_Error
     */
    public function put_object(array $settings, string $object, string $absolute_path, string $mime, string $acl = 'public-read');

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
     * @return string|WP_Error
     */
    public function get_object(array $settings, string $object);

    /**
     * 同厂商服务端复制（换桶/换前缀）。跨厂商由上层用「拉回临时 + 上传」兜底。
     *
     * @param array<string, string> $to_settings
     * @param array<string, string> $from_settings
     * @return true|WP_Error
     */
    public function copy_object(array $to_settings, string $to_object, array $from_settings, string $from_object);

    /**
     * 分段拉回的一个区间请求（Range GET），end 为闭区间末字节。
     *
     * @param array<string, string> $settings
     * @return array{code: int, body: string, headers: array<string, string>}|WP_Error
     */
    public function get_object_range(array $settings, string $object, int $start, int $end);

    /**
     * @param array<string, string> $settings
     * @return true|WP_Error
     */
    public function probe(array $settings);

    /**
     * 读这一只桶的区域和 ACL。不列全部桶。
     *
     * @param array<string, string> $settings
     * @return array{region: string, url_access: string, app_id: string, custom_domain: string, notes: string[]}|WP_Error
     */
    public function inspect_bucket(array $settings);
}
