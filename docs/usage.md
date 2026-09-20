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

## 大文件

- 上传：32MB 以上自动分片（阿里云 OSS、S3 兼容全家：AWS / 火山 TOS / GCS / R2 / MinIO 等）。COS / OBS / 七牛仍是单请求，超时按体积放大；几百 MB 的文件建议放这三家以外的厂商，或用厂商自己的工具直传后 `wp sss offload` 补登记。
- 拉回：全厂商分段下载，服务器内存占用固定（每段 8MB），大视频也能拉。

## 付费下载（WooCommerce）

设置 → 高级 → 付费下载目录：

- 不进桶（默认）：uploads/woocommerce_uploads 留在服务器，靠本地权限拦截直链。
- 私有进桶：对象以私有 ACL 上传，商品下载地址带签名，知道地址也拿不走。
- 照常：跟随全站对象访问设置。

## 存量地址

- 文章/区块模板/自定义背景头图里写死的本站 uploads 地址，渲染时自动换桶地址——只换确实已进桶的附件，未进桶的旧图保持本地。
- 要把地址写进数据库（换域名后），用迁移页的「改写文章中的媒体地址」，先预览再写入。

## 换桶 / 换厂商

    wp sss copy --to-bucket=新桶 --to-driver=aliyun-oss --dry-run
    wp sss copy --to-bucket=新桶 --limit=200          # 分批搬
    wp sss copy --to-bucket=新桶 --update-keys --yes  # 搬完改写对象键

同厂商走服务端复制（不占本地带宽），跨厂商分段中转。搬完把插件设置切到新桶。

## 失败与退避

- 同一附件连续失败 3 次后退出自动队列；后台「重试失败项」或 `wp sss offload --source=failed` 手动重试。
- 后台慢跑停转时，工具页会提示剩多少个失败项。

## 边界

- 多站点（Multisite）暂不支持，请勿在网络激活。
