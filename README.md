# BlueWorx Enhancements

BlueWorx Enhancements is a single-file WordPress plugin.

## Auto updates from GitHub Releases

To enable dashboard updates (no manual re-upload each change):

1. Set your repository in `wp-config.php`:

```php
define( 'BWX_GITHUB_REPO', 'your-org/your-repo' );
```

2. (Optional, for private repos) set a token in `wp-config.php`:

```php
define( 'BWX_GITHUB_TOKEN', 'your-github-token' );
```

3. For each release:
   - Increase plugin `Version` in `blueworx-enhancements.php`.
   - Create a GitHub release tag like `v1.1.0`.
   - Upload a plugin ZIP asset for that release.

WordPress will then show updates in the Plugins screen.

## Option 2: Direct file download

Download `blueworx-enhancements.php` directly from the repository and package it manually:

1. Create a folder named `blueworx-enhancements`.
2. Put `blueworx-enhancements.php` in that folder.
3. Zip the folder.
4. In WordPress go to **Plugins → Add New → Upload Plugin**.
5. Upload the zip and activate.

## What it does

- Changes login route to `/admin_dashboard`
- Returns 404 for direct `wp-login.php` access
- Returns 404 for guest access attempts to `/wp-admin` (except WordPress AJAX/POST endpoints)
- Adds **Settings → BlueWorx** page confirming plugin is active and showing custom login URL
