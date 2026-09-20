<?php
defined('ABSPATH') || exit;
/** @var string $panel */
/** @var callable $url */
/** @var array<string, mixed> $scan */
/** @var array<string, mixed> $diagnose */
/** @var array<string, mixed> $head */
?>
  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('equalizer'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('媒体体检', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('只读扫描最近一批附件：本地缺失、对外主机不一致、http 明文、失败标记、前缀不对。', 'wpsss'); ?></p>
      </div>
    </div>
    <?php if ($scan !== []) : ?>
      <div class="notice info">
        <?php echo Wpsss_Icons::svg('info'); ?>
        <span><?php echo esc_html(sprintf(
            '已检查 %d 个。本地缺失 %d，主机不一致 %d，http %d，失败 %d，前缀不对 %d。',
            (int) ($scan['checked'] ?? 0),
            count($scan['local_missing'] ?? []),
            count($scan['host_mismatch'] ?? []),
            count($scan['http'] ?? []),
            count($scan['failed'] ?? []),
            count($scan['prefix_mismatch'] ?? [])
        )); ?></span>
      </div>
      <?php if (!empty($scan['expected_host'])) : ?>
        <p class="meta"><?php echo esc_html(sprintf('当前对外主机：%s', $scan['expected_host'])); ?></p>
      <?php endif; ?>
      <?php foreach (['local_missing' => '本地文件不在', 'host_mismatch' => '对外主机不一致', 'http' => '仍是 http', 'failed' => '带失败标记', 'prefix_mismatch' => '对象键对不上前缀'] as $flag => $label) : ?>
        <?php $rows = isset($scan[$flag]) && is_array($scan[$flag]) ? $scan[$flag] : []; ?>
        <?php if ($rows === []) { continue; } ?>
        <ul class="wpsss-log">
          <?php foreach ($rows as $row) : ?>
            <li>
              <div class="n"><span class="pill warn"><?php echo esc_html($label); ?></span><span class="meta">#<?php echo (int) ($row['id'] ?? 0); ?></span></div>
              <div class="p"><?php echo esc_html((string) ($row['host'] ?? $row['key'] ?? '')); ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
      <?php if (!empty($scan['local_missing'])) : ?>
        <p class="meta"><a href="<?php echo esc_url($url('tools', ['panel' => 'move'])); ?>"><?php echo esc_html__('到迁移用「只补缺失」拉回', 'wpsss'); ?></a></p>
      <?php endif; ?>
      <?php if (!empty($scan['prefix_mismatch'])) : ?>
        <p class="meta"><?php echo esc_html__('前缀不对的附件不要直接拉回，先核对象键或改回原来的前缀。', 'wpsss'); ?></p>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_scan" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_scan'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('不改数据库，也不删桶。最多看最近 40 个。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('扫描附件', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('eye'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('诊断一条地址', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('粘贴附件 ID 或完整 URL，看它是不是媒体库里的、主机对不对、本地还在不在。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_diagnose" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_diagnose'); ?>
      <div class="field">
        <div><div class="field-label"><?php echo esc_html__('附件 ID 或 URL', 'wpsss'); ?></div><div class="field-help"><?php echo esc_html__('例如 128，或 https://static.example.com/2026/09/a.jpg', 'wpsss'); ?></div></div>
        <div class="field-ctl"><input type="text" name="wpsss_diagnose" value="<?php echo esc_attr((string) ($diagnose['input'] ?? '')); ?>" placeholder="128" /></div>
      </div>
      <?php if ($diagnose !== []) : ?>
        <div class="notice <?php echo !empty($diagnose['found']) ? 'ok' : 'info'; ?>">
          <?php echo Wpsss_Icons::svg('info'); ?>
          <span><?php
            if (!empty($diagnose['found'])) {
                echo esc_html(sprintf(
                    '附件 #%d · %s · 主机 %s%s',
                    (int) $diagnose['id'],
                    !empty($diagnose['offloaded']) ? __('已进桶', 'wpsss') : __('未进桶', 'wpsss'),
                    (string) ($diagnose['host'] ?: __('无', 'wpsss')),
                    !empty($diagnose['host_ok']) ? '' : ' · ' . __('与当前设置不一致', 'wpsss')
                ));
            } else {
                echo esc_html(implode(' ', $diagnose['notes'] ?? [__('没有对应附件', 'wpsss')]));
            }
          ?></span>
        </div>
        <?php if (!empty($diagnose['found'])) : ?>
          <p class="meta"><?php echo esc_html(sprintf(
              '本地%s · 对象键 %s%s',
              !empty($diagnose['local_exists']) ? __('还在', 'wpsss') : __('缺失', 'wpsss'),
              (string) ($diagnose['key'] !== '' ? $diagnose['key'] : __('无', 'wpsss')),
              !empty($diagnose['http']) ? ' · http' : ''
          )); ?></p>
          <?php if (!empty($diagnose['notes'])) : ?>
            <p class="meta"><?php echo esc_html(implode(' ', $diagnose['notes'])); ?></p>
          <?php endif; ?>
          <?php if (!empty($diagnose['offloaded']) && empty($diagnose['local_exists'])) : ?>
            <p class="meta"><a href="<?php echo esc_url($url('tools', ['panel' => 'move'])); ?>"><?php echo esc_html__('到迁移用「只补缺失」拉回', 'wpsss'); ?></a></p>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('只读。主题直传或不在媒体库的文件会提示找不到。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('诊断', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile"><?php echo Wpsss_Icons::svg('cloud'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('抽检远端对象', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('对已进桶的附件执行 HEAD 请求。库中有标记但桶内返回 404，通常是对象已被手动删除或更换过存储桶。', 'wpsss'); ?></p>
      </div>
    </div>
    <?php if ($head !== []) : ?>
      <div class="notice <?php echo empty($head['missing']) ? 'ok' : 'info'; ?>">
        <?php echo Wpsss_Icons::svg('info'); ?>
        <span><?php echo esc_html(sprintf(
            '抽检 %d 个，远端还在 %d，异常 %d。',
            (int) ($head['checked'] ?? 0),
            (int) ($head['ok'] ?? 0),
            count($head['missing'] ?? [])
        )); ?></span>
      </div>
      <?php foreach ($head['missing'] ?? [] as $row) : ?>
        <p class="meta">#<?php echo (int) ($row['id'] ?? 0); ?> · <?php echo esc_html((string) ($row['message'] ?? '')); ?></p>
      <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="wpsss_headcheck" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_headcheck'); ?>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('每批最多 10 个，不删除任何对象。', 'wpsss'); ?></span>
        <button class="btn btn-secondary" type="submit"><?php echo esc_html__('抽检一批', 'wpsss'); ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <span class="tile warn"><?php echo Wpsss_Icons::svg('sparkle'); ?></span>
      <div>
        <h2 class="card-title"><?php echo esc_html__('清除失败标记', 'wpsss'); ?></h2>
        <p class="card-sub"><?php echo esc_html__('只去掉错误提示，不会重新上传。之后可在日常里重试失败项。', 'wpsss'); ?></p>
      </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wpsss-need="wpsss_clear_confirm">
      <input type="hidden" name="action" value="wpsss_clear_errors" />
      <input type="hidden" name="wpsss_tools_panel" value="<?php echo esc_attr($panel); ?>" />
      <?php wp_nonce_field('wpsss_clear_errors'); ?>
      <div class="chk wpsss-gate">
        <label><input type="checkbox" name="wpsss_clear_confirm" value="1" /> <?php echo esc_html__('我确认清除失败标记', 'wpsss'); ?></label>
      </div>
      <div class="card-foot start">
        <span class="meta"><?php echo esc_html__('每批最多 50 条。', 'wpsss'); ?></span>
        <span class="wpsss-actions">
          <a class="btn btn-ghost" href="<?php echo esc_url($url('tools', ['panel' => 'daily'])); ?>"><?php echo esc_html__('去日常重试', 'wpsss'); ?></a>
          <button class="btn btn-ghost" type="submit" data-wpsss-need-apply disabled data-wpsss-confirm="<?php echo esc_attr__('确认清除失败标记？不会重新上传。', 'wpsss'); ?>"><?php echo esc_html__('清除标记', 'wpsss'); ?></button>
        </span>
      </div>
    </form>
  </section>
