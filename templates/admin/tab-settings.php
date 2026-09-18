<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$opts = $view['opts'];
$mode = (string) $view['mode'];
$key = Wpsss_Options::OPTION_KEY;
$regions = Wpsss_Options::regions();
$region_label = $regions[$opts['region']] ?? $opts['region'];
$adv = $mode === 'advanced';
?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('设置', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo $adv ? esc_html__('桶和密钥保存后生效。开关仍即时生效。', 'wpsss') : esc_html__('开关即时生效。桶和密钥要保存。', 'wpsss'); ?></p>
  </div>
  <div class="mode"><?php echo esc_html__('显示', 'wpsss'); ?>
    <span class="seg">
      <a class="<?php echo $adv ? '' : 'on'; ?>" href="<?php echo esc_url($url('settings', ['mode' => 'simple'])); ?>"><?php echo esc_html__('简单', 'wpsss'); ?></a>
      <a class="<?php echo $adv ? 'on' : ''; ?>" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>"><?php echo esc_html__('高级', 'wpsss'); ?></a>
    </span>
  </div>
</div>

<div class="wpcy-stack">
  <section class="card">
    <h2 class="section-title"><?php echo Wpsss_Icons::svg('cloud'); ?> <?php echo esc_html__('云厂商', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('一个插件、多个 driver。现在只能接阿里云 OSS。', 'wpsss'); ?></p>
    <div class="choice-cards">
      <label class="choice is-on"><input type="radio" checked disabled><span><div class="t"><?php echo esc_html__('阿里云 OSS', 'wpsss'); ?></div><div class="d"><?php echo esc_html__('现网已通。自定义域以后走 CyberCDN', 'wpsss'); ?></div></span></label>
      <label class="choice is-soon"><input type="radio" disabled><span><div class="t"><?php echo esc_html__('腾讯云 COS', 'wpsss'); ?></div><div class="d"><?php echo esc_html__('即将提供 · 不是 Mac Studio 那份官方套件', 'wpsss'); ?></div></span></label>
      <label class="choice is-soon"><input type="radio" disabled><span><div class="t"><?php echo esc_html__('华为云 OBS', 'wpsss'); ?></div><div class="d"><?php echo esc_html__('即将提供', 'wpsss'); ?></div></span></label>
    </div>
  </section>

  <?php if (!$adv) : ?>
  <section class="card">
    <h2 class="section-title"><?php echo esc_html__('当前组合', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('想改密钥或自定义域，切到「高级」。', 'wpsss'); ?></p>
    <div class="simple-row">
      <div class="tile<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('cloud'); ?></div>
      <div><div class="t"><?php echo esc_html__('存储桶', 'wpsss'); ?></div><div class="d"><?php echo esc_html($view['driver']->label() . ' · ' . $region_label); ?></div></div>
      <div class="r"><span class="pill<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo $opts['bucket'] !== '' ? esc_html($opts['bucket']) : esc_html__('未设置', 'wpsss'); ?></span></div>
    </div>
    <div class="simple-row">
      <div class="tile"><?php echo Wpsss_Icons::svg('image'); ?></div>
      <div><div class="t"><?php echo esc_html__('对外地址', 'wpsss'); ?></div><div class="d"><?php echo esc_html__('空着就用桶默认域名。填了只在 upload_url_path 为空或由本插件写入时才改', 'wpsss'); ?></div></div>
      <div class="r"><span class="scope"><?php echo $opts['custom_domain'] !== '' ? esc_html($opts['custom_domain']) : esc_html__('未设自定义域', 'wpsss'); ?></span></div>
    </div>
    <?php foreach (['keep_local' => ['上传后保留本地文件', '关掉须人批。源站还能退回'], 'upload_thumbs' => ['同时上传缩略图', '附件生成的尺寸一并进桶']] as $field => $copy) : ?>
      <?php $on = $opts[$field] === '1'; ?>
      <div class="simple-row">
        <div class="tile<?php echo $on ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('plug'); ?></div>
        <div><div class="t"><?php echo esc_html($copy[0]); ?></div><div class="d"><?php echo esc_html($copy[1]); ?></div></div>
        <div class="r">
          <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wpsss_quick" />
            <?php wp_nonce_field('wpsss_quick'); ?>
            <input type="hidden" name="<?php echo esc_attr($field); ?>" value="<?php echo $on ? '0' : '1'; ?>" />
            <button type="submit" class="toggle<?php echo $on ? ' on' : ''; ?> js-wpsss-toggle"><i></i><?php echo $on ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
  <?php else : ?>
  <section class="card">
    <h2 class="section-title"><?php echo esc_html__('桶与密钥', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('用 RAM 子用户，只授这一只桶。不要填账号根密钥。', 'wpsss'); ?></p>
    <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
      <?php settings_fields('wpsss'); ?>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('桶名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('只填桶名，不要带 .oss-cn-*.aliyuncs.com', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[bucket]" type="text" value="<?php echo esc_attr($opts['bucket']); ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('区域', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('和源站同区域才勾内网', 'wpsss'); ?></div></div><div class="field-ctl"><select name="<?php echo esc_attr($key); ?>[region]"><?php foreach ($regions as $id => $label) : ?><option value="<?php echo esc_attr($id); ?>"<?php selected($opts['region'], $id); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div></div>
      <div class="field"><div><div class="field-label">AccessKey ID</div><div class="field-help"><?php echo esc_html__('RAM 子用户', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_id]" type="text" value="<?php echo esc_attr($opts['access_key_id']); ?>" autocomplete="off" /></div></div>
      <div class="field"><div><div class="field-label">AccessKey Secret</div><div class="field-help"><?php echo $view['secret_set'] ? esc_html__('已保存。留空保存时不改', 'wpsss') : esc_html__('不会出现在页面源码里', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_secret]" type="password" value="" autocomplete="new-password" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('走 OSS 内网', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('源站和桶在同一阿里云区域时再开', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[use_internal]" value="0" />
        <div class="chk"><label><input type="checkbox" name="<?php echo esc_attr($key); ?>[use_internal]" value="1" <?php checked($opts['use_internal'], '1'); ?> /> <?php echo esc_html__('已开启', 'wpsss'); ?></label></div>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('自定义域名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('备案后的 HTTPS。边缘用现成 CyberCDN Zone', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[custom_domain]" type="text" value="<?php echo esc_attr($opts['custom_domain']); ?>" placeholder="https://static.example.com" /></div></div>
      <input type="hidden" name="<?php echo esc_attr($key); ?>[keep_local]" value="<?php echo esc_attr($opts['keep_local']); ?>" />
      <input type="hidden" name="<?php echo esc_attr($key); ?>[upload_thumbs]" value="<?php echo esc_attr($opts['upload_thumbs']); ?>" />
      <div class="card-foot">
        <button class="btn btn-primary" type="submit"><?php echo esc_html__('保存设置', 'wpsss'); ?></button>
      </div>
    </form>
  </section>
  <?php endif; ?>
</div>
