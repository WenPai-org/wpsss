# WPSSS — 执行合约

跨 agent。事实在 `CLAUDE.md`。

## 1. 先读

1. 本文件
2. `CLAUDE.md`
3. `docs/develop.md`
4. `docs/ai-context/HANDOFF.md`
5. 集群：linuxjoy `docs/ops/sop-wpsss.md`、`docs/ai-context/name-confusion.md` WPSSS 行

## 2. 边界

- 工位 wenpai `~/Projects/wpsss`。Mac 有 leftover 座位时不要 Write `~/Projects/*`。
- 测试只许桶 `wenpai-wpsss-test`。不要写爱可 / 文风 / w.org / 印迹桶。
- 默认 `keep_local=1`。关掉须人批。
- 拉回默认只写磁盘。进桶标记还在则网页地址仍是桶。工具页拆日常/排障/迁移；拉回先选下一批或指定一个。

- 不装 wptea、不 JSON 改线上 `active_plugins`。生产装插件须 board + `#20`。
- 插件只许软链进 `~/Studio`。禁止 `rsync --delete`。
- 对照 sy-records Apache-2.0，不整仓粘贴 SDK。签名是 OSS REST V1。
- 后台跟文派叶子 **4.0 方向 E**。不要退回 `form-table`。
- leftover YAML 只报不删。

## 3. 验证

```bash
php -l wpsss.php
php tests/run.php
wp --path=/home/parallels/Studio/wpsss-dev sss status
wp --path=/home/parallels/Studio/wpsss-dev sss doctor
```

改签名再跑 signer 单测。改 offload / CLI 再 probe。没跑只能写「已改，未验证」。

## 4. 提交

人要才 commit。远程 `ssh://git@feicode.com:2222/WenPai-org/wpsss.git`。不要 apex `:2222`。不要把 `.env`、密钥、`._*` 推进仓。

## 5. 签名

linuxjoy 会话写 `[Cursor]` / `[CC]` / `[CX]`。顶班写 `[代wenpai]`。
