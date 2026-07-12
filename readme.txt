=== FBS Secure Optimize ===
Contributors: fazlebari
Tags: performance, security, optimization, speed, cache
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WordPress plugin for performance optimization and security enhancement. Features asset optimization, database cleanup, and security.

== Description ==

FBS Secure Optimize is a powerful WordPress plugin designed to improve your website's performance and security. It combines essential optimization features with robust security measures to help you create a faster, more secure website.

= Performance Features =

* **Asset Optimization**: Minify and combine CSS and JavaScript files to reduce file size and HTTP requests
* **Lazy Loading**: Implement lazy loading for images and iframes to improve page load times
* **Database Cleanup**: Clean up post revisions, auto-drafts, spam comments, and expired transients
* **Automatic Cleanup**: Schedule automatic database cleanup on a daily, weekly, or monthly basis

= Security Features =

* **Login Security**: Limit login attempts to prevent brute force attacks with IP blocking
* **Security Headers**: Add essential security headers like X-Content-Type-Options, X-Frame-Options, and more
* **WordPress Version Hiding**: Remove WordPress version information from your site's head and RSS feeds
* **IP Whitelisting**: Whitelist trusted IP addresses to bypass login attempt limits

= Key Benefits =

* **Improved Performance**: Faster page load times through asset optimization and lazy loading
* **Enhanced Security**: Protection against common web vulnerabilities and brute force attacks
* **Database Optimization**: Clean up unnecessary data to improve database performance
* **Easy to Use**: Simple, intuitive interface with clear settings and real-time statistics
* **WordPress Standards**: Built following WordPress coding standards and best practices

= Perfect For =

* Website owners looking to improve their site's performance
* Developers who need a comprehensive optimization solution
* Anyone concerned about website security
* Sites with large databases that need regular cleanup
* WordPress sites that want to hide their version information

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/fbs-optimize` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the FBS Secure Optimize menu to configure the plugin
4. Configure your performance and security settings according to your needs

== Frequently Asked Questions ==

= Does this plugin work with caching plugins? =

Yes, FBS Secure Optimize is designed to work alongside popular caching plugins. However, you may need to clear your cache after making changes to asset optimization settings.

= Will lazy loading affect my SEO? =

No, lazy loading actually improves SEO by reducing page load times, which is a ranking factor. The plugin uses modern lazy loading techniques that are SEO-friendly.

= Is it safe to clean the database? =

Yes, the database cleanup features are designed to be safe and only remove unnecessary data like old revisions, auto-drafts, and spam comments. However, we recommend backing up your database before performing cleanup operations.

= Can I whitelist my IP address? =

Yes, you can add your IP address to the whitelist in the Login Security settings to bypass login attempt limits.

= Does the plugin work with multisite? =

The current version is designed for single-site installations. Multisite support may be added in future versions.

== Screenshots ==

1. Main settings page with performance and security tabs
2. Asset optimization settings
3. Database cleanup statistics
4. Login security configuration
5. Security headers settings
6. Statistics dashboard

== Changelog ==
= 1.0.2 =
* New: Integrated "Our Plugins" portfolio showcase page.
* New: Integrated "Meet The Author" profile details page.
* Tweak: Redesigned the backend UI with modern aesthetics, clean drop shadows, and responsive grid layouts.

= 1.0.1 =
*Compatible with WordPress 6.9 version 

= 1.0.0 =
* Initial release
* Asset optimization (CSS/JS minification and combination)
* Lazy loading for images and iframes
* Database cleanup tools
* Login security with attempt limiting
* Security headers implementation
* WordPress version hiding
* Admin interface with tabs
* Statistics dashboard

== Upgrade Notice ==

= 1.0.0 =
Initial release of FBS Secure Optimize. Install to start optimizing your WordPress site's performance and security.

== Support ==

For support, feature requests, or bug reports, please visit our support forum or contact us through our website.

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data. All settings and data remain on your server. The plugin only processes data locally to optimize your website's performance and security.

== Technical Requirements ==

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* Sufficient server resources for asset optimization features

== Performance Impact ==

FBS Secure Optimize is designed to have minimal impact on your site's performance. The plugin only loads necessary components and uses efficient caching mechanisms to ensure optimal performance.

== Security Considerations ==

All user inputs are properly sanitized and validated. The plugin uses WordPress nonces for form security and follows WordPress security best practices throughout the codebase.
