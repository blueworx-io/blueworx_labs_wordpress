# BlueWorx Enhancements

BlueWorx Enhancements is a single-file WordPress plugin.

## Direct Download from Repository

You can download the plugin file directly from this repository:

- `blueworx-enhancements.php`

After downloading, place the file inside a folder named `blueworx-enhancements`, then zip that folder and upload it to WordPress.

## Install (WordPress Admin)

1. Create folder: `blueworx-enhancements`
2. Put `blueworx-enhancements.php` in that folder.
3. Zip the folder.
4. In WordPress go to **Plugins → Add New → Upload Plugin**.
5. Upload the zip and activate.

## What it does

- Changes login route to `/admin_dashboard`
- Returns 404 for direct `wp-login.php` access
- Returns 404 for guest access attempts to `/wp-admin` (except WordPress AJAX/POST endpoints)
- Adds **Settings → BlueWorx** page confirming plugin is active and showing custom login URL
