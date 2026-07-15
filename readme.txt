=== BlueWorx Labs | WordPress Enhancements ===
Contributors:      blueworx
Tags:              login, security, custom login url, hardening, cache
Requires at least: 5.0
Tested up to:      6.9
Requires PHP:      8.0
Stable tag:        1.12.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Hardens WordPress security and refreshes Cloudways cache when pages or posts change.

== Description ==

BlueWorx Labs improves your WordPress site by:

* Replacing the default `/wp-login.php` login URL with a custom `/admin_login` URL.
* Blocking all direct access to `/wp-login.php` - visitors are redirected to the homepage.
* Blocking unauthenticated access to `/wp-admin` - visitors are redirected to the homepage.
* Working reliably on both Apache and Nginx servers, including Cloudways stacks, without relying on `.htaccess` rewrite rules.
* Refreshing Cloudways/Varnish cache when posts or pages are published, updated, restored, or deleted.
* Disabling comments, suppressing selected admin emails, and cleaning up the user profile screen.

The new login URL and cache refresh status are displayed in the **BlueWorx** admin menu.

== Installation ==

1. Upload the `blueworx-labs-wordpress` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **BlueWorx** to see your new login URL and active enhancements.
4. Bookmark your new login URL before logging out.

== Frequently Asked Questions ==

= What is my new login URL? =
After activating the plugin, go to **BlueWorx > Enhancements**. Your new login URL will be displayed there.

= What happens if I visit wp-login.php or wp-admin directly? =
You will be redirected to the homepage with a 301 redirect.

= Does this work on Nginx? =
Yes. The plugin uses pure PHP request interception and does not rely on `.htaccess` or server-level rewrite rules.

= Will password reset emails still work? =
Yes. All password reset, logout, and account confirmation flows use the custom URL automatically.

= How does the cache refresh work? =
When a page or post changes, the plugin refreshes the edited content, homepage, and related listing pages. A manual **Refresh Cache Now** button is also available in **BlueWorx > Cache**.

== Development Layout ==

The plugin is split into focused files for easier updates:

* `blueworx-labs-wordpress.php` loads the plugin.
* `includes/` contains admin, login, cache, comments, email, and helper functions.
* `assets/js/` contains admin screen scripts.

== Changelog ==

Changes from 1.5.0 onward are tracked in CHANGELOG.md. Versions 1.0.0–1.4.30
were released as "BlueWorx Enhancements".
