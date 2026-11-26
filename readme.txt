=== FBS Secure Optimize – Speed Optimization, Security Headers & Malware Scanner ===
Contributors: fazlebari
Tags: performance, security, malware-scanner, security-headers, database-cleanup
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular speed optimization, database & autoload cleaner, HTTP security headers, login brute-force shield, and 6-layer malware scanner.

== Description ==

**FBS Secure Optimize** (engineered by [FBS Fusion](https://fbsfusion.com/)) is a modern, lightweight performance and security suite built around **6 Independent Zero-Overhead Modules**.

Most sites don't need bloated, all-or-nothing plugins that slow down `wp-admin` or clash with existing caching layers. With **FBS Secure Optimize**, every engine has its own **1-Click Master Toggle** in the Module Control Center:

* **Disabled Modules = Zero Runtime Overhead:** Any module you toggle off is completely bypassed at boot—zero frontend hooks, zero database queries, and zero conflicts with **WP Rocket, LiteSpeed Cache, FlyingPress, Autoptimize, Cloudflare, or WooCommerce**.
* **Auto-Hide Clean Interface:** Turning a module off automatically hides its configuration panels and tabs so your dashboard stays focused and clutter-free.

= 6 Independent Modules Included Free =

**1. Asset & Core Web Vitals Optimizer (with 1-Click Universal Cache Purge & Guest Page Cache)**
Boost Lighthouse Performance, Largest Contentful Paint (LCP), Cumulative Layout Shift (CLS), and Total Blocking Time (TBT) without jQuery bloat or fragile script bundling:
* **Auto-Preload Above-the-Fold LCP Image & Hero CSS Background:** Automatically detects your top hero banner—including CSS `background-image: url(...)` hero sections from page builders and themes—and injects early `<link rel="preload" as="image" fetchpriority="high">` in `<head>`, while stripping misplaced `fetchpriority="high"` from offscreen `loading="lazy"` images.
* **Zero-CLS Full-Width Builder Rows & Inline SVG Stabilizer:** Pre-calculates exact viewport geometry before First Paint to eliminate large layout shifts (`0.70+` CLS) caused by JS-stretched full-bleed rows (SiteOrigin Page Builder, WPBakery) and un-sized inline `<svg viewBox="...">` icons.
* **Zero-CLS Missing Image `width` & `height` Injection (Full-Page):** Automatically detects local raster and `.svg` `<img>` tags across headers, content, widgets, and footers missing explicit dimensions and injects cached `width` and `height` attributes.
* **Hoist & De-Block Chained CSS `@import` Google Fonts:** Extracts render-blocking `@import` Google Fonts hidden inside theme `style.css` files into parallel non-blocking `preconnect` + async swap links and serves a cached de-blocked stylesheet.
* **Non-Blocking Google Fonts, Custom `@font-face` Swap & Icon Stylesheets:** Converts render-blocking Google Fonts, Font Awesome, Elementor Icons, Ionicons, Line Awesome, and Magnific Popup stylesheets to asynchronous `media="print"` swap with `<noscript>` fallback, and automatically upgrades text `@font-face` rules and Google Fonts URLs from `font-display: auto`/`block` (FOIT) to `font-display: swap`.
* **Auto-Inline Small Local CSS (`<= 16 KB`) & Strip `0`-Byte CSS:** Inlines small render-blocking CSS files into `<head>` while rewriting relative `url(...)` paths and removing empty `0`-byte plugin stylesheets.
* **Smart Post-Render Unused Plugin CSS/JS & reCAPTCHA Unloader:** Inspects the complete rendered HTML document (including headers, popups, widgets, and footers) and safely strips unused Contact Form 7, orphaned Google reCAPTCHA v3, Smash Balloon Instagram Feed, Slick/Carousel, WPForms, TablePress, and unused block inline CSS on pages where those elements do not exist.
* **Delay External & Inline Third-Party Trackers & reCAPTCHA v3 (TBT/INP):** Delays external and inline bootstrap trackers (GTM, GA4, Microsoft Clarity, Meta Pixel, Hotjar, LinkedIn, TikTok, and invisible reCAPTCHA v3) until first user interaction or post-load idle—automatically bypassed on WooCommerce cart, checkout, and account pages.
* **Ultra-Light Guest HTML Disk Page Cache:** Optional 1-click static disk HTML page cache (`X-FBS-Cache: HIT`) for logged-out visitors with marketing UTM/GCLID cache-hit support, automatic post/comment/menu invalidation, and automatic bypass when LiteSpeed Cache, WP Rocket, W3 Total Cache, SiteGround, WP Fastest Cache, or 20i StackCache is active.
* **Top Admin Bar & Dashboard 1-Click "Purge Cache":** Instantly clears FBS inlined/de-blocked CSS, CLS dimension caches, guest disk page cache, WordPress Object Cache (Redis/Memcached), and triggers compatible cache flushes across LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Fastest Cache, WP Super Cache, Autoptimize, SiteGround, Breeze, and WP Engine without triggering shared-host OPcache worker locks.
* **Iframe & Video Lazy Loading:** Defers embedded maps and video players until visitors scroll near them.
* **Safe Non-Critical JS Deferring:** Adds `defer` to frontend scripts while automatically protecting `jQuery`, WooCommerce checkout flows, and scripts with synchronous inline dependencies.
* **Core Bloat Removal & Heartbeat Control:** Strip inline emoji scripts/styles, disable frontend Dashicons for guests, dequeue unused oEmbed scripts, clean `<head>` discovery tags, and throttle Heartbeat API to 60 seconds.

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

**6. Deep Malware, Database Rootkit, Cloaking & Hidden-Admin Scanner (with 1-Click Direct-DB Remediation)**
Built from real-world forensic incident response to uncover stealth database rootkits and cloaked spam traditional filesystem scanners miss:
* **Layer 1 — Root Directory PHP Audit:** Flags unauthorized `.php` drop-ins sitting in your site root (`ABSPATH`) while content-verifying legitimate WAF bootstraps (`wordfence-waf.php`, `aios-bootstrap.php`, `malcare-waf.php`).
* **Layer 2 — Official Core, Plugin & Theme Checksums:** Verifies WordPress Core files, installed WordPress.org plugins (`downloads.wordpress.org/plugin-checksums/`), and official themes (`themes.svn.wordpress.org`) against cryptographic hashes—eliminating false positives on vendor files (such as Elementor's bundled Twig test suites) while flagging any tampered file.
* **Layer 3 — Zero-Tolerance `uploads/` Shell Scan:** Detects disguised PHP webshells inside `wp-content/uploads/`.
* **Layer 4 — `mu-plugins` Persistence Audit:** Inspects Must-Use plugins where stealth malware hides from the standard Plugins screen.
* **Layer 5 — Deep Database Code Snippets, CPTs & WP-Cron Scanner:** Directly queries `wp_snippets` / `ms_snippets` (*Code Snippets* — bypassing self-hiding `admin_footer` CSS/JS rootkits), *Header Footer Code Manager* (`hfcm_scripts`), *XYZ PHP Code*, Elementor Custom Code (`elementor_snippet`), *WPCode*, *Woody Snippets*, injected `post`/`page` content, header/footer `wp_options`, and scheduled `cron` persistence hooks—plus lists a complete **Active Database Code Snippets Inventory** for 100% visibility.
* **Layer 6 — 6-Hook Reflection, Rogue-Admin Anomaly & Application Passwords Audit:** Uses PHP `Reflection` across `pre_user_query`, `views_users`, `users_list_table_query_args`, `load-user-edit.php`, `load-users.php`, and `delete_user` to pinpoint exact `file:line` or `eval()'d code` database hooks; cross-checks raw SQL `usermeta` capabilities against `get_users()` / `WP_User_Query` (catching hidden admins, empty/invalid emails, and default backdoor logins); and audits active REST API **Application Passwords** with 1-click revocation.
* **Front-End Live DOM & Googlebot Cloaked SEO Spam Scanner:** Performs dual HTTP loopback checks (Standard Visitor vs. Googlebot User-Agent + Google Referer) to catch off-screen hidden links (`left: -9999px`, `font-size: 0`) and conditional search-engine cloaking.
* **Safe 1-Click Direct-DB Remediation Included Free:** View full database snippet payloads, deactivate or delete malicious snippets directly via `$wpdb`, force-delete tamper-protected rogue admin users directly in MySQL (reassigning authored posts safely), revoke Application Passwords, and purge all caches while invalidating other user sessions in one click.

= Unlock Active Defense with FBS Secure Optimize Pro =

Need real-time attack blocking and 1-click filesystem shell eradication? [FBS Secure Optimize Pro](https://fbsfusion.com/plugins/) extends the free plugin with 5 additional independent modules:
* **Early-Boot Web Application Firewall (WAF):** Blocks SQL Injection (SQLi), Cross-Site Scripting (XSS), Directory Traversal, Null-Byte exploits, and bad-bot user-agents at priority `0` (includes safe **Learning Mode**).
* **429 Anti-Bot Flood Rate Limiter:** Sliding-window per-IP request throttling (`HTTP 429 Too Many Requests`) to prevent scraper floods from triggering `502 Bad Gateway` crashes.
* **Real-Time Admin Capability Guard:** Locks an **Approved Administrator Whitelist** and automatically disarms unauthorized privilege escalation while destroying rogue sessions.
* **1-Click Filesystem Malware Eradication & Auto-Quarantine:** Delete flagged backdoor PHP files directly from the Scanner + automated daily scan email alerts.
* **Custom CSP Header Builder & Scheduled Daily Scans:** Test `Content-Security-Policy` directives in `Report-Only` mode before enforcing `Grade A+` headers, plus automated daily scan email alerts.

= External Services =

When an administrator manually clicks **Run 6-Layer Deep Scan** inside `wp-admin` (or enables scheduled scans in Pro), the plugin connects to official public **WordPress.org APIs** solely to verify cryptographic file integrity:
* **WordPress.org Core Checksums API** (`https://api.wordpress.org/core/checksums/1.0/`): Fetches official MD5 hashes for your installed WordPress version.
* **WordPress.org Plugin Checksums CDN** (`https://downloads.wordpress.org/plugin-checksums/`): Fetches official MD5/SHA-256 hashes when a plugin file matches a heuristic rule so unmodified vendor libraries are verified clean without false positives.
* **WordPress.org Themes API & SVN** (`https://api.wordpress.org/themes/info/1.2/` and `https://themes.svn.wordpress.org/`): Verifies critical active/parent theme templates (`functions.php`, `header.php`, `footer.php`) when hosted on WordPress.org.
* **Data sent:** Only the WordPress core version, or the slug and version of the specific WordPress.org plugin/theme being verified during an admin-initiated scan.
* **Privacy:** Zero personal data, visitor IPs, user accounts, or site content is ever transmitted externally. Governed by the [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/).

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

= 3.0.0 =
* Major Release: Deep Database Forensics, 1-Click Direct-DB Malware Remediation & Next-Gen Mobile Core Web Vitals Engine.
* New: Deep Database Code & Snippet Injection Scanner — directly inspects `wp_snippets` & `ms_snippets` (*Code Snippets* plugin, bypassing self-hiding `admin_footer` CSS/JS rootkits), *Header Footer Code Manager* (`hfcm_scripts`), *XYZ PHP Code*, Elementor Custom Code (`elementor_snippet`), *WPCode*, *Woody Snippets*, injected `post`/`page` content, header/footer `wp_options`, and scheduled `WP-Cron` persistence hooks, plus displays a full **Active Database Code Snippets Inventory** for 100% visibility.
* New: 6-Hook User-Concealment Reflection & Application Passwords Audit — reflects callbacks across `pre_user_query`, `views_users`, `users_list_table_query_args`, `load-user-edit.php`, `load-users.php`, and `delete_user` (flagging `eval()'d code` / database hooks as `CRITICAL`), detects rogue admins with empty/invalid emails or default backdoor usernames, and audits active REST API `_application_passwords` with 1-click revocation.
* New: Front-End Live DOM & Googlebot Cloaked SEO Spam Scanner — performs dual HTTP loopback requests (Visitor vs. Googlebot User-Agent + Google Referer) to detect off-screen hidden spam links (`left: -9999px`, `font-size: 0`) and conditional search-engine cloaking.
* New: Official WordPress.org Plugin & Theme Checksum Verification — verifies heuristic matches inside `wp-content/plugins/` against official `downloads.wordpress.org/plugin-checksums/` MD5/SHA-256 hashes (eliminating vendor false positives such as Elementor's Twig `IntegrationTestCase.php`) and audits active/parent theme critical templates (`functions.php`, `header.php`, `footer.php`).
* New: Safe 1-Click Direct-DB Remediation in Free Edition — view full database code payloads, deactivate or delete malicious snippets directly via `$wpdb`, force-delete tamper-protected rogue admin users directly in MySQL (reassigning authored posts and destroying sessions), revoke Application Passwords, and purge all caches while invalidating all other user sessions in 1 click.
* Improved: Mobile Core Web Vitals & LCP Optimizer — added external page-builder/theme stylesheet (`post-*.css`) hero `background-image` auto-preloading with `:not(...)` pseudo-class selector resolution and transient caching, upgraded inline custom `@font-face` rules and `display=auto`/`block` Google Fonts URLs to `font-display: swap` to prevent Slow-4G FOIT text LCP delays, unlocked smart `jQuery` deferring when WordPress Core outputs `jQuery.uiBackCompat`, raised the small-CSS auto-inline threshold to `16 KB`, added 20i `StackCache` auto-bypass, and removed `opcache_reset()` from cache purges to prevent `429 Too Many Requests` worker locks on 20i/StackCP and shared hosts.

= 2.2.1 =
* Fix: Prevented a one-time blank screen on `wp-admin/admin-post.php` when saving settings immediately after a plugin update on LiteSpeed LSAPI / PHP-FPM servers by skipping unnecessary `opcache_reset()` calls during settings saves and deferring manual-purge OPcache resets until after HTTP response/redirect completion (`register_shutdown_function`).
* Fix: Scoped `disable_wp_emojis()` strictly to the frontend (`! is_admin()`) and replaced raw Unicode emojis on Pro feature cards with crisp Dashicons badges so broken Twemoji `<img>` icons never appear inside `wp-admin`.
* Tweak: Added output buffering around third-party cache purge hooks, automatic safe redirect fallback, and seasonal Black Friday 40% OFF Pro spotlight inside plugin screens.

= 2.2.0 =
* New: Auto-Preload Above-the-Fold LCP Image & Hero CSS Background (detects both hero `<img>` banners and page-builder CSS `background-image` heroes, injects `<link rel="preload" as="image" fetchpriority="high">` after `<meta name="viewport">`, skips header logos/SVGs, and demotes offscreen `loading="lazy"` images to `fetchpriority="low"`, plus optional manual URL override).
* New: Zero-CLS Full-Width Builder Rows & Inline SVG Stabilizer (measures exact post-viewport `--fbs-cw` geometry immediately after `<body ...>` to eliminate `0.70+` layout shifts from JS-stretched full-bleed rows in SiteOrigin Page Builder & WPBakery before First Paint, and injects intrinsic `viewBox` dimensions on un-sized inline `<svg>` icons).
* New: Hoist & De-Block Chained CSS `@import` Google Fonts (extracts blocking `@import` Google Fonts inside theme `style.css` files into non-blocking `preconnect` + swap links and serves a cached de-blocked stylesheet).
* New: Non-Blocking Icon & Decorative Stylesheets (converts Font Awesome, Elementor Icons, Ionicons, Line Awesome, and Magnific Popup CSS to async `media="print"` swap).
* New: Smart Post-Render Unused Plugin CSS/JS & reCAPTCHA Unloader (inspects complete rendered HTML DOM and safely strips unused Contact Form 7, orphaned Google reCAPTCHA v3, orphaned `wp-polyfill`/`wp-i18n`/`wp-hooks` dependencies, Smash Balloon Instagram Feed, Slick/Carousel, WPForms, TablePress, and unused block social-links CSS on pages where absent).
* New: Smart Inline-Verified `jQuery` Deferring & Upgraded Third-Party JS Delay (captures both external `<script src>` and inline GTM/Clarity/Meta/Hotjar bootstrap snippets plus invisible reCAPTCHA v3, and auto-defers `jquery-core`/`jquery-migrate` when zero synchronous inline scripts reference `jQuery`/`$`).
* New: Optional Ultra-Light Guest HTML Disk Page Cache (`X-FBS-Cache: HIT`) with marketing parameter (`utm_*`, `gclid`, `fbclid`) cache-hit support, automatic content invalidation, and 1-Click "Apply Max PageSpeed Preset" button.
* Fix: Eliminated false-positive `CRITICAL` scanner alerts on content-verified legitimate root WAF bootstrap files (`wordfence-waf.php`, `aios-bootstrap.php`, `malcare-waf.php`) and compiled Timber/Twig (`wp-content/uploads/timber-cache/`) or Blade template cache files while still catching tampered or spoofed webshells.
* Fix: Eliminated wasted render-blocking HTTP requests on empty `0`-byte local plugin stylesheets and protected `wp_add_inline_script(..., 'after')` handles during JS deferring.

= 2.1.1 =
* Fix: Eliminated false-positive `OS Command Execution (shell_exec/system)` alerts in the 6-Layer Malware Scanner on legitimate Composer vendor libraries (such as `Monolog\Handler\ProcessHandler` used by FluentSMTP / Google API Client, `Symfony\Component\Process`, and `Composer\Util\ProcessExecutor`) while preserving full detection if those files are tampered with or contain superglobal input (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`).
* Dev: Added the `fbsseop_scanner_is_safe_match` filter for custom scanner false-positive exclusions.

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

= 3.0.0 =
Major v3.0 release! Adds Deep DB Code Snippets/CPT/WP-Cron rootkit scanner, 6-Hook Reflection, App Passwords audit, Googlebot cloaking checks, Official WP.org checksums, Free 1-Click DB Remediation, and Next-Gen Mobile LCP & font-display:swap optimization.

= 2.2.1 =
Fixes a one-time blank screen on admin-post.php when saving settings right after updating on LiteSpeed / PHP-FPM servers by deferring OPcache resets to post-response shutdown.

= 2.2.0 =
Major Free Core Web Vitals upgrade! Adds Auto-Preload for Hero CSS Background & LCP Images, Zero-CLS Full-Width Builder & SVG Stabilizer, CSS @import Google Fonts De-Blocker, Smart Unused Plugin/reCAPTCHA Unloader, Inline Tracker Delay, and Optional Guest HTML Disk Cache.

= 2.1.1 =
Eliminates false-positive malware scanner warnings on legitimate Composer vendor libraries (e.g. Monolog ProcessHandler in FluentSMTP) without reducing real threat detection.

= 2.1.0 =
Adds 4 universal Core Web Vitals boosters (Non-Blocking Google Fonts, Small-CSS Auto-Inlining, Zero-CLS Image Width/Height Injection, and Third-Party JS Interaction Delay) plus 1-Click Top Admin Bar & Universal Cache Purge.

= 2.0.0 =
Major v2.0 upgrade! Adds 6 Independent Zero-Overhead Module Toggles with Auto-Hide UI, 6-Layer Malware & Hidden-Admin Scanner, 1-Click Uploads PHP Shield, Autoload Bloat Inspector, and LCP-safe lazy loading.
