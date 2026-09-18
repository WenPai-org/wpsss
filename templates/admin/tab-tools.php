<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$stats = isset($view['stats']) && is_array($view['stats']) ? $view['stats'] : ['total' => 0, 'offloaded' => 0, 'pending' => 0];
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
        <p class="card-sub"><?php echo esc_html(sprintf('媒体库一共 %d 个，已进桶 %d，还没传 %d。一次最多 20 个。留本地。', (int) $stats['total'], (int) $stats['offloaded'], (int) $stats['pending'])); ?></p>
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
      <span class="tile"><?php echo Wpsss_Icons::svg('server'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('改写文章里的本地上传地址', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('把正文、摘要和普通字段里的 /wp-content/uploads 换成桶域。序列化字段不改。先预览再写库。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_rewrite" />
      <?php wp_nonce_field('wpsss_rewrite'); ?>
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
