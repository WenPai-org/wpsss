<?php
defined('ABSPATH') || exit;
/** @var string $panel */
/** @var int $pending */
/** @var int $failed */
/** @var array<string, mixed> $stats */
/** @var array<int, array<string, mixed>> $history */
?>
  <section class="card">
    <div class="card-head">
      <span class="tile accent"><?php echo Wpsss_Icons::svg('plug'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('测试连通', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('向存储桶写入探测对象并立即删除，用于验证密钥与权限。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_probe" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_probe'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('测试会向桶写入并删除一个临时对象，用来验证密钥与权限。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('测试连通', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile<?php echo ($pending > 0) ? ' warn' : ' ok'; ?>"><?php echo Wpsss_Icons::svg('image'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('补传已有附件', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html(sprintf(
            '已进桶 %d / 共 %d 个附件，待上传 %d，失败 %d。',
            (int) $stats['offloaded'],
            (int) $stats['total'],
            (int) $stats['pending'],
            (int) ($stats['failed'] ?? 0)
        )); ?></p>
      </div>
    </div>
    <form class="js-wpsss-offload-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_offload" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_offload'); ?>
      <div class="wpsss-progress js-wpsss-offload-bar" hidden aria-hidden="true"><span></span></div>
      <p class="meta js-wpsss-offload-progress" aria-live="polite"></p>
      <div class="card-foot start">
        <span class="meta js-wpsss-offload-hint"><?php
          if ($pending > 0 && (int) ($failed ?? 0) > 0) {
              echo esc_html(sprintf(__('待上传 %d 个，其中 %d 个上次失败。失败项会自动退出队列，点「重试失败项」单独处理。', 'wpsss'), (int) $pending, (int) $failed));
          } elseif ($pending > 0 && (int) ($offload_cursor ?? 0) > 0) {
              echo esc_html(sprintf(__('接着上次的位置（编号 %d）继续，从新到旧。关掉页面，后台每分钟最多补 2 个。', 'wpsss'), (int) $offload_cursor));
          } elseif ($pending > 0) {
              echo esc_html__('开始后一次传一个。关掉页面，后台每分钟最多补 2 个，补完自动停；点「暂停」立即全停。', 'wpsss');
          } elseif ((int) ($failed ?? 0) > 0) {
              echo esc_html(sprintf(__('待传已清完，剩 %d 个失败项。到「体检」查明原因，再点「重试失败项」。', 'wpsss'), (int) $failed));
          } else {
              echo esc_html__('全部附件已进桶，新上传会自动进桶。', 'wpsss');
          }
        ?></span>
        <span class="wpsss-actions">
          <?php if ($pending > 0 && (int) ($offload_cursor ?? 0) > 0) : ?>
            <button class="btn btn-ghost" type="submit" name="wpsss_offload_reset" value="1" title="<?php echo esc_attr__('清掉续传位置，下一轮从最新的附件重新扫起；已进桶的会自动跳过。', 'wpsss'); ?>"><?php echo esc_html__('从最新开始', 'wpsss'); ?></button>
          <?php endif; ?>
          <button class="btn btn-ghost js-wpsss-offload-stop" type="button" hidden><?php echo esc_html__('暂停', 'wpsss'); ?></button>
          <button class="btn btn-secondary js-wpsss-offload-run" type="submit" name="wpsss_offload" value="1" <?php disabled($pending <= 0); ?>><?php echo esc_html__('开始补传', 'wpsss'); ?></button>
        </span>
      </div>
    </form>
    <?php if ((int) ($stalled ?? 0) > 0) : ?>
    <div class="notice warn">
      <?php echo Wpsss_Icons::svg('info'); ?>
      <span><?php echo esc_html(sprintf(__('后台慢跑已停：剩 %d 个附件连续失败 3 次以上。请到「体检」查明原因，再用下方按钮重试。', 'wpsss'), (int) $stalled)); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($failed > 0) : ?>
    <form class="js-wpsss-offload-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_offload" />
      <input type="hidden" name="wpsss_retry" value="1" />
      <input type="hidden" name="source" value="failed" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_offload'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html(sprintf(__('有 %d 个附件进桶失败。点「重试失败项」只处理它们。', 'wpsss'), $failed)); ?></span>
        <button class="btn btn-ghost js-wpsss-offload-run" type="submit"><?php echo esc_html__('重试失败项', 'wpsss'); ?></button>
      </div>
    </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('操作记录', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('最近 50 条上传、失败、删除与拉回的操作记录。', 'wpsss'); ?></p>
      </div>
    </div>
    <?php if ($history === []) : ?>
      <div class="empty">
        <div class="tile"><?php echo Wpsss_Icons::svg('cloud'); ?></div>
        <p><?php echo esc_html__('暂无记录', 'wpsss'); ?></p>
        <p class="meta"><?php echo esc_html__('补传、拉回或删除附件后将显示在此。', 'wpsss'); ?></p>
      </div>
    <?php else : ?>
      <ul class="wpsss-log">
        <?php foreach ($history as $row) : ?>
          <?php $event = (string) ($row['event'] ?? ''); ?>
          <li>
            <div class="n"><span class="pill <?php echo esc_attr(Wpsss_History::event_tone($event)); ?>"><?php echo esc_html(Wpsss_History::event_label($event)); ?></span><?php if ((int) ($row['count'] ?? 1) > 1) : ?><span class="meta"> ×<?php echo (int) $row['count']; ?></span><?php endif; ?></div>
            <div class="p"><?php
                $detail = Wpsss_History::format_detail($event, (string) ($row['detail'] ?? ''));
                $time = Wpsss_History::format_time((string) ($row['t'] ?? ''));
                echo esc_html($detail !== '' ? $time . ' · ' . $detail : $time);
            ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
