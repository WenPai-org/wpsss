# 使用

后台左侧 **文派对象存储**（不是「设置 → WPSSS」）。

## 页面

| 页 | 干什么 |
|----|--------|
| 概览 | 桶 / 对外地址 / 本地备份是否接通 |
| 设置 · 简单 | 看当前组合；本地备份、缩略图开关即时写 |
| 设置 · 高级 | 厂商、桶、区域、密钥、前缀、重命名、图片样式、原图保护、自定义域 |
| 工具 | 测试连通、补传已有附件、改写文章 URL。清边缘尚未开放 |

## WP-CLI

在已激活本插件的站点上：

```bash
wp sss status
wp sss set --from-env
wp sss probe
wp sss doctor
wp sss offload --dry-run
wp sss pull --dry-run
wp sss url <附件ID>
wp sss rewrite
wp sss upload ./a.png --key=manual/a.png
wp sss delete manual/a.png
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
