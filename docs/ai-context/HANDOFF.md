# WPSSS 交接

下一个 agent 从这里接着做。不要从零猜产品。

## 现在（2026-09-18）

- **0.4.0**：Aliyun v1 闭环（新上传、已有补传、媒体库列/批量/筛选/侧栏、文章和普通字段 URL 改写、`wp sss`、doctor）。
- 对象 PUT 带 `x-oss-object-acl: public-read`；RAM 拒 ACL 时回退无 ACL。桶仍是 private。
- 关 `keep_local` 须 `--allow-no-local`。简单页不能一键关本地备份。
- 沙箱 `~/Studio/wpsss-dev` 软链工作树或 `~/Projects/wpsss`。
- 组织仍是 staging candidate，未 publish。
- **未做（须人批）**：自定义域、CyberCDN purge、第二家 driver、生产站。

## 建议下一刀（等人说再做）

自定义域 + 现成 CyberCDN Zone。不要买节点。不要装 wptea。

## 会话里已经否决

整站上 OSS。腾讯官方套件。生产桶。`rsync --delete` 进 Studio。JSON 改 `active_plugins`。扫盘清 shm/OSS。关 `keep_local`。

## 联系

集群 SOP / 计划 / 原型在 linuxjoy，不在本仓复制密钥。
