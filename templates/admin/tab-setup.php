<?php
defined('ABSPATH') || exit;
/** @var array<string, mixed> $view */
$url = $view['url'];
$step = (int) ($view['setup_step'] ?? 1);
$opts = $view['opts'];
$on = (bool) $view['configured'];
$site_mode = Wpsss_Options::sanitize_site_mode($opts['site_mode'] ?? 'domestic');
$site_modes = Wpsss_Options::site_modes();
$saved_driver = (string) $opts['driver'];
$driver = $saved_driver;
$drivers = Wpsss_Options::driver_ids();
$preview_driver = isset($_GET['preview_driver']) ? sanitize_key((string) wp_unslash($_GET['preview_driver'])) : '';
if ($preview_driver !== '' && isset($drivers[$preview_driver])) {
    $driver = $preview_driver;
}
$groups = Wpsss_Options::driver_groups_for_mode($site_mode, $driver);
$group_label = [
    'cn' => __('国内云', 'wpsss'),
    'intl' => __('国际与兼容云', 'wpsss'),
];
$grouped_regions = Wpsss_Options::grouped_regions($driver);
$url_access = ($opts['url_access'] ?? 'public') === 'signed' ? 'signed' : 'public';
$driver_help = [
    Wpsss_Options::DRIVER_ALIYUN => __('国内站点常用', 'wpsss'),
    Wpsss_Options::DRIVER_COS => __('适合已有腾讯云 COS 桶', 'wpsss'),
    Wpsss_Options::DRIVER_KODO => __('对外访问须配置自定义域名', 'wpsss'),
    Wpsss_Options::DRIVER_OBS => __('适合已有华为云 OBS 桶', 'wpsss'),
    Wpsss_Options::DRIVER_TOS => __('适合已有火山引擎 TOS 桶', 'wpsss'),
    Wpsss_Options::DRIVER_S3 => __('兼容 AWS、Cloudflare R2、MinIO', 'wpsss'),
    Wpsss_Options::DRIVER_GCS => __('适合 Google Cloud 存储桶', 'wpsss'),
];
$bucket_help = [
    Wpsss_Options::DRIVER_ALIYUN => __('仅填写桶名，不含域名后缀', 'wpsss'),
    Wpsss_Options::DRIVER_COS => __('格式 name-APPID', 'wpsss'),
    Wpsss_Options::DRIVER_KODO => __('仅填写空间名称', 'wpsss'),
    Wpsss_Options::DRIVER_OBS => __('仅填写桶名', 'wpsss'),
    Wpsss_Options::DRIVER_TOS => __('仅填写桶名', 'wpsss'),
    Wpsss_Options::DRIVER_S3 => __('仅填写桶名；Endpoint 可在高级设置调整', 'wpsss'),
    Wpsss_Options::DRIVER_GCS => __('仅填写桶名；Endpoint 可在高级设置调整', 'wpsss'),
];
$show_app_id = $driver === Wpsss_Options::DRIVER_COS
    || ($driver === Wpsss_Options::DRIVER_S3 && ($opts['s3_preset'] ?? '') === 'r2');
$id_set = $opts['access_key_id'] !== '';
$id_tail = $id_set ? substr($opts['access_key_id'], -4) : '';
$done_rows = isset($view['setup_done_rows']) && is_array($view['setup_done_rows']) ? $view['setup_done_rows'] : [];
$doctor = isset($view['doctor']) && is_array($view['doctor']) ? $view['doctor'] : [];
$regions = Wpsss_Options::regions_for($driver);
$region_in_list = isset($regions[$opts['region']]);
$region_label = $regions[$opts['region']] ?? $opts['region'];
$steps = [
    1 => __('站点场景', 'wpsss'),
    2 => __('连接存储', 'wpsss'),
    3 => __('已为你配置', 'wpsss'),
    4 => __('完成', 'wpsss'),
];
$s3_preset = $driver === Wpsss_Options::DRIVER_GCS ? 'gcs' : ($driver === Wpsss_Options::DRIVER_TOS ? 'tos' : 'aws');
?>
<div class="wizard wpsss-setup">
  <nav class="steps" aria-label="<?php echo esc_attr__('设置向导步骤', 'wpsss'); ?>">
    <?php foreach ($steps as $num => $label) : ?>
      <?php
        $state = 'todo';
        if ($num < $step) {
            $state = 'done';
        } elseif ($num === $step) {
            $state = 'cur';
        }
        ?>
      <?php if ($num > 1) : ?><span class="bar" aria-hidden="true"></span><?php endif; ?>
      <span class="st <?php echo esc_attr($state); ?>">
        <span class="n"><?php echo $state === 'done' ? '✓' : (string) (int) $num; ?></span>
        <span><?php echo esc_html($label); ?></span>
      </span>
    <?php endforeach; ?>
  </nav>

  <?php if ($step === 1) : ?>
    <h1><?php echo esc_html__('选择站点场景', 'wpsss'); ?></h1>
    <p class="lede"><?php echo esc_html__('用于调整推荐云厂商顺序；不会自动创建或绑定存储桶。', 'wpsss'); ?></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_setup" />
      <input type="hidden" name="wpsss_setup_action" value="step1" />
      <?php wp_nonce_field('wpsss_setup'); ?>
      <div class="choice-cards">
        <?php foreach ($site_modes as $id => $row) : ?>
          <?php $active = $site_mode === $id; ?>
          <label class="choice<?php echo $active ? ' is-on' : ''; ?>">
            <input type="radio" name="site_mode" value="<?php echo esc_attr($id); ?>" <?php checked($active); ?> />
            <span>
              <div class="t"><?php echo esc_html($row['label']); ?></div>
              <div class="d"><?php echo esc_html($row['help']); ?></div>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="actions">
        <button class="btn btn-primary" type="submit"><?php echo esc_html__('下一步', 'wpsss'); ?></button>
        <a class="btn btn-ghost" href="<?php echo esc_url($url('overview')); ?>"><?php echo esc_html__('稍后再说', 'wpsss'); ?></a>
      </div>
    </form>

  <?php elseif ($step === 2) : ?>
    <h1><?php echo esc_html__('连接存储桶', 'wpsss'); ?></h1>
    <p class="lede"><?php echo esc_html__('填写与存储桶对应的云厂商、区域与访问密钥。保存后可随时在高级设置调整。', 'wpsss'); ?></p>
    <?php if ($preview_driver !== '' && $preview_driver !== $saved_driver) : ?>
      <div class="notice info wpsss-setup-preview">
        <?php echo Wpsss_Icons::svg('info'); ?>
        <span><?php echo esc_html(sprintf(
            __('正在预览「%s」，尚未保存。当前生效：「%s」。', 'wpsss'),
            $drivers[$driver] ?? $driver,
            $drivers[$saved_driver] ?? $saved_driver
        )); ?></span>
      </div>
    <?php endif; ?>
    <form id="wpsss-setup-step2" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpsss-setup-form">
      <input type="hidden" name="action" value="wpsss_setup" />
      <input type="hidden" name="wpsss_setup_action" value="step2" />
      <input type="hidden" name="s3_preset" value="<?php echo esc_attr($s3_preset); ?>" />
      <?php wp_nonce_field('wpsss_setup'); ?>

      <section class="card wpsss-setup-block">
        <h2 class="section-title"><?php echo Wpsss_Icons::svg('cloud'); ?> <?php echo esc_html__('云厂商', 'wpsss'); ?></h2>
        <?php foreach ($groups as $gid => $items) : ?>
          <p class="choice-group"><?php echo esc_html($group_label[$gid] ?? $gid); ?></p>
          <div class="choice-cards is-vendors">
            <?php foreach ($items as $id => $label) : ?>
              <?php $active = $driver === $id; ?>
              <label class="choice<?php echo $active ? ' is-on' : ''; ?>">
                <input type="radio" name="driver" value="<?php echo esc_attr($id); ?>" data-wpsss-setup-driver="1" <?php checked($active); ?> />
                <span class="tile vendor-tile<?php echo $active ? ' accent' : ''; ?>"><?php echo Wpsss_Icons::vendor($id); ?></span>
                <span>
                  <div class="t"><?php echo esc_html($label); ?></div>
                  <?php if ($active) : ?><div class="d"><?php echo esc_html($driver_help[$id] ?? ''); ?></div><?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="card wpsss-setup-block">
        <h2 class="section-title"><?php echo esc_html__('桶与密钥', 'wpsss'); ?></h2>
        <div class="field"><div><div class="field-label"><?php echo esc_html__('存储桶名称', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html($bucket_help[$driver] ?? ''); ?></div></div><div class="field-ctl"><input class="js-wpsss-bucket" name="bucket" type="text" value="<?php echo esc_attr($opts['bucket']); ?>" autocomplete="off" required /></div></div>
        <div class="field"><div><div class="field-label"><?php echo esc_html__('区域', 'wpsss'); ?></div></div><div class="field-ctl">
          <select name="region">
            <?php if ($opts['region'] !== '' && !$region_in_list) : ?>
              <option value="<?php echo esc_attr($opts['region']); ?>" selected><?php echo esc_html($opts['region'] . __('（当前保存）', 'wpsss')); ?></option>
            <?php endif; ?>
            <?php foreach (['cn' => __('国内', 'wpsss'), 'overseas' => __('海外', 'wpsss')] as $gid => $glabel) : ?>
              <?php if ($grouped_regions[$gid] === []) { continue; } ?>
              <optgroup label="<?php echo esc_attr($glabel); ?>">
                <?php foreach ($grouped_regions[$gid] as $rid => $rlabel) : ?>
                  <option value="<?php echo esc_attr($rid); ?>" <?php selected($opts['region'], $rid); ?>><?php echo esc_html($rlabel); ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div></div>
        <?php if ($show_app_id) : ?>
        <div class="field"><div><div class="field-label"><?php echo ($opts['s3_preset'] ?? '') === 'r2' ? esc_html__('R2 Account ID', 'wpsss') : esc_html__('APPID（COS）', 'wpsss'); ?></div><div class="field-help"><?php echo ($opts['s3_preset'] ?? '') === 'r2' ? esc_html__('用于拼接服务 Endpoint。', 'wpsss') : esc_html__('若桶名已含 APPID 可留空。', 'wpsss'); ?></div></div><div class="field-ctl"><input class="js-wpsss-app-id" name="app_id" type="text" value="<?php echo esc_attr($opts['app_id']); ?>" autocomplete="off" /></div></div>
        <?php endif; ?>
        <div class="field"><div><div class="field-label"><?php echo esc_html__('访问密钥 ID', 'wpsss'); ?></div><div class="field-help"><?php echo $id_set ? esc_html(sprintf(__('已保存 · 末四位 %s', 'wpsss'), $id_tail)) : esc_html__('RAM 子用户 AccessKey ID', 'wpsss'); ?></div></div><div class="field-ctl"><input name="access_key_id" type="text" value="" autocomplete="off" placeholder="<?php echo $id_set ? esc_attr('••••' . $id_tail) : ''; ?>" <?php echo $id_set ? '' : 'required'; ?> /></div></div>
        <div class="field"><div><div class="field-label"><?php echo esc_html__('访问密钥 Secret', 'wpsss'); ?></div><div class="field-help"><?php echo $view['secret_set'] ? esc_html__('已保存；留空保存时保持不变。', 'wpsss') : esc_html__('不会在页面中显示', 'wpsss'); ?></div></div><div class="field-ctl"><input name="access_key_secret" type="password" value="" autocomplete="new-password" <?php echo $view['secret_set'] ? '' : 'required'; ?> /></div></div>
        <div class="field"><div><div class="field-label"><?php echo esc_html__('对象访问', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('公开读适合静态资源；私有桶可选签名访问。', 'wpsss'); ?></div></div><div class="field-ctl">
          <select name="url_access" class="js-wpsss-access">
            <option value="public"<?php selected($url_access, 'public'); ?>><?php echo esc_html__('公开读', 'wpsss'); ?></option>
            <?php if (Wpsss_Options::supports_signed_get($driver)) : ?>
            <option value="signed"<?php selected($url_access, 'signed'); ?>><?php echo esc_html__('签名访问', 'wpsss'); ?></option>
            <?php endif; ?>
          </select>
        </div></div>
      </section>

      <div class="actions">
        <button class="btn btn-primary" type="submit"><?php echo esc_html__('保存并继续', 'wpsss'); ?></button>
        <a class="btn btn-ghost" href="<?php echo esc_url($url('setup', ['step' => '1'])); ?>"><?php echo esc_html__('上一步', 'wpsss'); ?></a>
      </div>
    </form>

  <?php elseif ($step === 3) : ?>
    <h1><?php echo esc_html__('已为你做的', 'wpsss'); ?></h1>
    <p class="lede"><?php echo esc_html__('以下默认项已就绪；细项可在设置页调整。', 'wpsss'); ?></p>
    <ul class="wpsss-done-list">
      <?php foreach ($done_rows as $row) : ?>
        <li>
          <span class="tile ok"><?php echo Wpsss_Icons::svg('sparkle'); ?></span>
          <div>
            <div class="t"><?php echo esc_html($row['t']); ?></div>
            <div class="d"><?php echo esc_html($row['d']); ?></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($doctor !== []) : ?>
      <?php
        $doctor_context = 'setup';
        $doctor_expanded = true;
        $doctor_show_foot = false;
        include WPSSS_DIR . 'templates/admin/part-doctor-card.php';
        ?>
    <?php endif; ?>
    <div class="actions">
      <a class="btn btn-primary" href="<?php echo esc_url($url('setup', ['step' => '4'])); ?>"><?php echo esc_html__('下一步', 'wpsss'); ?></a>
      <a class="btn btn-ghost" href="<?php echo esc_url($url('setup', ['step' => '2'])); ?>"><?php echo esc_html__('修改桶与密钥', 'wpsss'); ?></a>
    </div>

  <?php else : ?>
    <h1><?php echo esc_html__('可以开始用了', 'wpsss'); ?></h1>
    <p class="lede"><?php echo esc_html__('建议先测试连通，再上传媒体或补传历史附件。', 'wpsss'); ?></p>
    <section class="card wpsss-setup-summary">
      <div class="simple-row">
        <div class="tile ok"><?php echo Wpsss_Icons::vendor($saved_driver); ?></div>
        <div>
          <div class="t"><?php echo esc_html__('存储桶', 'wpsss'); ?></div>
          <div class="d"><?php echo esc_html(($drivers[$saved_driver] ?? $saved_driver) . ' · ' . $region_label . ' · ' . $opts['bucket']); ?></div>
        </div>
        <div class="r"><span class="pill ok"><?php echo esc_html__('已连接', 'wpsss'); ?></span></div>
      </div>
      <div class="simple-row">
        <div class="tile"><?php echo Wpsss_Icons::svg('map'); ?></div>
        <div>
          <div class="t"><?php echo esc_html__('站点场景', 'wpsss'); ?></div>
          <div class="d"><?php echo esc_html($site_modes[$site_mode]['label'] ?? $site_mode); ?></div>
        </div>
      </div>
    </section>
    <div class="actions wpsss-setup-finish-actions">
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="wpsss_setup" />
        <input type="hidden" name="wpsss_setup_action" value="finish" />
        <?php wp_nonce_field('wpsss_setup'); ?>
        <button class="btn btn-primary" type="submit"><?php echo esc_html__('完成向导', 'wpsss'); ?></button>
      </form>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="wpsss_probe" />
        <input type="hidden" name="wpsss_return_tab" value="setup" />
        <?php wp_nonce_field('wpsss_probe'); ?>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('测试连通', 'wpsss'); ?></button>
      </form>
      <a class="btn btn-ghost" href="<?php echo esc_url($url('tools', ['panel' => 'daily'])); ?>"><?php echo esc_html__('打开工具页', 'wpsss'); ?></a>
    </div>
  <?php endif; ?>
</div>
