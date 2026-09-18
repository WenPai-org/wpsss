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
