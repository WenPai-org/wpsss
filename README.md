# WPSSS · 文派对象存储

一个 WordPress 插件，四个对象存储 driver：阿里云 OSS / 腾讯云 COS / 七牛 KODO / 华为云 OBS。自定义域名以后接 CyberCDN。**HTML 仍在源站。**

| | |
|--|--|
| 真源 | FeiCode 私有 [`WenPai-org/wpsss`](https://feicode.com/WenPai-org/wpsss)（`git.feicode.com:2222`） |
| 工位 | wenpai `~/Projects/wpsss`（`wenpai-repo start wpsss`） |
| 版本 | 0.6.0 |
| 许可 | Apache-2.0（NOTICE 写明对照 sy-records） |

**不是** Mac `~/Studio/wpsss`（腾讯云官方 COS/CDN 套件）。**不是** `wenpai-static-host`。**不是** 火山 ImageX。

## 人怎么用

1. 激活插件。后台左侧「文派对象存储」。
2. **设置 → 高级**：桶、区域、RAM 密钥。默认保留本地文件。
3. **工具 → 测试连通**。或 `wp sss probe`。
4. 上传媒体，附件 URL 应是桶域或自定义域。

开关即时生效。桶和密钥要保存。

## Agent / CLI

```bash
wenpai-repo start wpsss --task <短名>
wp --path=~/Studio/wpsss-dev sss status
wp --path=~/Studio/wpsss-dev sss set --from-env
wp --path=~/Studio/wpsss-dev sss probe
wp --path=~/Studio/wpsss-dev sss doctor
```

`--from-env` 读 `~/.config/linuxjoy/wpsss-test.env`（`WPSSS_OSS_*`）。密钥不进 git、不进对话。

下一个 agent **先读**：

1. [`AGENTS.md`](AGENTS.md)
2. [`CLAUDE.md`](CLAUDE.md)
3. [`docs/develop.md`](docs/develop.md)
4. [`docs/ai-context/HANDOFF.md`](docs/ai-context/HANDOFF.md)

集群 SOP：linuxjoy `docs/ops/sop-wpsss.md`。计划：`docs/plans/2026-09-18-wpsss.md`。UI 原型：`docs/plans/wpsss-ui/`。
