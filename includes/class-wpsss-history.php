<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 同步历史。对照四家插件的「同步记录」，只留最近 50 条。
 */
class Wpsss_History
{
    public const OPTION_KEY = 'wpsss_history';

    /**
     * @return array<int, array<string, string>>
     */
    public static function all(): array
    {
        $rows = get_option(self::OPTION_KEY, []);
        return is_array($rows) ? $rows : [];
    }

    public static function add(string $event, string $detail): void
    {
        $rows = self::all();
        array_unshift($rows, [
            't' => gmdate('Y-m-d H:i:s'),
            'event' => sanitize_key($event),
            'detail' => sanitize_text_field($detail),
        ]);
        $rows = array_slice($rows, 0, 50);
        update_option(self::OPTION_KEY, $rows, false);
    }
}
