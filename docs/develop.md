# 开发（给下一个 agent）

## 这是什么

文派要自己的「对象存储挂 WordPress」：媒体进桶，页面仍在源站，自定义域以后走 **CyberCDN**（不是整站上 OSS，不是腾讯官方套件）。

2026-09-18 从零建仓。对照 [sy-records](https://github.com/sy-records) Apache-2.0 全家桶的上传/删除/URL 路径，**自写 OSS REST + Signature V1**，不粘贴 Aliyun SDK。后台按文派叶子 **4.0 方向 E**（人批「就按这个来」）：页头横向 概览 / 设置 / 工具。

集群会话拍板：linuxjoy `docs/plans/2026-09-18-wpsss.md`、SOP `#36`、cluster-recall **#8398**（UI 跟 4.0 E）。

## 开工

```bash
# 在 wenpai
wenpai-repo start wpsss --task <短名>
# 常驻真源
cd ~/Projects/wpsss
# 沙箱（已有，SQLite；无 DISPLAY 不要 studio create）
ln -sfn ~/Projects/wpsss ~/Studio/wpsss-dev/wp-content/plugins/wpsss
wp --path=~/Studio/wpsss-dev plugin activate wpsss
wp --path=~/Studio/wpsss-dev sss set --from-env
wp --path=~/Studio/wpsss-dev sss probe
```

Mac 上 linuxjoy 有一份同步稿 `office/cursor/wpsss-aliyun-20260918/`，**不要当真源改完不推**。有 leftover `vm-takeover` 座位时，本机 Write 进 `~/Projects/*` 会被拒。

## 代码地图

| 要改 | 文件 | 注意 |
|------|------|------|
| 后台壳 / tab | `includes/class-wpsss-admin.php` + `templates/admin/` | query `tab=` `mode=`；不要 hash |
| 4.0 外观 | `assets/css/e.css` + `admin.css` | token 跟 `~/wt/wpcy-proto/E/e.css`。不要另起一套色 |
| 选项 | `class-wpsss-options.php` | `upload_url_path` 只在空或自有旗时改 |
| 上传/删/URL | `class-wpsss-offload.php` | 图走 metadata；非图走 `wp_handle_upload` |
| 已有附件 / 文章 URL | `class-wpsss-library.php` | `wp sss offload` / `rewrite` |
| 媒体库列 / 批量 / 侧栏 | `class-wpsss-media.php` | 列表「对象存储」列 |
| 签名 | `class-wpsss-oss-signer.php` | 改完必跑 `php tests/unit-oss-signer.php` |
| 阿里请求 | `drivers/class-wpsss-driver-aliyun-oss.php` | `wp_remote_request` PUT/DELETE |
| 新云厂商 | 新 `drivers/class-wpsss-driver-*.php` + `register_driver` | 先接口齐，再 UI 打开选择卡 |
| CLI | `class-wpsss-cli.php` | 密钥不 echo |

`Wpsss_Driver`：`id` `label` `is_configured` `put_object` `delete_objects` `probe`。

## 已拍板

- 一个插件多个 driver，v1 只有阿里。
- 默认留本地。探测对象写了即删。
- 测试桶 `wenpai-wpsss-test` / `oss-cn-hangzhou` / RAM `wpsss-test`。
- 后台跟 4.0 E，RemixIcon line 内联，不用图标字体 CDN。
- 清缓存以后接 `cybercdn-url-purge`，工具页先灰着。
- 本轮不装 wptea。

## 未做（须人批再开）

1. 自定义域 + CyberCDN Zone（备案 CNAME，复用现成六边，不要加机器）。
2. COS / OBS / KODO driver。
3. 接到自用生产站。
4. 把 `e.css` 收成共享包 / `wpcy-settings` schema（现在是抄进来的视觉层）。

## 陷阱

| 坑 | 处理 |
|----|------|
| `~/Studio/wpsss` 是腾讯官方套件 | 不要焊 |
| Studio 无 DISPLAY | 用系统 `wp` + SQLite 沙箱 |
| AppleDouble `._*` | `.gitignore` 已挡 |
| `empty('0')` 会吞 checkbox | sanitize 用 `=== '1'` |
| 关 `keep_local` | sanitize / 简单页 / `wp sss set` 都挡住；须 `--allow-no-local` 且人批 |
| FeiCode 端口 | `:2222`，不要 apex |
| leftover GC | 只报不删 |

## 验证

```bash
php tests/run.php
wp --path=/home/parallels/Studio/wpsss-dev sss doctor
wp --path=/home/parallels/Studio/wpsss-dev sss probe
```

改 UI 后在沙箱后台点：概览空态/接通态、设置简单/高级保存、工具探测。
