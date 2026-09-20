<?php
defined('ABSPATH') || exit;
/** @var string $panel */
/** @var string $rewrite_from */
/** @var string $rewrite_to */
/** @var array<string, mixed> $rewrite */
/** @var array<string, mixed> $opts */
$keep_on = (($opts['keep_local'] ?? '1') === '1');
?>
  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('download'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('拉回本地', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('把已进桶的附件写回这台服务器的 uploads。每批最多 20 个。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wpsss-need="wpsss_pull_confirm" data-wpsss-confirm="<?php echo esc_attr__('拉回会覆盖 uploads 里的同名文件。默认不删桶。继续？', 'wpsss'); ?>">
      <input type="hidden" name="action" value="wpsss_pull" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_pull'); ?>
      <div class="notice warn">
        <?php echo Wpsss_Icons::svg('info'); ?>
        <span><?php echo esc_html__('会覆盖 uploads 里的同名文件。默认不删桶；本地不对，还能再从桶里取。', 'wpsss'); ?></span>
      </div>
      <p class="wpsss-backup-note"><?php echo $keep_on
          ? esc_html__('备份：当前开着本地备份。若 uploads 里的文件和桶里不是同一版，先把本地那份拷走再拉回。', 'wpsss')
          : esc_html__('备份：当前没开本地备份。uploads 里可能本来就没有文件。删桶之前必须先拉回并打开核对，否则可能丢掉最后一份。', 'wpsss'); ?></p>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_pull_confirm" value="1" /> <?php echo esc_html__('接受覆盖 uploads 里的同名文件', 'wpsss'); ?></label>
        <label><input type="checkbox" name="wpsss_pull_delete" value="1" class="js-wpsss-pull-delete" /> <?php echo esc_html__('还要永久删除桶内对象', 'wpsss'); ?></label>
      </div>
      <div class="wpsss-caution js-wpsss-pull-delete-confirm wpsss-gate-extra" hidden>
        <div class="notice bad">
          <?php echo Wpsss_Icons::svg('info'); ?>
          <span><strong><?php echo esc_html__('桶里没有回收站。', 'wpsss'); ?></strong> <?php echo esc_html__('对象存储控制台通常也找不回来。文章、缩略图、付费下载若还指向桶地址，会立刻裂图。', 'wpsss'); ?></span>
        </div>
        <p class="wpsss-backup-note"><?php echo esc_html__('先做完这三步，再勾下面：', 'wpsss'); ?></p>
        <ol class="wpsss-backup-steps">
          <li><?php echo esc_html__('先不要勾删除，只拉回本地。', 'wpsss'); ?></li>
          <li><?php echo esc_html__('到媒体库打开文件，确认能看、体积正常。', 'wpsss'); ?></li>
          <li><?php echo $keep_on
              ? esc_html__('确认本地备份仍开着，或你已经另存了一份。', 'wpsss')
              : esc_html__('本地备份已关。请另存一份能打开的文件，再删桶。', 'wpsss'); ?></li>
        </ol>
        <div class="chk">
          <label><input type="checkbox" name="wpsss_pull_delete_confirm" value="1" /> <?php echo esc_html__('本地已经打开核对过，可以丢掉桶里那份', 'wpsss'); ?></label>
        </div>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('默认可逆：只写回本地。删桶是另一回事。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit" data-wpsss-need-apply disabled data-wpsss-label="<?php echo esc_attr__('拉回一批', 'wpsss'); ?>" data-wpsss-label-delete="<?php echo esc_attr__('拉回并删除', 'wpsss'); ?>"><?php echo esc_html__('拉回一批', 'wpsss'); ?></button>
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
      <div class="notice warn">
        <?php echo Wpsss_Icons::svg('info'); ?>
        <span><?php echo esc_html__('写入文章正文和普通字段，插件不能撤销。序列化字段不会改。', 'wpsss'); ?></span>
      </div>
      <p class="wpsss-backup-note"><?php echo esc_html__('备份：先点预览看命中篇数。写入前用主机面板、备份插件或 wp db export 留一份数据库。', 'wpsss'); ?></p>
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
        <label><input type="checkbox" name="wpsss_rewrite_confirm" value="1" /> <?php echo esc_html__('我已经预览过，并且有数据库备份', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('没有预览结果时，不要写入。', 'wpsss'); ?></span>
        <span class="wpsss-actions">
          <button class="btn btn-ghost" type="submit" name="wpsss_rewrite_apply" value=""><?php echo esc_html__('预览', 'wpsss'); ?></button>
          <button class="btn btn-secondary" type="submit" name="wpsss_rewrite_apply" value="1" data-wpsss-need-apply disabled data-wpsss-confirm="<?php echo esc_attr__('会写入数据库，插件不能撤销。确认已经预览，并且有数据库备份？', 'wpsss'); ?>"><?php echo esc_html__('写入数据库', 'wpsss'); ?></button>
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
      <div class="notice warn">
        <?php echo Wpsss_Icons::svg('info'); ?>
        <span><?php echo esc_html__('只去掉插件标记，不删桶。前台可能改回本地 uploads 地址。', 'wpsss'); ?></span>
      </div>
      <p class="wpsss-backup-note"><?php echo $keep_on
          ? esc_html__('备份：本地备份开着，前台多半改回 uploads。先确认本地文件还在。', 'wpsss')
          : esc_html__('备份：本地备份已关。解除后前台可能裂图。先拉回，或确认本地还有文件。', 'wpsss'); ?></p>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_release_confirm" value="1" /> <?php echo $keep_on
            ? esc_html__('我明白前台会改回本地地址', 'wpsss')
            : esc_html__('我确认本地还有文件，或接受前台可能裂图', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('每批最多 20 个。此操作不删桶。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit" data-wpsss-need-apply disabled data-wpsss-confirm="<?php echo esc_attr__('确认解除进桶标记？不删桶。若本地没有文件，前台可能裂图。', 'wpsss'); ?>"><?php echo esc_html__('解除一批', 'wpsss'); ?></button>
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
