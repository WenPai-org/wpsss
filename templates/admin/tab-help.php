<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$on = (bool) $view['configured'];
$docs = static function (string $slug): string {
    return WPSSS_DOCS_URL . '/' . $slug;
};
?>
<div class="wenpai-page-head">
  <div>
    <h1 class="wenpai-h1"><?php echo esc_html__('帮助', 'wpsss'); ?></h1>
    <p class="wenpai-lede"><?php echo esc_html__('媒体进桶，网页仍从自己的服务器打开。', 'wpsss'); ?></p>
  </div>
</div>

<section class="card wpsss-help-card">
  <div class="card-head">
    <span class="tile ok"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
    <div>
      <h2 class="card-title"><?php echo esc_html__('快速开始', 'wpsss'); ?></h2>
      <p class="card-sub"><?php echo esc_html__('首次配置按以下顺序完成。', 'wpsss'); ?></p>
    </div>
  </div>
  <ol class="wpsss-help-steps">
    <li>
      <strong><?php echo esc_html__('填写桶与密钥', 'wpsss'); ?></strong>
      <span><?php echo esc_html__('请按设置向导选择云厂商，并保存桶名、区域与访问密钥。也可以在高级设置中手动填写。', 'wpsss'); ?></span>
      <span class="wpsss-help-step-actions">
        <a class="btn btn-secondary" href="<?php echo esc_url($url('setup')); ?>"><?php echo esc_html__('开始向导', 'wpsss'); ?></a>
        <a class="btn btn-ghost" href="<?php echo esc_url($docs('start-guide')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('图文教程', 'wpsss'); ?></a>
      </span>
    </li>
    <li>
      <strong><?php echo esc_html__('测试连通', 'wpsss'); ?></strong>
      <span><?php echo esc_html__('在工具页写入并删除探测对象，确认密钥与权限正确。', 'wpsss'); ?></span>
      <span class="wpsss-help-step-actions">
        <a class="btn btn-secondary" href="<?php echo esc_url($url('tools', ['panel' => 'daily'])); ?>"><?php echo esc_html__('前往工具', 'wpsss'); ?></a>
      </span>
    </li>
    <li>
      <strong><?php echo esc_html__('上传媒体', 'wpsss'); ?></strong>
      <span><?php echo esc_html__('新上传的附件会自动进入存储桶。已有附件请在工具页点「开始补传」。关闭页面后，后台会继续补传。', 'wpsss'); ?></span>
      <span class="wpsss-help-step-actions">
        <a class="btn btn-ghost" href="<?php echo esc_url($docs('upload-offload')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('补传说明', 'wpsss'); ?></a>
      </span>
    </li>
  </ol>
  <?php if ($on) : ?>
  <div class="card-foot start">
    <span class="meta"><?php echo esc_html__('当前已连接存储桶，可在概览查看运行体检。', 'wpsss'); ?></span>
    <a class="btn btn-ghost" href="<?php echo esc_url($url('overview')); ?>"><?php echo esc_html__('查看概览', 'wpsss'); ?></a>
  </div>
  <?php endif; ?>
</section>

<section class="card wpsss-help-card">
  <div class="card-head">
    <span class="tile"><?php echo Wpsss_Icons::svg('help'); ?></span>
    <div>
      <h2 class="card-title"><?php echo esc_html__('完整文档', 'wpsss'); ?></h2>
      <p class="card-sub"><?php echo esc_html__('每篇教程都在 wpcy.com 文档站，持续更新。', 'wpsss'); ?></p>
    </div>
  </div>
  <dl class="wpsss-help-dl">
    <dt><?php echo esc_html__('入门与配置', 'wpsss'); ?></dt>
    <dd>
      <a href="<?php echo esc_url($docs('start-guide')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('五分钟上手', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('choose-vendor')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('选择云厂商与站点场景', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('access-keys')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('密钥安全与最小权限', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('object-access')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('公开读与签名 URL', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('custom-domain')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('自定义域名与 CDN', 'wpsss'); ?></a>
    </dd>
    <dt><?php echo esc_html__('日常使用', 'wpsss'); ?></dt>
    <dd>
      <a href="<?php echo esc_url($docs('upload-offload')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('上传与补传', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('large-files')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('大文件分片', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('woocommerce')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('WooCommerce 付费下载', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('pull-local')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('拉回本地与卸载', 'wpsss'); ?></a>
    </dd>
    <dt><?php echo esc_html__('进阶', 'wpsss'); ?></dt>
    <dd>
      <a href="<?php echo esc_url($docs('rewrite-urls')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('迁移与地址改写', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('wp-cli')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('WP-CLI 手册', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('troubleshooting')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('体检与排障', 'wpsss'); ?></a> ·
      <a href="<?php echo esc_url($docs('faq')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('常见问题速答', 'wpsss'); ?></a>
    </dd>
  </dl>
</section>

<div class="wpsss-help-stack">
  <details class="card wpsss-help-details" open>
    <summary>
      <span class="tile"><?php echo Wpsss_Icons::svg('home'); ?></span>
      <span class="wpsss-help-summary-text">
        <strong><?php echo esc_html__('后台页面', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('概览、设置、工具各负责什么。', 'wpsss'); ?></span>
      </span>
    </summary>
    <div class="wpsss-help-body">
      <dl class="wpsss-help-dl">
        <dt><?php echo esc_html__('概览', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('当前连接状态、对外地址、本地备份与运行体检摘要。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('设置 · 简单', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('查看当前组合，并使用日常开关。开关立即生效。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('设置 · 高级', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('云厂商、桶名、区域、密钥、对象前缀、图片样式等完整选项。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('工具', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('分为日常、排障、迁移三组。高危操作请先勾选确认项。', 'wpsss'); ?></dd>
      </dl>
    </div>
  </details>

  <details class="card wpsss-help-details">
    <summary>
      <span class="tile"><?php echo Wpsss_Icons::svg('info'); ?></span>
      <span class="wpsss-help-summary-text">
        <strong><?php echo esc_html__('常见问题速答', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('最常见的六个问题；完整排障见文档。', 'wpsss'); ?></span>
      </span>
    </summary>
    <div class="wpsss-help-body">
      <dl class="wpsss-help-dl">
        <dt><?php echo esc_html__('上传后前台图片无法显示', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('使用公开读时，请确认存储桶允许匿名访问，或已绑定 HTTPS 自定义域名。使用签名访问时，插件会生成带有效期的地址。匿名访问被拒绝是预期结果。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('已有附件如何进桶', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('在「工具 → 日常」点「开始补传」。每次上传一个附件，可以暂停。关闭页面后，后台会继续补传。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('为什么点图片变成下载', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('自 2019 年起，阿里云存储桶的默认域名常把图片当作文件下载。请填写 HTTPS 自定义域名，或改用签名访问。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('文章里仍是旧的 uploads 地址', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('显示页面时，插件会自动改写已进桶附件的地址。要把新地址写入数据库，请到迁移页使用改写工具，先预览再写入。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('拉回会不会删掉桶里的文件', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('默认不会删除。勾选删除后会出现红色警示和三步核对。存储桶里没有回收站，请确认后再操作。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('想停用插件，但先保留存储桶中的文件', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('请使用「解除进桶标记」。插件不再改写这些附件，对象仍留在存储桶中。', 'wpsss'); ?></dd>
      </dl>
      <p class="wpsss-help-note"><a href="<?php echo esc_url($docs('troubleshooting')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('更多问题请查看排障文档', 'wpsss'); ?></a></p>
    </div>
  </details>

  <details class="card wpsss-help-details">
    <summary>
      <span class="tile"><?php echo Wpsss_Icons::svg('server'); ?></span>
      <span class="wpsss-help-summary-text">
        <strong><?php echo esc_html__('WP-CLI', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('在已激活插件的站点上可用。', 'wpsss'); ?></span>
      </span>
    </summary>
    <div class="wpsss-help-body">
      <pre class="wpsss-help-code"><code>wp sss status
wp sss doctor
wp sss probe
wp sss set --from-env
wp sss offload --dry-run
wp sss pull --dry-run --on-exist=skip
wp sss copy --to-bucket=新桶 --dry-run</code></pre>
      <p class="wpsss-help-note"><a href="<?php echo esc_url($docs('wp-cli')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('完整命令手册 →', 'wpsss'); ?></a><?php echo esc_html__('　密钥请通过环境文件配置，不要在命令行明文传递。', 'wpsss'); ?></p>
    </div>
  </details>

  <section class="card wpsss-help-card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('info'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('边界与支持范围', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('这些场景目前不支持或有限制，使用前请先了解。', 'wpsss'); ?></p>
      </div>
    </div>
    <ul class="wpsss-edge-list">
      <li><?php echo esc_html__('多站点（Multisite）暂不支持；不要在网络层激活。', 'wpsss'); ?></li>
      <li><?php echo esc_html__('腾讯云 COS、华为云 OBS 和七牛云的超大文件以单次请求上传，超时时间会随文件体积增加。超过 100MB 的文件建议使用阿里云 OSS 或 S3 兼容存储。', 'wpsss'); ?></li>
    </ul>
  </section>
  <section class="card wpsss-help-card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('feedback'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('需要更多帮助', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('产品文档与工单支持请访问 wpcy.com。', 'wpsss'); ?></p>
      </div>
    </div>
    <div class="card-foot wpsss-help-cta">
      <a class="btn btn-secondary" href="<?php echo esc_url(WPSSS_DOCS_URL); ?>" target="_blank" rel="noopener"><?php echo Wpsss_Icons::svg('external'); ?><?php echo esc_html__('文档中心', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="<?php echo esc_url(WPSSS_PRODUCT_URL); ?>" target="_blank" rel="noopener"><?php echo esc_html__('产品官网', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="<?php echo esc_url(WPSSS_SUPPORT_URL); ?>" target="_blank" rel="noopener"><?php echo esc_html__('提交反馈', 'wpsss'); ?></a>
    </div>
  </section>
</div>
