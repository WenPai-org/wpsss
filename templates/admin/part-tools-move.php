<?php
defined('ABSPATH') || exit;
/** @var string $panel */
/** @var string $rewrite_from */
/** @var string $rewrite_to */
/** @var array<string, mixed> $rewrite */
?>
  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('download'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('拉回本地', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('将已进桶的附件写回本地 uploads 目录；默认保留远端对象。每批最多 20 个。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wpsss-need="wpsss_pull_confirm" data-wpsss-confirm="<?php echo esc_attr__('确认拉回本地？会覆盖 uploads 里的同名文件。', 'wpsss'); ?>">
      <input type="hidden" name="action" value="wpsss_pull" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_pull'); ?>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_pull_confirm" value="1" /> <?php echo esc_html__('我确认拉回本地，并接受覆盖 uploads 中的同名文件', 'wpsss'); ?></label>
        <label><input type="checkbox" name="wpsss_pull_delete" value="1" class="js-wpsss-pull-delete" /> <?php echo esc_html__('同时永久删除桶内对象', 'wpsss'); ?></label>
        <label class="js-wpsss-pull-delete-confirm wpsss-gate-extra" hidden><input type="checkbox" name="wpsss_pull_delete_confirm" value="1" /> <?php echo esc_html__('我明白删除后无法从回收站恢复，文章里的桶地址会失效', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('默认可逆：只写回本地，远端仍留着。勾选删除须第二次确认。', 'wpsss'); ?></span>
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
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
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
        <label><input type="checkbox" name="wpsss_rewrite_confirm" value="1" /> <?php echo esc_html__('我确认写入数据库，并已先预览命中范围', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('建议先预览，确认无误后再写入数据库。', 'wpsss'); ?></span>
        <span class="wpsss-actions">
          <button class="btn btn-ghost" type="submit" name="wpsss_rewrite_apply" value=""><?php echo esc_html__('预览', 'wpsss'); ?></button>
          <button class="btn btn-secondary" type="submit" name="wpsss_rewrite_apply" value="1" data-wpsss-need-apply disabled data-wpsss-confirm="<?php echo esc_attr__('确认把文章和字段里的地址写入数据库？建议先预览。', 'wpsss'); ?>"><?php echo esc_html__('写入数据库', 'wpsss'); ?></button>
        </span>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('解除进桶标记', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('插件不再改写这些附件。桶内对象保留。用于迁走插件或换对接方式。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wpsss-need="wpsss_release_confirm">
      <input type="hidden" name="action" value="wpsss_release" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_release'); ?>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_release_confirm" value="1" /> <?php echo esc_html__('我确认解除进桶标记，并明白前台可能改回本地地址', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('每批最多 20 个。此操作不删桶。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit" data-wpsss-need-apply disabled data-wpsss-confirm="<?php echo esc_attr__('确认解除进桶标记？桶内对象不会删除，但插件不再改写这些附件。', 'wpsss'); ?>"><?php echo esc_html__('解除一批', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('server'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('导出设置', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('下载不含密钥的 JSON，便于备份或在另一站点对照。密钥不会写入文件。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_export" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_export'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('AccessKey 与 Secret 不会出现在导出文件中。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('导出 JSON', 'wpsss'); ?></button>
      </div>
    </form>
  </section>
