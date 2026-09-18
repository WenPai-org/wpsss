# WPSSS — 项目事实

文派对象存储。一个插件、多个 driver，先阿里云 OSS。自定义域名接 CyberCDN。HTML 仍在源站。

## 身份

| | |
|--|--|
| 组织 | `product-wpsss`（staging candidate，`co-wenpai`）。未进 published `registry.yml` |
| 工作站 | linuxjoy `configs/wenpai-products.yml` id `wpsss` |
| 真源 | FeiCode `WenPai-org/wpsss` |
| 工位 | wenpai `~/Projects/wpsss` |
| 沙箱 | wenpai `~/Studio/wpsss-dev`（SQLite；软链到本仓） |
| UI | 文派叶子 4.0 方向 E。页头：概览 / 设置 / 工具 |

## 目录

```
wpsss.php                 入口、WP-CLI 登记
includes/
  class-wpsss-plugin.php  单例、driver、Settings API
  class-wpsss-admin.php   菜单、enqueue、probe/quick
  class-wpsss-cli.php     wp wpsss status|set|probe|doctor
  class-wpsss-options.php 选项、区域、upload_url_path 旗
  class-wpsss-oss-signer.php  Signature V1
  class-wpsss-offload.php 上传/删/URL/srcset
  class-wpsss-icons.php   RemixIcon 内联
  drivers/class-wpsss-driver-aliyun-oss.php
assets/css/e.css          4.0 token（从 wpcy-proto/E 抄，去掉 #wpcontent 锁）
templates/admin/          壳 + 三 tab
tests/unit-oss-signer.php
```

## 选项

`wpsss_options`：`driver` `bucket` `region` `access_key_id` `access_key_secret` `custom_domain` `keep_local` `upload_thumbs` `use_internal`。

`wpsss_owns_upload_url_path`：只有该项为空或由自己写入时才改 `upload_url_path`。

## 测试桶

文派账号杭州 `wenpai-wpsss-test`，RAM `wpsss-test`。密钥 `~/.config/linuxjoy/wpsss-test.env`（`WPSSS_OSS_*`）。

## 不要做

整站上 OSS。焊腾讯官方套件。Lua cwebp。扫盘清桶。加 CyberCDN 机器（那是 devops）。

下一刀（须人批）：自定义域 + CyberCDN Zone；COS/OBS/KODO；接到生产站。
