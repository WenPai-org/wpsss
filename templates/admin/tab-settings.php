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
$adv = $mode === 'advanced';
$drivers = Wpsss_Options::driver_ids();
$public_base = (string) ($view['public_base'] ?? '');
$has_style = trim((string) ($opts['image_style'] ?? '')) !== '';
$groups = Wpsss_Options::driver_groups();
$group_label = [
    'cn' => '国内云',
    'intl' => '国际与兼容云',
];
$driver_help = Wpsss_Options::driver_help();
$url_access = ($opts['url_access'] ?? 'public') === 'signed' ? 'signed' : 'public';
?>
<?php $setup_done = ($opts['setup_completed_at'] ?? '') !== ''; ?>
<div class="wenpai-page-head">
  <div>
    <h1 class="wenpai-h1"><?php echo esc_html__('设置', 'wpsss'); ?></h1>
    <p class="wenpai-lede"><?php echo $adv ? esc_html__('桶与密钥保存后生效；开关仍即时生效。', 'wpsss') : esc_html__('开关即时生效。', 'wpsss'); ?></p>
    <?php if (!$setup_done && !$view['configured']) : ?>
      <p class="wpsss-setup-hint"><a href="<?php echo esc_url($url('setup')); ?>"><?php echo esc_html__('首次配置？走 4 步设置向导', 'wpsss'); ?></a></p>
    <?php endif; ?>
  </div>
  <div class="mode">
    <?php
    echo Wenpai_Admin_UI::mode_switch_html(array(
      'icon' => 'eye',
      'label' => __('显示', 'wpsss'),
      'wrap' => false,
      'items' => array(
        array('label' => __('简单', 'wpsss'), 'href' => $url('settings', ['mode' => 'simple']), 'current' => !$adv),
        array('label' => __('高级', 'wpsss'), 'href' => $url('settings', ['mode' => 'advanced']), 'current' => $adv),
      ),
    ));
    ?>
    <?php if ($adv) : ?>
      <button class="btn btn-primary" type="submit" form="wpsss-settings-form"><?php echo esc_html__('保存设置', 'wpsss'); ?></button>
    <?php endif; ?>
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
      <summary class="wpsss-row">
        <div class="wpsss-tile js-wpsss-vendor-tile<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::vendor($driver); ?></div>
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
                  <span class="wpsss-tile vendor-tile<?php echo $on ? ' accent' : ''; ?>"><?php echo Wpsss_Icons::vendor($id); ?></span>
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
    <div class="wpsss-row is-static">
      <div class="wpsss-tile<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::vendor($driver); ?></div>
      <div><div class="t"><?php echo esc_html__('存储桶', 'wpsss'); ?></div><div class="d"><?php echo esc_html(($drivers[$driver] ?? $driver) . ' · ' . $region_label); ?></div></div>
      <div class="r"><span class="wpsss-pill<?php echo $view['configured'] ? ' ok' : ''; ?>"><?php echo $opts['bucket'] !== '' ? esc_html($opts['bucket']) : esc_html__('未设置', 'wpsss'); ?></span></div>
    </div>
    <div class="wpsss-row is-static">
      <div class="wpsss-tile<?php echo $opts['custom_domain'] !== '' ? ' ok' : ' warn'; ?>"><?php echo Wpsss_Icons::svg('external'); ?></div>
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
          <span class="wpsss-pill ok"><?php echo esc_html__('自定义域名', 'wpsss'); ?></span>
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
      <div class="wpsss-row is-action">
        <div class="wpsss-tile<?php echo $on ? ' ok' : ($field === 'keep_local' ? ' warn' : ''); ?>"><?php echo Wpsss_Icons::svg($copy[2]); ?></div>
        <div>
          <div class="t"><?php echo esc_html($copy[0]); ?></div>
          <div class="d"><?php
            if ($field === 'origin_protect') {
                echo $has_style
                    ? esc_html__('原图地址也会带上处理样式，避免直接打开未处理文件。', 'wpsss')
                    : esc_html__('须先在高级设置填写图片样式，否则不生效。', 'wpsss');
            } else {
                echo esc_html($copy[1]);
            }
          ?></div>
        </div>
        <div class="r">
          <?php if ($field === 'keep_local' && $on) : ?>
            <button type="button" class="toggle on is-locked" disabled title="<?php echo esc_attr__('关闭本地备份须到「设置 → 高级」操作；关闭后服务器不再保留副本，故障时无法从本地恢复', 'wpsss'); ?>" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo esc_html__('已开启', 'wpsss'); ?></button>
          <?php elseif ($field === 'origin_protect' && !$has_style && !$on) : ?>
            <button type="button" class="toggle is-locked" disabled title="<?php echo esc_attr__('须先在高级设置填写图片样式', 'wpsss'); ?>" data-on="<?php echo esc_attr__('已开启', 'wpsss'); ?>" data-off="<?php echo esc_attr__('未开启', 'wpsss'); ?>"><i></i><?php echo esc_html__('未开启', 'wpsss'); ?></button>
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
    <div class="wpsss-row is-action">
      <div class="wpsss-tile<?php echo $url_access === 'signed' ? ' ok' : ''; ?>"><?php echo Wpsss_Icons::svg('eye'); ?></div>
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
    <?php
    $fw = $view['fw'] ?? null;
    if ($fw) {
        foreach (['bucket', 'region', 'origin_region', 'url_access', 'signed_ttl', 's3_preset', 'endpoint', 'force_path_style', 'app_id', 'access_key_id', 'access_key_secret', 'use_internal', 'oss_signature', 'oss_auth', 'oss_ram_role'] as $fid) {
            echo $fw->field_html($fid);
        }
    }
    ?>
  </section>
  <section class="card" id="wpsss-access">
    <h2 class="section-title"><?php echo esc_html__('访问与处理', 'wpsss'); ?></h2>
    <p class="section-desc"><?php echo esc_html__('访客看到的地址，以及上传后的命名与图片处理。', 'wpsss'); ?></p>
    <?php
    if ($fw) {
        foreach (['custom_domain', 'object_prefix', 'auto_rename', 'image_style', 'origin_protect', 'exclude_ext', 'woo_policy', 'skip_exist', 'cache_control', 'rewrite_from', 'ci_group'] as $fid) {
            echo $fw->field_html($fid);
        }
    }
    ?>
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
