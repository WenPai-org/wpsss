<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $doctor */
/** @var callable $url */
$context = isset($doctor_context) ? (string) $doctor_context : 'overview';
$tone = (string) ($doctor['tone'] ?? 'ok');
$blockers = (array) ($doctor['blockers'] ?? []);
$warnings = (array) ($doctor['warnings'] ?? []);
$oks = (array) ($doctor['oks'] ?? []);
$has_issue = $tone !== 'ok';
$show_body = $context === 'setup' || $has_issue;
$show_foot = isset($doctor_show_foot) ? (bool) $doctor_show_foot : ($context === 'overview' && $has_issue);
$expanded = $show_body && (!isset($doctor_expanded) || $doctor_expanded);
$pill_cls = $tone === 'ok' ? 'ok' : ($tone === 'block' ? 'bad' : 'warn');
$tile_cls = $tone === 'ok' ? ' ok' : ($tone === 'block' ? ' bad' : ' warn');
$card_mod = $has_issue ? ' wpsss-doctor-card--alert' : '';
if ($blockers !== []) {
    $summary = sprintf(__('%d 项需要先处理', 'wpsss'), count($blockers));
    if ($warnings !== []) {
        $summary .= sprintf(__(' · %d 项警告', 'wpsss'), count($warnings));
    }
} elseif ($warnings !== []) {
    $summary = sprintf(__('%d 项警告，建议查看', 'wpsss'), count($warnings));
} else {
    $summary = __('检查通过', 'wpsss');
}
?>
<section class="card wpsss-doctor-card<?php echo esc_attr($card_mod); ?>">
  <?php if (!$show_body) : ?>
  <div class="wpsss-doctor-summary">
    <span class="tile<?php echo esc_attr($tile_cls); ?>"><?php echo Wpsss_Icons::svg('info'); ?></span>
    <div class="wpsss-doctor-summary-text">
      <h2 class="card-title"><?php echo esc_html__('运行体检', 'wpsss'); ?></h2>
      <p class="card-sub wpsss-doctor-summary-meta"><?php echo esc_html($summary); ?></p>
    </div>
    <div class="wpsss-doctor-summary-side">
      <span class="pill <?php echo esc_attr($pill_cls); ?>"><?php echo esc_html((string) ($doctor['label'] ?? '')); ?></span>
    </div>
  </div>
  <?php else : ?>
  <details class="wpsss-doctor-details"<?php echo $expanded ? ' open' : ''; ?>>
    <summary class="wpsss-doctor-summary">
      <span class="tile<?php echo esc_attr($tile_cls); ?>"><?php echo Wpsss_Icons::svg('info'); ?></span>
      <div class="wpsss-doctor-summary-text">
        <h2 class="card-title"><?php echo esc_html__('运行体检', 'wpsss'); ?></h2>
        <p class="card-sub wpsss-doctor-summary-meta"><?php echo esc_html($summary); ?></p>
      </div>
      <div class="wpsss-doctor-summary-side">
        <span class="pill <?php echo esc_attr($pill_cls); ?>"><?php echo esc_html((string) ($doctor['label'] ?? '')); ?></span>
        <span class="wpsss-doctor-toggle-hint" aria-hidden="true">
          <span class="wpsss-doctor-toggle-label">
            <span class="when-closed"><?php echo esc_html__('展开明细', 'wpsss'); ?></span>
            <span class="when-open"><?php echo esc_html__('收起明细', 'wpsss'); ?></span>
          </span>
          <?php echo Wpsss_Icons::svg('chevron-down', 'wpsss-doctor-chevron'); ?>
        </span>
      </div>
    </summary>
    <div class="wpsss-doctor-body">
      <ul class="wpsss-doctor-list">
        <?php foreach ($blockers as $line) : ?>
          <li class="is-block"><span class="pill bad"><?php echo esc_html__('阻断', 'wpsss'); ?></span><span><?php echo esc_html((string) $line); ?></span></li>
        <?php endforeach; ?>
        <?php foreach ($warnings as $line) : ?>
          <li class="is-warn"><span class="pill warn"><?php echo esc_html__('警告', 'wpsss'); ?></span><span><?php echo esc_html((string) $line); ?></span></li>
        <?php endforeach; ?>
        <?php if ($context === 'setup') : ?>
          <?php foreach ($oks as $line) : ?>
            <li class="is-ok"><span class="pill ok"><?php echo esc_html__('正常', 'wpsss'); ?></span><span><?php echo esc_html((string) $line); ?></span></li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
      <?php if ($show_foot) : ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('要验证密钥与权限，请到工具页做一次测试连通。', 'wpsss'); ?></span>
        <a class="btn btn-secondary" href="<?php echo esc_url($url('tools', ['panel' => 'daily'])); ?>"><?php echo esc_html__('测试连通', 'wpsss'); ?></a>
      </div>
      <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>
</section>
