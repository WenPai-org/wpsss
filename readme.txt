=== WPSSS ===
Contributors: wenpai
Tags: oss, cdn, aliyun, offload
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.4.0
License: Apache-2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0

文派对象存储。一个插件、多个 driver；先做阿里云 OSS，自定义域名接 CyberCDN。

== Description ==

对照 sy-records Apache-2.0 全家桶的上传 / 删除 / URL 路径，自写 OSS REST + Signature V1，不整仓粘贴 SDK。
不是 Mac Studio/wpsss 腾讯云官方套件，也不是 wenpai-static-host。

== Changelog ==

= 0.4.0 =
* CLI 改为 `wp sss`。关 keep_local 须人批。
* 已有附件补传、媒体库列 / 批量 / 筛选、文章和字段 URL 改写。
* 对象默认 public-read，桶仍可 private。
* 后台按文派叶子 4.0 方向 E：概览 / 设置 / 工具。

= 0.2.0-dev =
* 阿里云 OSS：上传原图和缩略图、删附件同步删远端、自定义域改 URL。
* 默认保留本地文件。Settings API。连通探测写了即删。

= 0.1.0-dev =
* 骨架：设置页 + Aliyun OSS driver 登记。
