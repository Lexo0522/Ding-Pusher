# Kate522 Notifier for DingTalk Documentation

[English](readme.txt)

![Version](https://img.shields.io/badge/version-v1.0.4-2563eb)
![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)

> Current stable version: `v1.0.4`

Kate522 Notifier for DingTalk is a WordPress plugin that automatically detects new posts and pushes them to DingTalk bots. It also supports new user notifications, deduplication, record management, and exports.

## Features
- DingTalk bot configuration: Webhook and security verification (Keyword/Signature/IP Whitelist).
- Trigger scenarios: new post publishing, updates, and new user registrations.
- Templates and placeholders: text/link/Markdown templates.
- Deduplication and record management: mark, clear, export CSV / XLSX.
- Export auto-cleanup: files are kept for 24 hours by default.
- Retry on failures with log messages.
- Help page and bilingual UI support.

## Installation
1. Admin upload: go to "Plugins" → "Add New" → "Upload Plugin", select the ZIP and activate.
2. FTP upload: unzip and upload to `wp-content/plugins/`, then activate in WordPress.

## Configuration
1. Create a DingTalk group bot and copy the Webhook.
2. Go to "Kate522 Notifier for DingTalk" → "Settings" in the admin panel and enter the Webhook.
3. Select security method and fill in keyword/signature/IP whitelist.
4. Choose message type and template, then save.
5. Send a test message to verify the configuration.

## Usage
- View pushed posts in "Push Records".
- Mark, unmark, or clear records.
- Export records to CSV / XLSX from the records page.

## Export Notes
- Supports both CSV and XLSX formats.
- XLSX requires ZipArchive or PclZip; use CSV if unavailable.
- Export files are kept for 24 hours by default then auto-cleaned.

## FAQ
**Q:** What if push fails?
**A:** Check if the Webhook is correct, security settings match, and the server network can access DingTalk.

**Q:** Why are there no push records?
**A:** Records are generated only after successful push, trigger a push first and check the logs.

**Q:** XLSX export is unavailable?
**A:** Confirm that ZipArchive or PclZip is enabled on the server, or use CSV export instead.

## Changelog



### v1.0.4

- Fixed garbled text in the plugin entry and admin pages.
- Corrected bootstrap file paths so the plugin can load normally.
- Repaired the settings page syntax issue that blocked admin rendering.

### v1.0.3

- Fixed garbled default Chinese text in main entry file
- Strengthened core layer Webhook verification logic
- Transformed export download process into controlled download

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
- Plugin Documentation: https://github.com/Lexo0522/Ding-Pusher
- Issue Feedback: https://github.com/Lexo0522/Ding-Pusher/issues
- Contact Author: kate522@88.com

## License
This plugin is licensed under GPLv2 or later, see `LICENSE` for details.
