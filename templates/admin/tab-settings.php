<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$opts = $view['opts'];
$mode = (string) $view['mode'];
$key = Wpsss_Options::OPTION_KEY;
$driver = (string) $opts['driver'];
$site_mode = Wpsss_Options::sanitize_site_mode($opts['site_mode'] ?? 'domestic');
$site_modes = Wpsss_Options::site_modes();
$regions = Wpsss_Options::regions_for($driver);
$region_label = $regions[$opts['region']] ?? $opts['region'];
$grouped_regions = Wpsss_Options::grouped_regions($driver);
$adv = $mode === 'advanced';
$drivers = Wpsss_Options::driver_ids();
$groups = Wpsss_Options::driver_groups_for_mode($site_mode, $driver);
$group_label = [
    'cn' => '国内厂商（海外区域可自选）',
    'intl' => '国外 / S3 兼容',
];
$s3_family = Wpsss_Options::is_s3_family($driver);
$driver_help = [
    Wpsss_Options::DRIVER_ALIYUN => '现网已通。自定义域以后走 CyberCDN',
    Wpsss_Options::DRIVER_COS => '自写 REST，不是 Mac Studio 那份官方套件',
    Wpsss_Options::DRIVER_KODO => '对外地址必须填自定义域',
    Wpsss_Options::DRIVER_OBS => '签名走 OBS V2，不粘 SDK',
    Wpsss_Options::DRIVER_TOS => '火山对象存储。S3 兼容域 tos-s3-{region}.volces.com。不是 ImageX',
    Wpsss_Options::DRIVER_S3 => '一张卡盖住 AWS / Spaces / R2 / MinIO / Wasabi',
    Wpsss_Options::DRIVER_GCS => 'HMAC 互操作密钥。不是 JSON key file，不粘 Google SDK',
];
$url_access = ($opts['url_access'] ?? 'public') === 'signed' ? 'signed' : 'public';
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
  <?php if ($adv) : ?>
  <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" id="wpsss-settings-form">
    <?php settings_fields('wpsss'); ?>
  <?php endif; ?>
  <section class="card">
    <h2 class="section-title"><?php echo Wpsss_Icons::svg('cloud'); ?> <?php echo esc_html__('站点场景', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('跟文派叶子一样：先选站点在哪，再挑云。切换只改推荐列表，不改已保存的桶和密钥。', 'wpsss'); ?></p>
    <?php if (!$adv) : ?>
    <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_quick" />
      <?php wp_nonce_field('wpsss_quick'); ?>
    <?php endif; ?>
    <div class="choice-cards">
      <?php foreach ($site_modes as $id => $row) : ?>
        <?php $on = $site_mode === $id; ?>
        <label class="choice<?php echo $on ? ' is-on' : ''; ?>">
          <?php if ($adv) : ?>
            <input type="radio" name="<?php echo esc_attr($key); ?>[site_mode]" value="<?php echo esc_attr($id); ?>" <?php checked($on); ?> />
          <?php else : ?>
            <input type="radio" name="site_mode" value="<?php echo esc_attr($id); ?>" <?php checked($on); ?> />
          <?php endif; ?>
          <span>
            <div class="t"><?php echo esc_html($row['label']); ?></div>
            <div class="d"><?php echo esc_html($row['help']); ?></div>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php if (!$adv) : ?>
      <div class="card-foot">
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('应用场景', 'wpsss'); ?></button>
      </div>
    </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="section-title"><?php echo Wpsss_Icons::svg('cloud'); ?> <?php echo esc_html__('云厂商', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('国内走自家 REST。国外和火山 TOS 走自写 SigV4。国内厂商的海外区域可自选。', 'wpsss'); ?></p>
    <?php foreach ($groups as $gid => $items) : ?>
      <p class="section-desc"><?php echo esc_html($group_label[$gid] ?? $gid); ?></p>
      <div class="choice-cards">
        <?php foreach ($items as $id => $label) : ?>
          <?php $on = $driver === $id; ?>
          <label class="choice<?php echo $on ? ' is-on' : ''; ?>">
            <?php if ($adv) : ?>
              <input type="radio" name="<?php echo esc_attr($key); ?>[driver]" value="<?php echo esc_attr($id); ?>" <?php checked($on); ?> />
            <?php else : ?>
              <input type="radio" <?php echo $on ? 'checked' : ''; ?> disabled />
            <?php endif; ?>
            <span>
              <div class="t"><?php echo esc_html($label); ?></div>
              <div class="d"><?php echo esc_html($driver_help[$id] ?? ''); ?></div>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if (!$adv) : ?>
  <section class="card">
    <h2 class="section-title"><?php echo esc_html__('当前组合', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('想改密钥、厂商或自定义域，切到「高级」。', 'wpsss'); ?></p>
    <div class="simple-row">
      <div class="tile<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('cloud'); ?></div>
      <div><div class="t"><?php echo esc_html__('存储桶', 'wpsss'); ?></div><div class="d"><?php echo esc_html(($drivers[$driver] ?? $driver) . ' · ' . $region_label); ?></div></div>
      <div class="r"><span class="pill<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo $opts['bucket'] !== '' ? esc_html($opts['bucket']) : esc_html__('未设置', 'wpsss'); ?></span></div>
    </div>
    <div class="simple-row">
      <div class="tile"><?php echo Wpsss_Icons::svg('image'); ?></div>
      <div><div class="t"><?php echo esc_html__('对外地址', 'wpsss'); ?></div><div class="d"><?php echo esc_html__('空着就用桶默认域名。七牛必须填自定义域。', 'wpsss'); ?></div></div>
      <div class="r"><span class="scope"><?php echo $opts['custom_domain'] !== '' ? esc_html($opts['custom_domain']) : esc_html__('未设自定义域', 'wpsss'); ?></span></div>
    </div>
    <?php foreach ([
        'keep_local' => ['上传后保留本地文件', '关掉须人批。源站还能退回'],
        'upload_thumbs' => ['同时上传缩略图', '附件生成的尺寸一并进桶'],
        'origin_protect' => ['原图保护', '对已进桶的地址追加图片样式'],
    ] as $field => $copy) : ?>
      <?php $on = $opts[$field] === '1'; ?>
      <div class="simple-row">
        <div class="tile<?php echo $on ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('plug'); ?></div>
        <div><div class="t"><?php echo esc_html($copy[0]); ?></div><div class="d"><?php echo esc_html($copy[1]); ?></div></div>
        <div class="r">
          <?php if ($field === 'keep_local' && $on) : ?>
            <button type="button" class="toggle on" disabled title="<?php echo esc_attr__('关掉须人批', 'wpsss'); ?>"><i></i><?php echo esc_html__('已开启', 'wpsss'); ?></button>
          <?php else : ?>
          <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wpsss_quick" />
            <?php wp_nonce_field('wpsss_quick'); ?>
            <input type="hidden" name="<?php echo esc_attr($field); ?>" value="<?php echo $on ? '0' : '1'; ?>" />
            <button type="submit" class="toggle<?php echo $on ? ' on' : ''; ?> js-wpsss-toggle"><i></i><?php echo $on ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="simple-row">
      <div class="tile<?php echo $url_access === 'signed' ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('image'); ?></div>
      <div><div class="t"><?php echo esc_html__('对象访问', 'wpsss'); ?></div><div class="d"><?php echo $url_access === 'signed' ? esc_html__('签名 URL。私有桶也能看图。', 'wpsss') : esc_html__('公开读。桶若禁匿名，前台会裂图。', 'wpsss'); ?></div></div>
      <div class="r">
        <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="wpsss_quick" />
          <?php wp_nonce_field('wpsss_quick'); ?>
          <input type="hidden" name="url_access" value="<?php echo $url_access === 'signed' ? 'public' : 'signed'; ?>" />
          <button type="submit" class="toggle<?php echo $url_access === 'signed' ? ' on' : ''; ?> js-wpsss-toggle"><i></i><?php echo $url_access === 'signed' ? esc_html__('签名访问', 'wpsss') : esc_html__('公开读', 'wpsss'); ?></button>
        </form>
      </div>
    </div>
  </section>
  <?php else : ?>
  <section class="card">
    <h2 class="section-title"><?php echo esc_html__('桶与密钥', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('用子用户，只授这一只桶。不要填账号根密钥。', 'wpsss'); ?></p>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('桶名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('COS 填 name-appid。不要带厂商域名', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[bucket]" type="text" value="<?php echo esc_attr($opts['bucket']); ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('区域', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('只列当前厂商。国内/海外分组，自己选。', 'wpsss'); ?></div></div><div class="field-ctl"><select name="<?php echo esc_attr($key); ?>[region]"><?php foreach (['cn' => '国内', 'overseas' => '海外'] as $rid => $rlabel) : if ($grouped_regions[$rid] === []) { continue; } ?><optgroup label="<?php echo esc_attr($rlabel); ?>"><?php foreach ($grouped_regions[$rid] as $id => $label) : ?><option value="<?php echo esc_attr($id); ?>"<?php selected($opts['region'], $id); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('对象访问', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('公开读要桶允许匿名或自定义域。签名访问目前支持阿里 OSS 与 S3 兼容 / TOS。', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[url_access]">
          <option value="public"<?php selected($url_access, 'public'); ?>><?php echo esc_html__('公开读', 'wpsss'); ?></option>
          <option value="signed"<?php selected($url_access, 'signed'); ?>><?php echo esc_html__('签名访问', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('签名有效期（秒）', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('默认 3600。最长 7 天。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[signed_ttl]" type="number" min="60" max="604800" value="<?php echo esc_attr((string) ($opts['signed_ttl'] ?? '3600')); ?>" /></div></div>
      <?php if ($s3_family) : ?>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('S3 预设', 'wpsss'); ?></div><div class="field-help"><?php echo $driver === Wpsss_Options::DRIVER_GCS ? esc_html__('GCS 锁定 gcs，走 HMAC + SigV4。', 'wpsss') : ($driver === Wpsss_Options::DRIVER_TOS ? esc_html__('火山 TOS 锁定 tos，走 SigV4。', 'wpsss') : esc_html__('改预设会带上默认 endpoint。密钥另填。', 'wpsss')); ?></div></div><div class="field-ctl">
        <?php if ($driver === Wpsss_Options::DRIVER_TOS || $driver === Wpsss_Options::DRIVER_GCS) : ?>
          <input type="hidden" name="<?php echo esc_attr($key); ?>[s3_preset]" value="tos" />
          <input type="text" value="<?php echo esc_attr(Wpsss_Options::s3_presets()['tos']); ?>" disabled />
        <?php else : ?>
        <select name="<?php echo esc_attr($key); ?>[s3_preset]">
          <?php foreach (Wpsss_Options::s3_presets() as $id => $label) : ?>
            <option value="<?php echo esc_attr($id); ?>"<?php selected($opts['s3_preset'], $id); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('Endpoint', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('空着就按预设拼。R2 填账号到 APPID；AWS 可空。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[endpoint]" type="text" value="<?php echo esc_attr($opts['endpoint']); ?>" placeholder="https://tos-s3-cn-beijing.volces.com" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('Path-style', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('R2 / MinIO 默认开。AWS 虚拟主机默认关。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[force_path_style]" value="0" />
        <div class="chk"><label><input type="checkbox" name="<?php echo esc_attr($key); ?>[force_path_style]" value="1" <?php checked($opts['force_path_style'], '1'); ?> /> <?php echo esc_html__('已开启', 'wpsss'); ?></label></div>
      </div></div>
      <?php else : ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>[s3_preset]" value="<?php echo esc_attr($opts['s3_preset']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[endpoint]" value="<?php echo esc_attr($opts['endpoint']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[force_path_style]" value="<?php echo esc_attr($opts['force_path_style']); ?>" />
      <?php endif; ?>
      <div class="field"><div><div class="field-label"><?php echo $driver === Wpsss_Options::DRIVER_S3 && ($opts['s3_preset'] ?? '') === 'r2' ? esc_html__('R2 Account ID', 'wpsss') : esc_html__('APPID（COS / R2）', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('COS 桶名已含 -数字 时可空。R2 用来拼 *.r2.cloudflarestorage.com', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[app_id]" type="text" value="<?php echo esc_attr($opts['app_id']); ?>" autocomplete="off" /></div></div>
      <div class="field"><div><div class="field-label">AccessKey ID</div><div class="field-help"><?php echo esc_html__('子用户 / SecretId', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_id]" type="text" value="<?php echo esc_attr($opts['access_key_id']); ?>" autocomplete="off" /></div></div>
      <div class="field"><div><div class="field-label">AccessKey Secret</div><div class="field-help"><?php echo $view['secret_set'] ? esc_html__('已保存。留空保存时不改', 'wpsss') : esc_html__('不会出现在页面源码里', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_secret]" type="password" value="" autocomplete="new-password" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('走内网', 'wpsss'); ?></div><div class="field-help"><?php echo $driver === Wpsss_Options::DRIVER_TOS ? esc_html__('同区域源站再开。签名域 tos-s3-{region}.ivolces.com', 'wpsss') : esc_html__('源站和桶在同一云厂商区域时再开', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[use_internal]" value="0" />
        <div class="chk"><label><input type="checkbox" name="<?php echo esc_attr($key); ?>[use_internal]" value="1" <?php checked($opts['use_internal'], '1'); ?> /> <?php echo esc_html__('已开启', 'wpsss'); ?></label></div>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('自定义域名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('备案后的 HTTPS。七牛必填。边缘用现成 CyberCDN Zone', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[custom_domain]" type="text" value="<?php echo esc_attr($opts['custom_domain']); ?>" placeholder="https://static.example.com" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('对象前缀 / 子目录', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('进桶路径前面再加一层，例如 media', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[object_prefix]" type="text" value="<?php echo esc_attr($opts['object_prefix']); ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('自动重命名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('对照四家插件的文件名随机化', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[auto_rename]">
          <option value="off"<?php selected($opts['auto_rename'], 'off'); ?>><?php echo esc_html__('不改文件名', 'wpsss'); ?></option>
          <option value="md5"<?php selected($opts['auto_rename'], 'md5'); ?>><?php echo esc_html__('MD5', 'wpsss'); ?></option>
          <option value="time"<?php selected($opts['auto_rename'], 'time'); ?>><?php echo esc_html__('时间戳+随机', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('图片样式', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('原样接到 URL 后。阿里如 ?x-oss-process=style/st；七牛如 !blog', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[image_style]" type="text" value="<?php echo esc_attr($opts['image_style']); ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('原图保护', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('开了就把样式接到附件和正文图上', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[origin_protect]" value="0" />
        <div class="chk"><label><input type="checkbox" name="<?php echo esc_attr($key); ?>[origin_protect]" value="1" <?php checked($opts['origin_protect'], '1'); ?> /> <?php echo esc_html__('已开启', 'wpsss'); ?></label></div>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('额外改写来源', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('工具页改写时，除本地 uploads 外再换这一段', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[rewrite_from]" type="text" value="<?php echo esc_attr($opts['rewrite_from']); ?>" placeholder="https://old-cdn.example.com" /></div></div>
      <?php if ($driver === Wpsss_Options::DRIVER_COS) : ?>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('COS 数据万象', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('样式走 URL。审核只记开关，不代跑整站扫描', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_slim]" value="0" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_preview]" value="0" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_audit]" value="0" />
        <div class="chk"><label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_slim]" value="1" <?php checked($opts['ci_slim'], '1'); ?> /> <?php echo esc_html__('图片极智压缩 / webp', 'wpsss'); ?></label></div>
        <div class="chk"><label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_preview]" value="1" <?php checked($opts['ci_preview'], '1'); ?> /> <?php echo esc_html__('文档预览参数', 'wpsss'); ?></label></div>
        <div class="chk"><label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_audit]" value="1" <?php checked($opts['ci_audit'], '1'); ?> /> <?php echo esc_html__('内容审核（仅开关）', 'wpsss'); ?></label></div>
      </div></div>
      <?php else : ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_slim]" value="<?php echo esc_attr($opts['ci_slim']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_preview]" value="<?php echo esc_attr($opts['ci_preview']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_audit]" value="<?php echo esc_attr($opts['ci_audit']); ?>" />
      <?php endif; ?>
      <input type="hidden" name="<?php echo esc_attr($key); ?>[keep_local]" value="<?php echo esc_attr($opts['keep_local']); ?>" />
      <input type="hidden" name="<?php echo esc_attr($key); ?>[upload_thumbs]" value="<?php echo esc_attr($opts['upload_thumbs']); ?>" />
      <div class="card-foot">
        <button class="btn btn-primary" type="submit"><?php echo esc_html__('保存设置', 'wpsss'); ?></button>
      </div>
    </form>
  </section>
  <?php endif; ?>
</div>
