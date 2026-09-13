<!-- DevSponsors Badges -->
<p align="center">
  <a href="https://devsponsors.github.io"><img src="https://img.shields.io/badge/DevSponsors-Verified_OSS-6366f1?style=for-the-badge&logo=github" alt="DevSponsors Verified"></a>
  <a href="https://devsponsors.github.io"><img src="https://img.shields.io/badge/Sponsor-DevSponsors_Hub-emerald?style=for-the-badge&logo=github-sponsors" alt="DevSponsors Sponsor"></a>
  <a href="https://devsponsors.github.io/mediakit.html"><img src="https://img.shields.io/badge/Infrastructure-DevSponsors_Cloud-ec4899?style=for-the-badge&logo=server" alt="DevSponsors Cloud"></a>
</p>

=== RickUp ===
Contributors: m4tinbeigi-official
Tags: backup, rollback,telegram,backup
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WordPress backup and rollback plugin with Telegram integration, proxy support, and more. Secure your site effortlessly!

== Description ==

RickUp is a powerful, user-friendly WordPress plugin designed for automated backups and easy rollback functionality. It handles daily database and uploads backups separately, stores them locally on your host, and optionally sends them to Telegram via bot (with built-in proxy support for regions where Telegram is filtered). Customize backup frequency, enable email notifications, encrypt files, and rollback plugins/themes to previous versions—all from a modern admin dashboard.

### Key Features:
* **Automated Backups**: Schedule daily/weekly/monthly backups for database, uploads, or full site (including plugins/themes).
* **Telegram Integration**: Send backups directly to a Telegram channel/group via bot. Supports proxy (HTTP/SOCKS5) for filtered regions.
* **Rollback**: Easily switch plugins/themes to previous versions from WordPress.org API—auto-backup before changes.
* **Security & Extras**: Simple encryption, retention policies, email alerts, and detailed logs.
* **User-Friendly UI**: Clean, intuitive dashboard with tabs, switches, and one-click actions.

Perfect for site owners needing reliable backups without complexity. Developed by Rick Sanchez—explore more at [ricksacnehz.ir](https://ricksacnehz.ir).

== Installation ==

1. Upload the `rickup` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **RickUp > Settings** to configure backup frequency, Telegram bot (token from @BotFather, chat ID from @userinfobot), proxy details (if needed), and other options.
4. Test Telegram connection with the "Test Telegram" button.
5. Backups start automatically—view/manage in **RickUp > Backups**.

**Requirements**:
- PHP 7.4+ with ZipArchive extension (for file backups).
- WordPress 5.0+.

== Frequently Asked Questions ==

= Does RickUp support proxy for Telegram in filtered countries? =
Yes! Configure proxy host, port, username, and password in settings. It uses WordPress's `wp_remote_post` with proxy args for seamless integration.

= How do I rollback a plugin? =
In **RickUp > Rollback**, select the Plugins tab. If previous versions are available, click "Rollback to [version]". It auto-downloads, installs, and backs up first.

= Are backups encrypted? =
Yes, enable "Encrypt Backups" and set a key (min 8 chars). Uses simple XOR encryption (demo; recommend openssl for production).

= Can I get notifications? =
Enable "Email Notifications" to receive success/failure alerts at your admin email.

= What about large files for Telegram? =
Files >48MB are chunked automatically (sent as parts). Telegram limit: 50MB per file.

== Screenshots ==

1. Settings page with frequency, Telegram, and proxy options.
2. Backups management table.
3. Rollback tabs for plugins/themes.
4. Logs overview.

(Upload screenshots to `/assets/` in your plugin folder for WordPress.org display.)

== Changelog ==

= 1.1 - October 27, 2025 =
* Added proxy support for Telegram (HTTP/SOCKS5).
* Introduced email notifications and full-site backups.
* Enhanced rollback with better version filtering.
* Improved logging and UI polish.
* Security: Full sanitization/escaping.

= 1.0 - Initial Release =
* Core backup/rollback features.
* Telegram integration without proxy.
* Basic admin dashboard.

== Upgrade Notice ==

= 1.1 =
Update for proxy support and new features. No data migration needed—back up first!

== Support ==

Report issues or suggest features at [ricksacnehz.ir/support](https://ricksacnehz.ir/) or via WordPress.org forums.

== Credits ==

- Developed by Rick Sanchez.
- Icons from Dashicons (WordPress).
- Thanks to the WordPress community!

== Translation ==

RickUp is translation-ready. Contribute at [ricksacnehz.ir/](https://ricksacnehz.ir/).