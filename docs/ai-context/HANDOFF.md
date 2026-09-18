# WPSSS 交接

下一个 agent 从这里接着做。不要从零猜产品。

## 现在（2026-09-18）

- **main `262c6bc` 已是 0.3.0-dev**：阿里上传/删除/URL + 4.0 E 后台 + `wp wpsss` CLI + AGENTS/CLAUDE/develop。
- 沙箱 `~/Studio/wpsss-dev` 已激活，测过测试桶（导入 URL 进桶，删附件 OSS 404）。
- 组织仍是 staging candidate，未 publish。
- **未做**：自定义域、CyberCDN 焊 purge、第二家 driver、装任何生产站。

## 建议下一刀（等人说再做）

自定义域 + 现成 CyberCDN Zone。不要买节点。不要装 wptea。

## 会话里已经否决

整站上 OSS。腾讯官方套件。生产桶。`rsync --delete` 进 Studio。JSON 改 `active_plugins`。扫盘清 shm/OSS。

## 联系

集群 SOP / 计划 / 原型在 linuxjoy，不在本仓复制密钥。
