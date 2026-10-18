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
$public_base = (string) ($view['public_base'] ?? '');
$has_style = trim((string) ($opts['image_style'] ?? '')) !== '';
$groups = Wpsss_Options::driver_groups();
$group_label = [
    'cn' => '国内云',
    'intl' => '国际与兼容云',
];
$s3_family = Wpsss_Options::is_s3_family($driver);
$driver_help = Wpsss_Options::driver_help();
$bucket_help = Wpsss_Options::bucket_help();
$url_access = ($opts['url_access'] ?? 'public') === 'signed' ? 'signed' : 'public';
$oss_signature = (($opts['oss_signature'] ?? 'v4') === 'v1') ? 'v1' : 'v4';
$oss_auth = (($opts['oss_auth'] ?? 'ak') === 'ram') ? 'ram' : 'ak';
$s3_locked = $driver === Wpsss_Options::DRIVER_GCS ? 'gcs' : ($driver === Wpsss_Options::DRIVER_TOS ? 'tos' : '');
$show_app_id = $driver === Wpsss_Options::DRIVER_COS
    || ($driver === Wpsss_Options::DRIVER_S3 && ($opts['s3_preset'] ?? '') === 'r2');
$id_set = $opts['access_key_id'] !== '';
$id_tail = $id_set ? substr($opts['access_key_id'], -4) : '';
$region_in_list = isset($regions[$opts['region']]);
$show_origin = $driver === Wpsss_Options::DRIVER_ALIYUN
    && in_array((string) $opts['region'], ['oss-accelerate', 'oss-accelerate-overseas'], true);
?>
<?php $setup_done = ($opts['setup_completed_at'] ?? '') !== ''; ?>
<div class="wenpai-page-head">
  <div>
    <h1 class="wenpai-h1"><?php echo esc_html__('设置', 'wpsss'); ?></h1>
    <p class="wenpai-lede"><?php echo $adv ? esc_html__('桶与密钥保存后生效；开关仍即时生效。', 'wpsss') : esc_html__('开关即时生效。', 'wpsss'); ?></p>
    <?php if (!$setup_done && !$view['configured']) : ?>
      <p class="wpsss-setup-hint"><a href="<?php echo esc_url($url('setup')); ?>"><?php echo esc_html__('首次配置请使用 4 步设置向导', 'wpsss'); ?></a></p>
    <?php endif; ?>
  </div>
  <div class="mode"><?php echo Wpsss_Icons::svg('eye'); ?><span><?php echo esc_html__('显示', 'wpsss'); ?></span>
    <span class="seg">
      <a class="<?php echo $adv ? '' : 'on'; ?>" href="<?php echo esc_url($url('settings', ['mode' => 'simple'])); ?>"><?php echo esc_html__('简单', 'wpsss'); ?></a>
      <a class="<?php echo $adv ? 'on' : ''; ?>" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>"><?php echo esc_html__('高级', 'wpsss'); ?></a>
    </span>
  </div>
</div>

<?php if ($adv) : ?>
  <div class="notice info wpsss-preview-banner" hidden>
    <?php echo Wpsss_Icons::svg('info'); ?>
    <span class="js-wpsss-preview-text"></span>
    <button type="button" class="btn btn-ghost js-wpsss-revert"><?php echo esc_html__('恢复已保存值', 'wpsss'); ?></button>
  </div>
<?php endif; ?>

<?php if ($adv) : ?>
<form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" id="wpsss-settings-form" class="wenpai-stack">
  <?php settings_fields('wpsss'); ?>
<?php else : ?>
<div class="wenpai-stack">
<?php endif; ?>
  <?php if ($adv || !$view['configured']) : ?>
  <section class="card" id="wpsss-scene">
    <h2 class="section-title"><?php echo Wpsss_Icons::svg('map'); ?> <?php echo esc_html__('站点场景', 'wpsss'); ?></h2>
    <?php if ($adv) : ?><p class="section-desc"><?php echo esc_html__('仅调整推荐厂商列表，不会更改已保存的存储桶。', 'wpsss'); ?></p><?php endif; ?>
    <?php if (!$adv) : ?>
    <form class="wpsss-toggle-form js-wpsss-instant" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_quick" />
      <?php wp_nonce_field('wpsss_quick'); ?>
    <?php endif; ?>
    <div class="choice-cards<?php echo $adv ? ' is-compact' : ''; ?>">
      <?php foreach ($site_modes as $id => $row) : ?>
        <?php $on = $site_mode === $id; ?>
        <label class="choice<?php echo $on ? ' is-on' : ''; ?>">
          <?php if ($adv) : ?>
            <input type="radio" name="<?php echo esc_attr($key); ?>[site_mode]" value="<?php echo esc_attr($id); ?>" data-wpsss-disclose="site_mode" <?php checked($on); ?> />
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
      <div class="card-foot wpsss-scene-foot">
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('应用所选场景', 'wpsss'); ?></button>
      </div>
    </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($adv) : ?>
  <section class="card" id="wpsss-vendor">
    <h2 class="section-title"><?php echo Wpsss_Icons::svg('cloud'); ?> <?php echo esc_html__('云厂商', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('请选择与存储桶对应的厂商。', 'wpsss'); ?></p>
    <details class="wpsss-vendor-more"<?php echo $view['configured'] ? '' : ' open'; ?>>
      <summary class="simple-row">
        <div class="tile js-wpsss-vendor-tile<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::vendor($driver); ?></div>
        <div>
          <div class="t js-wpsss-vendor-title"><?php echo esc_html($drivers[$driver] ?? $driver); ?></div>
          <div class="d js-wpsss-vendor-help"><?php echo esc_html($driver_help[$driver] ?? __('点击更换厂商', 'wpsss')); ?></div>
        </div>
        <div class="r"><span class="btn btn-ghost"><span class="when-closed"><?php echo esc_html__('更换', 'wpsss'); ?></span><span class="when-open"><?php echo esc_html__('收起', 'wpsss'); ?></span></span></div>
      </summary>
      <div class="wpsss-vendor-grid">
        <?php foreach ($groups as $gid => $items) : ?>
          <div class="js-wpsss-vendor-group" data-wpsss-group="<?php echo esc_attr($gid); ?>">
            <p class="choice-group"><?php echo esc_html($group_label[$gid] ?? $gid); ?></p>
            <div class="choice-cards is-vendors is-compact">
              <?php foreach ($items as $id => $label) : ?>
                <?php $on = $driver === $id; ?>
                <label class="choice<?php echo $on ? ' is-on' : ''; ?>">
                  <input type="radio" name="<?php echo esc_attr($key); ?>[driver]" value="<?php echo esc_attr($id); ?>" data-wpsss-disclose="driver" <?php checked($on); ?> />
                  <span class="tile vendor-tile<?php echo $on ? ' accent' : ''; ?>"><?php echo Wpsss_Icons::vendor($id); ?></span>
                  <span>
                    <div class="t"><?php echo esc_html($label); ?></div>
                    <div class="d"><?php echo esc_html($driver_help[$id] ?? ''); ?></div>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  </section>
  <?php endif; ?>

  <?php if (!$adv) : ?>
  <section class="card">
    <h2 class="section-title"><?php echo esc_html__('当前组合', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('更换云厂商或密钥，请切换到高级模式。', 'wpsss'); ?></p>
    <div class="simple-row is-static">
      <div class="tile<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::vendor($driver); ?></div>
      <div><div class="t"><?php echo esc_html__('存储桶', 'wpsss'); ?></div><div class="d"><?php echo esc_html(($drivers[$driver] ?? $driver) . ' · ' . $region_label); ?></div></div>
      <div class="r"><span class="pill<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo $opts['bucket'] !== '' ? esc_html($opts['bucket']) : esc_html__('未设置', 'wpsss'); ?></span></div>
    </div>
    <div class="simple-row is-static">
      <div class="tile<?php echo $opts['custom_domain'] !== '' ? ' ok' : ' warn'; ?>"><?php echo Wpsss_Icons::svg('external'); ?></div>
      <div>
        <div class="t"><?php echo esc_html__('对外地址', 'wpsss'); ?></div>
        <div class="d<?php echo $public_base !== '' ? ' is-url' : ''; ?>"><?php
          if ($opts['custom_domain'] !== '') {
              echo esc_html($opts['custom_domain']);
          } elseif ($public_base !== '') {
              echo esc_html($public_base);
          } else {
              echo esc_html__('未设置自定义域名时，前台将使用存储桶默认域名。', 'wpsss');
          }
        ?></div>
      </div>
      <div class="r">
        <?php if ($opts['custom_domain'] !== '') : ?>
          <span class="pill ok"><?php echo esc_html__('自定义域名', 'wpsss'); ?></span>
        <?php else : ?>
          <a class="btn btn-ghost" href="<?php echo esc_url($url('settings', ['mode' => 'advanced'])); ?>#wpsss-access"><?php echo esc_html__('填写自定义域名', 'wpsss'); ?></a>
        <?php endif; ?>
      </div>
    </div>
    <?php foreach ([
        'keep_local' => ['本地备份', '文件进桶后仍留在 uploads', 'plug'],
        'upload_thumbs' => ['同时上传缩略图', '附件生成的各尺寸文件一并上传', 'image'],
        'origin_protect' => ['原图保护', '媒体库与文章中的原图也会带上图片样式；未填写样式时不生效', 'sparkle'],
    ] as $field => $copy) : ?>
      <?php $on = $opts[$field] === '1'; ?>
      <div class="simple-row is-action">
        <div class="tile<?php echo $on ? ' ok' : ($field === 'keep_local' ? ' warn' : ''); ?>"><?php echo Wpsss_Icons::svg($copy[2]); ?></div>
        <div>
          <div class="t"><?php echo esc_html($copy[0]); ?></div>
          <div class="d"><?php
            if ($field === 'origin_protect') {
                echo $has_style
                    ? esc_html__('原图地址也会带上处理样式，避免直接打开未处理文件。', 'wpsss')
                    : esc_html__('请先在高级设置填写图片样式，否则此开关不会生效。', 'wpsss');
            } else {
                echo esc_html($copy[1]);
            }
          ?></div>
        </div>
        <div class="r">
          <?php if ($field === 'keep_local' && $on) : ?>
            <button type="button" class="toggle on is-locked" disabled title="<?php echo esc_attr__('关闭本地备份须到「设置 → 高级」操作；关闭后服务器不再保留副本，故障时无法从本地恢复', 'wpsss'); ?>" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo esc_html__('已开启', 'wpsss'); ?></button>
          <?php elseif ($field === 'origin_protect' && !$has_style && !$on) : ?>
            <a class="btn btn-ghost" href="<?php echo esc_url(add_query_arg(array('page' => 'wpsss', 'tab' => 'settings', 'mode' => 'advanced'), admin_url('admin.php'))); ?>"><?php echo esc_html__('去填写图片样式', 'wpsss'); ?></a>
          <?php else : ?>
          <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wpsss_quick" />
            <?php wp_nonce_field('wpsss_quick'); ?>
            <input type="hidden" name="<?php echo esc_attr($field); ?>" value="<?php echo $on ? '0' : '1'; ?>" />
            <noscript><button class="btn btn-secondary" type="submit"><?php echo esc_html__('保存', 'wpsss'); ?></button></noscript>
            <button type="button" class="toggle<?php echo $on ? ' on' : ''; ?> js-wpsss-toggle" title="<?php echo esc_attr($copy[1]); ?>" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $on ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="simple-row is-action">
      <div class="tile<?php echo $url_access === 'signed' ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('eye'); ?></div>
      <div>
        <div class="t"><?php echo esc_html__('对象访问', 'wpsss'); ?></div>
        <div class="d"><?php echo $url_access === 'signed'
            ? esc_html__('前台使用带时效的签名地址，适合私有桶', 'wpsss')
            : esc_html__('公开读要求存储桶允许匿名访问；若桶禁止匿名访问，前台图片可能无法显示。', 'wpsss'); ?></div>
      </div>
      <div class="r">
        <form class="wpsss-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="wpsss_quick" />
          <?php wp_nonce_field('wpsss_quick'); ?>
          <input type="hidden" name="url_access" value="<?php echo $url_access === 'signed' ? 'public' : 'signed'; ?>" />
          <noscript><button class="btn btn-secondary" type="submit"><?php echo esc_html__('保存', 'wpsss'); ?></button></noscript>
          <button type="button" class="toggle<?php echo $url_access === 'signed' ? ' on' : ''; ?> js-wpsss-toggle" title="<?php echo esc_attr($url_access === 'signed' ? __('使用签名 URL，私有桶也可正常访问', 'wpsss') : __('公开读；若桶禁止匿名访问，前台图片可能无法显示', 'wpsss')); ?>" data-on="<?php echo esc_attr__('签名访问', 'wpsss'); ?>" data-off="<?php echo esc_attr__('公开读', 'wpsss'); ?>"><i></i><?php echo $url_access === 'signed' ? esc_html__('签名访问', 'wpsss') : esc_html__('公开读', 'wpsss'); ?></button>
        </form>
      </div>
    </div>
  </section>
  <?php else : ?>
  <section class="card" id="wpsss-fields">
    <h2 class="section-title"><?php echo esc_html__('桶与密钥', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('建议使用子用户密钥，并仅授予当前存储桶权限。', 'wpsss'); ?></p>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('桶名', 'wpsss'); ?></div><div class="field-help js-wpsss-bucket-help"><?php echo esc_html($bucket_help[$driver] ?? __('只填写存储桶名称，不要带域名。', 'wpsss')); ?></div></div><div class="field-ctl">
        <div class="field-ctl-row">
          <input name="<?php echo esc_attr($key); ?>[bucket]" type="text" value="<?php echo esc_attr($opts['bucket']); ?>" class="js-wpsss-bucket" autocomplete="off" />
          <button type="button" class="btn btn-secondary js-wpsss-inspect"><?php echo esc_html__('读取桶信息', 'wpsss'); ?></button>
        </div>
        <p class="hint js-wpsss-inspect-hint" hidden></p>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('区域', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('填写桶名与密钥后，可点击「读取桶信息」自动回填。', 'wpsss'); ?></div></div><div class="field-ctl"><select name="<?php echo esc_attr($key); ?>[region]" class="js-wpsss-region"><?php if ($opts['region'] !== '' && !$region_in_list) : ?><option value="<?php echo esc_attr($opts['region']); ?>" selected><?php echo esc_html($opts['region'] . '（当前保存）'); ?></option><?php endif; ?><?php foreach (['cn' => '国内', 'overseas' => '海外'] as $rid => $rlabel) : if ($grouped_regions[$rid] === []) { continue; } ?><optgroup label="<?php echo esc_attr($rlabel); ?>"><?php foreach ($grouped_regions[$rid] as $id => $label) : ?><option value="<?php echo esc_attr($id); ?>"<?php selected($opts['region'], $id); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div></div>
      <div class="field js-wpsss-for-aliyun js-wpsss-origin-region"<?php echo $show_origin ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('源区域', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('使用传输加速时，仍按这个区域连接阿里云。可留空，默认杭州。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[origin_region]" type="text" value="<?php echo esc_attr((string) ($opts['origin_region'] ?? '')); ?>" placeholder="oss-cn-hangzhou" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('对象访问', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('公开读要求存储桶允许匿名访问。签名访问支持阿里云 OSS 与 S3 / TOS / GCS，其余厂商请使用公开读。', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[url_access]" class="js-wpsss-access">
          <option value="public"<?php selected($url_access, 'public'); ?>><?php echo esc_html__('公开读', 'wpsss'); ?></option>
          <option value="signed"<?php selected($url_access, 'signed'); ?>><?php echo esc_html__('签名访问', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field js-wpsss-ttl"<?php echo $url_access === 'signed' ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('签名有效期（秒）', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('默认 3600 秒，最长 7 天。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[signed_ttl]" type="number" min="60" max="604800" value="<?php echo esc_attr((string) ($opts['signed_ttl'] ?? '3600')); ?>" /></div></div>
      <div class="field js-wpsss-for-s3"<?php echo $s3_family ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('S3 预设', 'wpsss'); ?></div><div class="field-help js-wpsss-s3-preset-help"><?php echo $s3_locked === 'gcs' ? esc_html__('Google Cloud Storage 使用此预设，不能更换。', 'wpsss') : ($s3_locked === 'tos' ? esc_html__('火山引擎使用此预设，不能更换。', 'wpsss') : esc_html__('将自动填入默认 Endpoint。', 'wpsss')); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[s3_preset]" class="js-wpsss-s3-preset"<?php echo $s3_locked !== '' ? ' hidden' : ''; ?>>
          <?php foreach (Wpsss_Options::s3_presets() as $id => $label) : ?>
            <option value="<?php echo esc_attr($id); ?>"<?php selected($s3_locked !== '' ? $s3_locked : $opts['s3_preset'], $id); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" class="js-wpsss-s3-lock-label" value="<?php echo $s3_locked !== '' ? esc_attr(Wpsss_Options::s3_presets()[$s3_locked]) : ''; ?>" disabled<?php echo $s3_locked !== '' ? '' : ' hidden'; ?> />
      </div></div>
      <div class="field js-wpsss-for-s3"<?php echo $s3_family ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('Endpoint', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('留空则按上方预设生成。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[endpoint]" type="text" value="<?php echo esc_attr($opts['endpoint']); ?>" placeholder="https://tos-s3-cn-beijing.volces.com" /></div></div>
      <div class="field js-wpsss-for-s3"<?php echo $s3_family ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('路径风格访问', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('R2、MinIO 等平台通常需要启用。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[force_path_style]" value="<?php echo $opts['force_path_style'] === '1' ? '1' : '0'; ?>" />
        <button type="button" class="toggle<?php echo $opts['force_path_style'] === '1' ? ' on' : ''; ?> js-wpsss-flip" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $opts['force_path_style'] === '1' ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
      </div></div>
      <div class="field js-wpsss-app-id-field"<?php echo $show_app_id ? '' : ' hidden'; ?>><div><div class="field-label js-wpsss-app-id-label"><?php echo ($opts['s3_preset'] ?? '') === 'r2' && $driver === Wpsss_Options::DRIVER_S3 ? esc_html__('R2 Account ID', 'wpsss') : esc_html__('APPID（COS）', 'wpsss'); ?></div><div class="field-help js-wpsss-app-id-help"><?php echo ($opts['s3_preset'] ?? '') === 'r2' && $driver === Wpsss_Options::DRIVER_S3 ? esc_html__('用于拼接服务 Endpoint。', 'wpsss') : esc_html__('请填写创建存储桶的主账号 APPID。子账号的 APPID 无效。桶名已包含 APPID 时可以留空。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[app_id]" type="text" value="<?php echo esc_attr($opts['app_id']); ?>" autocomplete="off" class="js-wpsss-app-id" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('访问密钥 ID', 'wpsss'); ?></div><div class="field-help"><?php echo $id_set ? esc_html(sprintf('已保存 · 末四位 %s', $id_tail)) : esc_html__('RAM 子用户 AccessKey ID', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_id]" type="text" value="" autocomplete="off" placeholder="<?php echo $id_set ? esc_attr('••••' . $id_tail) : ''; ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('访问密钥 Secret', 'wpsss'); ?></div><div class="field-help"><?php echo $view['secret_set'] ? esc_html__('已保存；留空保存时保持不变。', 'wpsss') : esc_html__('不会在页面中显示', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[access_key_secret]" type="password" value="" autocomplete="new-password" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('使用内网', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('网站服务器与存储桶在同一区域时可以启用。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[use_internal]" value="<?php echo $opts['use_internal'] === '1' ? '1' : '0'; ?>" />
        <button type="button" class="toggle<?php echo $opts['use_internal'] === '1' ? ' on' : ''; ?> js-wpsss-flip" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $opts['use_internal'] === '1' ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
      </div></div>
      <div class="field js-wpsss-for-aliyun"<?php echo $driver === Wpsss_Options::DRIVER_ALIYUN ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('签名版本', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('默认 V4。部分新区域只认 V4；旧对接可改回 V1。', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[oss_signature]">
          <option value="v4"<?php selected($oss_signature, 'v4'); ?>><?php echo esc_html__('V4（推荐）', 'wpsss'); ?></option>
          <option value="v1"<?php selected($oss_signature, 'v1'); ?>><?php echo esc_html__('V1', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field js-wpsss-for-aliyun"<?php echo $driver === Wpsss_Options::DRIVER_ALIYUN ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('凭证', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('一般用 RAM 子用户密钥。网站服务器是阿里云 ECS 且绑了角色时，可选实例角色，不必再填密钥。', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[oss_auth]" class="js-wpsss-oss-auth">
          <option value="ak"<?php selected($oss_auth, 'ak'); ?>><?php echo esc_html__('AccessKey', 'wpsss'); ?></option>
          <option value="ram"<?php selected($oss_auth, 'ram'); ?>><?php echo esc_html__('ECS 实例 RAM 角色', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field js-wpsss-for-aliyun js-wpsss-ram-role"<?php echo $driver === Wpsss_Options::DRIVER_ALIYUN && $oss_auth === 'ram' ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('RAM 角色名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('可留空，由实例元数据返回。临时密钥不会写入设置。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[oss_ram_role]" type="text" value="<?php echo esc_attr((string) ($opts['oss_ram_role'] ?? '')); ?>" autocomplete="off" /></div></div>
  </section>
  <section class="card" id="wpsss-access">
    <h2 class="section-title"><?php echo esc_html__('访问与处理', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('访客看到的地址，以及上传后的命名与图片处理。', 'wpsss'); ?></p>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('自定义域名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('访客看到的媒体地址。CDN 已指向存储桶时，填写它的 HTTPS 地址即可，不限定厂商。使用七牛时必须填写此项。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[custom_domain]" type="text" value="<?php echo esc_attr($opts['custom_domain']); ?>" placeholder="https://static.example.com" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('对象前缀 / 子目录', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('相当于对象路径前缀，如 media。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[object_prefix]" type="text" value="<?php echo esc_attr($opts['object_prefix']); ?>" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('自动重命名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('上传时自动重命名，降低直链被猜测的风险。', 'wpsss'); ?></div></div><div class="field-ctl">
        <select name="<?php echo esc_attr($key); ?>[auto_rename]">
          <option value="off"<?php selected($opts['auto_rename'], 'off'); ?>><?php echo esc_html__('不改文件名', 'wpsss'); ?></option>
          <option value="md5"<?php selected($opts['auto_rename'], 'md5'); ?>><?php echo esc_html__('MD5', 'wpsss'); ?></option>
          <option value="time"<?php selected($opts['auto_rename'], 'time'); ?>><?php echo esc_html__('时间戳+随机', 'wpsss'); ?></option>
        </select>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('图片样式', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('填写后接到每张图片地址后面，由存储桶做缩放或质量处理。以 ? 开头视为查询参数，例如 ?x-oss-process=image/quality,q_80。GIF 与 SVG 不会追加。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[image_style]" type="text" value="<?php echo esc_attr($opts['image_style']); ?>" class="js-wpsss-style" placeholder="?x-oss-process=image/quality,q_80" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('原图保护', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('打开后，媒体库和文章里的原图也会带上上面的样式，避免访客直接打开未处理的原图。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[origin_protect]" value="<?php echo $opts['origin_protect'] === '1' ? '1' : '0'; ?>" />
        <button type="button" class="toggle<?php echo $opts['origin_protect'] === '1' ? ' on' : ''; ?> js-wpsss-flip js-wpsss-protect-toggle"<?php echo !$has_style && $opts['origin_protect'] !== '1' ? ' hidden' : ''; ?> data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo $opts['origin_protect'] === '1' ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
        <p class="hint js-wpsss-protect-hint"<?php echo trim((string) ($opts['image_style'] ?? '')) !== '' ? ' hidden' : ''; ?>><?php echo esc_html__('未填写图片样式时，此开关不会生效。', 'wpsss'); ?></p>
      </div></div>
      <div class="field js-wpsss-for-cos"<?php echo $driver === Wpsss_Options::DRIVER_COS ? '' : ' hidden'; ?>><div><div class="field-label"><?php echo esc_html__('COS 数据万象', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('压缩与文档预览追加到 URL。内容审核会检查新评论。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_slim]" value="0" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_preview]" value="0" />
        <input type="hidden" name="<?php echo esc_attr($key); ?>[ci_audit]" value="0" />
        <div class="chk">
          <label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_slim]" value="1" <?php checked($opts['ci_slim'], '1'); ?> /> <?php echo esc_html__('图片极智压缩 / webp', 'wpsss'); ?></label>
          <label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_preview]" value="1" <?php checked($opts['ci_preview'], '1'); ?> /> <?php echo esc_html__('文档预览参数', 'wpsss'); ?></label>
          <label><input type="checkbox" name="<?php echo esc_attr($key); ?>[ci_audit]" value="1" <?php checked($opts['ci_audit'], '1'); ?> /> <?php echo esc_html__('内容审核', 'wpsss'); ?></label>
        </div>
      </div></div>
      <?php
        $more_open = trim((string) ($opts['exclude_ext'] ?? '')) !== ''
            || (($opts['woo_policy'] ?? 'exclude') !== 'exclude')
            || (($opts['skip_exist'] ?? '0') === '1')
            || trim((string) ($opts['cache_control'] ?? '')) !== ''
            || trim((string) ($opts['rewrite_from'] ?? '')) !== '';
      ?>
      <details class="wpsss-more"<?php echo $more_open ? ' open' : ''; ?>>
        <summary>
          <span class="t"><?php echo esc_html__('更多处理', 'wpsss'); ?></span>
          <span class="d"><?php echo esc_html__('扩展名、付费下载、缓存响应头，以及迁移时的原地址。不改变上面的访客地址。', 'wpsss'); ?></span>
        </summary>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('不进桶的扩展名', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('用逗号分隔。php、htaccess 等可执行扩展名始终不会上传。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[exclude_ext]" type="text" value="<?php echo esc_attr((string) ($opts['exclude_ext'] ?? '')); ?>" placeholder="svg,zip,exe" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('付费下载目录', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('管理 uploads/woocommerce_uploads 中的商品文件。不进桶：留在服务器，由本地权限控制访问。私有进桶：对象不公开，下载地址带签名。照常进桶：跟随全站设置。', 'wpsss'); ?></div></div><div class="field-ctl"><select name="<?php echo esc_attr($key); ?>[woo_policy]">
        <option value="exclude"<?php selected(($opts['woo_policy'] ?? 'exclude'), 'exclude'); ?>><?php echo esc_html__('不进桶（推荐）', 'wpsss'); ?></option>
        <option value="private"<?php selected(($opts['woo_policy'] ?? 'exclude'), 'private'); ?>><?php echo esc_html__('私有进桶，下载使用签名地址', 'wpsss'); ?></option>
        <option value="off"<?php selected(($opts['woo_policy'] ?? 'exclude'), 'off'); ?>><?php echo esc_html__('照常进桶', 'wpsss'); ?></option>
      </select></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('远端已有则跳过', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('补传前先检查桶里是否已有同名对象；已有则跳过，不覆盖。', 'wpsss'); ?></div></div><div class="field-ctl">
        <input type="hidden" name="<?php echo esc_attr($key); ?>[skip_exist]" value="<?php echo (($opts['skip_exist'] ?? '0') === '1') ? '1' : '0'; ?>" />
        <button type="button" class="toggle<?php echo ($opts['skip_exist'] ?? '0') === '1' ? ' on' : ''; ?> js-wpsss-flip" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo ($opts['skip_exist'] ?? '0') === '1' ? esc_html__('已开启', 'wpsss') : esc_html__('未开启', 'wpsss'); ?></button>
      </div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('Cache-Control', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('写入对象响应头。留空则公开读 30 天、签名访问 1 小时。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[cache_control]" type="text" value="<?php echo esc_attr((string) ($opts['cache_control'] ?? '')); ?>" placeholder="public, max-age=2592000" /></div></div>
      <div class="field"><div><div class="field-label"><?php echo esc_html__('额外改写来源', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('迁移域名时，在此填写原 CDN 地址。', 'wpsss'); ?></div></div><div class="field-ctl"><input name="<?php echo esc_attr($key); ?>[rewrite_from]" type="text" value="<?php echo esc_attr($opts['rewrite_from']); ?>" placeholder="https://old-cdn.example.com" /></div></div>
      </details>
      <input type="hidden" name="<?php echo esc_attr($key); ?>[keep_local]" value="<?php echo esc_attr($opts['keep_local']); ?>" />
      <input type="hidden" name="<?php echo esc_attr($key); ?>[upload_thumbs]" value="<?php echo esc_attr($opts['upload_thumbs']); ?>" />
  </section>
  <div class="wpsss-savebar">
    <span class="meta js-wpsss-save-meta"><?php echo esc_html__('密钥留空保存时保持不变。', 'wpsss'); ?></span>
    <button class="btn btn-primary" type="submit"><?php echo esc_html__('保存设置', 'wpsss'); ?></button>
  </div>
  <?php endif; ?>
<?php if ($adv) : ?>
</form>
<?php else : ?>
</div>
<?php endif; ?>
