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
        'help' => __('仅当 uploads 目录缺少对应文件时从存储桶下载；已有文件保持不变，不修改对外 URL。', 'wpsss'),
        'confirm' => '',
        'dialog' => '',
    ],
    'rename' => [
        'label' => __('旧文件改名保留', 'wpsss'),
        'help' => __('先把现有本地文件改名为「原名-local-时间戳」保留，再写入桶里的文件；媒体库条目与存储路径不变。', 'wpsss'),
        'confirm' => __('我确认先把已有本地文件改名保留，再写入桶里的文件', 'wpsss'),
        'dialog' => __('已有本地文件会先改名为「原名-local-时间戳」保留，再写入桶里的文件。是否继续？', 'wpsss'),
    ],
    'overwrite' => [
        'label' => __('覆盖', 'wpsss'),
        'help' => __('用存储桶中的文件直接替换 uploads 中的同名文件；替换后无法从回收站恢复。', 'wpsss'),
        'confirm' => __('我确认覆盖 uploads 中的同名文件', 'wpsss'),
        'dialog' => __('将覆盖 uploads 中的同名文件，且无法从回收站恢复；默认不删除存储桶对象。是否继续？', 'wpsss'),
    ],
];
?>
  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('download'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('拉回本地', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('把桶里的文件下载回服务器的 uploads 目录。请先选择范围，再选择本地已有同名文件时的处理方式。默认只写磁盘，对外地址仍指向存储桶。', 'wpsss'); ?></p>
      </div>
    </div>
    <form class="js-wpsss-pull-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-mode="<?php echo esc_attr($pull_exist); ?>" data-scope="<?php echo esc_attr($pull_scope); ?>">
      <input type="hidden" name="action" value="wpsss_pull" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_pull'); ?>
      <p class="wpsss-pull-kicker"><?php echo esc_html__('选择范围', 'wpsss'); ?></p>
      <div class="seg wpsss-pull-seg" role="radiogroup" aria-label="<?php echo esc_attr__('选择范围', 'wpsss'); ?>">
        <label class="<?php echo $pull_scope === 'batch' ? 'on' : ''; ?>">
          <input type="radio" name="wpsss_pull_scope" value="batch" class="js-wpsss-pull-scope" <?php checked($pull_scope === 'batch'); ?> />
          <?php echo esc_html__('批量拉回', 'wpsss'); ?>
        </label>
        <label class="<?php echo $pull_scope === 'one' ? 'on' : ''; ?>">
          <input type="radio" name="wpsss_pull_scope" value="one" class="js-wpsss-pull-scope" <?php checked($pull_scope === 'one'); ?> />
          <?php echo esc_html__('单个附件', 'wpsss'); ?>
        </label>
      </div>
      <p class="wpsss-pull-hint js-wpsss-pull-scope-hint"><?php echo $pull_scope === 'one'
          ? esc_html__('仅处理指定的一个附件。编号可在「媒体 → 媒体库」中打开文件后查看（例如 128）。', 'wpsss')
          : esc_html__('对已进桶的附件按媒体库编号从新到旧拉回，一次拉一个；关掉页面即停，再点「拉回」可继续。', 'wpsss'); ?></p>
      <div class="field js-wpsss-pull-one"<?php echo $pull_scope === 'one' ? '' : ' hidden'; ?>>
        <div>
          <div class="field-label"><?php echo esc_html__('媒体库编号', 'wpsss'); ?></div>
          <div class="field-help"><?php echo esc_html__('附件编号，可在媒体库打开文件后，从地址栏或右侧详情中查看。', 'wpsss'); ?></div>
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
                  $bits[] = __('对外地址仍指向存储桶', 'wpsss');
              }
          } else {
              $bits[] = sprintf(__('将写入 %d', 'wpsss'), (int) ($result['ok'] ?? 0));
              $bits[] = sprintf(__('跳过 %d', 'wpsss'), (int) ($result['skip'] ?? 0));
              if ((int) ($result['renamed'] ?? 0) > 0) {
                  $bits[] = sprintf(__('改名保留 %d', 'wpsss'), (int) $result['renamed']);
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
          <span><strong><?php echo esc_html__('桶里没有回收站。', 'wpsss'); ?></strong> <?php echo esc_html__('对象存储控制台通常也无法恢复。文章、缩略图、付费下载若仍指向桶地址，删除后前台图片将立即无法显示。', 'wpsss'); ?><?php echo class_exists('WooCommerce', false) ? ' ' . esc_html__('Woo 付费下载若仍指向桶地址，删除对象将影响订单交付。', 'wpsss') : ''; ?></span>
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
      <p class="meta js-wpsss-pull-progress" aria-live="polite"></p>
      <div class="card-foot start">
        <span class="meta"><?php
          if ((int) ($pull_cursor ?? 0) > 0 && $pull_id <= 0) {
              echo esc_html(sprintf(__('已处理到编号 %d，下一批从编号更小的附件继续。', 'wpsss'), (int) $pull_cursor));
          } else {
              echo esc_html__('建议先预览再执行。仅写入磁盘，对外 URL 默认仍指向存储桶。', 'wpsss');
          }
        ?></span>
        <span class="wpsss-actions">
          <?php if ((int) ($pull_cursor ?? 0) > 0 && $pull_id <= 0) : ?>
            <button class="btn btn-ghost" type="submit" name="wpsss_pull_reset" value="1"><?php echo esc_html__('从头', 'wpsss'); ?></button>
          <?php endif; ?>
          <button class="btn btn-ghost js-wpsss-pull-go" type="submit" name="wpsss_pull_apply" value=""><?php echo esc_html__('预览', 'wpsss'); ?></button>
          <button class="btn btn-ghost js-wpsss-pull-stop" type="button" hidden><?php echo esc_html__('暂停', 'wpsss'); ?></button>
          <button class="btn btn-secondary js-wpsss-pull-go js-wpsss-pull-run" type="submit" name="wpsss_pull_apply" value="1" data-wpsss-need-apply <?php echo $pull_exist === 'skip' ? '' : 'disabled '; ?>data-wpsss-label="<?php echo esc_attr__('拉回', 'wpsss'); ?>" data-wpsss-label-delete="<?php echo esc_attr__('拉回并删除', 'wpsss'); ?>" data-wpsss-confirm="<?php echo esc_attr($exist_modes[$pull_exist]['dialog']); ?>"><?php echo esc_html__('拉回', 'wpsss'); ?></button>
        </span>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('server'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('改写文章中的媒体地址', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('只替换你填写的来源地址。会写入正文、摘要和文章自定义字段（post meta，含序列化数据）。不会改网站设置（options）、主题设置（theme_mods）和磁盘上的 CSS。', 'wpsss'); ?></p>
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
        <span><?php echo esc_html__('写入正文、摘要和文章自定义字段（post meta，含序列化数据），插件不能撤销。不会改网站设置（options）、主题设置（theme_mods）和磁盘上的 CSS。写完请清页面缓存和 CDN。', 'wpsss'); ?></span>
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
        <span><?php echo esc_html__('只去掉插件的进桶标记，不删除桶内对象。前台可能改回本地 uploads 地址。改完请清页面缓存。', 'wpsss'); ?></span>
      </div>
      <p class="wpsss-backup-note"><?php echo $keep_on
          ? esc_html__('备份：本地备份已开启，前台通常会改回 uploads 地址。请先确认本地文件仍在。', 'wpsss')
          : esc_html__('备份：本地备份已关闭。解除后若本地无文件，前台图片可能无法显示。请先拉回，或确认本地仍有副本。', 'wpsss'); ?></p>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_release_confirm" value="1" /> <?php echo $keep_on
            ? esc_html__('我明白前台会改回本地地址', 'wpsss')
            : esc_html__('我确认本地仍有文件，或接受前台图片可能无法显示', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('每批最多 20 个，不会删除桶内对象。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit" data-wpsss-need-apply disabled data-wpsss-confirm="<?php echo esc_attr__('确认解除进桶标记？不会删除桶内对象。若本地没有文件，前台图片可能无法显示。', 'wpsss'); ?>"><?php echo esc_html__('解除一批', 'wpsss'); ?></button>
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
