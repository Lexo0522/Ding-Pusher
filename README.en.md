# Ding Pusher

[中文](README.md)

![Version](https://img.shields.io/badge/version-v1.0.2-2563eb)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)

> Current stable version: `v1.0.2`

Ding Pusher is a WordPress plugin that automatically detects new posts and pushes them to DingTalk bots. It also supports new user notifications, deduplication, records management, and exports.

## Features
- DingTalk bot setup: Webhook and security (Keyword/Signature/IP Whitelist).
- Triggers: new posts, updates, and new user registrations.
- Templates and placeholders: text/link/Markdown templates.
- Deduplication and record management: mark, clear, export CSV / XLSX.
- Export cleanup: files are kept for 24 hours by default.
- Retry on failures with logs.
- Help page and bilingual UI.

## Installation
1. Admin upload: go to Plugins → Add New → Upload Plugin, select the ZIP and activate.
2. FTP upload: unzip and upload to `wp-content/plugins/`, then activate in WordPress.

## Setup
1. Create a DingTalk group bot and copy the Webhook URL.
2. Go to “Ding Pusher” → “Settings” and paste the Webhook.
3. Choose a security mode and fill Keyword/Signature/IP Whitelist.
4. Choose message type and templates, then save.
5. Send a test message to verify.

## Usage
- Check pushed posts in “Records”.
- Mark, unmark, or clear records as needed.
- Export records from the Records page as CSV / XLSX.

## Export Notes
- CSV and XLSX are supported.
- XLSX requires ZipArchive or PclZip. Use CSV if unavailable.
- Export files are cleaned up after 24 hours by default.

## FAQ
**Q:** What if push fails?
**A:** Check the Webhook, security settings, and server network access to DingTalk.

**Q:** Why are there no records?
**A:** Records are created only after a successful push. Trigger a push and check logs.

**Q:** XLSX export is unavailable?
**A:** Make sure ZipArchive or PclZip is enabled on the server, or export CSV instead.

## Changelog
### v1.0.2
- Added XLSX export compatibility with ZipArchive/PclZip and clearer availability notices.
- Improved locale loading, English translations, and Help page content.
- Expanded Help page with quick start and troubleshooting.

### v1.0.1
- Refactored plugin entry and core classes.
- Added uninstall cleanup and refined admin copy.
- Fixed parts of the push flow.

### v1.0.0
- Initial release.
- Added new post push, deduplication, and new user notifications.
- Added multiple message types.

## Support
- Docs: https://github.com/Lexo0522/Ding-Pusher
- Issues: https://github.com/Lexo0522/Ding-Pusher/issues
- Contact: kate522@88.com

## License
This plugin is licensed under GPLv2 or later. See `LICENSE` for details.
