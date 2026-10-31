=== FBS Secure Optimize – Speed Optimization, Security Headers & Malware Scanner ===
Contributors: fazlebari
Tags: performance, security, malware-scanner, security-headers, database-cleanup
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular WordPress performance optimization, database cleanup, HTTP security headers, WP hardening, and 6-layer malware & hidden-admin scanner.

== Description ==

**FBS Secure Optimize** (engineered by [FBS Fusion](https://fbsfusion.com/)) is a lightweight, modular performance and security suite built around **6 Independent Module Toggles**. Enable only the exact speed and security engines your site needs—disabled modules register zero frontend hooks for zero conflicts with WP Rocket, LiteSpeed Cache, or Cloudflare.

= 6 Independent Modules =

1. **Asset & Bloat Optimizer**: LCP-safe native HTML5 image & iframe lazy loading (automatically prioritizes your first hero image with `fetchpriority="high"`), non-critical JS deferring, Emoji/Dashicons removal, `<head>` cleanup, and WP Heartbeat throttling.
2. **Database & Autoload Cleaner**: Prune post revisions, old auto-drafts, spam/trashed comments, expired transients, orphaned postmeta, `OPTIMIZE TABLE`, scheduled WP-Cron maintenance, plus a built-in **Top 5 Heaviest Autoloaded `wp_options` Inspector**.
3. **HTTP Security Headers**: Toggleable `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security (HSTS)`, and WordPress version masking.
4. **Login Brute-Force Shield**: Automatic IP lockouts after failed login attempts, IP allowlisting, username enumeration error masking, and 1-click lockout clearing.
5. **WP Core Hardening & Uploads Shield**: Disable XML-RPC & `X-Pingback`, lock theme/plugin PHP editors, block `?author=1` and REST `/wp/v2/users` enumeration, detect high-risk file-manager plugins, and block direct PHP script execution inside `wp-content/uploads/` in 1 click.
6. **6-Layer Malware, Checksum & Hidden-Admin Scanner**: Verifies official WordPress.org Core MD5 checksums, detects unknown root PHP files, flags zero-tolerance PHP shells inside `wp-content/uploads/`, audits `mu-plugins`, scans plugin/theme heuristics and `wp_options`, and uncovers stealth `pre_user_query` hidden administrator accounts.

= External Services =

When an administrator manually triggers the **6-Layer Malware Scanner** in `wp-admin`, the plugin requests official WordPress core MD5 file hashes from the public WordPress.org Checksums API (`https://api.wordpress.org/core/checksums/1.0/`). Only your WordPress version number is sent in that request; no personal or site visitor data is transmitted.

== Installation ==

1. Upload the `fbs-secure-optimize` folder to `/wp-content/plugins/` or install directly through **Plugins → Add New** in WordPress.
2. Activate **FBS Secure Optimize**.
3. Open **FBS Optimize → Control Center** to toggle modules and run your first 6-Layer Security Scan.

== Changelog ==

= 2.0.0 =
* Major Release: Re-architected around 6 Independent Module Toggles (zero runtime overhead for disabled modules).
* New: 6-Layer Malware, Official WP.org Core MD5 Checksum & Anti-Hidden-Admin (`pre_user_query`) Scanner with safe file snippet preview.
* New: WP Core Hardening & 1-Click `wp-content/uploads/.htaccess` PHP Execution Shield + High-Risk Plugin Monitor.
* New: Autoload Bloat Inspector showing total `wp_options` autoloaded KB and top 5 heaviest autoload rows.
* New: LCP-safe native lazy loading (`fetchpriority="high"` on first hero image), Emoji/Dashicons bloat removal, and Heartbeat throttling.
* Fix: Made HTTP Security Headers strictly toggle-controlled so external fonts and embeds never break out of the box.
* Tweak: Modern SaaS Control Center UI and full WordPress Coding Standards (WPCS) & Plugin Check compliance.

= 1.0.2 =
* New: Integrated "Our Plugins" portfolio showcase page and "Meet The Author" profile page.
* Tweak: Redesigned backend UI.

= 1.0.1 =
* Tested compatibility with WordPress 6.9.

= 1.0.0 =
* Initial release.
