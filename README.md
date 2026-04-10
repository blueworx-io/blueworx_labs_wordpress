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
   - Build a ZIP with `./scripts/build-zip.sh`.
   - Create a GitHub release tag like `v1.1.0`.
   - Upload `dist/blueworx-enhancements.zip` as a release asset.

WordPress will then show updates in the Plugins screen.

## Option 1: Build ZIP locally (recommended)

This repository does **not** commit the ZIP artifact (to avoid binary-file PR issues).

Generate the uploadable plugin ZIP with:

```bash
./scripts/build-zip.sh
```

This creates:

- `dist/blueworx-enhancements.zip`

Then install from WordPress:

1. In WordPress, go to **Plugins → Add New → Upload Plugin**.
2. Upload `dist/blueworx-enhancements.zip` and click **Install Now**.
3. Activate the plugin.
4. Open **Settings → BlueWorx** to confirm it is active.

## Option 2: Direct file download

You can also download `blueworx-enhancements.php` directly from the repository and package it manually:

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
