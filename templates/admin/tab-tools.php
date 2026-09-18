<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$stats = isset($view['stats']) && is_array($view['stats']) ? $view['stats'] : ['total' => 0, 'offloaded' => 0, 'pending' => 0, 'failed' => 0];
$history = isset($view['history']) && is_array($view['history']) ? $view['history'] : [];
$opts = $view['opts'];
?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('工具', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo esc_html__('危险动作放这里，不和普通配置混在一起。', 'wpsss'); ?></p>
  </div>
</div>
<div class="wpcy-stack">
  <section class="card">
    <div class="card-head">
      <span class="tile accent"><?php echo Wpsss_Icons::svg('plug'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('测试连通', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('往桶写一个探测对象再立刻删。先保存再测。', 'wpsss'); ?></p>
      </div>
    </div>
    <div class="notice info">
      <?php echo Wpsss_Icons::svg('info'); ?>
      <span><?php echo esc_html__('成功只说「已写入并删除探测对象」。失败用「暂时无法接通存储桶，」加上原因。', 'wpsss'); ?></span>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_probe" />
      <?php wp_nonce_field('wpsss_probe'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('不要对生产桶做探测以外的批量操作。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('测试连通', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile<?php echo ((int) $stats['pending'] > 0) ? ' warn' : ' ok'; ?>"><?php echo Wpsss_Icons::svg('image'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('补传已有附件', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html(sprintf(
            '媒体库一共 %d 个，已进桶 %d，还没传 %d，失败 %d。一次最多 20 个。',
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
        <span class="meta"><?php echo esc_html__('只写测试桶。新上传会自动进桶。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('补传一批', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('download'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('拉回本地', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('把已进桶的附件再写成 uploads。默认不删远端。一次最多 20 个。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_pull" />
      <?php wp_nonce_field('wpsss_pull'); ?>
      <div class="chk" style="padding:0 20px 12px">
        <label><input type="checkbox" name="wpsss_pull_confirm" value="1" /> <?php echo esc_html__('我确认拉回测试站附件', 'wpsss'); ?></label>
      </div>
      <div class="chk" style="padding:0 20px 12px">
        <label><input type="checkbox" name="wpsss_pull_delete" value="1" /> <?php echo esc_html__('同时删远端（附件 URL 改回本地）', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('keep_local=1 时本地多半已在，拉回会覆盖。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('拉回一批', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('server'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('改写文章里的本地上传地址', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('把正文、摘要和普通字段里的地址换成桶域。序列化字段不改。先预览再写库。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_rewrite" />
      <?php wp_nonce_field('wpsss_rewrite'); ?>
      <div class="field" style="padding:0 20px">
        <div><div class="field-label"><?php echo esc_html__('从', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('空着就用本站 uploads', 'wpsss'); ?></div></div>
        <div class="field-ctl"><input type="text" name="wpsss_rewrite_from" value="<?php echo esc_attr((string) $opts['rewrite_from']); ?>" placeholder="https://old.example.com/wp-content/uploads" /></div>
      </div>
      <div class="field" style="padding:0 20px">
        <div><div class="field-label"><?php echo esc_html__('到', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('空着就用当前对外地址', 'wpsss'); ?></div></div>
        <div class="field-ctl"><input type="text" name="wpsss_rewrite_to" value="<?php echo esc_attr((string) $view['public_base']); ?>" /></div>
      </div>
      <div class="chk" style="padding:0 20px 12px">
        <label><input type="checkbox" name="wpsss_rewrite_confirm" value="1" /> <?php echo esc_html__('我确认这是测试站 / 要改的就是这些文章', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <button class="btn btn-ghost" type="submit" name="wpsss_rewrite_apply" value=""><?php echo esc_html__('预览', 'wpsss'); ?></button>
        <button class="btn btn-secondary" type="submit" name="wpsss_rewrite_apply" value="1"><?php echo esc_html__('写进文章', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('同步记录', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('最近 50 条进桶 / 失败 / 删除。对照四家插件的同步历史。', 'wpsss'); ?></p>
      </div>
    </div>
    <?php if ($history === []) : ?>
      <p class="section-desc" style="padding:0 20px 16px"><?php echo esc_html__('还没有记录。', 'wpsss'); ?></p>
    <?php else : ?>
      <ul class="svc-stack" style="padding:0 20px 16px">
        <?php foreach (array_slice($history, 0, 12) as $row) : ?>
          <li>
            <div>
              <div class="n"><?php echo esc_html((string) ($row['event'] ?? '')); ?></div>
              <div class="p"><?php echo esc_html((string) ($row['t'] ?? '') . ' · ' . (string) ($row['detail'] ?? '')); ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('flash'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('按 URL 清边缘', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('以后接 cybercdn-url-purge。现在不开。', 'wpsss'); ?></p>
      </div>
      <span class="pill"><?php echo esc_html__('即将提供', 'wpsss'); ?></span>
    </div>
    <div class="card-foot">
      <button class="btn btn-secondary" type="button" disabled><?php echo esc_html__('按 URL 清边缘', 'wpsss'); ?></button>
    </div>
  </section>
</div>
