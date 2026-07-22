<?php
/**
 * Public front-end layer — bootstrap.
 *
 * The plugin renders the marketing site itself rather than relying on a theme,
 * so the site is identical wherever it is hosted. Loaded only when the
 * public_site feature is on.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BLUEWORX_LABS_PATH . 'includes/public/helpers-public.php';
require_once BLUEWORX_LABS_PATH . 'includes/public/pages.php';
require_once BLUEWORX_LABS_PATH . 'includes/public/render.php';
require_once BLUEWORX_LABS_PATH . 'includes/public/assets.php';
