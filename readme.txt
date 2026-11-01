=== FBS Secure Optimize – Speed Optimization, Security Headers & Malware Scanner ===
Contributors: fazlebari
Tags: performance, security, malware-scanner, security-headers, database-cleanup
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular speed optimization, database & autoload cleaner, HTTP security headers, login brute-force shield, and 6-layer malware scanner.

== Description ==

**FBS Secure Optimize** (engineered by [FBS Fusion](https://fbsfusion.com/)) is a modern, lightweight performance and security suite built around **6 Independent Zero-Overhead Modules**.

Most sites don't need bloated, all-or-nothing plugins that slow down `wp-admin` or clash with existing caching layers. With **FBS Secure Optimize**, every engine has its own **1-Click Master Toggle** in the Module Control Center:

* **Disabled Modules = Zero Runtime Overhead:** Any module you toggle off is completely bypassed at boot—zero frontend hooks, zero database queries, and zero conflicts with **WP Rocket, LiteSpeed Cache, FlyingPress, Autoptimize, Cloudflare, or WooCommerce**.
* **Auto-Hide Clean Interface:** Turning a module off automatically hides its configuration panels and tabs so your dashboard stays focused and clutter-free.

= 6 Independent Modules Included Free =

**1. Asset & Core Web Vitals Optimizer (with 1-Click Universal Cache Purge)**
Boost Lighthouse Performance, Largest Contentful Paint (LCP), Cumulative Layout Shift (CLS), and Total Blocking Time (TBT) without jQuery bloat or fragile script bundling:
* **Non-Blocking Google Fonts & Preconnect:** Converts render-blocking Google Fonts stylesheets to asynchronous `media="print"` swap with `<noscript>` fallback, enforces `display=swap`, and injects `preconnect` hints for `fonts.googleapis.com` and `fonts.gstatic.com`.
* **Auto-Inline Small Local CSS (`<= 8 KB`):** Automatically inlines tiny render-blocking theme and plugin CSS files into `<head>` while safely rewriting relative `url(...)` paths and caching minified output.
* **Zero-CLS Missing Image `width` & `height` Injection:** Automatically detects local `<img>` tags missing explicit dimensions and injects cached `width` and `height` attributes to prevent layout shifts.
* **LCP-Safe Native Lazy Loading:** Adds native `loading="lazy"` and `decoding="async"` to offscreen images while stripping lazy-loading and applying `fetchpriority="high"` on your first above-the-fold hero image.
* **Delay Third-Party Analytics & Pixels (TBT/INP):** Optional 1-click toggle to delay heavy third-party trackers (GTM, GA4, Meta Pixel, Microsoft Clarity, Hotjar, LinkedIn, TikTok) until first user interaction or a 3.5s fallback—automatically bypassed on WooCommerce cart, checkout, and account pages.
* **Top Admin Bar & Dashboard 1-Click "Purge Cache":** Instantly clears FBS inlined CSS & CLS dimension caches, WordPress Object Cache (Redis/Memcached), PHP OPcache, and triggers compatible cache flushes across LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Fastest Cache, WP Super Cache, Autoptimize, SiteGround, Breeze, and WP Engine.
* **Iframe & Video Lazy Loading:** Defers embedded maps and video players until visitors scroll near them.
* **Safe Non-Critical JS Deferring:** Adds `defer` to frontend scripts while automatically excluding `jQuery` core and sensitive checkout/account flows.
* **Core Bloat Removal:** Strip inline emoji scripts/styles, disable frontend Dashicons for logged-out visitors, dequeue unused oEmbed scripts, and clean `<head>` RSD, WLW, and shortlink tags.
* **Heartbeat API Throttling:** Throttles `admin-ajax.php` polling to 60 seconds to prevent CPU spikes and `502` timeouts on shared or VPS hosting.

**2. Database & Autoload Cleaner**
Keep MySQL lean for sub-200ms Server Time to First Byte (TTFB):
* **1-Click & Scheduled Cleanup:** Safely remove post revisions, old auto-drafts (`> 7 days`), spam and trashed comments, expired transients, and orphaned `postmeta` rows.
* **MySQL Table Optimization:** Run `OPTIMIZE TABLE` across core tables manually or automatically via daily, weekly, or monthly WP-Cron schedules.
* **Built-In Autoload Bloat Inspector:** Instantly audit total autoloaded `wp_options` size (in KB) alongside your **Top 5 Heaviest Autoloaded Options** so hidden database bottlenecks never go unnoticed.

**3. HTTP Security Headers**
Achieve a hardened browser security posture without breaking stylesheets, fonts, or payment gateways:
* Toggleable `X-Content-Type-Options: nosniff` (MIME-sniffing defense)
* Toggleable `X-Frame-Options: SAMEORIGIN` (clickjacking protection)
* Toggleable `Referrer-Policy: strict-origin-when-cross-origin`
* Toggleable `Permissions-Policy` (restricts unauthorized camera, microphone, geolocation, and USB access)
* Optional `Strict-Transport-Security (HSTS)` for HTTPS sites
* Hide core generator meta tags and asset version fingerprinting (`?ver=`)

**4. Unified Login Brute-Force Shield**
Stop automated credential-stuffing and brute-force bots before they exhaust PHP workers:
* **Automatic IP Lockouts:** Lock out attacking IPs after a configurable number of failed login attempts and lockout window.
* **Trusted IP Allowlist:** Whitelist your office or developer IPs so you are never locked out.
* **Username Enumeration Masking:** Replace verbose login failure hints with generic error messages so attackers cannot harvest valid usernames.
* **Live Telemetry & 1-Click Reset:** View recent failed login attempts (IP, username, UTC timestamp) and clear active lockouts in one click.

**5. Core Hardening & 1-Click Uploads PHP Shield**
Close the most common entry points used in automated site compromises:
* **1-Click `wp-content/uploads/` PHP Execution Shield:** Generates hardened `.htaccess` rules blocking direct execution of backdoor `.php`, `.phtml`, and `.phar` shells uploaded into media folders.
* **Disable XML-RPC & `X-Pingback`:** Eliminates amplified `system.multicall` brute-force and pingback DDoS vectors.
* **Block Username Harvesting:** Stops `?author=1` archive scans and restricts unauthenticated `REST API /wp/v2/users` enumeration.
* **Lock Theme & Plugin File Editors:** Blocks arbitrary PHP code editing inside `wp-admin` via capability mapping (`map_meta_cap`).
* **High-Risk Plugin Detector:** Alerts you if risky file-manager or database-admin plugins are left active in production, with 1-click deactivation.

**6. 6-Layer Malware, Core Checksum & Hidden-Admin Scanner**
Built from real-world forensic incident response to uncover threats traditional scanners miss:
* **Layer 1 — Root Directory PHP Audit:** Flags unauthorized `.php` drop-ins sitting in your site root (`ABSPATH`).
* **Layer 2 — Official Core MD5 Checksums:** Verifies every file inside `wp-admin/` and `wp-includes/` against official `api.wordpress.org` cryptographic MD5 hashes.
* **Layer 3 — Zero-Tolerance `uploads/` Shell Scan:** Detects disguised PHP webshells inside `wp-content/uploads/`.
* **Layer 4 — `mu-plugins` Persistence Audit:** Inspects Must-Use plugins where stealth malware often hides from the standard Plugins screen.
* **Layer 5 — Heuristic Signature & `wp_options` DB Scan:** Scans plugins, themes, and database options for obfuscated `eval(base64_decode(...))`, `gzinflate`, shell execution, and injected script payloads, complete with a **Safe Read-Only Code Snippet Preview** modal.
* **Layer 6 — Stealth `pre_user_query` Hidden-Admin Detector:** Compares high-level `WP_User_Query` results against raw database `capabilities` usermeta rows to expose rogue administrator accounts hidden by malicious hooks.

= Unlock Active Defense with FBS Secure Optimize Pro =

Need real-time attack blocking and 1-click threat eradication? [FBS Secure Optimize Pro](https://fbsfusion.com/plugins/) extends the free plugin with 5 additional independent modules:
* **Early-Boot Web Application Firewall (WAF):** Blocks SQL Injection (SQLi), Cross-Site Scripting (XSS), Directory Traversal, Null-Byte exploits, and bad-bot user-agents at priority `0` (includes safe **Learning Mode**).
* **429 Anti-Bot Flood Rate Limiter:** Sliding-window per-IP request throttling (`HTTP 429 Too Many Requests`) to prevent scraper floods from triggering `502 Bad Gateway` crashes.
* **Real-Time Admin Capability Guard:** Locks an **Approved Administrator Whitelist** and automatically disarms unauthorized privilege escalation while destroying rogue sessions.
* **1-Click Malware & Rogue-User Eradication:** Delete flagged backdoor files, malicious database rows, and stealth admin accounts directly from the 6-Layer Scanner.
* **Custom CSP Header Builder & Scheduled Daily Scans:** Test `Content-Security-Policy` directives in `Report-Only` mode before enforcing `Grade A+` headers, plus automated daily scan email alerts.

= External Services =

When an administrator manually clicks **Run 6-Layer Deep Scan** inside `wp-admin` (or enables scheduled scans in Pro), the plugin connects to the official public **WordPress.org Core Checksums API** (`https://api.wordpress.org/core/checksums/1.0/`) to fetch official MD5 file hashes for your installed core version and locale.
* **Data sent:** Only your WordPress version string and locale (for example, `version=7.1&locale=en_US`), solely when a scan runs.
* **Privacy:** Zero personal data, visitor IPs, user accounts, or site content is ever transmitted. Governed by the [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/).

== Installation ==

1. In your dashboard, go to **Plugins → Add New**, search for **FBS Secure Optimize**, and click **Install Now** (or upload the `fbs-secure-optimize` folder to `/wp-content/plugins/`).
2. Click **Activate**.
3. Open **FBS Optimize → Control Center** from the left admin menu.
4. Toggle on the exact speed and security modules your site needs:
   * Already using a page-caching plugin? Keep **Database & Autoload Cleaner**, **HTTP Security Headers**, **Login Shield**, **WP Hardening**, and **6-Layer Malware Scanner** enabled, and toggle **Asset Optimizer** off if your cache plugin already handles lazy loading.
5. Open **Headers & Hardening** and click **1-Click Shield `wp-content/uploads/`** to block PHP execution in media directories.
6. Open **6-Layer Malware Scanner** and click **Run 6-Layer Deep Scan** to establish a clean security baseline.

== Frequently Asked Questions ==

= Will FBS Secure Optimize conflict with WP Rocket, LiteSpeed Cache, or Cloudflare? =

No. Every engine is controlled by an **Independent Module Toggle** in the Module Control Center. If another plugin already handles script deferring or lazy loading, simply toggle **Asset & Bloat Optimizer** off—disabled modules are bypassed at boot and register zero frontend hooks, while you continue enjoying Database Cleanup, Autoload Inspection, Security Headers, Login Protection, Core Hardening, and the 6-Layer Malware Scanner.

= If I disable a module, does it hide those settings in the dashboard? =

Yes. Turning any module off in **Module Control Center** immediately hides its configuration panel (and hides the corresponding top navigation tab if all modules in that group are disabled) while safely preserving your saved preferences in case you re-enable it later.

= Is the Asset Optimizer safe for eCommerce checkouts and LCP images? =

Yes. Unlike aggressive lazy-loaders that delay your top hero banner and hurt Largest Contentful Paint (LCP), **FBS Secure Optimize** automatically skips lazy-loading on the first image in your content and adds `fetchpriority="high"`. Likewise, JavaScript deferring automatically excludes `jQuery` core and sensitive cart/checkout pages.

= Will enabling HTTP Security Headers break external fonts, analytics, or embeds? =

No. The free HTTP Security Headers module ships with safe, standards-compliant defaults (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, and `Permissions-Policy`) that never block third-party CSS, fonts, or payment scripts. Every header is individually toggleable.

= How does the Stealth Hidden-Admin Detector work? =

Sophisticated malware often creates a backdoor administrator account and hooks `pre_user_query` or `users_list_table_query_args` so the rogue account is completely invisible on your **Users → All Users** screen. Layer 6 of our scanner cross-checks standard user queries against raw `usermeta` capability rows in MySQL to expose any hidden or stealth-elevated administrator immediately.

= Does the free Malware Scanner delete files automatically? =

Never automatically. In the Free edition, the 6-Layer Scanner performs a 100% safe, read-only forensic audit and lets you inspect suspicious code inside the **Safe File Snippet Preview** modal so core or custom files are never accidentally deleted. One-click deletion and auto-disarming are optional tools available in Pro.

= How does the 1-Click "Purge Cache" button work? =

Whenever you edit theme CSS, upload new images, or change optimization toggles, click **Purge Cache** in the WordPress Top Admin Bar (frontend or backend) or inside the FBS Secure Optimize header. It immediately invalidates cached inline CSS and image CLS dimension maps, flushes WordPress Object Cache (Redis/Memcached) and PHP OPcache, and triggers cache-clear hooks for LiteSpeed Cache, WP Rocket, Autoptimize, W3 Total Cache, WP Super Cache, WP Fastest Cache, SiteGround, Breeze, and WP Engine. Caches also auto-purge when you switch themes, save Customizer changes, or update plugins/themes.

= What happens if I uninstall FBS Secure Optimize? =

Uninstalling the plugin through the **Plugins** screen cleanly removes its saved options, clears scheduled cleanup cron hooks, and drops its custom `fbsseop_login_attempts` database table—leaving zero orphaned clutter behind.

== Screenshots ==

1. **Module Control Center** — Live telemetry KPI strip (`Cleanable DB Bloat`, `Autoloaded wp_options`, `Failed Logins`, `Uploads PHP Shield`) and 6 Independent Zero-Overhead Module Toggles with live Auto-Hide.
2. **Speed & Database Optimization** — LCP-safe native lazy loading (`fetchpriority="high"`), JS deferring, Heartbeat control, 1-click database cleanup, and Top 5 Heaviest Autoloaded `wp_options` Inspector.
3. **Headers, Core Hardening & Login Shield** — Toggleable Grade A HTTP security headers, 1-click `wp-content/uploads/` PHP execution shield, XML-RPC & REST `/users` hardening, and brute-force IP lockouts.
4. **6-Layer Malware, Core Checksum & Hidden-Admin Scanner** — Official WordPress.org MD5 checksum verification, root/uploads PHP shell inspection, safe code preview, and stealth `pre_user_query` hidden-admin detection.
5. **WAF Firewall, 429 Rate Limiter & Admin Whitelist Guard (Pro)** — Optional active firewall, anti-bot flood rate limiting, real-time capability guard, and Custom CSP header builder.
6. **Meet The Author & Engineering Studio** — Engineered by Fazle Bari & FBS Fusion following strict WordPress Coding Standards (WPCS).

== Changelog ==

= 2.1.0 =
* New: Non-Blocking Google Fonts & Preconnect optimizer (eliminates render-blocking Google Fonts CSS, enforces `display=swap`, and adds automatic `preconnect` resource hints).
* New: Auto-Inline Small Local CSS (`<= 8 KB`) with automatic relative `url(...)` path rewriting and salt-versioned transient caching.
* New: Zero-CLS Missing Image `width` & `height` auto-injection for local content, thumbnail, and widget images, plus stronger LCP hero image protection (strips theme-injected `loading="lazy"` on image #1).
* New: Delay Third-Party Analytics & Tracking Pixels (`gtag.js`, `gtm.js`, Meta Pixel, Clarity, Hotjar, LinkedIn, TikTok) until first user interaction or 3.5s fallback, automatically bypassing WooCommerce cart/checkout/account pages.
* New: 1-Click **Purge Cache** button in both the WordPress Top Admin Bar (frontend & `wp-admin`) and plugin Hero Header—flushes FBS Asset/CLS caches, WP Object Cache, PHP OPcache, and compatible host/page caches, plus automatic invalidation on theme/plugin updates.

= 2.0.0 =
* Major Release: Re-architected around 6 Independent Zero-Overhead Module Toggles with live Auto-Hide admin UX.
* New: 6-Layer Malware, Official Core MD5 Checksum & Stealth Hidden-Admin (`pre_user_query`) Scanner with safe code snippet preview modal.
* New: Core Hardening module featuring 1-Click `wp-content/uploads/.htaccess` PHP Execution Shield, XML-RPC blocker, author/REST user enumeration defense, file editor lock, and High-Risk Plugin detector.
* New: Built-in `wp_options` Autoload Bloat Inspector displaying total autoloaded KB and Top 5 heaviest autoload rows.
* New: LCP-safe native lazy loading (`fetchpriority="high"` on first hero image), iframe lazy loading, Emoji/Dashicons bloat removal, and 60s Heartbeat API throttling.
* Tweak: Modern SaaS Control Center UI, redesigned Meet The Author page, and full WordPress 7.1 & Plugin Check compliance.

= 1.0.2 =
* New: Integrated "Our Plugins" showcase page and "Meet The Author" profile page.
* Tweak: Redesigned backend dashboard styling.

= 1.0.1 =
* Tested compatibility with WordPress 6.9.

= 1.0.0 =
* Initial release on WordPress.org.

== Upgrade Notice ==

= 2.1.0 =
Adds 4 universal Core Web Vitals boosters (Non-Blocking Google Fonts, Small-CSS Auto-Inlining, Zero-CLS Image Width/Height Injection, and Third-Party JS Interaction Delay) plus 1-Click Top Admin Bar & Universal Cache Purge.

= 2.0.0 =
Major v2.0 upgrade! Adds 6 Independent Zero-Overhead Module Toggles with Auto-Hide UI, 6-Layer Malware & Hidden-Admin Scanner, 1-Click Uploads PHP Shield, Autoload Bloat Inspector, and LCP-safe lazy loading.
