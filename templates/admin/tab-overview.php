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
<?php endif; ?>

<div class="card hero-w is-open">
  <?php /* 营销双栏：hero-body 加 hero-body-v6--split */ ?>
  <div class="hero-body hero-body-v6">
    <div class="hero-l">
      <div class="eyebrow"><?php echo esc_html__('文派对象存储', 'wpsss'); ?></div>
      <h1><?php echo $on ? esc_html__('新媒体会进桶，旧页面不用搬。', 'wpsss') : esc_html__('还没有接桶。', 'wpsss'); ?></h1>
      <p><?php echo $on
          ? esc_html__('HTML 仍在源站。自定义域名以后走 CyberCDN，不是整站上 OSS。', 'wpsss')
          : esc_html__('先保存桶名和 RAM 密钥，再测连通。不要用生产桶。', 'wpsss'); ?></p>
      <div class="cta">
        <a class="btn btn-secondary" href="<?php echo esc_url($url('settings')); ?>"><?php echo esc_html__('查看设置', 'wpsss'); ?></a>
        <?php if ($on) : ?>
          <a class="btn btn-ghost" href="<?php echo esc_url($url('tools')); ?>"><?php echo esc_html__('测试连通', 'wpsss'); ?></a>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero-svc">
      <div class="hero-svc-h"><?php echo esc_html__('核心接通', 'wpsss'); ?></div>
      <ul class="svc-stack">
        <li>
          <span class="tile<?php echo $on ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('存储桶', 'wpsss'); ?></div>
            <?php if ($on) : ?><div class="p"><?php echo esc_html(sprintf('%s · %s · %s', $view['driver']->label(), $region_label, $opts['bucket'])); ?></div><?php endif; ?>
          </div>
          <div class="svc-r"><span class="pill<?php echo $on ? ' ok' : ''; ?>"><?php echo $on ? esc_html__('已连接', 'wpsss') : esc_html__('未配置', 'wpsss'); ?></span></div>
        </li>
        <li>
          <span class="tile"><?php echo Wpsss_Icons::svg('external'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('对外地址', 'wpsss'); ?></div>
            <?php if ($on && $custom) : ?><div class="p"><?php echo esc_html((string) $view['public_base']); ?></div><?php endif; ?>
          </div>
          <div class="svc-r"><span class="pill"><?php echo $url_access === 'signed' ? esc_html__('签名访问', 'wpsss') : ($custom ? esc_html__('自定义域名', 'wpsss') : esc_html__('默认域名', 'wpsss')); ?></span></div>
        </li>
        <li>
          <span class="tile<?php echo $keep ? ' ok' : ' warn'; ?>"><?php echo Wpsss_Icons::svg('plug'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('本地备份', 'wpsss'); ?></div>
          </div>
          <div class="svc-r"><span class="pill<?php echo $keep ? ' ok' : ' warn'; ?>"><?php echo $keep ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></span></div>
        </li>
        <?php if ($on && $stats !== []) : ?>
        <li>
          <span class="tile<?php echo ((int) ($stats['pending'] ?? 0) === 0) ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('server'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('媒体库附件', 'wpsss'); ?></div>
            <div class="p"><?php echo esc_html(sprintf(
                '已进桶 %d / 共 %d',
                (int) ($stats['offloaded'] ?? 0),
                (int) ($stats['total'] ?? 0)
            )); ?><?php if ((int) ($stats['pending'] ?? 0) > 0) {
                echo esc_html(sprintf(' · 待上传 %d', (int) $stats['pending']));
            } ?></div>
          </div>
          <?php if ((int) ($stats['pending'] ?? 0) > 0) : ?>
          <div class="svc-r"><a class="btn btn-ghost" href="<?php echo esc_url($url('tools')); ?>"><?php echo esc_html__('补传', 'wpsss'); ?></a></div>
          <?php endif; ?>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<?php if ($on && $doctor !== []) : ?>
  <?php
    $doctor_context = 'overview';
    include WPSSS_DIR . 'templates/admin/part-doctor-card.php';
    ?>
<?php endif; ?>
</div>
