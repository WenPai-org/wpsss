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

<div class="wpsss-help-stack">
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
  <div class="wpsss-help-docs">
    <?php foreach ([
        __('入门与配置', 'wpsss') => [
            'start-guide' => __('五分钟上手', 'wpsss'),
            'choose-vendor' => __('选择云厂商与站点场景', 'wpsss'),
            'access-keys' => __('密钥安全与最小权限', 'wpsss'),
            'object-access' => __('公开读与签名 URL', 'wpsss'),
            'custom-domain' => __('自定义域名与 CDN', 'wpsss'),
        ],
        __('日常使用', 'wpsss') => [
            'upload-offload' => __('上传与补传', 'wpsss'),
            'large-files' => __('大文件分片', 'wpsss'),
            'woocommerce' => __('WooCommerce 付费下载', 'wpsss'),
            'pull-local' => __('拉回本地与卸载', 'wpsss'),
        ],
        __('进阶', 'wpsss') => [
            'rewrite-urls' => __('迁移与地址改写', 'wpsss'),
            'wp-cli' => __('WP-CLI 手册', 'wpsss'),
            'troubleshooting' => __('体检与排障', 'wpsss'),
            'faq' => __('常见问题速答', 'wpsss'),
        ],
    ] as $doc_label => $doc_items) : ?>
    <div class="wpsss-help-doc-group">
      <p class="wpsss-help-doc-label"><?php echo esc_html($doc_label); ?></p>
      <ul>
        <?php foreach ($doc_items as $doc_slug => $doc_title) : ?>
        <li><a href="<?php echo esc_url($docs($doc_slug)); ?>" target="_blank" rel="noopener"><?php echo esc_html($doc_title); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>
</section>

  <section class="card wpsss-help-card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('info'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('支持范围', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('当前可用的站点形态，以及各厂商在大文件和访问地址上的差别。', 'wpsss'); ?></p>
      </div>
    </div>
    <ul class="wpsss-scope">
      <li>
        <strong><?php echo esc_html__('站点', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('目前支持单个站点。多站点（Multisite）暂不支持，请在具体站点里激活。', 'wpsss'); ?></span>
      </li>
      <li>
        <strong><?php echo esc_html__('页面', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('上传的媒体进入存储桶。页面和文章仍从本站服务器打开。', 'wpsss'); ?></span>
      </li>
      <li>
        <strong><?php echo esc_html__('厂商', 'wpsss'); ?></strong>
        <span><?php
          echo esc_html__('目前接入阿里云 OSS、腾讯云 COS、华为云 OBS、七牛云、火山 TOS、Google Cloud Storage 和 S3 兼容存储。没有你的厂商时，请从页面右上角「', 'wpsss');
          echo '<a href="' . esc_url(WPSSS_SUPPORT_URL) . '" target="_blank" rel="noopener">' . esc_html__('反馈', 'wpsss') . '</a>';
          echo esc_html__('」告诉我们。', 'wpsss');
        ?></span>
      </li>
      <li>
        <strong><?php echo esc_html__('大文件', 'wpsss'); ?></strong>
        <span>
          <p><?php echo esc_html__('可以分片：阿里云 OSS、S3 兼容存储。', 'wpsss'); ?></p>
          <p><?php echo esc_html__('一次请求：腾讯云 COS、华为云 OBS、七牛云。超过 100MB 建议改用可以分片的厂商。', 'wpsss'); ?></p>
        </span>
      </li>
      <li>
        <strong><?php echo esc_html__('七牛云', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('访客地址必须使用 HTTPS 自定义域名。', 'wpsss'); ?></span>
      </li>
    </ul>
  </section>
</div>
