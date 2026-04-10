# BlueWorx Enhancements

BlueWorx Enhancements is a single-file WordPress plugin.

## Build ZIP locally (recommended)

This repository does **not** commit the ZIP artifact (to avoid binary-file PR issues).

Generate the uploadable plugin ZIP with:

```bash
./scripts/build-zip.sh
```

This creates:

- `dist/blueworx-enhancements.zip`

## Install (WordPress Admin)

1. Run `./scripts/build-zip.sh`.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload `dist/blueworx-enhancements.zip` and click **Install Now**.
4. Activate the plugin.
5. Open **Settings → BlueWorx** to confirm it is active.

## What it does

- Changes login route to `/admin_dashboard`
- Returns 404 for direct `wp-login.php` access
- Returns 404 for guest access attempts to `/wp-admin` (except WordPress AJAX/POST endpoints)
- Adds **Settings → BlueWorx** page confirming plugin is active and showing custom login URL
