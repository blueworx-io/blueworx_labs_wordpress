# Basic Actionable Plan: Manage and Deploy WordPress Plugin Updates via GitHub

## Goal
Host your plugin code in GitHub, edit it with Codex, and push updates to all WordPress sites that have the plugin installed.

---

## Important Reality Check
WordPress **does not natively auto-update plugins from GitHub**.

To make your plan work, you need one of these update paths:
1. **Use WordPress.org plugin repository** (best for public plugins), or
2. **Use a GitHub update checker inside your plugin** (works for private or public GitHub repos).

This plan uses option 2 because it matches your private/public flexibility.

---

## Step-by-Step Implementation

### 1) Put your plugin in GitHub
1. Create a GitHub repository (public or private).
2. Add your plugin files, including:
   - Main plugin file with valid header (`Plugin Name`, `Version`, etc.)
   - `readme.txt` (recommended)
3. Push your initial commit.

**Tip:** Keep semantic versions (`1.0.0`, `1.0.1`, `1.1.0`, etc.).

---

### 2) Add GitHub-based update support to the plugin
Use a proven updater library such as:
- `YahnisElsts/plugin-update-checker`

In your plugin bootstrap file, initialize the update checker to point to your GitHub repo and branch.

If your repo is private, configure authentication (token) according to the library docs.

---

### 3) Define your release workflow
For every plugin update:
1. Make code changes locally (or with Codex).
2. Bump plugin version in your main plugin file.
3. Commit and push.
4. Create a Git tag/release (e.g., `v1.0.1`) on GitHub.

Sites will detect a new version when update checks run.

---

### 4) Install plugin on each WordPress site from your distribution ZIP
For consistency:
1. Build a clean plugin ZIP from your release.
2. Install the ZIP on each site once.
3. Keep the plugin slug/folder name stable forever.

After first install, future versions should appear as updates (if updater is configured correctly).

---

### 5) Choose update behavior per site
On each site, decide:
- **Manual updates:** Admin clicks “Update now” when available.
- **Automatic updates:** Enable auto-updates for that plugin in WordPress.

For business-critical sites, manual/staged updates are safer.

---

### 6) Add a safe deployment policy
Before every release:
1. Test on staging site.
2. Validate activation/deactivation.
3. Run a quick smoke test of key plugin features.
4. Publish release only after pass.

---

### 7) Secure private-repo updates (if private)
If using private GitHub repo:
1. Generate least-privileged token.
2. Store token securely (not hardcoded in source).
3. Rotate token periodically.

---

## Minimal Ongoing Checklist (Per Update)
1. Make code changes.
2. Bump plugin version.
3. Commit + push.
4. Tag release.
5. Confirm update appears on one test site.
6. Roll out to all sites (manual or auto).

---

## Recommended Structure for Your Repo
- `your-plugin.php` (main plugin file)
- `includes/`
- `assets/`
- `readme.txt`
- `composer.json` (if using Composer)
- `vendor/` (if your deployment process includes vendored dependencies)

---

## Common Pitfalls to Avoid
- Forgetting to bump plugin version (no update shown).
- Changing plugin slug/folder name (breaks update path).
- Not tagging releases (some updater setups rely on tags/releases).
- Using private repo without proper token/auth.

---

## What to do next (first 60 minutes)
1. Create GitHub repo and push plugin skeleton.
2. Add update-checker library.
3. Wire updater in plugin bootstrap.
4. Create `v0.1.0` release.
5. Install on one staging site and verify update detection.

Once this works on staging, repeat install across all sites.
