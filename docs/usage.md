# 使用

后台左侧 **文派对象存储**（不是「设置 → WPSSS」）。

## 页面

| 页 | 干什么 |
|----|--------|
| 概览 | 连接状态、对外地址、本地备份、媒体统计与运行体检；未配置时可进设置向导 |
| 设置向导 | 4 步：站点场景 → 桶与密钥 → 已为你配置 → 完成/测试连通（`?page=wpsss&tab=setup`） |
| 设置 · 简单 | 站点场景与常用开关；保存后即时生效 |
| 设置 · 高级 | 云厂商、桶、区域、密钥、前缀、重命名、图片样式、原图保护、自定义域 |
| 工具 | 页内三组：日常（连通、补传可接着上次、记录）、排障（体检含前缀不对、诊断、抽检、清除失败）、迁移（拉回先选「批量拉回 / 单个附件」，再选本地已有时只补缺失 / 旧文件改名保留 / 覆盖；只写盘不改前台；改写含序列化 post meta；删桶先警示） |
| 帮助 | 快速开始、页面说明、常见问题与 WP-CLI 参考（插件内） |

## WP-CLI

在已激活本插件的站点上：

```bash
wp sss status
wp sss set --from-env
wp sss probe
wp sss doctor
wp sss offload --dry-run
wp sss pull --dry-run --on-exist=skip
wp sss pull --before=100
wp sss offload --before=100
wp sss scan
wp sss diagnose 128
wp sss url <附件ID>
wp sss rewrite
wp sss upload ./a.png --key=manual/a.png
wp sss delete manual/a.png --yes
```

`set --from-env` 读 `~/.config/linuxjoy/wpsss-test.env`。不要 `cat` 密钥进会话。

## 验收（测试桶）

1. `wp sss doctor` 无阻断，`keep_local=1`。匿名 403 只 WARN。
2. `wp sss probe` 成功。
3. 媒体库导入一张图：附件 URL 是 `https://<bucket>.<region>.aliyuncs.com/...`。
4. 已有附件：`wp sss offload`；文章硬编码地址：`wp sss rewrite --apply`。
5. `wp sss url <ID>` 应看到桶域、`offloaded=1`、签名 HEAD 200。
6. 对象 PUT 会带 public-read；测试桶若开了阻止公共访问，匿名 HEAD 仍 403，不能当没传上去。
7. 删附件后本地文件一并没了。

不要对生产桶做探测以外的批量操作。
