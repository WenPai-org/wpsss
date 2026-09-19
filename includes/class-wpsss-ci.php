<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

class Wpsss_Ci
{
    /** @var callable|null */
    public static $transport = null;

    public function register(): void
    {
        add_filter('pre_comment_approved', [$this, 'filter_comment'], 20, 2);
    }

    /**
     * @param mixed $approved
     * @param array<string, mixed> $commentdata
     * @return mixed
     */
    public function filter_comment($approved, $commentdata)
    {
        $opts = class_exists('Wpsss_Options') ? Wpsss_Options::get() : [];
        if (($opts['ci_audit'] ?? '0') !== '1') {
            return $approved;
        }
        if (($opts['driver'] ?? '') !== 'qcloud-cos') {
            return $approved;
        }
        $text = trim((string) ($commentdata['comment_content'] ?? ''));
        if ($text === '') {
            return $approved;
        }
        $verdict = self::audit_text($text, $opts);
        if ($verdict === 'block') {
            return 'spam';
        }
        if ($verdict === 'review') {
            return 0;
        }
        return $approved;
    }

    /**
     * @param array<string, mixed> $opts
     * @return 'pass'|'review'|'block'
     */
    public static function audit_text(string $text, array $opts): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'pass';
        }
        if (function_exists('apply_filters')) {
            $via = apply_filters('wpsss_ci_audit_text', null, $text, $opts);
            if ($via === 'pass' || $via === 'review' || $via === 'block') {
                return $via;
            }
        }
        $http = self::request_audit($text, $opts);
        if ($http === 'pass' || $http === 'review' || $http === 'block') {
            return $http;
        }
        return 'pass';
    }

    /**
     * @param array<string, mixed> $opts
     */
    public static function request_body(string $text): string
    {
        $text = self::clip_text($text);
        return '<Request><Input><Content>' . base64_encode($text) . '</Content></Input></Request>';
    }

    /**
     * @param array<string, mixed> $opts
     */
    public static function request_url(array $opts): string
    {
        $bucket = (string) ($opts['bucket'] ?? '');
        $region = (string) ($opts['region'] ?? '');
        $app = (string) ($opts['app_id'] ?? '');
        if ($bucket === '' || $region === '') {
            return '';
        }
        if ($app !== '' && !preg_match('/-\d+$/', $bucket)) {
            $bucket .= '-' . $app;
        }
        return 'https://' . $bucket . '.ci.' . $region . '.myqcloud.com/text/auditing';
    }

    /**
     * @return 'pass'|'review'|'block'|'fail'
     */
    public static function parse_verdict(string $xml): string
    {
        if ($xml === '') {
            return 'fail';
        }
        if (preg_match('/<State>([^<]+)<\/State>/', $xml, $state) && strcasecmp(trim($state[1]), 'Failed') === 0) {
            return 'fail';
        }
        if (!preg_match('/<Result>(\d+)<\/Result>/', $xml, $m)) {
            return 'fail';
        }
        $result = (int) $m[1];
        if ($result === 1) {
            return 'block';
        }
        if ($result === 2) {
            return 'review';
        }
        return 'pass';
    }

    /**
     * @param array<string, mixed> $opts
     * @return 'pass'|'review'|'block'|'fail'
     */
    public static function request_audit(string $text, array $opts): string
    {
        $url = self::request_url($opts);
        $ak = (string) ($opts['access_key_id'] ?? '');
        $sk = (string) ($opts['access_key_secret'] ?? '');
        if ($url === '' || $ak === '' || $sk === '') {
            return 'fail';
        }
        $body = self::request_body($text);
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $now = time();
        $headers = [
            'Host' => $host,
            'Content-Type' => 'application/xml',
        ];
        if (class_exists('Wpsss_Cos_Signer')) {
            $headers['Authorization'] = Wpsss_Cos_Signer::authorization(
                $ak,
                $sk,
                'POST',
                '/text/auditing',
                ['Host' => $host],
                [],
                $now,
                $now + 600
            );
        }
        $raw = self::post($url, $headers, $body);
        if ($raw === null) {
            self::log_fail('empty or failed HTTP');
            return 'fail';
        }
        $verdict = self::parse_verdict($raw);
        if ($verdict === 'fail') {
            self::log_fail('unusable response');
        }
        return $verdict;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function post(string $url, array $headers, string $body): ?string
    {
        if (is_callable(self::$transport)) {
            $out = (self::$transport)($url, $headers, $body);
            return is_string($out) ? $out : null;
        }
        if (!function_exists('wp_remote_post')) {
            return null;
        }
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 8,
            'redirection' => 0,
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 300) {
            return null;
        }
        return (string) wp_remote_retrieve_body($response);
    }

    private static function clip_text(string $text): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 10000, 'UTF-8');
        }
        return substr($text, 0, 10000);
    }

    private static function log_fail(string $why): void
    {
        if (function_exists('error_log')) {
            error_log('WPSSS CI audit failed open: ' . $why);
        }
    }
}
