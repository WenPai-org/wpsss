<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$tab = (string) $view['tab'];
$url = $view['url'];
$wizard = $tab === 'setup';
$tabs = [
    'overview' => ['概览', 'home'],
    'settings' => ['设置', 'equalizer'],
    'tools' => ['工具', 'server'],
];
?>
<?php
$shell_tabs = [];
if (!$wizard) {
    foreach ($tabs as $id => $meta) {
        $shell_tabs[] = ['id' => $id, 'label' => $meta[0], 'url' => $url($id), 'icon_html' => Wpsss_Icons::svg($meta[1])];
    }
}
$head_extra = '';
if ($wizard) {
    $head_extra = '<div class="wpsss-setup-exit-wrap"><a class="btn btn-ghost wpsss-setup-exit" href="' . esc_url($url('overview')) . '">' . Wpsss_Icons::svg('home') . esc_html__('退出向导', 'wpsss') . '</a></div>';
}
Wenpai_Admin_Shell::open([
    'slug' => 'wpsss',
    'name' => __('文派智储', 'wpsss'),
    'mark_html' => Wpsss_Icons::svg('cloud'),
    'home_url' => $url('overview'),
    'current' => $tab,
    'tabs' => $shell_tabs,
    'head_extra' => $head_extra,
    'help_url' => $url('help'),
    'help_label' => __('帮助', 'wpsss'),
    'help_icon_html' => Wpsss_Icons::svg('help'),
    'feedback_url' => WPSSS_SUPPORT_URL,
    'feedback_label' => __('反馈', 'wpsss'),
    'feedback_icon_html' => Wpsss_Icons::svg('feedback'),
]);
?>
<?php if ($view['notice'] !== '') : ?>
  <?php
    $cls = 'info';
    if ($view['level'] === 'ok') {
        $cls = 'ok';
    } elseif ($view['level'] === 'error') {
        $cls = 'warn';
    }
    ?>
  <div class="wenpai-wrap wpsss-notice-wrap">
    <div class="notice <?php echo esc_attr($cls); ?>">
      <?php echo Wpsss_Icons::svg('info'); ?>
      <span><?php echo esc_html((string) $view['notice']); ?></span>
    </div>
  </div>
<?php endif; ?>
