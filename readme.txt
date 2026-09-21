=== WPSSS ===
Contributors: wenpai
Tags: oss, cdn, aliyun, offload
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.9.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

文派智储（WPSSS）。一个插件：国内四家 + 火山 TOS + Amazon S3 兼容：阿里云 OSS / 腾讯云 COS / 七牛云 KODO / 华为云 OBS。

== Description ==

媒体文件存进对象存储桶，网页仍从自己的服务器打开。支持自定义域名、签名访问、大文件分片、WooCommerce 付费下载保护与 WP-CLI。

== Changelog ==

= 0.5.0 =
四家 driver + 共有能力（样式/原图保护/重命名/前缀/同步记录/CLI upload-delete）。

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
