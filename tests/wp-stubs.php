<?php
/**
 * 无 WP 的最小桩，只给仓内单测用。
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['wpsss_options_store'] = [];

function get_option($key, $default = false)
{
    return array_key_exists($key, $GLOBALS['wpsss_options_store'])
        ? $GLOBALS['wpsss_options_store'][$key]
        : $default;
}

function update_option($key, $value)
{
    $GLOBALS['wpsss_options_store'][$key] = $value;
    return true;
}

function delete_option($key)
{
    unset($GLOBALS['wpsss_options_store'][$key]);
    return true;
}

function sanitize_text_field($value)
{
    return trim((string) $value);
}

function esc_url_raw($value)
{
    return trim((string) $value);
}

function untrailingslashit($value)
{
    return rtrim((string) $value, '/');
}

function sanitize_key($value)
{
    $value = strtolower((string) $value);
    return preg_replace('/[^a-z0-9_\-]/', '', $value) ?: '';
}

function wp_parse_url($url, $component = -1)
{
    return parse_url($url, $component);
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return $text;
    }
}

