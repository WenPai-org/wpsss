<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$opts = $view['opts'];
$mode = (string) $view['mode'];
$key = Wpsss_Options::OPTION_KEY;
$saved_driver = (string) $opts['driver'];
$saved_site_mode = Wpsss_Options::sanitize_site_mode($opts['site_mode'] ?? 'domestic');
$driver = $saved_driver;
$site_mode = $saved_site_mode;
$site_modes = Wpsss_Options::site_modes();
$regions = Wpsss_Options::regions_for($driver);
$region_label = $regions[$opts['region']] ?? $opts['region'];
$grouped_regions = Wpsss_Options::grouped_regions($driver);
$adv = $mode === 'advanced';
$drivers = Wpsss_Options::driver_ids();
if ($adv) {
    $preview_driver = isset($_GET['preview_driver']) ? sanitize_key((string) $_GET['preview_driver']) : '';
    if ($preview_driver !== '' && isset($drivers[$preview_driver])) {
        $driver = $preview_driver;
    }
    if (isset($_GET['preview_site_mode'])) {
        $site_mode = Wpsss_Options::sanitize_site_mode(wp_unslash((string) $_GET['preview_site_mode']));
    }
    $regions = Wpsss_Options::regions_for($driver);
    $region_label = $regions[$opts['region']] ?? $opts['region'];
    $grouped_regions = Wpsss_Options::grouped_regions($driver);
}
$is_preview = $adv && ($driver !== $saved_driver || $site_mode !== $saved_site_mode);
$groups = Wpsss_Options::driver_groups_for_mode($site_mode, $driver);
$group_label = [
    'cn' => '国内云',
    'intl' => '国际与兼容云',
];
$s3_family = Wpsss_Options::is_s3_family($driver);
$driver_help = [
    Wpsss_Options::DRIVER_ALIYUN => '国内站点常用',
    Wpsss_Options::DRIVER_COS => '适合已有腾讯云 COS 桶',
    Wpsss_Options::DRIVER_KODO => '对外访问须配置自定义域名',
    Wpsss_Options::DRIVER_OBS => '适合已有华为云 OBS 桶',
    Wpsss_Options::DRIVER_TOS => '适合已有火山引擎 TOS 桶',
    Wpsss_Options::DRIVER_S3 => '兼容 AWS、Cloudflare R2、MinIO',
    Wpsss_Options::DRIVER_GCS => '适合 Google Cloud 存储桶',
];
$bucket_help = [
    Wpsss_Options::DRIVER_ALIYUN => '仅填写桶名，不含域名后缀',
    Wpsss_Options::DRIVER_COS => '格式 name-APPID',
    Wpsss_Options::DRIVER_KODO => '仅填写空间名称',
    Wpsss_Options::DRIVER_OBS => '仅填写桶名',
    Wpsss_Options::DRIVER_TOS => '仅填写桶名',
    Wpsss_Options::DRIVER_S3 => '仅填写桶名，Endpoint 另填',
    Wpsss_Options::DRIVER_GCS => '仅填写桶名，Endpoint 另填',
];
$url_access = ($opts['url_access'] ?? 'public') === 'signed' ? 'signed' : 'public';
?>
<?php $setup_done = ($opts['setup_completed_at'] ?? '') !== ''; ?>
<div class="wpcy-page-head">
  <div>
    <h1 class="wpcy-h1"><?php echo esc_html__('设置', 'wpsss'); ?></h1>
    <p class="wpcy-lede"><?php echo $adv ? esc_html__('桶与密钥保存后生效；开关仍即时生效。', 'wpsss') : esc_html__('开关即时生效。', 'wpsss'); ?></p>
    <?php if (!$setup_done && !$view['configured']) : ?>
      <p class="wpsss-setup-hint"><a href="<?php echo esc_url($url('setup')); ?>"><?php echo esc_html__('首次配置？走 4 步设置向导', 'wpsss'); ?></a></p>
    <?php endif; ?>
  </div>
  <div class="mode"><?php echo Wpsss_Icons::svg('eye'); ?><span><?php echo esc_html__('显示', 'wpsss'); ?></span>
    <span class="seg">
      <a class="<?php echo $adv ? '' : 'on'; ?>" href="<?php echo esc_url($url('settings', ['mode' => 'simple'])); ?>"><?php echo esc_html__('简单', 'wpsss'); ?></a>
      <a class="<?php echo $adv ? 'on' : ''; ?>" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>"><?php echo esc_html__('高级', 'wpsss'); ?></a>
    </span>
    <?php if ($adv) : ?>
      <button class="btn btn-primary" type="submit" form="wpsss-settings-form"><?php echo esc_html__('保存设置', 'wpsss'); ?></button>
    <?php endif; ?>
  </div>
</div>

<?php if ($is_preview) : ?>
  <div class="notice info wpsss-preview-banner">
    <?php echo Wpsss_Icons::svg('info'); ?>
    <span><?php echo esc_html(sprintf(
        '正在预览「%s · %s」，尚未保存。当前生效：「%s · %s」。',
        $site_modes[$site_mode]['label'] ?? $site_mode,
        $drivers[$driver] ?? $driver,
        $site_modes[$saved_site_mode]['label'] ?? $saved_site_mode,
        $drivers[$saved_driver] ?? $saved_driver
    )); ?></span>
    <a class="btn btn-ghost" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>"><?php echo esc_html__('回到已保存', 'wpsss'); ?></a>
  </div>
<?php endif; ?>

<?php if ($adv) : ?>
<form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" id="wpsss-settings-form" class="wpcy-stack">
  <?php settings_fields('wpsss'); ?>
<?php else : ?>
<div class="wpcy-stack">
<?php endif; ?>
  <section class="card">
    <h2 class="section-title"><?php echo Wpsss_Icons::svg('map'); ?> <?php echo esc_html__('站点场景', 'wpsss'); ?></h2>
    <?php if ($adv) : ?><p class="section-desc"><?php echo esc_html__('仅调整推荐厂商列表，不会更改已保存的存储桶。', 'wpsss'); ?></p><?php endif; ?>
    <?php if (!$adv) : ?>
    <form class="wpsss-toggle-form js-wpsss-instant" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_quick" />
      <?php wp_nonce_field('wpsss_quick'); ?>
    <?php endif; ?>
    <div class="choice-cards">
      <?php foreach ($site_modes as $id => $row) : ?>
        <?php $on = $site_mode === $id; ?>
        <label class="choice<?php echo $on ? ' is-on' : ''; ?>">
          <?php if ($adv) : ?>
            <input type="radio" name="<?php echo esc_attr($key); ?>[site_mode]" value="<?php echo esc_attr($id); ?>" data-wpsss-preview="site_mode" <?php checked($on); ?> />
          <?php else : ?>
            <input type="radio" name="site_mode" value="<?php echo esc_attr($id); ?>" <?php checked($on); ?> />
          <?php endif; ?>
          <span>
            <div class="t"><?php echo esc_html($row['label']); ?></div>
            <?php if ($adv || $on) : ?><div class="d"><?php echo esc_html($row['help']); ?></div><?php endif; ?>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php if (!$adv) : ?>
      <div class="card-foot wpsss-scene-foot">
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('应用场景', 'wpsss'); ?></button>
      </div>
    </form>
    <?php endif; ?>
  </section>

  <?php if ($adv) : ?>
  <section class="card">
    <h2 class="section-title"><?php echo Wpsss_Icons::svg('cloud'); ?> <?php echo esc_html__('云厂商', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('媒体库文件将上传至对象存储。请选择与存储桶对应的厂商。', 'wpsss'); ?></p>
    <?php foreach ($groups as $gid => $items) : ?>
      <p class="choice-group"><?php echo esc_html($group_label[$gid] ?? $gid); ?></p>
      <div class="choice-cards is-vendors">
        <?php foreach ($items as $id => $label) : ?>
          <?php $on = $driver === $id; ?>
          <label class="choice<?php echo $on ? ' is-on' : ''; ?>">
            <input type="radio" name="<?php echo esc_attr($key); ?>[driver]" value="<?php echo esc_attr($id); ?>" data-wpsss-preview="driver" <?php checked($on); ?> />
            <span class="tile vendor-tile<?php echo $on ? ' accent' : ''; ?>"><?php echo Wpsss_Icons::vendor($id); ?></span>
            <span>
              <div class="t"><?php echo esc_html($label); ?></div>
              <?php if ($on) : ?><div class="d"><?php echo esc_html($driver_help[$id] ?? ''); ?></div><?php endif; ?>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if (!$adv) : ?>
  <section class="card">
    <h2 class="section-title"><?php echo esc_html__('当前组合', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('更换云厂商或密钥，请切换到高级模式。', 'wpsss'); ?></p>
    <div class="simple-row">
      <div class="tile<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::vendor($driver); ?></div>
      <div><div class="t"><?php echo esc_html__('存储桶', 'wpsss'); ?></div><div class="d"><?php echo esc_html(($drivers[$driver] ?? $driver) . ' · ' . $region_label); ?></div></div>
      <div class="r"><span class="pill<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo $opts['bucket'] !== '' ? esc_html($opts['bucket']) : esc_html__('未设置', 'wpsss'); ?></span></div>
    </div>
    <div class="simple-row">
      <div class="tile"><?php echo Wpsss_Icons::svg('eye'); ?></div>
      <div><div class="t"><?php echo esc_html__('对外地址', 'wpsss'); ?></div></div>
      <div class="r"><span class="scope"><?php echo $opts['custom_domain'] !== '' ? esc_html($opts['custom_domain']) : esc_html__('未设置自定义域名', 'wpsss'); ?></span></div>
    </div>
    <?php foreach ([
        'keep_local' => ['本地备份', '关闭此选项需管理员批准', 'plug'],
        'upload_thumbs' => ['同时上传缩略图', '附件生成的各尺寸文件一并上传', 'image'],
        'origin_protect' => ['原图保护', '为原图 URL 追加处理样式', 'sparkle'],
    ] as $field => $copy) : ?>
      <?php $on = $opts[$field] === '1'; ?>
      <div class="simple-row">
        <div class="tile<?php echo $on ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg($copy[2]); ?></div>
        <div><div class="t"><?php echo esc_html($copy[0]); ?></div></div>
        <div class="r">
          <?php if ($field === 'keep_local' && $on) : ?>
            <button type="button" class="toggle on" disabled title="<?php echo esc_attr__('关闭此选项需管理员批准', 'wpsss'); ?>" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo esc_html__('已开启', 'wpsss'); ?></button>
          <?php else : ?>
          <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wpsss_quick" />
            <?php wp_nonce_field('wpsss_quick'); ?>
            <input type="hidden" name="<?php echo esc_attr($field); ?>" value="<?php echo $on ? '0' : '1'; ?>" />
            <button type="button" class="toggle<?php echo $on ? ' on' : ''; ?> js-wpsss-toggle" title="<?php echo esc_attr($copy[1]); ?>" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $on ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="simple-row">
      <div class="tile<?php echo $url_access === 'signed' ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('eye'); ?></div>
      <div><div class="t"><?php echo esc_html__('对象访问', 'wpsss'); ?></div></div>
      <div class="r">
        <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="wpsss_quick" />
          <?php wp_nonce_field('wpsss_quick'); ?>
          <input type="hidden" name="url_access" value="<?php echo $url_access === 'signed' ? 'public' : 'signed'; ?>" />
          <button type="button" class="toggle<?php echo $url_access === 'signed' ? ' on' : ''; ?> js-wpsss-toggle" title="<?php echo esc_attr($url_access === 'signed' ? __('使用签名 URL，私有桶也可正常访问', 'wpsss') : __('公开读；若桶禁止匿名访问，前台图片可能无法显示', 'wpsss')); ?>" data-on="<?php echo esc_attr__('签名访问', 'wpsss'); ?>" data-off="<?php echo esc_attr__('公开读', 'wpsss'); ?>"><i></i><?php echo $url_access === 'signed' ? esc_html__('签名访问', 'wpsss') : esc_html__('公开读', 'wpsss'); ?></button>
        </form>
      </div>
    </div>
  </section>
  <?php else : ?>
  <section class="card" id="wpsss-fields">
    <h2 class="section-title"><?php echo esc_html__('桶与密钥', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('建议使用子用户密钥，并仅授予当前存储桶权限。', 'wpsss'); ?></p>
      <?php $region_in_list = isset($regions[$opts['region']]); ?>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('桶名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html($bucket_help[$driver] ?? __('仅填写桶名', 'wpsss')); ?></div></div><div class="field-ctl">
        <div class="field-ctl-row">
          <input name="<?php echo esc_attr($key); ?>[bucket]" type="text" value="<?php echo esc_attr($opts['bucket']); ?>" class="js-wpsss-bucket" autocomplete="off" />
          <button type="button" class="btn btn-secondary js-wpsss-inspect"><?php echo esc_html__('读取桶信息', 'wpsss'); ?></button>
        </div>
        <p class="hint js-wpsss-inspect-hint" hidden></p>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('区域', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('填写桶名与密钥后，可点击「读取桶信息」自动回填。', 'wpsss'); ?></div></div><div class="field-ctl"><select name="<?php echo esc_attr($key); ?>[region]"><?php if ($opts['region'] !== '' && !$region_in_list) : ?><option value="<?php echo esc_attr($opts['region']); ?>" selected><?php echo esc_html($opts['region'] . '（当前保存）'); ?></option><?php endif; ?><?php foreach (['cn' => '国内', 'overseas' => '海外'] as $rid => $rlabel) : if ($grouped_regions[$rid] === []) { continue; } ?><optgroup label="<?php echo esc_attr($rlabel); ?>"><?php foreach ($grouped_regions[$rid] as $id => $label) : ?><option value="<?php echo esc_attr($id); ?>"<?php selected($opts['region'], $id); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('对象访问', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('公开读要求存储桶允许匿名访问。', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[url_access]" class="js-wpsss-access">
          <option value="public"<?php selected($url_access, 'public'); ?>><?php echo esc_html__('公开读', 'wpsss'); ?></option>
          <option value="signed"<?php selected($url_access, 'signed'); ?>><?php echo esc_html__('签名访问', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field js-wpsss-ttl"<?php echo $url_access === 'signed' ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('签名有效期（秒）', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('默认 3600 秒，最长 7 天。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[signed_ttl]" type="number" min="60" max="604800" value="<?php echo esc_attr((string) ($opts['signed_ttl'] ?? '3600')); ?>" /></div></div>
      <?php if ($s3_family) : ?>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('S3 预设', 'wpsss'); ?></div><div class="field-help"><?php echo $driver === Wpsss_Options::DRIVER_GCS ? esc_html__('Google 云固定为此预设。', 'wpsss') : ($driver === Wpsss_Options::DRIVER_TOS ? esc_html__('火山引擎固定为此预设。', 'wpsss') : esc_html__('将自动填入默认 Endpoint。', 'wpsss')); ?></div></div><div class="field-ctl">
        <?php if ($driver === Wpsss_Options::DRIVER_TOS || $driver === Wpsss_Options::DRIVER_GCS) : ?>
          <?php $locked = $driver === Wpsss_Options::DRIVER_GCS ? 'gcs' : 'tos'; ?>
          <input type="hidden" name="<?php echo esc_attr($key); ?>[s3_preset]" value="<?php echo esc_attr($locked); ?>" />
          <input type="text" value="<?php echo esc_attr(Wpsss_Options::s3_presets()[$locked]); ?>" disabled />
        <?php else : ?>
        <select name="<?php echo esc_attr($key); ?>[s3_preset]">
          <?php foreach (Wpsss_Options::s3_presets() as $id => $label) : ?>
            <option value="<?php echo esc_attr($id); ?>"<?php selected($opts['s3_preset'], $id); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('Endpoint', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('留空则按上方预设生成。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[endpoint]" type="text" value="<?php echo esc_attr($opts['endpoint']); ?>" placeholder="https://tos-s3-cn-beijing.volces.com" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('路径风格访问', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('R2、MinIO 等平台通常需要启用。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[force_path_style]" value="<?php echo $opts['force_path_style'] === '1' ? '1' : '0'; ?>" />
        <button type="button" class="toggle<?php echo $opts['force_path_style'] === '1' ? ' on' : ''; ?> js-wpsss-flip" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $opts['force_path_style'] === '1' ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
      </div></div>
      <?php else : ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>[s3_preset]" value="<?php echo esc_attr($opts['s3_preset']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[endpoint]" value="<?php echo esc_attr($opts['endpoint']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[force_path_style]" value="<?php echo esc_attr($opts['force_path_style']); ?>" />
      <?php endif; ?>
      <?php
        $show_app_id = $driver === Wpsss_Options::DRIVER_COS
            || ($driver === Wpsss_Options::DRIVER_S3 && ($opts['s3_preset'] ?? '') === 'r2');
        $id_set = $opts['access_key_id'] !== '';
        $id_tail = $id_set ? substr($opts['access_key_id'], -4) : '';
        ?>
      <?php if ($show_app_id) : ?>
      <div class="field"><div><div class="field-label"><?php echo ($opts['s3_preset'] ?? '') === 'r2' ? esc_html__('R2 Account ID', 'wpsss') : esc_html__('APPID（COS）', 'wpsss'); ?></div><div class="field-help"><?php echo ($opts['s3_preset'] ?? '') === 'r2' ? esc_html__('用于拼接服务 Endpoint。', 'wpsss') : esc_html__('若桶名已含 APPID 可留空。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[app_id]" type="text" value="<?php echo esc_attr($opts['app_id']); ?>" autocomplete="off" class="js-wpsss-app-id" /></div></div>
      <?php else : ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>[app_id]" value="<?php echo esc_attr($opts['app_id']); ?>" />
      <?php endif; ?>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('访问密钥 ID', 'wpsss'); ?></div><div class="field-help"><?php echo $id_set ? esc_html(sprintf('已保存 · 末四位 %s', $id_tail)) : esc_html__('RAM 子用户 AccessKey ID', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_id]" type="text" value="" autocomplete="off" placeholder="<?php echo $id_set ? esc_attr('••••' . $id_tail) : ''; ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('访问密钥 Secret', 'wpsss'); ?></div><div class="field-help"><?php echo $view['secret_set'] ? esc_html__('已保存；留空保存时不更改', 'wpsss') : esc_html__('不会在页面中显示', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_secret]" type="password" value="" autocomplete="new-password" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('走内网', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('源站与存储桶位于同一区域时可启用。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[use_internal]" value="<?php echo $opts['use_internal'] === '1' ? '1' : '0'; ?>" />
        <button type="button" class="toggle<?php echo $opts['use_internal'] === '1' ? ' on' : ''; ?> js-wpsss-flip" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $opts['use_internal'] === '1' ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('自定义域名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('七牛云必填；请使用 HTTPS 地址。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[custom_domain]" type="text" value="<?php echo esc_attr($opts['custom_domain']); ?>" placeholder="https://static.example.com" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('对象前缀 / 子目录', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('相当于对象路径前缀，如 media。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[object_prefix]" type="text" value="<?php echo esc_attr($opts['object_prefix']); ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('自动重命名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('上传时自动重命名，降低直链被猜测的风险。', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[auto_rename]">
          <option value="off"<?php selected($opts['auto_rename'], 'off'); ?>><?php echo esc_html__('不改文件名', 'wpsss'); ?></option>
          <option value="md5"<?php selected($opts['auto_rename'], 'md5'); ?>><?php echo esc_html__('MD5', 'wpsss'); ?></option>
          <option value="time"<?php selected($opts['auto_rename'], 'time'); ?>><?php echo esc_html__('时间戳+随机', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('图片样式', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('追加到图片 URL 末尾。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[image_style]" type="text" value="<?php echo esc_attr($opts['image_style']); ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('原图保护', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('应用于媒体库与文章内容中的图片。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[origin_protect]" value="<?php echo $opts['origin_protect'] === '1' ? '1' : '0'; ?>" />
        <button type="button" class="toggle<?php echo $opts['origin_protect'] === '1' ? ' on' : ''; ?> js-wpsss-flip" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $opts['origin_protect'] === '1' ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('额外改写来源', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('迁移域名时，在此填写原 CDN 地址。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[rewrite_from]" type="text" value="<?php echo esc_attr($opts['rewrite_from']); ?>" placeholder="https://old-cdn.example.com" /></div></div>
      <?php if ($driver === Wpsss_Options::DRIVER_COS) : ?>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('COS 数据万象', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('仅记录功能开关，不会代为执行处理任务。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_slim]" value="0" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_preview]" value="0" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_audit]" value="0" />
        <div class="chk">
          <label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_slim]" value="1" <?php checked($opts['ci_slim'], '1'); ?> /> <?php echo esc_html__('图片极智压缩 / webp', 'wpsss'); ?></label>
          <label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_preview]" value="1" <?php checked($opts['ci_preview'], '1'); ?> /> <?php echo esc_html__('文档预览参数', 'wpsss'); ?></label>
          <label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_audit]" value="1" <?php checked($opts['ci_audit'], '1'); ?> /> <?php echo esc_html__('内容审核', 'wpsss'); ?></label>
        </div>
      </div></div>
      <?php else : ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_slim]" value="<?php echo esc_attr($opts['ci_slim']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_preview]" value="<?php echo esc_attr($opts['ci_preview']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_audit]" value="<?php echo esc_attr($opts['ci_audit']); ?>" />
      <?php endif; ?>
      <input type="hidden" name="<?php echo esc_attr($key); ?>[keep_local]" value="<?php echo esc_attr($opts['keep_local']); ?>" />
      <input type="hidden" name="<?php echo esc_attr($key); ?>[upload_thumbs]" value="<?php echo esc_attr($opts['upload_thumbs']); ?>" />
  </section>
  <div class="wpsss-savebar">
    <span class="meta"><?php echo $is_preview ? esc_html__('预览中的更改需保存后才会生效。', 'wpsss') : esc_html__('密钥留空保存时将保留已保存的值。', 'wpsss'); ?></span>
    <button class="btn btn-primary" type="submit"><?php echo esc_html__('保存设置', 'wpsss'); ?></button>
  </div>
  <?php endif; ?>
<?php if ($adv) : ?>
</form>
<?php else : ?>
</div>
<?php endif; ?>
