<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

class Wpsss_Ci
{
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
        return $approved;
    }

    /**
     * @param array<string, string> $opts
     * @return 'pass'|'review'|'block'
     */
    public static function audit_text(string $text, array $opts): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'pass';
        }
        // 本轮不打真实万象 HTTP（无测试 CI 帐）。
        // 提供 filter 让测试/以后接线：
        if (!function_exists('apply_filters')) {
            return 'pass';
        }
        $via = apply_filters('wpsss_ci_audit_text', null, $text, $opts);
        if ($via === 'pass' || $via === 'review' || $via === 'block') {
            return $via;
        }
        return 'pass'; // 未接线 = 放行，不当假安全
    }
}
