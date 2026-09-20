# WPSSS 交接

下一个 agent 从这里接着做。不要从零猜产品。

## 现在（2026-09-20）

- **0.8.0**，FeiCode `origin/main` **`55618dc`**（推前 `4a4989c`）。
- 工具页拆 **日常 / 排障 / 迁移**。拉回两问：**拉哪些**（下一批 / 指定一个）+ **本地已有时**（只补缺失 / 旁路改名 / 覆盖）。不要再做成「附件 / 全部」数字框。
- **拉回只写磁盘**。进桶标记还在则网页地址仍是桶。要改地址：解除标记，或拉回并删除。
- 旁路改名失败会把 sidecar 搬回正式名。对象键对不上当前前缀则停。改写会动正文、摘要、post meta（含序列化）；不改 options / theme_mods / 磁盘 CSS。
- 补传/拉回按 ID 倒序续跑，可「从头」。CLI：`wp sss pull --before=` / `wp sss offload --before=`。
- 沙箱 Mac Studio `:8895`。测试桶 `wenpai-wpsss-test`。茶馆试点已装：`keep_local=1`，`custom_domain=https://m.wptea.com`，桶 `wenpai-wpsss-wptea`，**不要写** `upload_url_path`。本刀未 overlay tea，除非人再批。
- leftover 座位（wenpai 墨图爱可等）**不要 vm-return**。Mac 不要 Write `~/Projects/*`。

## 现在（2026-09-19）

- **0.8.0**（`28d6c9a`）：国内 OSS/COS/KODO/OBS + 火山 TOS + S3 兼容 + GCS HMAC 卡。自写 REST / SigV4，不粘 SDK。
- **sy-records 共有能力已对齐**：缩略图、留/删本地、自定义域字段、库内 URL 改写、全区域、同步记录、自动重命名、媒体编辑/原图、图片样式、原图保护、对象前缀、失败可见、正文运行时改写、`wp sss upload|delete`。
- **0.8.0 增量**：Woo 付费下载、拉回本地（工具/CLI/媒体库）、站点场景 domestic/crossborder/mixed、签名读（阿里 OSS / S3 / TOS / GCS）。
- **后台 4.0 E**：概览 / 设置 / 工具 / 帮助 + **4 步设置向导**（`tab=setup`，不强制跳转）。向导：站点场景 → 桶与密钥 → 已为你配置 → 完成/测试连通；`setup_completed_at` 标记完成。
- **沙箱**：Mac Studio `~/Studio/wpsss-dev`（`:8895`）。Aliyun 测试桶 `wenpai-wpsss-test`：`doctor` 无阻断，`probe` 成功。匿名 403 在 private 桶属 WARN。
- **组织**：staging candidate，未 publish。
- **未做（须人批）**：自定义域 + CyberCDN Zone、生产站、COS/KODO/OBS/TOS/GCS 实桶 probe、万象审核实打 CI、CDN 按 URL 清边缘、换桶迁移工具。

## 2026-09-19 21:50 - [Cursor] sy-records 对齐全量

五路并行：URL/GIF/签名补 COS·KODO·OBS；改写翻页 + 序列化 walker；恢复 `pull_batch`；媒体库 JS / 原图保护 filter；`origin_region` / https endpoint / 卸载自有 `upload_url_path`；帮助 FAQ；`Wpsss_Ci` 挂评论（失败放行）。产品 `a59dfd6` 已推 FeiCode main。

## 2026-09-19 22:20 - [Cursor] COS 万象文本审核 HTTP

`Wpsss_Ci` 对评论走 `POST /text/auditing`（COS 签名，失败放行）。filter 仍可覆盖。未接监控图表。

## 2026-09-19 22:10 - [Cursor] OSS V4 + ECS RAM

阿里默认签名 V4（官方 canonical 哈希已锁）。高级设置可改回 V1。`oss_auth=ram` 时问 ECS 元数据取 STS，不落 options。未接 CyberCDN，未关 keep_local。

## 2026-09-19 22:00 - [Cursor] 缩略图键 + 签名样式

`rewrite_url` 按 URL 文件名取对象键（thumb / original / intermediate 不再全回原图）。`serve_object_url` 把阿里 `x-oss-process` 写进签名串，避免 `??`。媒体库 JS 的 sizes 走同一条改写。未接 CyberCDN / V4 / RAM。

## 2026-09-20 - [Cursor] 拉回运营坑 + 范围拆开

人批：完整任务计划后做，再核验；「附件 / 全部」看不懂。计划 `.specs/F-pull-ops-pits.md`（linuxjoy SoT）。写盘≠改地址、sidecar 回滚、前缀闸、改写诚实、Woo 删桶句、批次续跑。拉回 UI 改成「下一批 / 指定一个」。Studio 已点验。茶馆未再 overlay。

## 建议下一刀（等人说再做）

1. 人批后再 overlay 茶馆（回退点仍是 `wpsss.bak-20260920-1126`）
2. COS/KODO/OBS 等测试桶 probe
3. CDN 按 URL 清边缘（不做桶孤儿 GC）

默认不要新装 wptea。茶馆试点已经在。不要 JSON 改 `active_plugins`。

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
