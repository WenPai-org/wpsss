# WPSSS 交接

下一个 agent 从这里接着做。不要从零猜产品。

## 现在（2026-09-18）

- **0.5.0**：四家 driver 都接上了（阿里 OSS / 腾讯 COS / 七牛 KODO / 华为 OBS），自写 REST，不粘 SDK。
- 共有能力对齐 sy-records：缩略图、留/删本地、自定义域字段、库内 URL 改写（from/to）、全区域、同步记录、自动重命名、媒体编辑/原图、多站点 uploads 路径、`wp sss upload|delete`、图片样式、原图保护、对象前缀、失败可见、正文运行时改写。
- COS 另有 APPID、万象开关（slim / 文档预览 URL、审核只记开关）。七牛对外必须自定义域。
- Aliyun 沙箱回归：`wp sss doctor` 无阻断，`probe` 成功。匿名 403 仍 WARN。
- 关 `keep_local` 仍须 `--allow-no-local`。简单页不能一键关本地备份。
- 组织仍是 staging candidate，未 publish。
- **未做（须人批）**：自定义域 + CyberCDN Zone、生产站、万象审核实打 CI、清边缘。

## 建议下一刀（等人说再做）

自定义域 + 现成 CyberCDN Zone。不要买节点。不要装 wptea。COS/KODO/OBS 只在有对应测试桶后再 `probe`。

## 会话里已经否决

整站上 OSS。腾讯官方套件。生产桶。`rsync --delete` 进 Studio。JSON 改 `active_plugins`。扫盘清 shm/OSS。关 `keep_local`。

## 联系

集群 SOP / 计划 / 原型在 linuxjoy，不在本仓复制密钥。

## 2026-09-18 23:20 - [Cursor] 0.6.0 S3 兼容

自写 SigV4。driver `s3-compat` + 国内第五张 `volc-tos`（内部 tos 预设）。预设 aws/do/r2/minio/wasabi/tos/custom。BOS 已删。单测 `tests/unit-s3-signer.php`。没有人批的 AWS/R2/TOS 桶，不宣称 probe 绿。不粘 SDK，不装 WOM。

## 2026-09-18 23:50 - [Cursor] 0.7.0 站点场景 + 签名读

跟文派叶子：domestic / crossborder / mixed。切换只改推荐厂商，不改桶密钥。国内云海外区域可自选。url_access=signed 给阿里 OSS 与 S3/TOS。无新桶不宣称海外 probe。

## 2026-09-18 23:55 - [Cursor] 不做百度 BOS / ImageX

人批：没做的先等。sy-records `volcengine-imagex-wordpress` 不当对象存储。百度 BOS 不做（含 S3 预设）。TOS 仍是火山对象存储卡。

## 2026-09-18 23:58 - [Cursor] TOS 要做；S3 对照 Human Made S3-Uploads

火山对象存储继续做（TOS 卡 + 官方区域 + 内网 ivolces）。S3 对照 github.com/humanmade/S3-Uploads：只记能力（endpoint/path-style/私有+签名/Cache-Control），不装、不粘 AWS SDK、不抄 stream wrapper。ImageX / BOS 仍不做。

## 2026-09-19 00:30 - [Cursor] 0.8.0 Woo / 拉回 / GCS

Woo 付费下载改写到桶 URL（没装 Woo 空操作）。工具/CLI/媒体库拉回本地，默认不删远端。GCS 命名卡走 HMAC + SigV4，不粘 Google SDK。无 GCS 测试桶，不宣称 probe。
