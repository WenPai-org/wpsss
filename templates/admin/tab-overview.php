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
<div class="wenpai-page-head">
  <div>
    <h1 class="wenpai-h1"><?php echo esc_html__('概览', 'wpsss'); ?></h1>
    <p class="wenpai-lede"><?php echo esc_html__('媒体进桶，网页仍从自己的服务器打开。', 'wpsss'); ?></p>
  </div>
</div>

<div class="wenpai-stack">
<?php if (!$on) : ?>
<?php
Wenpai_Admin_UI::next( array( 'icon_html' => Wpsss_Icons::svg('cloud'), 'title' => __( '尚未连接存储桶', 'wpsss' ), 'desc' => __( '跟随 4 步设置向导填写桶与密钥，或在高级设置中手动配置。', 'wpsss' ), 'actions' => array( array( 'label' => __( '开始设置向导', 'wpsss' ), 'variant' => 'primary', 'href' => $url('setup') ), array( 'label' => __( '高级设置', 'wpsss' ), 'variant' => 'ghost', 'href' => $url('settings', ['mode' => 'advanced']) ) ) ) );
?>
<?php else : ?>
<section class="card">
  <h2 class="section-title"><?php echo esc_html__('运行状态', 'wpsss'); ?></h2>
  <?php
ob_start();
?><span class="pill ok"><?php echo esc_html__('已连接', 'wpsss'); ?></span><?php
echo Wenpai_Admin_UI::row_html( array( 'icon_html' => Wpsss_Icons::svg($opts['driver']), 'state' => 'ok', 'title' => __( '存储桶', 'wpsss' ), 'desc' => sprintf('%s · %s · %s', $view['driver']->label(), $region_label, $opts['bucket']), 'control_html' => (string) ob_get_clean() ) );
?>
  <?php
  ob_start();
  ?>
  <span class="pill<?php echo $public_base !== '' ? ' ok' : ' warn'; ?>"><?php echo $url_access === 'signed' ? esc_html__('签名访问', 'wpsss') : ($custom ? esc_html__('自定义域名', 'wpsss') : esc_html__('默认域名', 'wpsss')); ?></span>
  <?php if (!$custom) : ?>
    <a class="btn btn-ghost" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>#wpsss-access"><?php echo esc_html__('填写自定义域名', 'wpsss'); ?></a>
  <?php endif;
  $wpsss_right = (string) ob_get_clean();
  echo Wenpai_Admin_UI::row_html(array(
    'icon_html' => Wpsss_Icons::svg('external'),
    'state' => $public_base !== '' ? 'ok' : 'warn',
    'title' => __('对外地址', 'wpsss'),
    'desc' => $public_base !== '' ? $public_base : __('未设置自定义域名时，前台将使用存储桶默认域名。', 'wpsss'),
    'right_html' => $wpsss_right,
  ));
  ?>
  <?php
  echo Wenpai_Admin_UI::row_html(array(
    'icon_html' => Wpsss_Icons::svg('plug'),
    'state' => $keep ? 'ok' : 'warn',
    'title' => __('本地备份', 'wpsss'),
    'desc' => $keep ? __('文件进桶后仍留在 uploads', 'wpsss') : __('仅对象存储中保留文件', 'wpsss'),
    'pill' => array('text' => $keep ? __('已开启', 'wpsss') : __('未开启', 'wpsss'), 'state' => $keep ? 'ok' : 'warn'),
  ));
  ?>
  <?php if ($stats !== []) : ?>
  <?php
  ob_start();
  if ($pending > 0) : ?>
    <a class="btn btn-ghost" href="<?php echo esc_url($url('tools', ['panel' => 'daily'])); ?>"><?php echo esc_html__('补传', 'wpsss'); ?></a>
  <?php endif;
  $wpsss_stats_right = (string) ob_get_clean();
  echo Wenpai_Admin_UI::row_html(array(
    'icon_html' => Wpsss_Icons::svg('server'),
    'state' => $pending === 0 && (int) ($stats['failed'] ?? 0) === 0 ? 'ok' : ($pending > 0 ? '' : 'warn'),
    'title' => __('媒体库附件', 'wpsss'),
    'desc' => sprintf('已进桶 %d / 共 %d', (int) ($stats['offloaded'] ?? 0), (int) ($stats['total'] ?? 0)) . ($pending > 0 ? sprintf(' · 待上传 %d', $pending) : ''),
    'right_html' => $wpsss_stats_right,
  ));
  ?>
  <?php endif; ?>
  <div class="card-foot start">
    <span class="meta"><?php echo esc_html__('开关与密钥在设置页。', 'wpsss'); ?></span>
    <span class="wpsss-actions">
      <a class="btn btn-secondary" href="<?php echo esc_url($url('settings')); ?>"><?php echo esc_html__('设置', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="<?php echo esc_url($url('tools', ['panel' => 'daily'])); ?>"><?php echo esc_html__('测试连通', 'wpsss'); ?></a>
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
