<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$on = (bool) $view['configured'];
$opts = $view['opts'];
$regions = Wpsss_Options::regions();
$region_label = $regions[$opts['region']] ?? $opts['region'];
$keep = $opts['keep_local'] === '1';
$custom = $opts['custom_domain'] !== '';
?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('概览', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo esc_html__('媒体进桶，页面仍在源站。', 'wpsss'); ?></p>
  </div>
</div>

<?php if (!$on) : ?>
<div class="next">
  <span class="tile"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
  <div>
    <strong><?php echo esc_html__('先接一只存储桶。', 'wpsss'); ?></strong>
    <div class="meta"><?php echo esc_html__('填桶名和 RAM 密钥，测通后再谈自定义域。', 'wpsss'); ?></div>
  </div>
  <a class="btn btn-primary" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>"><?php echo esc_html__('去设置', 'wpsss'); ?></a>
</div>
<?php endif; ?>

<div class="card hero-w is-open" style="margin-top:20px">
  <div class="hero-body hero-body-v6">
    <div class="hero-l">
      <div class="eyebrow"><?php echo esc_html__('文派对象存储', 'wpsss'); ?></div>
      <h1><?php echo $on ? esc_html__('新媒体会进桶，旧页面不用搬。', 'wpsss') : esc_html__('还没有接桶。', 'wpsss'); ?></h1>
      <p><?php echo $on ? esc_html__('HTML 仍在源站。自定义域名以后走 CyberCDN，不是整站上 OSS。', 'wpsss') : esc_html__('先保存桶名和 RAM 密钥，再测连通。不要用生产桶。', 'wpsss'); ?></p>
      <?php if ($on) : ?>
        <div class="cta"><a class="btn btn-secondary" href="<?php echo esc_url($url('settings')); ?>"><?php echo esc_html__('查看设置', 'wpsss'); ?></a></div>
      <?php endif; ?>
    </div>
    <div class="hero-svc">
      <div class="hero-svc-h"><?php echo esc_html__('核心接通', 'wpsss'); ?></div>
      <ul class="svc-stack">
        <li>
          <span class="tile<?php echo $on ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('存储桶', 'wpsss'); ?></div>
            <div class="p"><?php echo $on ? esc_html(sprintf('经 %s 接通 · %s', $view['driver']->label(), $region_label)) : esc_html__('还没有密钥 · 不要填账号根密钥', 'wpsss'); ?></div>
          </div>
          <div class="svc-r"><span class="pill<?php echo $on ? ' ok' : ''; ?>"><?php echo $on ? esc_html__('已接通', 'wpsss') : esc_html__('未启用', 'wpsss'); ?></span></div>
        </li>
        <li>
          <span class="tile"><?php echo Wpsss_Icons::svg('image'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('对外地址', 'wpsss'); ?> <?php if (!$custom) : ?><span class="scope"><?php echo esc_html__('未设自定义域', 'wpsss'); ?></span><?php endif; ?></div>
            <div class="p"><?php echo $custom ? esc_html((string) $view['public_base']) : esc_html__('桶默认域名 · 以后可挂 CyberCDN', 'wpsss'); ?></div>
          </div>
          <div class="svc-r"><span class="pill"><?php echo $custom ? esc_html__('自定义域', 'wpsss') : esc_html__('直连桶', 'wpsss'); ?></span></div>
        </li>
        <li>
          <span class="tile<?php echo $keep ? ' ok' : ' warn'; ?>"><?php echo Wpsss_Icons::svg('plug'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('本地备份', 'wpsss'); ?></div>
            <div class="p"><?php echo esc_html__('上传后源站仍留一份 · 删附件时远端一并删', 'wpsss'); ?></div>
          </div>
          <div class="svc-r"><span class="pill<?php echo $keep ? ' ok' : ' warn'; ?>"><?php echo $keep ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></span></div>
        </li>
        <?php if ($on && isset($view['stats']) && is_array($view['stats'])) : ?>
        <li>
          <span class="tile<?php echo ((int) $view['stats']['pending'] === 0) ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('image'); ?></span>
          <div>
            <div class="n"><?php echo esc_html__('已有附件', 'wpsss'); ?></div>
            <div class="p"><?php echo esc_html(sprintf('已进桶 %d / 共 %d · 未传 %d', (int) $view['stats']['offloaded'], (int) $view['stats']['total'], (int) $view['stats']['pending'])); ?></div>
          </div>
          <div class="svc-r"><a class="btn btn-ghost" href="<?php echo esc_url($url('tools')); ?>"><?php echo esc_html__('去补传', 'wpsss'); ?></a></div>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
