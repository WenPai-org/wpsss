# 验收 — wpsss Playground 错态

- 席：本机 WordPress Playground，`http://127.0.0.1:16018`。不是长期站。没有改 `play.wenpai.net` 仓库或线上 registry。没有装 WooCommerce。没有填密钥。
- 谁：Grok
- 日期：2026-09-22
- 包：分支 `feat/vendor-wenpai-admin-ui-0.2.3` 在 `4dc5ef1` 的 `git archive`，目录前缀 `wpsss/`。
- 运行：CLI 启动行写的是 PHP 8.3、WordPress latest。站点健康信息是 WordPress 7.1.1、PHP 8.5.10。下面以站点健康为准。
- kit：`wenpai-admin.css?ver=0.2.3`、`wenpai-admin-wp.css?ver=0.2.3`、`wenpai-admin.js?ver=0.2.3`
- 错态：工具 → 迁移。「解除进桶标记」的 `.wenpai-gate` 确认勾选未勾，提交按钮带 `disabled`。页面里去掉这个按钮的 `disabled` 和 `data-wpsss-confirm` 后提交同一张表单，勾选保持未勾。`handle_release()` 在调用 `release_flags()` 之前转到错误提示。
- 看见：页顶 `.notice.warn`，文案「解除标记前请先勾选确认项。」地址是 `admin.php?page=wpsss&tab=tools&wpsss_level=error&panel=move`（`wpsss_notice` 为上面这句）。`render()` 把 `wpsss_level=error` 画成 `warn`，不是 `bad`。
- 拉回第二勾：同一页先选「覆盖」，再勾「还要永久删除桶内对象」。`.wenpai-gate.js-wpsss-pull-gate` 可见，第一勾未勾。`.wpsss-gate-extra` 可见，里面是 `.notice.bad`「桶里没有回收站。」第二勾未勾。「拉回并删除」按钮 `disabled`。这一下没有提交。
- 未改配置：随后概览仍是「尚未连接存储桶」。设置简单仍显示桶「未设置」，没有「已保存」密钥。工具 → 日常仍是「还没有操作记录」。截图无密钥、无真桶名。
- 截图：`playground-error-notice.png`（页顶提示）、`playground-error-page.png`（提示在工具 → 迁移页上）、`playground-error-gate.png`（拉回第二勾未满足，按钮禁用）。
- 产品代码未改。未跑 lint，未跑 `php tests/run.php`。未合 main。
