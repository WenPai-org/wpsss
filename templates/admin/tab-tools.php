<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
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
