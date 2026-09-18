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
            echo esc_html__('未接通', 'wpsss');
            return;
        }
        $err = Wpsss_Library::last_error($post_id);
        if ($err !== '') {
            echo '<span class="pill warn">' . esc_html__('失败', 'wpsss') . '</span>';
            echo '<div class="p">' . esc_html($err) . '</div>';
            return;
        }
        if (Wpsss_Library::is_offloaded($post_id)) {
            echo '<span class="pill ok">' . esc_html__('已进桶', 'wpsss') . '</span>';
            return;
        }
        echo esc_html__('未进桶', 'wpsss');
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
            $pull = wp_nonce_url(
                admin_url('admin-post.php?action=wpsss_pull_one&id=' . (int) $post->ID),
                'wpsss_pull_one_' . (int) $post->ID
            );
            $actions['wpsss_pull'] = '<a href="' . esc_url($pull) . '">' . esc_html__('拉回本地', 'wpsss') . '</a>';
            return $actions;
        }
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=wpsss_offload_one&id=' . (int) $post->ID),
            'wpsss_offload_one_' . (int) $post->ID
        );
        $actions['wpsss'] = '<a href="' . esc_url($url) . '">' . esc_html__('传到对象存储', 'wpsss') . '</a>';
        return $actions;
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function bulk_actions(array $actions): array
    {
        $actions['wpsss_offload'] = __('传到对象存储', 'wpsss');
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
                $result = $this->offload->pull_attachment((int) $id, false);
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
        echo '<p>' . esc_html($off ? __('已进桶', 'wpsss') : __('未进桶', 'wpsss')) . '</p>';
        if ($err !== '') {
            echo '<p>' . esc_html($err) . '</p>';
        }
        if ($key !== '') {
            echo '<p><code>' . esc_html($key) . '</code></p>';
        }
        if (is_string($url) && $url !== '') {
            echo '<p><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('打开地址', 'wpsss') . '</a></p>';
        }
        if ($off) {
            return;
        }
        $href = wp_nonce_url(
            admin_url('admin-post.php?action=wpsss_offload_one&id=' . $id),
            'wpsss_offload_one_' . $id
        );
        echo '<p><a class="button" href="' . esc_url($href) . '">' . esc_html__('传到对象存储', 'wpsss') . '</a></p>';
    }

    public function admin_notices(): void
    {
        if (!is_admin()) {
            return;
        }
        if (isset($_GET['wpsss_offload'])) {
            $ok = sanitize_key((string) $_GET['wpsss_offload']) === 'ok';
            echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . ' is-dismissible"><p>';
            echo esc_html($ok ? __('已传到对象存储。', 'wpsss') : __('没传上去，看本地文件和桶权限。', 'wpsss'));
            echo '</p></div>';
        }
        if (!isset($_GET['wpsss_bulk_ok'])) {
            return;
        }
        $ok = (int) $_GET['wpsss_bulk_ok'];
        $fail = isset($_GET['wpsss_bulk_fail']) ? (int) $_GET['wpsss_bulk_fail'] : 0;
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html(sprintf('批量补传：成功 %d，失败 %d。', $ok, $fail));
        echo '</p></div>';
    }
}
