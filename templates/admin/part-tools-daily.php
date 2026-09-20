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
        <span class="meta"><?php echo esc_html__('请在测试桶上操作；生产环境须另行审批。', 'wpsss'); ?></span>
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
            '媒体库共 %d 个附件，已进桶 %d，待上传 %d，失败 %d。每批最多 20 个。',
            (int) $stats['total'],
            (int) $stats['offloaded'],
            (int) $stats['pending'],
            (int) ($stats['failed'] ?? 0)
        )); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_offload" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_offload'); ?>
      <div class="card-foot start">
        <span class="meta"><?php
          if ((int) ($offload_cursor ?? 0) > 0) {
              echo esc_html(sprintf('接着 ID <%d。新上传会自动进桶。', (int) $offload_cursor));
          } elseif ($pending > 0) {
              echo esc_html__('新上传的附件会自动进桶。', 'wpsss');
          } else {
              echo esc_html__('当前没有待补传的附件。', 'wpsss');
          }
        ?></span>
        <span class="wpsss-actions">
          <?php if ((int) ($offload_cursor ?? 0) > 0) : ?>
            <button class="btn btn-ghost" type="submit" name="wpsss_offload_reset" value="1"><?php echo esc_html__('从头', 'wpsss'); ?></button>
          <?php endif; ?>
          <button class="btn btn-secondary" type="submit" <?php disabled($pending <= 0); ?>><?php echo esc_html__('补传一批', 'wpsss'); ?></button>
        </span>
      </div>
    </form>
    <?php if ($failed > 0) : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_offload" />
      <input type="hidden" name="wpsss_retry" value="1" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_offload'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html(sprintf('有 %d 个上次失败的附件，可只重试这些。', $failed)); ?></span>
        <button class="btn btn-ghost" type="submit"><?php echo esc_html__('重试失败项', 'wpsss'); ?></button>
      </div>
    </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('同步记录', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('最近 50 条上传、失败、删除与拉回记录。', 'wpsss'); ?></p>
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
