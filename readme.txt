=== Ding Pusher ===
Contributors: kate522
Tags: dingtalk, dingding, webhook, notifications, wordpress
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically push WordPress new posts and new user registration messages to DingTalk bots.

== Description ==
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
1. Admin upload: go to Plugins → Add New → Upload Plugin, select the ZIP and activate.
2. FTP upload: unzip and upload to `wp-content/plugins/`, then activate in WordPress.

== Frequently Asked Questions ==
= What if push fails? =
Check the Webhook, security settings, and server network access to DingTalk.

= Why are there no records? =
Records are created only after a successful push. Trigger a push and check logs.

= XLSX export is unavailable? =
Make sure ZipArchive or PclZip is enabled on the server, or export CSV instead.

== Changelog ==
= 1.0.2 =
- Added XLSX export compatibility with ZipArchive/PclZip and clearer availability notices.
- Improved locale loading, English translations, and Help page content.
- Expanded Help page with quick start and troubleshooting.

= 1.0.1 =
- Refactored plugin entry and core classes.
- Added uninstall cleanup and refined admin copy.
- Fixed parts of the push flow.

= 1.0.0 =
- Initial release.
- Added new post push, deduplication, and new user notifications.
- Added multiple message types.

== Upgrade Notice ==
= 1.0.2 =
Improves XLSX export compatibility and expands help and translations.
