=== BlueWorx Enhancements ===
Contributors:      blueworx
Tags:              login, security, custom login url, hardening
Requires at least: 5.0
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.3.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Hardens WordPress security by replacing the default login URL with a custom one and blocking direct access to wp-login.php and wp-admin.

== Description ==

BlueWorx Enhancements improves the security of your WordPress site by:

* Replacing the default `/wp-login.php` login URL with a custom `/admin_login` URL.
* Blocking all direct access to `/wp-login.php` — visitors are redirected to the homepage.
* Blocking unauthenticated access to `/wp-admin` — visitors are redirected to the homepage.
* Working reliably on both Apache and Nginx servers (including Cloudways stacks) without relying on `.htaccess` rewrite rules.

The new login URL is displayed in **Settings > BlueWorx** for easy reference.

== Installation ==

1. Upload the `blueworx-enhancements` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **Settings > BlueWorx** to see your new login URL.
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

== Changelog ==

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

= 1.2.0 =
Recommended update — includes improved sanitisation and escaping throughout.
