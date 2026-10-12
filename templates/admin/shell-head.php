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
<div class="wenpai-top"><div class="wenpai-wrap">
  <div class="wenpai-head">
    <a class="wenpai-brand" href="<?php echo esc_url($url('overview')); ?>">
      <span class="wenpai-mark"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
      <span class="wenpai-name"><?php echo esc_html__('文派智储', 'wpsss'); ?></span>
    </a>
    <?php if (!$wizard) : ?>
    <nav class="wenpai-tabs" aria-label="<?php echo esc_attr__('文派智储', 'wpsss'); ?>">
      <?php foreach ($tabs as $id => $meta) : ?>
        <a class="wenpai-tab<?php echo $tab === $id ? ' is-active' : ''; ?>" href="<?php echo esc_url($url($id)); ?>">
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
    <div class="wenpai-head-right">
      <a class="btn btn-ghost wenpai-head-action<?php echo $tab === 'help' ? ' is-active' : ''; ?>" href="<?php echo esc_url($url('help')); ?>" title="<?php echo esc_attr__('帮助', 'wpsss'); ?>"><?php echo Wpsss_Icons::svg('help'); ?><span class="wenpai-head-label"><?php echo esc_html__('帮助', 'wpsss'); ?></span></a>
      <a class="btn btn-ghost wenpai-head-action" href="<?php echo esc_url(WPSSS_SUPPORT_URL); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr__('反馈', 'wpsss'); ?>"><?php echo Wpsss_Icons::svg('chat'); ?><span class="wenpai-head-label"><?php echo esc_html__('反馈', 'wpsss'); ?></span></a>
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
  <div class="wenpai-wrap wpsss-notice-wrap">
    <div class="notice <?php echo esc_attr($cls); ?>">
      <?php echo Wpsss_Icons::svg('info'); ?>
      <span><?php echo esc_html((string) $view['notice']); ?></span>
    </div>
  </div>
<?php endif; ?>
