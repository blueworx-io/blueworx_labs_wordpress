# BlueWorx Enhancements – Release and Update Workflow

## Repository Structure

```text
/blueworx_enhancements
  blueworx_enhancements.php
  plugin-update-checker/
```

## GitHub Authentication for Private Repositories

The plugin supports authenticated update checks against private GitHub repositories.

1. Create a GitHub Personal Access Token (classic or fine-grained) with **repository read access**.
2. Add the token in one of these ways:
   - `wp-config.php`: `define('BWX_GITHUB_TOKEN', 'ghp_xxx');`
   - environment variable: `BWX_GITHUB_TOKEN`
   - filter: `bwx_github_update_token`

## Versioning Rules (required each release)

1. Update plugin header version in `blueworx_enhancements.php`.
2. Update `BWX_ENHANCEMENTS_VERSION` to the same value.
3. Create a Git tag that matches plugin version (e.g., plugin `1.0.1` => tag `v1.0.1`).
4. Create a GitHub Release for that tag.
5. Upload a plugin ZIP where the root folder is `blueworx_enhancements/`.

## ZIP Validation

Build ZIP from the parent directory and exclude VCS/dev files:

```bash
cd ..
zip -r blueworx_enhancements-v1.0.0.zip blueworx_enhancements \
  -x 'blueworx_enhancements/.git/*' \
  -x 'blueworx_enhancements/.distignore' \
  -x 'blueworx_enhancements/README.md'
unzip -l blueworx_enhancements-v1.0.0.zip
```

Expected: archive paths begin with `blueworx_enhancements/` (not loose root files).

## WordPress Update Flow Test Checklist

1. Install and activate plugin.
2. Release `v1.0.0` and verify install source.
3. Make a plugin code change.
4. Bump version (for example `1.0.1`).
5. Push commit and tag `v1.0.1`.
6. Create GitHub release and attach ZIP.
7. In WordPress, trigger update check and confirm update appears.
8. (Optional) Enable plugin auto-updates in **Plugins** screen.
