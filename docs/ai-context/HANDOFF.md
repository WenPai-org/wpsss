# WPSSS 交接

下一个 agent 从这里接着做。不要从零猜产品。

## 现在（2026-09-19）

- **0.8.0**（`28d6c9a`）：国内 OSS/COS/KODO/OBS + 火山 TOS + S3 兼容 + GCS HMAC 卡。自写 REST / SigV4，不粘 SDK。
- **sy-records 共有能力已对齐**：缩略图、留/删本地、自定义域字段、库内 URL 改写、全区域、同步记录、自动重命名、媒体编辑/原图、图片样式、原图保护、对象前缀、失败可见、正文运行时改写、`wp sss upload|delete`。
- **0.8.0 增量**：Woo 付费下载、拉回本地（工具/CLI/媒体库）、站点场景 domestic/crossborder/mixed、签名读（阿里 OSS / S3 / TOS / GCS）。
- **后台 4.0 E**：概览 / 设置 / 工具 / 帮助 + **4 步设置向导**（`tab=setup`，不强制跳转）。向导：站点场景 → 桶与密钥 → 已为你配置 → 完成/测试连通；`setup_completed_at` 标记完成。
- **沙箱**：Mac Studio `~/Studio/wpsss-dev`（`:8895`）。Aliyun 测试桶 `wenpai-wpsss-test`：`doctor` 无阻断，`probe` 成功。匿名 403 在 private 桶属 WARN。
- **组织**：staging candidate，未 publish。
- **未做（须人批）**：自定义域 + CyberCDN Zone、生产站、COS/KODO/OBS/TOS/GCS 实桶 probe、万象审核实打 CI、CDN 按 URL 清边缘、换桶迁移工具。

## 建议下一刀（等人说再做）

1. 自定义域 + 现成 CyberCDN Zone
2. COS/KODO/OBS 等测试桶 probe
3. CDN 按 URL 清边缘（工具页占位接真 API）

不要装 wptea。不要 JSON 改 `active_plugins`。

## 会话里已经否决

整站上 OSS。腾讯官方套件。生产桶。`rsync --delete` 进 Studio。扫盘清 OSS。关 `keep_local` 无人批。

## 联系

集群 SOP / 计划 / 原型在 linuxjoy，不在本仓复制密钥。

## 2026-09-18 23:20 - [Cursor] 0.6.0 S3 兼容

自写 SigV4。driver `s3-compat` + 国内第五张 `volc-tos`（内部 tos 预设）。预设 aws/do/r2/minio/wasabi/tos/custom。BOS 已删。单测 `tests/unit-s3-signer.php`。没有人批的 AWS/R2/TOS 桶，不宣称 probe 绿。不粘 SDK，不装 WOM。

## 2026-09-18 23:50 - [Cursor] 0.7.0 站点场景 + 签名读

跟文派叶子：domestic / crossborder / mixed。切换只改推荐厂商，不改桶密钥。国内云海外区域可自选。url_access=signed 给阿里 OSS 与 S3/TOS/GCS。无新桶不宣称海外 probe。

## 2026-09-18 23:55 - [Cursor] 不做百度 BOS / ImageX

人批：没做的先等。sy-records `volcengine-imagex-wordpress` 不当对象存储。百度 BOS 不做（含 S3 预设）。TOS 仍是火山对象存储卡。

## 2026-09-18 23:58 - [Cursor] TOS 要做；S3 对照 Human Made S3-Uploads

火山对象存储继续做（TOS 卡 + 官方区域 + 内网 ivolces）。S3 对照 github.com/humanmade/S3-Uploads：只记能力（endpoint/path-style/私有+签名/Cache-Control），不装、不粘 AWS SDK、不抄 stream wrapper。ImageX / BOS 仍不做。

## 2026-09-19 00:30 - [Cursor] 0.8.0 Woo / 拉回 / GCS

Woo 付费下载改写到桶 URL（没装 Woo 空操作）。工具/CLI/媒体库拉回本地，默认不删远端。GCS 命名卡走 HMAC + SigV4，不粘 Google SDK。无 GCS 测试桶，不宣称 probe。

## 2026-09-19 19:52 - [Cursor] 后台文案 + 概览体检

概览/工具页按集群记忆 #8463 收文案（专业、友好、渐进式披露）。概览新增 `Wpsss_CLI::doctor_report()` 运行体检摘要。设置页简单模式隐藏厂商网格、选中项才展开说明。

## 2026-09-19 19:58 - [Cursor] 插件内帮助页

新增 `tab=help`：快速开始、后台页面说明、站点场景/访问方式、常见问题、WP-CLI 参考；`<details>` 渐进式披露。页头/页脚「帮助」改链插件内；反馈仍链 wenpai.org/support。

## 2026-09-19 20:10 - [Cursor] 4 步设置向导

参照 WPCY 4.0 onboarding 壳（`.wizard` / `.steps`，680px，向导页隐藏横 Tab）。`admin_post_wpsss_setup` 分步保存；概览 `.next`、设置 hint、帮助快速开始链向导；probe 可回跳 `setup&step=4`。

## 2026-09-19 20:05 - [Cursor] url.php 签名方法补回 + Studio 点验

rsync 误删后 `class-wpsss-url.php` 缺 `sign_object_url` / `sign_rewritten_text`，doctor 触发 fatal。已补回。Studio `:8895` 四步向导 UI 绿；`php tests/run.php` 全绿。同步 wenpai `~/Projects/wpsss` 用 `rsync -az`（**勿** `--delete` 对 SoT 不完整树）。
