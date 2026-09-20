<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$stats = isset($view['stats']) && is_array($view['stats']) ? $view['stats'] : ['total' => 0, 'offloaded' => 0, 'pending' => 0, 'failed' => 0];
$history = isset($view['history']) && is_array($view['history']) ? $view['history'] : [];
$history = Wpsss_History::collapsed($history);
$opts = $view['opts'];
$rewrite = isset($view['rewrite']) && is_array($view['rewrite']) ? $view['rewrite'] : [];
$pending = (int) ($stats['pending'] ?? 0);
$rewrite_from = (string) ($rewrite['from'] ?? $opts['rewrite_from'] ?? '');
$rewrite_to = (string) ($rewrite['to'] ?? $view['public_base'] ?? '');
?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('工具', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo esc_html__('批量与迁移操作集中在此；执行前请先保存设置。', 'wpsss'); ?></p>
  </div>
</div>
<div class="wpcy-stack">
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
      <?php wp_nonce_field('wpsss_offload'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo $pending > 0 ? esc_html__('新上传的附件会自动进桶。', 'wpsss') : esc_html__('当前没有待补传的附件。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit" <?php disabled($pending <= 0); ?>><?php echo esc_html__('补传一批', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('download'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('拉回本地', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('将已进桶的附件写回本地 uploads 目录；默认保留远端对象。每批最多 20 个。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wpsss-need="wpsss_pull_confirm">
      <input type="hidden" name="action" value="wpsss_pull" />
      <?php wp_nonce_field('wpsss_pull'); ?>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_pull_confirm" value="1" /> <?php echo esc_html__('我确认在测试站执行拉回操作', 'wpsss'); ?></label>
        <label><input type="checkbox" name="wpsss_pull_delete" value="1" /> <?php echo esc_html__('同时删除远端对象，并将附件 URL 改回本地', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('已开启本地备份时，拉回会覆盖 uploads 中的同名文件。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit" data-wpsss-need-apply disabled><?php echo esc_html__('拉回一批', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('server'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('改写文章中的媒体地址', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('只替换你填写的来源地址。不会自动扫整站 uploads。序列化字段不修改。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wpsss-need="wpsss_rewrite_confirm">
      <input type="hidden" name="action" value="wpsss_rewrite" />
      <?php wp_nonce_field('wpsss_rewrite'); ?>
      <div class="field">
        <div><div class="field-label"><?php echo esc_html__('来源地址', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('必须填写要替换的旧地址，例如本站 uploads 或旧 CDN。', 'wpsss'); ?></div></div>
        <div class="field-ctl"><input type="text" name="wpsss_rewrite_from" value="<?php echo esc_attr($rewrite_from); ?>" placeholder="https://example.com/wp-content/uploads" required /></div>
      </div>
      <div class="field">
        <div><div class="field-label"><?php echo esc_html__('目标地址', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('留空则使用当前对外地址', 'wpsss'); ?></div></div>
        <div class="field-ctl"><input type="text" name="wpsss_rewrite_to" value="<?php echo esc_attr($rewrite_to); ?>" /></div>
      </div>
      <?php if ($rewrite !== []) : ?>
        <?php
          $result = isset($rewrite['result']) && is_array($rewrite['result']) ? $rewrite['result'] : [];
          $samples = isset($result['samples']) && is_array($result['samples']) ? $result['samples'] : [];
          $applied = !empty($rewrite['apply']);
          ?>
        <div class="notice <?php echo $applied ? 'ok' : 'info'; ?>">
          <?php echo Wpsss_Icons::svg('info'); ?>
          <span><?php echo esc_html(sprintf(
              '%s：扫描 %d 篇文章，命中 %d 篇；扫描 %d 个字段，命中 %d 个。',
              $applied ? __('已改写', 'wpsss') : __('预览结果', 'wpsss'),
              (int) ($result['scanned'] ?? 0),
              (int) ($result['changed'] ?? 0),
              (int) ($result['meta_scanned'] ?? 0),
              (int) ($result['meta_changed'] ?? 0)
          )); ?><?php echo $samples !== [] ? ' ' . esc_html(sprintf(__('示例附件 #%s', 'wpsss'), implode('、#', $samples))) : ''; ?></span>
        </div>
      <?php endif; ?>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_rewrite_confirm" value="1" /> <?php echo esc_html__('我确认在测试站执行地址改写', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('建议先预览，确认无误后再写入数据库。', 'wpsss'); ?></span>
        <span class="wpsss-actions">
          <button class="btn btn-ghost" type="submit" name="wpsss_rewrite_apply" value=""><?php echo esc_html__('预览', 'wpsss'); ?></button>
          <button class="btn btn-secondary" type="submit" name="wpsss_rewrite_apply" value="1" data-wpsss-need-apply disabled><?php echo esc_html__('写入数据库', 'wpsss'); ?></button>
        </span>
      </div>
    </form>
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

</div>
