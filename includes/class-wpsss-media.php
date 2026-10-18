<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 媒体库列、批量、筛选、附件侧栏。
 */
final class Wpsss_Media
{
    /** @var Wpsss_Plugin */
    private $plugin;

    /** @var Wpsss_Offload */
    private $offload;

    public function __construct(Wpsss_Plugin $plugin, Wpsss_Offload $offload)
    {
        $this->plugin = $plugin;
        $this->offload = $offload;
    }

    public function register(): void
    {
        add_filter('manage_media_columns', [$this, 'columns']);
        add_action('manage_media_custom_column', [$this, 'column'], 10, 2);
        add_filter('media_row_actions', [$this, 'row_actions'], 10, 2);
        add_filter('bulk_actions-upload', [$this, 'bulk_actions']);
        add_filter('handle_bulk_actions-upload', [$this, 'handle_bulk'], 10, 3);
        add_action('restrict_manage_posts', [$this, 'filter_dropdown']);
        add_action('pre_get_posts', [$this, 'filter_query']);
        add_action('add_meta_boxes_attachment', [$this, 'add_metabox']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_filter('wp_prepare_attachment_for_js', [$this, 'prepare_attachment_for_js'], 20, 3);
    }

    /**
     * @param mixed $response
     * @param mixed $attachment
     * @param mixed $meta
     * @return mixed
     */
    public function prepare_attachment_for_js($response, $attachment, $meta)
    {
        unset($meta);
        if (!is_array($response)) {
            return $response;
        }
        $id = is_object($attachment) ? (int) $attachment->ID : (int) $attachment;
        $response['wpsssOffloaded'] = Wpsss_Library::is_offloaded($id);
        if (!$response['wpsssOffloaded']) {
            return $response;
        }
        $response['url'] = $this->offload->rewrite_url((string) ($response['url'] ?? ''), $id);
        if (isset($response['sizes']) && is_array($response['sizes'])) {
            foreach ($response['sizes'] as $size => $item) {
                if (!is_array($item) || empty($item['url'])) {
                    continue;
                }
                $response['sizes'][$size]['url'] = $this->offload->rewrite_url((string) $item['url'], $id);
            }
        }
        if (isset($response['icon']) && is_string($response['icon']) && $response['icon'] !== ''
            && method_exists('Wpsss_Url', 'rewrite_attachment_js')
        ) {
            $icon = Wpsss_Url::rewrite_attachment_js(
                ['url' => $response['url'], 'icon' => $response['icon']],
                $response['url']
            );
            $response['icon'] = $icon['icon'];
        }
        if (Wpsss_Options::get()['keep_local'] !== '1' && empty($response['filesizeInBytes'])) {
            $stored = (int) get_post_meta($id, '_wpsss_filesize', true);
            if ($stored > 0) {
                $response['filesizeInBytes'] = $stored;
            }
        }
        return $response;
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        $columns['wpsss'] = __('对象存储', 'wpsss');
        return $columns;
    }

    public function column(string $column, int $post_id): void
    {
        if ($column !== 'wpsss') {
            return;
        }
        if (!$this->plugin->active_driver()->is_configured(Wpsss_Options::get())) {
            echo esc_html__('未连接', 'wpsss');
            return;
        }
        $err = Wpsss_Library::last_error($post_id);
        if ($err !== '') {
            echo '<span class="pill warn">' . esc_html__('失败', 'wpsss') . '</span>';
            echo '<div class="p">' . esc_html($err) . '</div>';
            return;
        }
        if (Wpsss_Library::is_offloaded($post_id)) {
            echo '<span class="pill ok" data-wpsss-offloaded="1">' . esc_html__('已进桶', 'wpsss') . '</span>';
            return;
        }
        echo '<span class="pill">' . esc_html__('未进桶', 'wpsss') . '</span>';
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function row_actions(array $actions, WP_Post $post): array
    {
        if (!current_user_can('upload_files') || $post->post_type !== 'attachment') {
            return $actions;
        }
        if (Wpsss_Library::is_offloaded((int) $post->ID)) {
            if (current_user_can('manage_options')) {
                $pull = add_query_arg(
                    [
                        'page' => 'wpsss',
                        'tab' => 'tools',
                        'panel' => 'move',
                        'pull_id' => (int) $post->ID,
                    ],
                    admin_url('options-general.php')
                );
            } else {
                $pull = get_edit_post_link($post->ID, 'raw');
                if (!is_string($pull) || $pull === '') {
                    $pull = admin_url('post.php?post=' . (int) $post->ID . '&action=edit');
                }
                $pull .= '#wpsss_attachment';
            }
            $actions['wpsss_pull'] = '<a href="' . esc_url($pull) . '">' . esc_html__('拉回本地', 'wpsss') . '</a>';
            return $actions;
        }
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=wpsss_offload_one&id=' . (int) $post->ID),
            'wpsss_offload_one_' . (int) $post->ID
        );
        $actions['wpsss'] = '<a href="' . esc_url($url) . '">' . esc_html__('补传到桶', 'wpsss') . '</a>';
        return $actions;
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function bulk_actions(array $actions): array
    {
        $actions['wpsss_offload'] = __('补传到桶', 'wpsss');
        $actions['wpsss_pull'] = __('拉回本地', 'wpsss');
        return $actions;
    }

    /**
     * @param string[] $post_ids
     */
    public function handle_bulk(string $redirect, string $doaction, array $post_ids): string
    {
        if ($doaction === 'wpsss_pull') {
            $ok = 0;
            $fail = 0;
            foreach ($post_ids as $id) {
                $result = $this->offload->pull_attachment((int) $id, false, 'rename');
                if (is_wp_error($result)) {
                    $fail++;
                    continue;
                }
                $ok++;
            }
            return add_query_arg(['wpsss_bulk_ok' => $ok, 'wpsss_bulk_fail' => $fail], $redirect);
        }
        if ($doaction !== 'wpsss_offload') {
            return $redirect;
        }
        $ok = 0;
        $fail = 0;
        foreach ($post_ids as $id) {
            $result = $this->offload->offload_attachment((int) $id);
            if (is_wp_error($result)) {
                $fail++;
                continue;
            }
            $ok++;
        }
        return add_query_arg(
            [
                'wpsss_bulk_ok' => $ok,
                'wpsss_bulk_fail' => $fail,
            ],
            $redirect
        );
    }

    public function filter_dropdown(string $post_type): void
    {
        if ($post_type !== 'attachment') {
            return;
        }
        $cur = isset($_GET['wpsss_state']) ? sanitize_key((string) $_GET['wpsss_state']) : '';
        echo '<label class="screen-reader-text" for="filter-by-wpsss">' . esc_html__('对象存储', 'wpsss') . '</label>';
        echo '<select name="wpsss_state" id="filter-by-wpsss">';
        echo '<option value="">' . esc_html__('对象存储（全部）', 'wpsss') . '</option>';
        echo '<option value="offloaded"' . selected($cur, 'offloaded', false) . '>' . esc_html__('已进桶', 'wpsss') . '</option>';
        echo '<option value="pending"' . selected($cur, 'pending', false) . '>' . esc_html__('未进桶', 'wpsss') . '</option>';
        echo '<option value="failed"' . selected($cur, 'failed', false) . '>' . esc_html__('失败', 'wpsss') . '</option>';
        echo '</select>';
        echo '<label class="wenpai-gate wpsss-media-bulk-gate">';
        echo '<input type="checkbox" class="js-wpsss-media-bulk-gate" /> ';
        echo esc_html__('拉回会先把已有本地文件改名保留。永久删除已进桶的附件时，桶内对象一并删除，没有回收站。', 'wpsss');
        echo '</label>';
    }

    public function filter_query(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('post_type') !== 'attachment') {
            return;
        }
        $state = isset($_GET['wpsss_state']) ? sanitize_key((string) $_GET['wpsss_state']) : '';
        if ($state === 'offloaded') {
            $query->set('meta_key', Wpsss_Library::META_OFFLOADED);
            $query->set('meta_value', '1');
            return;
        }
        if ($state === 'failed') {
            $query->set('meta_key', Wpsss_Library::META_ERROR);
            $query->set('meta_compare', 'EXISTS');
            return;
        }
        if ($state !== 'pending') {
            return;
        }
        $query->set('meta_query', [
            'relation' => 'OR',
            [
                'key' => Wpsss_Library::META_OFFLOADED,
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => Wpsss_Library::META_OFFLOADED,
                'value' => '1',
                'compare' => '!=',
            ],
        ]);
    }

    public function add_metabox(): void
    {
        add_meta_box(
            'wpsss_attachment',
            __('对象存储', 'wpsss'),
            [$this, 'render_metabox'],
            'attachment',
            'side'
        );
    }

    public function render_metabox(WP_Post $post): void
    {
        $id = (int) $post->ID;
        $off = Wpsss_Library::is_offloaded($id);
        $key = (string) get_post_meta($id, Wpsss_Library::META_KEY, true);
        $url = wp_get_attachment_url($id);
        $err = Wpsss_Library::last_error($id);
        echo '<div class="wpsss-metabox">';
        echo '<div class="wpsss-metabox-status">';
        if ($off) {
            echo '<span class="pill ok">' . esc_html__('已进桶', 'wpsss') . '</span>';
        } elseif ($err !== '') {
            echo '<span class="pill warn">' . esc_html__('失败', 'wpsss') . '</span>';
        } else {
            echo '<span class="pill">' . esc_html__('未进桶', 'wpsss') . '</span>';
        }
        echo '</div>';
        if ($err !== '') {
            echo '<p class="wpsss-metabox-meta">' . esc_html($err) . '</p>';
        }
        if ($key !== '') {
            echo '<p class="wpsss-metabox-key"><code>' . esc_html(Wpsss_History::short_key($key)) . '</code></p>';
        }
        if (is_string($url) && $url !== '') {
            echo '<p class="wpsss-metabox-link"><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('打开地址', 'wpsss') . '</a></p>';
            echo '<p class="wpsss-metabox-url"><code>' . esc_html($url) . '</code></p>';
        }
        echo '<div class="wpsss-metabox-actions">';
        if ($off) {
            if (current_user_can('manage_options')) {
                $pull = add_query_arg(
                    [
                        'page' => 'wpsss',
                        'tab' => 'tools',
                        'panel' => 'move',
                        'pull_id' => $id,
                    ],
                    admin_url('options-general.php')
                );
                echo '<a class="btn btn-ghost" href="' . esc_url($pull) . '">' . esc_html__('拉回本地', 'wpsss') . '</a>';
            } else {
                echo '<label class="chk wpsss-media-delete-gate"><input type="checkbox" class="js-wpsss-media-pull-gate" /> ' . esc_html__('拉回会先把已有本地文件改名保留。', 'wpsss') . '</label>';
                echo '<button type="button" class="btn btn-ghost js-wpsss-media-pull"';
                echo ' data-action="' . esc_url(admin_url('admin-post.php')) . '"';
                echo ' data-id="' . esc_attr((string) $id) . '"';
                echo ' data-nonce="' . esc_attr(wp_create_nonce('wpsss_pull_one_' . $id)) . '">';
                echo esc_html__('拉回本地', 'wpsss') . '</button>';
            }
            echo '<p class="wpsss-metabox-meta">' . esc_html__('从媒体库永久删除此附件时，桶内对象会一并删除。', 'wpsss') . '</p>';
            echo '<label class="chk wpsss-media-delete-gate"><input type="checkbox" class="js-wpsss-media-delete-gate" /> ' . esc_html__('永久删除会同时删掉桶内对象，没有回收站。本地副本还在。', 'wpsss') . '</label>';
        } else {
            $href = wp_nonce_url(
                admin_url('admin-post.php?action=wpsss_offload_one&id=' . $id),
                'wpsss_offload_one_' . $id
            );
            echo '<a class="btn btn-secondary" href="' . esc_url($href) . '">' . esc_html__('补传到桶', 'wpsss') . '</a>';
        }
        echo '</div></div>';
    }

    public function admin_notices(): void
    {
        if (!is_admin()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $id = is_object($screen) ? (string) $screen->id : '';
        if (!in_array($id, ['upload', 'attachment'], true)) {
            return;
        }
        if (isset($_GET['wpsss_offload'])) {
            $ok = sanitize_key((string) $_GET['wpsss_offload']) === 'ok';
            $this->render_flash(
                $ok ? 'ok' : 'bad',
                $ok ? __('已进桶。', 'wpsss') : __('上传失败，请检查本地文件与桶权限。', 'wpsss')
            );
        }
        if (isset($_GET['wpsss_pull'])) {
            $ok = sanitize_key((string) $_GET['wpsss_pull']) === 'ok';
            $this->render_flash(
                $ok ? 'ok' : 'bad',
                $ok ? __('已拉回本地。', 'wpsss') : __('拉回失败，请检查桶权限与本地磁盘。', 'wpsss')
            );
        }
        if (!isset($_GET['wpsss_bulk_ok'])) {
            return;
        }
        $ok = (int) $_GET['wpsss_bulk_ok'];
        $fail = isset($_GET['wpsss_bulk_fail']) ? (int) $_GET['wpsss_bulk_fail'] : 0;
        $this->render_flash(
            $fail > 0 ? 'warn' : 'ok',
            sprintf(__('批量操作：成功 %d，失败 %d。', 'wpsss'), $ok, $fail)
        );
    }

    private function render_flash(string $kind, string $message): void
    {
        $cls = in_array($kind, ['ok', 'warn', 'info'], true) ? $kind : 'info';
        echo '<div class="wpsss-flash notice ' . esc_attr($cls) . '">';
        echo Wpsss_Icons::svg('info');
        echo '<span>' . esc_html($message) . '</span>';
        echo '</div>';
    }
}
