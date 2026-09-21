<?php
/**
 * Store pages: SureCart's checkout, thank-you and customer dashboard, dressed
 * in the BlueWorx look, and the check that keeps its four pages present.
 *
 * One feature, several files. Each file is one job, ported from the ClubHouse
 * plugin's dashboard with the club-specific parts replaced by filters other
 * plugins hook. See docs/store-pages-api.md.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BLUEWORX_LABS_PATH . 'includes/store/pages.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/pages-notice.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/slot.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/views.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/actions.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/shell.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/context.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/assets.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/commerce.php';

// Task 7 adds dashboard.php here, after commerce.php.
