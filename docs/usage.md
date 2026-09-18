# 使用

后台左侧 **文派对象存储**（不是「设置 → WPSSS」）。

## 页面

| 页 | 干什么 |
|----|--------|
| 概览 | 桶 / 对外地址 / 本地备份是否接通 |
| 设置 · 简单 | 看当前组合；本地备份、缩略图开关即时写 |
| 设置 · 高级 | 桶、区域、密钥、内网、自定义域。保存走 Settings API |
| 工具 | 测试连通。清边缘缓存尚未开放 |

## WP-CLI

在已激活本插件的站点上：

```bash
wp wpsss status
wp wpsss set --from-env
wp wpsss probe
wp wpsss doctor
```

`set --from-env` 读 `~/.config/linuxjoy/wpsss-test.env`。不要 `cat` 密钥进会话。

## 验收（测试桶）

1. `wpsss doctor` 无阻断，`keep_local=1`。
2. `wpsss probe` 成功。
3. 媒体库导入一张图：附件 URL 是 `https://<bucket>.<region>.aliyuncs.com/...`。
4. 删附件后 OSS HEAD 404；本地文件一并没了。

不要对生产桶做探测以外的批量操作。
