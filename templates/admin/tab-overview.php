<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$on = (bool) $view['configured'];
$opts = $view['opts'];
$regions = Wpsss_Options::regions_for($opts['driver']);
$region_label = $regions[$opts['region']] ?? $opts['region'];
$keep = $opts['keep_local'] === '1';
$custom = $opts['custom_domain'] !== '';
$url_access = ($opts['url_access'] ?? 'public') === 'signed' ? 'signed' : 'public';
$doctor = isset($view['doctor']) && is_array($view['doctor']) ? $view['doctor'] : [];
$stats = isset($view['stats']) && is_array($view['stats']) ? $view['stats'] : [];
$public_base = (string) ($view['public_base'] ?? '');
$pending = (int) ($stats['pending'] ?? 0);
?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('概览', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo esc_html__('媒体进桶，页面仍在源站。', 'wpsss'); ?></p>
  </div>
</div>

<div class="wpcy-stack">
<?php if (!$on) : ?>
<div class="next">
  <span class="tile"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
  <div>
    <strong><?php echo esc_html__('尚未连接存储桶', 'wpsss'); ?></strong>
    <div class="meta"><?php echo esc_html__('跟随 4 步设置向导填写桶与密钥，或在高级设置中手动配置。', 'wpsss'); ?></div>
  </div>
  <div class="next-actions">
    <a class="btn btn-primary" href="<?php echo esc_url($url('setup')); ?>"><?php echo esc_html__('开始设置向导', 'wpsss'); ?></a>
    <a class="btn btn-ghost" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>"><?php echo esc_html__('高级设置', 'wpsss'); ?></a>
  </div>
</div>
<?php else : ?>
<section class="card">
  <h2 class="section-title"><?php echo esc_html__('运行状态', 'wpsss'); ?></h2>
  <div class="simple-row">
    <div class="tile ok"><?php echo Wpsss_Icons::vendor($opts['driver']); ?></div>
    <div>
      <div class="t"><?php echo esc_html__('存储桶', 'wpsss'); ?></div>
      <div class="d"><?php echo esc_html(sprintf('%s · %s · %s', $view['driver']->label(), $region_label, $opts['bucket'])); ?></div>
    </div>
    <div class="r"><span class="pill ok"><?php echo esc_html__('已连接', 'wpsss'); ?></span></div>
  </div>
  <div class="simple-row">
    <div class="tile"><?php echo Wpsss_Icons::svg('external'); ?></div>
    <div>
      <div class="t"><?php echo esc_html__('对外地址', 'wpsss'); ?></div>
      <?php if ($public_base !== '') : ?>
        <div class="d"><?php echo esc_html($public_base); ?></div>
      <?php elseif (!$custom) : ?>
        <div class="d"><?php echo esc_html__('未设置自定义域名时使用存储桶默认域名', 'wpsss'); ?></div>
      <?php endif; ?>
    </div>
    <div class="r">
      <span class="pill"><?php echo $url_access === 'signed' ? esc_html__('签名访问', 'wpsss') : ($custom ? esc_html__('自定义域名', 'wpsss') : esc_html__('默认域名', 'wpsss')); ?></span>
      <?php if (!$custom) : ?>
        <a class="btn btn-ghost" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>#wpsss-access"><?php echo esc_html__('去填写', 'wpsss'); ?></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="simple-row">
    <div class="tile<?php echo $keep ? ' ok' : ' warn'; ?>"><?php echo Wpsss_Icons::svg('plug'); ?></div>
    <div>
      <div class="t"><?php echo esc_html__('本地备份', 'wpsss'); ?></div>
      <div class="d"><?php echo $keep
          ? esc_html__('文件进桶后仍留在 uploads', 'wpsss')
          : esc_html__('仅对象存储中保留文件', 'wpsss'); ?></div>
    </div>
    <div class="r"><span class="pill<?php echo $keep ? ' ok' : ' warn'; ?>"><?php echo $keep ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></span></div>
  </div>
  <?php if ($stats !== []) : ?>
  <div class="simple-row">
    <div class="tile<?php echo $pending === 0 ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('server'); ?></div>
    <div>
      <div class="t"><?php echo esc_html__('媒体库附件', 'wpsss'); ?></div>
      <div class="d"><?php echo esc_html(sprintf(
          '已进桶 %d / 共 %d',
          (int) ($stats['offloaded'] ?? 0),
          (int) ($stats['total'] ?? 0)
      )); ?><?php if ($pending > 0) {
          echo esc_html(sprintf(' · 待上传 %d', $pending));
      } ?></div>
    </div>
    <div class="r">
      <?php if ($pending > 0) : ?>
        <a class="btn btn-ghost" href="<?php echo esc_url($url('tools')); ?>"><?php echo esc_html__('补传', 'wpsss'); ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="card-foot start">
    <span class="meta"><?php echo esc_html__('开关与密钥在设置里。', 'wpsss'); ?></span>
    <span class="wpsss-actions">
      <a class="btn btn-secondary" href="<?php echo esc_url($url('settings')); ?>"><?php echo esc_html__('设置', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="<?php echo esc_url($url('tools')); ?>"><?php echo esc_html__('测试连通', 'wpsss'); ?></a>
    </span>
  </div>
</section>
<?php endif; ?>

<?php if ($on && $doctor !== []) : ?>
  <?php
    $doctor_context = 'overview';
    include WPSSS_DIR . 'templates/admin/part-doctor-card.php';
    ?>
<?php endif; ?>
</div>
