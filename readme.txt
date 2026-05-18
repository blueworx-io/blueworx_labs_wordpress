=== BlueWorx Enhancements ===
Contributors:      blueworx
Tags:              login, security, custom login url, hardening, cache
Requires at least: 5.0
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.4.26
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

The new login URL and cache refresh status are displayed in the **BlueWorx** admin menu.

== Installation ==

1. Upload the `blueworx-enhancements` folder to `/wp-content/plugins/`.
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

* `blueworx-enhancements.php` loads the plugin.
* `includes/` contains admin, login, cache, comments, email, and helper functions.
* `assets/css/admin.css` contains admin styling.

== Changelog ==

= 1.4.26 =
* Changed: Role editor sections now start collapsed.

= 1.4.25 =
* Fixed: Empty role editor groups are removed after dragging.
* Added: Collapsible role editor sections.

= 1.4.24 =
* Added: Role settings export and made the role editor full width.

= 1.4.23 =
* Added: Role-based backend page access with Available, Allowed, and View Only controls.

= 1.4.22 =
* Added: Content Editor role, plain permission descriptions, alphabetical role columns, and Elementor template protection.

= 1.4.21 =
* Added: Business Owner and External Admin roles with a BlueWorx role editor.

= 1.4.20 =
* Fixed: WordPress login confirmation and password flows now work through the custom login URL.

= 1.4.19 =
* Fixed: Empty profile cards are cleaned up after delayed profile scripts run.

= 1.4.18 =
* Fixed: Profile cards with no visible usable content are hidden.

= 1.4.17 =
* Fixed: Empty profile cards are hidden when their contents are hidden.

= 1.4.16 =
* Changed: Application Passwords setting now saves when toggled.

= 1.4.15 =
* Fixed: ASENHA user roles now appear in their own profile card.

= 1.4.14 =
* Fixed: Application Passwords only show on admin user profiles when enabled.
* Changed: Application Passwords now has its own Enhancements card.

= 1.4.13 =
* Fixed: Profile cards now split Account Management, Application Passwords, SureCart, LearnDash, Course Info, and SureMembers cleanly.

= 1.4.12 =
* Fixed: BlueWorx stays in the regular menu order and SureMembers gets its own profile card.

= 1.4.11 =
* Fixed: Profile cards now split at LearnDash and SureMembers sections.

= 1.4.10 =
* Fixed: Elementor Notes profile card is now hidden.

= 1.4.9 =
* Changed: Renamed Toggle to More and restored BlueWorx to menu ordering.

= 1.4.8 =
* Added: Profile cards and Application Passwords visibility control.
* Changed: Hidden Elementor Notes, Elementor AI, and Personal Options on profile screens.

= 1.4.7 =
* Fixed: Toggle menu order now matches the editor and only appears when Toggle has items.

= 1.4.6 =
* Added: Excerpt support for Pages.

= 1.4.5 =
* Fixed: Toggle menu links now point directly to the original admin pages.

= 1.4.4 =
* Fixed: Toggle menu pages now keep their content when opened.

= 1.4.3 =
* Added: BlueWorx main menu, Enhancements page, Cache page, and improved menu editor controls.

= 1.4.2 =
* Changed: Split the plugin into focused include files for easier development.
* Changed: Moved admin styling into `assets/css/admin.css`.

= 1.4.0 =
* Added: Cloudways/Varnish cache refresh after post and page changes.
* Added: Cache Refresh section and manual Refresh Cache Now button under BlueWorx > Cache.
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
