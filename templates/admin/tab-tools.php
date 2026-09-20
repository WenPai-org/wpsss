<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$panel = isset($view['panel']) ? (string) $view['panel'] : 'daily';
$panel = in_array($panel, ['daily', 'check', 'move'], true) ? $panel : 'daily';
$stats = isset($view['stats']) && is_array($view['stats']) ? $view['stats'] : ['total' => 0, 'offloaded' => 0, 'pending' => 0, 'failed' => 0];
$history = isset($view['history']) && is_array($view['history']) ? $view['history'] : [];
$history = Wpsss_History::collapsed($history);
$opts = $view['opts'];
$rewrite = isset($view['rewrite']) && is_array($view['rewrite']) ? $view['rewrite'] : [];
$pull = isset($view['pull']) && is_array($view['pull']) ? $view['pull'] : [];
$pull_cursor = (int) ($view['pull_cursor'] ?? 0);
$offload_cursor = (int) ($view['offload_cursor'] ?? 0);
$pending = (int) ($stats['pending'] ?? 0);
$failed = (int) ($stats['failed'] ?? 0);
$rewrite_from = (string) ($rewrite['from'] ?? $opts['rewrite_from'] ?? '');
$rewrite_to = (string) ($rewrite['to'] ?? $view['public_base'] ?? '');
$ops = isset($view['ops']) && is_array($view['ops']) ? $view['ops'] : [];
$scan = isset($ops['scan']) && is_array($ops['scan']) ? $ops['scan'] : [];
$diagnose = isset($ops['diagnose']) && is_array($ops['diagnose']) ? $ops['diagnose'] : [];
$head = isset($ops['head']) && is_array($ops['head']) ? $ops['head'] : [];
$ledes = [
    'daily' => __('补传、连通测试与最近记录。', 'wpsss'),
    'check' => __('裂图、主机不对、桶里对象不见了时用。', 'wpsss'),
    'move' => __('拉回、改写、解除标记。危险项会再问一次。', 'wpsss'),
];
$panels = [
    'daily' => __('日常', 'wpsss'),
    'check' => __('排障', 'wpsss'),
    'move' => __('迁移', 'wpsss'),
];
?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('工具', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo esc_html($ledes[$panel]); ?></p>
  </div>
  <div class="mode">
    <span class="seg" role="tablist" aria-label="<?php echo esc_attr__('工具分组', 'wpsss'); ?>">
      <?php foreach ($panels as $id => $label) : ?>
        <a class="<?php echo $panel === $id ? 'on' : ''; ?>" href="<?php echo esc_url($url('tools', ['panel' => $id])); ?>"><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </span>
  </div>
</div>
<div class="wpcy-stack">
  <?php include WPSSS_DIR . 'templates/admin/part-tools-' . $panel . '.php'; ?>
</div>
