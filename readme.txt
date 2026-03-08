=== Ding Pusher ===
Contributors: kate522
Tags: dingtalk, dingding, webhook, notifications, wordpress
Requires at least: 5.8
Tested up to: 5.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically push WordPress new posts and new user registration messages to DingTalk bots.

== Description ==
**中文**
Ding Pusher 是一款 WordPress 插件，用于自动检测新文章并通过钉钉机器人推送，同时支持新用户注册提示、去重、记录管理与导出。

主要特性：
- 钉钉机器人配置：Webhook 与安全校验（关键词/加签/IP 白名单）。
- 触发场景：新文章发布、更新、新用户注册。
- 模板与占位符：文本/链接/Markdown 模板。
- 去重与记录管理：标记、清理、导出 CSV / XLSX。
- 导出自动清理：默认保留 24 小时。
- 失败重试与日志提示。
- 帮助页与中英文界面支持。

**English**
Ding Pusher is a WordPress plugin that automatically detects new posts and pushes them to DingTalk bots. It also supports new user notifications, deduplication, records management, and exports.

Key features:
- DingTalk bot setup: Webhook and security (Keyword/Signature/IP Whitelist).
- Triggers: new posts, updates, and new user registrations.
- Templates and placeholders: text/link/Markdown templates.
- Deduplication and record management: mark, clear, export CSV / XLSX.
- Export cleanup: files are kept for 24 hours by default.
- Retry on failures with logs.
- Help page and bilingual UI.

== Installation ==
**中文**
1. 后台上传：进入“插件” → “安装插件” → “上传插件”，选择压缩包并启用。
2. FTP 上传：解压后上传到 `wp-content/plugins/` 并在后台启用。

**English**
1. Admin upload: go to Plugins → Add New → Upload Plugin, select the ZIP and activate.
2. FTP upload: unzip and upload to `wp-content/plugins/`, then activate in WordPress.

== Frequently Asked Questions ==
= 推送失败怎么办？ / What if push fails? =
请检查 Webhook 是否正确、安全校验是否匹配，以及服务器网络是否可访问钉钉。
Check the Webhook, security settings, and server network access to DingTalk.

= 为什么没有推送记录？ / Why are there no records? =
记录仅在成功推送后生成，请先触发一次推送并查看日志。
Records are created only after a successful push. Trigger a push and check logs.

= XLSX 导出不可用？ / XLSX export is unavailable? =
请确认服务器已启用 ZipArchive 或 PclZip，或改用 CSV 导出。
Make sure ZipArchive or PclZip is enabled on the server, or export CSV instead.

== Changelog ==
= 1.0.2 =
- XLSX 导出加入 ZipArchive/PclZip 兼容与可用性提示。
- 语言加载更稳定，完善英文翻译与帮助页面。
- 帮助页补充快速开始、配置清单与排查说明。

= 1.0.1 =
- 重构插件主入口，拆分核心类与更新器类。
- 补充卸载清理逻辑并优化后台文案。
- 修复部分推送流程细节。

= 1.0.0 =
- 初始版本发布。
- 支持新文章推送、去重与新用户提示。
- 支持多种消息类型。

== Upgrade Notice ==
= 1.0.2 =
改进 XLSX 导出兼容性并完善帮助与翻译。
Improves XLSX export compatibility and expands help and translations.
