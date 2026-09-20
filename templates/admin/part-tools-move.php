<?php
defined('ABSPATH') || exit;
/** @var string $panel */
/** @var string $rewrite_from */
/** @var string $rewrite_to */
/** @var array<string, mixed> $rewrite */
/** @var array<string, mixed> $pull */
/** @var array<string, mixed> $opts */
$keep_on = (($opts['keep_local'] ?? '1') === '1');
$pull_exist = Wpsss_Offload::sanitize_on_exist((string) ($pull['on_exist'] ?? 'skip'));
$pull_id = (int) ($pull['only_id'] ?? 0);
$pull_scope = $pull_id > 0 ? 'one' : 'batch';
$exist_modes = [
    'skip' => [
        'label' => __('只补缺失', 'wpsss'),
        'help' => __('服务器上没有的才从桶里取。已经有的文件原样留着。不改网页地址。', 'wpsss'),
        'confirm' => '',
        'dialog' => '',
    ],
    'rename' => [
        'label' => __('旁路改名', 'wpsss'),
        'help' => __('本地那份改名留底（原名-local-时间），再用桶里的文件占原来的名字。媒体库编号不变。', 'wpsss'),
        'confirm' => __('我确认把已有本地文件旁路改名后再写入', 'wpsss'),
        'dialog' => __('已有本地文件会改成 原名-local-时间，正式文件名仍按媒体库写入桶里那份。继续？', 'wpsss'),
    ],
    'overwrite' => [
        'label' => __('覆盖', 'wpsss'),
        'help' => __('用桶里的文件直接换掉本地同名文件。换掉就没有回收站。', 'wpsss'),
        'confirm' => __('接受覆盖 uploads 里的同名文件', 'wpsss'),
        'dialog' => __('会覆盖 uploads 里的同名文件。覆盖前没有回收站。默认不删桶。继续？', 'wpsss'),
    ],
];
?>
  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('download'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('拉回本地', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('从桶里把文件写回这台服务器。先选拉哪些，再选本地已有时怎么办。网页地址默认还是桶。', 'wpsss'); ?></p>
      </div>
    </div>
    <form class="js-wpsss-pull-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-mode="<?php echo esc_attr($pull_exist); ?>" data-scope="<?php echo esc_attr($pull_scope); ?>">
      <input type="hidden" name="action" value="wpsss_pull" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_pull'); ?>
      <p class="wpsss-pull-kicker"><?php echo esc_html__('拉哪些', 'wpsss'); ?></p>
      <div class="seg wpsss-pull-seg" role="radiogroup" aria-label="<?php echo esc_attr__('拉哪些', 'wpsss'); ?>">
        <label class="<?php echo $pull_scope === 'batch' ? 'on' : ''; ?>">
          <input type="radio" name="wpsss_pull_scope" value="batch" class="js-wpsss-pull-scope" <?php checked($pull_scope === 'batch'); ?> />
          <?php echo esc_html__('下一批', 'wpsss'); ?>
        </label>
        <label class="<?php echo $pull_scope === 'one' ? 'on' : ''; ?>">
          <input type="radio" name="wpsss_pull_scope" value="one" class="js-wpsss-pull-scope" <?php checked($pull_scope === 'one'); ?> />
          <?php echo esc_html__('指定一个', 'wpsss'); ?>
        </label>
      </div>
      <p class="wpsss-pull-hint js-wpsss-pull-scope-hint"><?php echo $pull_scope === 'one'
          ? esc_html__('只拉媒体库里的这一个。编号在「媒体 → 媒体库」点开文件后能看到，例如 128。', 'wpsss')
          : esc_html__('已进桶的按编号从新到旧拉，一次最多 20 个。要继续就再点一次。', 'wpsss'); ?></p>
      <div class="field js-wpsss-pull-one"<?php echo $pull_scope === 'one' ? '' : ' hidden'; ?>>
        <div>
          <div class="field-label"><?php echo esc_html__('媒体库编号', 'wpsss'); ?></div>
          <div class="field-help"><?php echo esc_html__('就是附件 ID。在媒体库点开文件，地址栏或右侧详情里的数字。', 'wpsss'); ?></div>
        </div>
        <div class="field-ctl"><input type="number" name="wpsss_pull_id" class="js-wpsss-pull-id" value="<?php echo $pull_id > 0 ? (int) $pull_id : ''; ?>" min="1" step="1" placeholder="128" <?php echo $pull_scope === 'one' ? '' : 'disabled'; ?> /></div>
      </div>
      <p class="wpsss-pull-kicker"><?php echo esc_html__('本地已有文件时', 'wpsss'); ?></p>
      <div class="seg wpsss-pull-seg" role="radiogroup" aria-label="<?php echo esc_attr__('本地已有文件时', 'wpsss'); ?>">
        <?php foreach ($exist_modes as $id => $row) : ?>
          <label class="<?php echo $pull_exist === $id ? 'on' : ''; ?>">
            <input type="radio" name="wpsss_pull_exist" value="<?php echo esc_attr($id); ?>" class="js-wpsss-pull-exist" <?php checked($pull_exist === $id); ?> />
            <?php echo esc_html($row['label']); ?>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="wpsss-pull-hint js-wpsss-pull-hint<?php echo $pull_exist === 'overwrite' ? ' is-warn' : ''; ?>"><?php echo esc_html($exist_modes[$pull_exist]['help']); ?></p>
      <?php if ($pull !== []) : ?>
        <?php
          $result = isset($pull['result']) && is_array($pull['result']) ? $pull['result'] : [];
          $applied = !empty($pull['apply']);
          $bits = [];
          if ($applied) {
              $bits[] = sprintf(__('已拉回 %d', 'wpsss'), (int) ($result['ok'] ?? 0));
              if ((int) ($result['fail'] ?? 0) > 0) {
                  $bits[] = sprintf(__('失败 %d', 'wpsss'), (int) $result['fail']);
              }
              if (empty($pull['delete'])) {
                  $bits[] = __('前台仍走桶', 'wpsss');
              }
          } else {
              $bits[] = sprintf(__('将写入 %d', 'wpsss'), (int) ($result['ok'] ?? 0));
              $bits[] = sprintf(__('跳过 %d', 'wpsss'), (int) ($result['skip'] ?? 0));
              if ((int) ($result['renamed'] ?? 0) > 0) {
                  $bits[] = sprintf(__('旁路 %d', 'wpsss'), (int) $result['renamed']);
              }
          }
          if ((int) ($result['blocked'] ?? 0) > 0) {
              $bits[] = sprintf(__('前缀不符 %d', 'wpsss'), (int) $result['blocked']);
          }
          ?>
        <p class="wpsss-pull-result"><?php echo esc_html(implode(' · ', $bits)); ?></p>
      <?php endif; ?>
      <div class="chk wpsss-gate js-wpsss-pull-gate"<?php echo $pull_exist === 'skip' ? ' hidden' : ''; ?>>
        <label><input type="checkbox" name="wpsss_pull_confirm" value="1" /> <span class="js-wpsss-pull-confirm-text"><?php echo esc_html($exist_modes[$pull_exist]['confirm']); ?></span></label>
        <label class="js-wpsss-pull-delete-wrap"><input type="checkbox" name="wpsss_pull_delete" value="1" class="js-wpsss-pull-delete" /> <?php echo esc_html__('还要永久删除桶内对象', 'wpsss'); ?></label>
      </div>
      <div class="wpsss-caution js-wpsss-pull-delete-confirm wpsss-gate-extra" hidden>
        <div class="notice bad">
          <?php echo Wpsss_Icons::svg('info'); ?>
          <span><strong><?php echo esc_html__('桶里没有回收站。', 'wpsss'); ?></strong> <?php echo esc_html__('对象存储控制台通常也找不回来。文章、缩略图、付费下载若还指向桶地址，会立刻裂图。', 'wpsss'); ?><?php echo class_exists('WooCommerce', false) ? ' ' . esc_html__('Woo 付费下载若仍打桶，删桶等于动货款。', 'wpsss') : ''; ?></span>
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
        <span class="meta"><?php
          if ((int) ($pull_cursor ?? 0) > 0 && $pull_id <= 0) {
              echo esc_html(sprintf('下一批会接着编号小于 %d 的。', (int) $pull_cursor));
          } else {
              echo esc_html__('先预览再拉回。只写磁盘，网页地址默认还是桶。', 'wpsss');
          }
        ?></span>
        <span class="wpsss-actions">
          <?php if ((int) ($pull_cursor ?? 0) > 0 && $pull_id <= 0) : ?>
            <button class="btn btn-ghost" type="submit" name="wpsss_pull_reset" value="1"><?php echo esc_html__('从头', 'wpsss'); ?></button>
          <?php endif; ?>
          <button class="btn btn-ghost js-wpsss-pull-go" type="submit" name="wpsss_pull_apply" value=""><?php echo esc_html__('预览', 'wpsss'); ?></button>
          <button class="btn btn-secondary js-wpsss-pull-go" type="submit" name="wpsss_pull_apply" value="1" data-wpsss-need-apply <?php echo $pull_exist === 'skip' ? '' : 'disabled '; ?>data-wpsss-label="<?php echo esc_attr__('拉回', 'wpsss'); ?>" data-wpsss-label-delete="<?php echo esc_attr__('拉回并删除', 'wpsss'); ?>" data-wpsss-confirm="<?php echo esc_attr($exist_modes[$pull_exist]['dialog']); ?>"><?php echo esc_html__('拉回', 'wpsss'); ?></button>
        </span>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('server'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('改写文章中的媒体地址', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('只替换你填写的来源地址。会改正文、摘要和 post meta（含序列化）。不改 options、theme_mods、磁盘 CSS。', 'wpsss'); ?></p>
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
        <span><?php echo esc_html__('写入正文、摘要和 post meta（含序列化），插件不能撤销。不改 options / theme_mods / 磁盘 CSS。写完请清页面缓存和 CDN。', 'wpsss'); ?></span>
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
        <span><?php echo esc_html__('只去掉插件标记，不删桶。前台可能改回本地 uploads 地址。改完请清页面缓存。', 'wpsss'); ?></span>
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
