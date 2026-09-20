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
<div class="wpcy-top"><div class="wpcy-wrap">
  <div class="wpcy-head">
    <a class="wpcy-brand" href="<?php echo esc_url($url('overview')); ?>">
      <span class="wpcy-mark"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
      <span class="wpcy-name"><?php echo esc_html__('文派对象存储', 'wpsss'); ?></span>
    </a>
    <?php if (!$wizard) : ?>
    <nav class="wpcy-tabs" aria-label="<?php echo esc_attr__('文派对象存储', 'wpsss'); ?>">
      <?php foreach ($tabs as $id => $meta) : ?>
        <a class="wpcy-tab<?php echo $tab === $id ? ' is-active' : ''; ?>" href="<?php echo esc_url($url($id)); ?>">
          <?php echo Wpsss_Icons::svg($meta[1]); ?>
          <?php echo esc_html($meta[0]); ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php else : ?>
    <div class="wpsss-setup-exit-wrap">
      <a class="btn btn-ghost wpsss-setup-exit" href="<?php echo esc_url($url('overview')); ?>"><?php echo Wpsss_Icons::svg('home'); ?><?php echo esc_html__('退出向导', 'wpsss'); ?></a>
    </div>
    <?php endif; ?>
    <div class="wpcy-head-right">
      <a class="btn btn-ghost<?php echo $tab === 'help' ? ' is-active' : ''; ?>" href="<?php echo esc_url($url('help')); ?>"><?php echo Wpsss_Icons::svg('help'); ?><?php echo esc_html__('帮助', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="<?php echo esc_url(WPSSS_SUPPORT_URL); ?>" target="_blank" rel="noopener"><?php echo Wpsss_Icons::svg('chat'); ?><?php echo esc_html__('反馈', 'wpsss'); ?></a>
    </div>
  </div>
</div></div>
<?php if ($view['notice'] !== '') : ?>
  <?php
    $cls = 'info';
    if ($view['level'] === 'ok') {
        $cls = 'ok';
    } elseif ($view['level'] === 'error') {
        $cls = 'warn';
    }
    ?>
  <div class="wpcy-wrap wpsss-notice-wrap">
    <div class="notice <?php echo esc_attr($cls); ?>">
      <?php echo Wpsss_Icons::svg('info'); ?>
      <span><?php echo esc_html((string) $view['notice']); ?></span>
    </div>
  </div>
<?php endif; ?>
