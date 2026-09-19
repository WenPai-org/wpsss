<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$on = (bool) $view['configured'];
?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('帮助', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo esc_html__('媒体库进对象存储，网站页面仍在源站。', 'wpsss'); ?></p>
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
      <span><?php echo esc_html__('跟随设置向导选择云厂商并保存桶名、区域与访问密钥；也可在高级设置中手动填写。', 'wpsss'); ?></span>
      <a class="btn btn-secondary" href="<?php echo esc_url($url('setup')); ?>"><?php echo esc_html__('开始向导', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>"><?php echo esc_html__('高级设置', 'wpsss'); ?></a>
    </li>
    <li>
      <strong><?php echo esc_html__('测试连通', 'wpsss'); ?></strong>
      <span><?php echo esc_html__('在工具页写入并删除探测对象，确认密钥与权限正确。', 'wpsss'); ?></span>
      <a class="btn btn-secondary" href="<?php echo esc_url($url('tools')); ?>"><?php echo esc_html__('前往工具', 'wpsss'); ?></a>
    </li>
    <li>
      <strong><?php echo esc_html__('上传媒体', 'wpsss'); ?></strong>
      <span><?php echo esc_html__('新上传的附件将自动同步至存储桶；已有附件可在工具页补传。', 'wpsss'); ?></span>
    </li>
  </ol>
  <?php if ($on) : ?>
  <div class="card-foot start">
    <span class="meta"><?php echo esc_html__('当前已连接存储桶，可在概览查看运行体检。', 'wpsss'); ?></span>
    <a class="btn btn-ghost" href="<?php echo esc_url($url('overview')); ?>"><?php echo esc_html__('查看概览', 'wpsss'); ?></a>
  </div>
  <?php endif; ?>
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
        <dd><?php echo esc_html__('站点场景与常用开关；保存后即时生效。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('设置 · 高级', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('云厂商、桶名、区域、密钥、对象前缀、图片样式等完整选项。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('工具', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('测试连通、补传已有附件、拉回本地、改写文章中的媒体地址与同步记录。', 'wpsss'); ?></dd>
      </dl>
    </div>
  </details>

  <details class="card wpsss-help-details">
    <summary>
      <span class="tile"><?php echo Wpsss_Icons::svg('equalizer'); ?></span>
      <span class="wpsss-help-summary-text">
        <strong><?php echo esc_html__('站点场景与访问方式', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('按站点类型选择推荐厂商与对象访问模式。', 'wpsss'); ?></span>
      </span>
    </summary>
    <div class="wpsss-help-body">
      <dl class="wpsss-help-dl">
        <dt><?php echo esc_html__('国内站', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('访客与服务器主要位于中国大陆，推荐国内云对象存储。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('跨境 · 外贸站', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('服务器与访客在海外，推荐 S3 兼容或海外区域；国内云仍可选。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('混合站', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('访客来源包含境内外，两组厂商均列出供选择。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('公开读', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('附件 URL 可直接访问，要求存储桶允许匿名读取或使用已备案的自定义域名。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('签名访问', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('私有桶通过带有效期的签名 URL 对外提供；匿名探测返回 403 属预期。', 'wpsss'); ?></dd>
      </dl>
    </div>
  </details>

  <details class="card wpsss-help-details">
    <summary>
      <span class="tile"><?php echo Wpsss_Icons::svg('info'); ?></span>
      <span class="wpsss-help-summary-text">
        <strong><?php echo esc_html__('常见问题', 'wpsss'); ?></strong>
        <span><?php echo esc_html__('排查上传、访问与同步中的典型情况。', 'wpsss'); ?></span>
      </span>
    </summary>
    <div class="wpsss-help-body">
      <dl class="wpsss-help-dl">
        <dt><?php echo esc_html__('上传后前台图片无法显示', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('公开读模式下，请确认存储桶允许匿名访问或已配置 HTTPS 自定义域名。签名模式下前台由插件生成签名 URL。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('运行体检提示匿名访问受限', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('若已启用签名访问，匿名 403 为正常结果，不代表上传失败。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('本地备份是什么', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('开启后，文件进桶的同时保留服务器 uploads 目录中的副本，便于回滚与插件兼容。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('已有附件如何进桶', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('在工具页使用「补传已有附件」，或在媒体库对单个附件操作。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('文章里仍是旧的 uploads 地址', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('在工具页使用「改写文章中的媒体地址」，先预览命中数量，确认后再写入数据库。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('整站 HTML 会上传吗', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('不会。仅媒体库附件与相关对象进桶，页面 HTML 仍在 WordPress 源站。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('为什么点图片变成下载', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('阿里云 2019 年后默认域名常强制下载。请绑定 HTTPS 自定义域名，或使用签名访问。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('主题或其它插件自己传到 uploads，为什么没进桶', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('只拦 WordPress 媒体库钩子。第三方直传请在桶上开镜像回源到源站，或调用 wpsss_file_upload()。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('腾讯云 APPID 填谁的', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('填创建存储桶的主账号 APPID，不是子账户的。桶名已含 APPID 时可留空。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('七牛为什么必须填自定义域名', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('KODO 默认域不能稳定给访客用。自定义域留空则对外地址为空，前台会裂图。', 'wpsss'); ?></dd>
        <dt><?php echo esc_html__('内容审核做了什么', 'wpsss'); ?></dt>
        <dd><?php echo esc_html__('仅腾讯云 COS。打开后新评论会先走文本检测；接口失败时放行并记日志，避免误杀。', 'wpsss'); ?></dd>
      </dl>
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
wp sss pull --dry-run
wp sss rewrite
wp sss url &lt;附件ID&gt;</code></pre>
      <p class="wpsss-help-note"><?php echo esc_html__('密钥请通过环境文件或安全渠道配置，不要在命令行中明文传递。', 'wpsss'); ?></p>
    </div>
  </details>

  <section class="card wpsss-help-card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('chat'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('需要更多帮助', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('产品文档与工单支持在文派官网。', 'wpsss'); ?></p>
      </div>
    </div>
    <div class="card-foot start">
      <a class="btn btn-secondary" href="https://www.wenpai.org/" target="_blank" rel="noopener"><?php echo Wpsss_Icons::svg('external'); ?><?php echo esc_html__('文派官网', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="https://www.wenpai.org/support/" target="_blank" rel="noopener"><?php echo esc_html__('提交反馈', 'wpsss'); ?></a>
    </div>
  </section>
</div>
