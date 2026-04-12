<?php
/**
 * Uninstall cleanup for BlueWorx Enhancements.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'blueworx_enhancements_version' );
