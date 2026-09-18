<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$tab = (string) $view['tab'];
$url = $view['url'];
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
    <nav class="wpcy-tabs" aria-label="<?php echo esc_attr__('文派对象存储', 'wpsss'); ?>">
      <?php foreach ($tabs as $id => $meta) : ?>
        <a class="wpcy-tab<?php echo $tab === $id ? ' is-active' : ''; ?>" href="<?php echo esc_url($url($id)); ?>">
          <?php echo Wpsss_Icons::svg($meta[1]); ?>
          <?php echo esc_html($meta[0]); ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="wpcy-head-right">
      <a class="btn btn-ghost" href="https://wenpai.org" target="_blank" rel="noopener"><?php echo Wpsss_Icons::svg('help'); ?><?php echo esc_html__('帮助', 'wpsss'); ?></a>
    </div>
  </div>
</div></div>
<?php if ($view['notice'] !== '') : ?>
  <?php
    $cls = 'info';
    if ($view['level'] === 'ok') {
        $cls = 'info';
    } elseif ($view['level'] === 'error') {
        $cls = 'warn';
    }
    ?>
  <div class="wpcy-wrap" style="padding-top:16px">
    <div class="notice <?php echo esc_attr($cls); ?>">
      <?php echo Wpsss_Icons::svg('info'); ?>
      <span><?php echo esc_html((string) $view['notice']); ?></span>
    </div>
  </div>
<?php endif; ?>
