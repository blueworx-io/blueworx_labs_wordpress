=== BlueWorx Enhancements ===
Contributors:      blueworx
Tags:              login, security, custom login url, hardening, cache
Requires at least: 5.0
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.4.2
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Hardens WordPress security and refreshes Cloudways cache when pages or posts change.

== Description ==

BlueWorx Enhancements improves your WordPress site by:

* Replacing the default `/wp-login.php` login URL with a custom `/admin_login` URL.
* Blocking all direct access to `/wp-login.php` - visitors are redirected to the homepage.
* Blocking unauthenticated access to `/wp-admin` - visitors are redirected to the homepage.
* Working reliably on both Apache and Nginx servers, including Cloudways stacks, without relying on `.htaccess` rewrite rules.
* Refreshing Cloudways/Varnish cache when posts or pages are published, updated, restored, or deleted.
* Disabling comments, suppressing selected admin emails, and cleaning up the user profile screen.

The new login URL and cache refresh status are displayed in **Settings > BlueWorx**.

== Installation ==

1. Upload the `blueworx-enhancements` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **Settings > BlueWorx** to see your new login URL and cache refresh status.
4. Bookmark your new login URL before logging out.

== Frequently Asked Questions ==

= What is my new login URL? =
After activating the plugin, go to **Settings > BlueWorx**. Your new login URL will be displayed there.

= What happens if I visit wp-login.php or wp-admin directly? =
You will be redirected to the homepage with a 301 redirect.

= Does this work on Nginx? =
Yes. The plugin uses pure PHP request interception and does not rely on `.htaccess` or server-level rewrite rules.

= Will password reset emails still work? =
Yes. All password reset, logout, and account confirmation flows use the custom URL automatically.

= How does the cache refresh work? =
When a page or post changes, the plugin refreshes the edited content, homepage, and related listing pages. A manual **Clear Cache Now** button is also available in **Settings > BlueWorx**.

== Development Layout ==

The plugin is split into focused files for easier updates:

* `blueworx-enhancements.php` loads the plugin.
* `includes/` contains admin, login, cache, comments, email, and helper functions.
* `assets/css/admin.css` contains admin styling.

== Changelog ==

= 1.4.2 =
* Changed: Split the plugin into focused include files for easier development.
* Changed: Moved admin styling into `assets/css/admin.css`.

= 1.4.0 =
* Added: Cloudways/Varnish cache refresh after post and page changes.
* Added: Cache Refresh section and manual Clear Cache Now button under Settings > BlueWorx.
* Added: Elementor generated CSS cache refresh when Elementor is available.

= 1.3.0 =
* Added: Comments disabled completely across all post types, admin menu, admin bar, dashboard, and list table columns.
* Added: Admin email notifications suppressed for plugin updates, theme updates, new user registrations, password changes, and email changes.
* Added: Profile page sections hidden on user-edit.php and profile.php via targeted CSS.

= 1.2.2 =
* Updated: Plugin URI set to https://blueworx.io/
* Updated: Author URI set to https://profiles.wordpress.org/blueworx/

= 1.2.1 =
* Fixed: Plugin URI and Author URI updated to valid WordPress.org domains.
* Fixed: Applied wp_unslash() and sanitize_text_field() to $_SERVER['REQUEST_URI'] and $_SERVER['SCRIPT_NAME'].
* Fixed: Tested up to value updated to WordPress 6.9.

= 1.2.0 =
* Improved WordPress.org compliance: sanitisation, escaping, i18n, and inline documentation.

= 1.1.0 =
* Replaced rewrite-rule approach with pure PHP interception for Nginx + Apache compatibility.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.4.2 =
Restructures the plugin files for easier future development.
