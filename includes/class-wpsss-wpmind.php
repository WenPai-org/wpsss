<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * 可选文派心思（WPMind）联动。
 *
 * WPMind 未安装或未配置时静默退回原有审核逻辑，零硬依赖。
 */
final class Wpsss_Wpmind
{
    public const AUDIT_CONTEXT = 'wpsss_audit';
    public const BLOCK_THRESHOLD = 0.7;

    /** @var bool */
    private static $registered = false;

    public function register(): void
    {
        self::$registered = true;
        if (!function_exists('add_filter')) {
            return;
        }
        add_filter('wpsss_ci_audit_text', [self::class, 'filter_audit_text'], 10, 3);
        add_filter('wpmind_ecosystem_integrations', [self::class, 'filter_ecosystem_integrations'], 10, 2);
    }

    /**
     * 检查 WPMind 文本审核能力当前是否可用。
     */
    public static function is_available(): bool
    {
        if (function_exists('wpmind_is_available') && function_exists('wpmind_moderate')) {
            return (bool) wpmind_is_available();
        }
        if (function_exists('apply_filters')) {
            return (bool) apply_filters('wpmind_is_available', false);
        }
        return false;
    }

    /**
     * 检查 wpsss_ci_audit_text 审核钩子是否已接入。
     */
    public static function is_wired(): bool
    {
        if (function_exists('has_filter')) {
            return has_filter('wpsss_ci_audit_text', [self::class, 'filter_audit_text']) !== false
                || has_filter('wpsss_ci_audit_text') !== false;
        }
        return self::$registered;
    }

    /**
     * 优先委托 WPMind wpmind_moderate() 审核文本，失败或不可用时返回原值交由默认链路回退。
     *
     * @param mixed $verdict
     * @param mixed $text
     * @param mixed $opts
     * @return mixed
     */
    public static function filter_audit_text($verdict, $text, $opts = [])
    {
        if ($verdict === 'pass' || $verdict === 'review' || $verdict === 'block') {
            return $verdict;
        }
        if (!is_string($text)) {
            return $verdict;
        }
        $text = trim($text);
        if ($text === '') {
            return $verdict;
        }

        $result = self::call_moderate($text, is_array($opts) ? $opts : []);
        if ($result === null || self::is_error_result($result) || !is_array($result)) {
            return $verdict;
        }

        $mapped = self::map_verdict($result);
        return $mapped !== null ? $mapped : $verdict;
    }

    /**
     * 将 wpmind_moderate() 返回结构映射为 WPSSS 审核结论。
     *
     * @param array<string, mixed> $result
     * @return 'pass'|'review'|'block'|null
     */
    public static function map_verdict(array $result): ?string
    {
        $has_safe = array_key_exists('safe', $result);
        $has_categories = isset($result['categories']) && is_array($result['categories']);
        if (!$has_safe && !$has_categories) {
            return null;
        }

        $safe = $has_safe ? (bool) $result['safe'] : true;
        $categories = $has_categories ? $result['categories'] : [];

        $has_flagged = false;
        $max_flagged_score = 0.0;
        $has_flagged_score = false;
        $max_score = 0.0;
        $has_any_score = false;

        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $flagged = !empty($category['flagged']);
            $score_present = isset($category['score']) && is_numeric($category['score']);
            $score = $score_present ? (float) $category['score'] : 0.0;

            if ($score_present) {
                $has_any_score = true;
                if ($score > $max_score) {
                    $max_score = $score;
                }
            }

            if ($flagged) {
                $has_flagged = true;
                if ($score_present) {
                    $has_flagged_score = true;
                    if ($score > $max_flagged_score) {
                        $max_flagged_score = $score;
                    }
                }
            }
        }

        if (!$safe) {
            $effective_score = $has_flagged_score ? $max_flagged_score : $max_score;
            $score_known = $has_flagged_score || ($has_any_score && $max_score > 0.0);
            if ($score_known && $effective_score > 0.0 && $effective_score < self::BLOCK_THRESHOLD) {
                return 'review';
            }
            return 'block';
        }

        if ($has_flagged) {
            return ($has_flagged_score && $max_flagged_score >= self::BLOCK_THRESHOLD)
                ? 'block'
                : 'review';
        }

        return 'pass';
    }

    /**
     * 在 WPMind 生态联动列表中声明文派智储联动状态。
     *
     * @param mixed $items
     * @param mixed $by_caller
     * @return array<int, array<string, mixed>>
     */
    public static function filter_ecosystem_integrations($items, $by_caller = []): array
    {
        $list = is_array($items) ? array_values($items) : [];
        $callers = is_array($by_caller) ? $by_caller : [];

        $existing_index = null;
        $existing_entry = [];
        foreach ($list as $idx => $item) {
            if (is_array($item) && (string) ($item['slug'] ?? '') === 'wpsss') {
                $existing_index = $idx;
                $existing_entry = $item;
                break;
            }
        }

        $has_caller_stats = isset($callers['wpsss']) || isset($callers[self::AUDIT_CONTEXT]);
        $requests = $has_caller_stats
            ? (int) ($callers['wpsss']['requests'] ?? 0) + (int) ($callers[self::AUDIT_CONTEXT]['requests'] ?? 0)
            : (int) ($existing_entry['requests'] ?? 0);
        $tokens = $has_caller_stats
            ? (int) ($callers['wpsss']['tokens'] ?? 0) + (int) ($callers[self::AUDIT_CONTEXT]['tokens'] ?? 0)
            : (int) ($existing_entry['tokens'] ?? 0);

        $active = self::is_wired() || self::is_available() || $requests > 0;
        $version = defined('WPSSS_VERSION')
            ? (string) constant('WPSSS_VERSION')
            : (string) ($existing_entry['version'] ?? '');
        $settings_url = self::settings_url();
        if ($settings_url === '' && isset($existing_entry['settings_url'])) {
            $settings_url = (string) $existing_entry['settings_url'];
        }

        $entry = [
            'slug' => 'wpsss',
            'name' => __('文派智储（WPSSS）', 'wpsss'),
            'desc' => __('云端媒体与内容安全审核已联动文派心思。', 'wpsss'),
            'feature' => __('内容审核', 'wpsss'),
            'icon' => 'cloud',
            'version' => $version,
            'settings_url' => $settings_url,
            'requests' => $requests,
            'tokens' => $tokens,
            'active' => $active,
        ];

        if ($existing_index !== null) {
            $list[$existing_index] = $entry;
        } else {
            $list[] = $entry;
        }

        return $list;
    }

    /**
     * 获取文派智储后台设置页链接。
     */
    public static function settings_url(): string
    {
        if (class_exists('Wenpai_Admin_Menu', false) && method_exists('Wenpai_Admin_Menu', 'url')) {
            return (string) Wenpai_Admin_Menu::url('wpsss', ['tab' => 'settings']);
        }
        if (function_exists('admin_url')) {
            if (function_exists('add_query_arg')) {
                return (string) add_query_arg(
                    ['page' => 'wpsss', 'tab' => 'settings'],
                    admin_url('options-general.php')
                );
            }
            return (string) admin_url('options-general.php?page=wpsss&tab=settings');
        }
        return '';
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    private static function call_moderate(string $text, array $opts)
    {
        unset($opts);
        $options = [
            'context' => self::AUDIT_CONTEXT,
        ];

        try {
            if (function_exists('wpmind_is_available') && function_exists('wpmind_moderate')) {
                if (!wpmind_is_available()) {
                    return null;
                }
                return wpmind_moderate($text, $options);
            }
            if (function_exists('apply_filters') && apply_filters('wpmind_is_available', false)) {
                return apply_filters('wpmind_moderate', null, $text, $options);
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('WPSSS WPMind moderate fallback: ' . $e->getMessage());
            }
            return null;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function is_error_result($value): bool
    {
        if (function_exists('is_wp_error') && is_wp_error($value)) {
            return true;
        }
        return class_exists('WP_Error', false) && $value instanceof WP_Error;
    }
}
