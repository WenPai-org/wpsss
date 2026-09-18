# WPSSS 交接

下一个 agent 从这里接着做。不要从零猜产品。

## 现在（2026-09-18）

- **main `09ea925` 已是 0.3.0-dev**（功能在 `262c6bc`，说明提交 `09ea925`）。
- 本会话工作树 `~/wt/wpsss/plugin-iter`（`wt/plugin-iter`）：补 options / offload / CLI 单测；doctor 分 `OK` / `WARN` / `BLOCK`；CLI 现为 `wp sss`（不再用 `wp wpsss`）；关 `keep_local` 须 `--allow-no-local`；简单页不能一键关掉本地备份。
- 沙箱 `~/Studio/wpsss-dev` 应软链到工作树验收。
- 组织仍是 staging candidate，未 publish。
- **未做**：自定义域、CyberCDN 焊 purge、第二家 driver、装任何生产站。人要才 commit。

## 建议下一刀（等人说再做）

自定义域 + 现成 CyberCDN Zone。不要买节点。不要装 wptea。

## 会话里已经否决

整站上 OSS。腾讯官方套件。生产桶。`rsync --delete` 进 Studio。JSON 改 `active_plugins`。扫盘清 shm/OSS。关 `keep_local`。

## 联系

集群 SOP / 计划 / 原型在 linuxjoy，不在本仓复制密钥。
