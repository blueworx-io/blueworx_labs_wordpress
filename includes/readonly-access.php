<?php
/**
 * The shared read-only guard.
 *
 * Two roles in this plugin are read-only: the BlueWorx support account, which
 * signs in with a key inside a window, and BlueWorx: External, which is a named
 * client account with an expiry date. What "read-only" MEANS is the same for
 * both, and it lives here so there is one implementation to review and one
 * place a gap gets closed.
 *
 * The guarantee is not the capability map. It is blueworx_readonly_block_writes(),
 * which refuses every non-GET request these accounts make, and refuses
 * admin-post.php whatever the method because that endpoint only ever acts.
 * Third-party plugins routinely write through their own AJAX and REST endpoints
 * without checking a meaningful capability, so a rule that depends on plugin
 * authors behaving correctly is not a safety model. A method-level block does
 * not depend on them.
 *
 * XML-RPC is a separate door, because it authenticates from the request body
 * after "init" has already passed. blueworx_readonly_block_xmlrpc() closes it.
 *
 * The capability map still does a job the block cannot: WordPress trashes,
 * deletes and activates through nonce'd GET links, which the method block never
 * sees, and capabilities such as unfiltered_html and install_plugins are onward
 * access rather than a write.
 *
 * Known gap, disclosed on both consoles: a plugin that writes in response to an
 * ordinary GET request on a screen of its own is not caught here.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The roles this guard applies to.
 *
 * blueworx_external_role_slug() is guarded because includes/external-access.php
 * loads after this file, and because a build that ships only support access
 * must still work.
 *
 * @return array Role slugs.
 */
function blueworx_readonly_roles() {
	$roles = array( blueworx_support_role_slug() );

	if ( function_exists( 'blueworx_external_role_slug' ) ) {
		$roles[] = blueworx_external_role_slug();
	}

	/**
	 * Filters the roles treated as read-only.
	 *
	 * Adding a role here subjects it to the write block, the GET-action gate and
	 * the personal-data screens gate. It does not create the role or grant it
	 * anything.
	 *
	 * @param array $roles Role slugs.
	 */
	return (array) apply_filters( 'blueworx_readonly_roles', $roles );
}

/**
 * Whether a user holds a given role.
 *
 * @param mixed  $user User to test.
 * @param string $slug Role slug.
 * @return bool True when the user holds it.
 */
function blueworx_readonly_user_has_role( $user, $slug ) {
	return $user instanceof WP_User && in_array( $slug, (array) $user->roles, true );
}

/**
 * The current user, when this request is a read-only one.
 *
 * Returns the user rather than a boolean because every caller that needs the
 * answer also needs to know WHICH read-only account is asking — the two roles
 * differ on personal data and on where their events are logged.
 *
 * @return WP_User|null Read-only user, or null.
 */
function blueworx_readonly_current_user() {
	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return null;
	}

	foreach ( blueworx_readonly_roles() as $slug ) {
		if ( blueworx_readonly_user_has_role( $user, $slug ) ) {
			return $user;
		}
	}

	return null;
}

/**
 * Whether this read-only user may see personal data.
 *
 * Support access has a switch for it, opened deliberately and for a window.
 * External access has none: a client looking round a demo never needs the
 * customer list, so the answer is always no rather than a setting somebody
 * could leave on.
 *
 * @param mixed $user Read-only user.
 * @return bool True when personal-data screens are allowed.
 */
function blueworx_readonly_data_allowed( $user ) {
	if ( blueworx_readonly_user_has_role( $user, blueworx_support_role_slug() ) ) {
		return blueworx_support_data_open();
	}

	return false;
}

/**
 * Records a refusal against whichever account caused it.
 *
 * Support access keeps an audit log, because a BlueWorx agent working on
 * somebody else's site has to be accountable to its owner. External access does
 * not: it is the site owner's own demo, the account is named, and a log of every
 * blocked click on it would be noise nobody reads.
 *
 * @param mixed  $user Read-only user.
 * @param string $type Event type.
 * @return void
 */
function blueworx_readonly_log_event( $user, $type ) {
	if ( blueworx_readonly_user_has_role( $user, blueworx_support_role_slug() ) ) {
		blueworx_support_log_event( $type );
	}

	/**
	 * Fires when a read-only account is refused something.
	 *
	 * @param string $type Event type.
	 * @param mixed  $user The refused user.
	 */
	do_action( 'blueworx_readonly_event', $type, $user );
}

/**
 * Capabilities removed from the administrator clone.
 *
 * These are the operations that are destructive or that grant onward access;
 * everything else is retained so admin screens still render, because WordPress
 * gates screen rendering on the same capabilities it gates writes on. The
 * read-only guarantee comes from the request-layer block, not from this list.
 *
 * unfiltered_html is included alongside the file/plugin/theme editing
 * capabilities because it is onward access by another route: raw script
 * saved into post or page content executes later, in a real administrator's
 * browser, when they view it.
 *
 * @return array Capability names.
 */
function blueworx_readonly_removed_caps() {
	return array(
		'edit_files',
		'edit_plugins',
		'edit_themes',
		'unfiltered_html',
		'install_plugins',
		'install_themes',
		'update_plugins',
		'update_themes',
		'update_core',
		'delete_plugins',
		'delete_themes',
		'export',
		'import',
		'create_users',
		'edit_users',
		'delete_users',
		'promote_users',
		'remove_users',

		/*
		 * Content deletion. WordPress trashes and deletes through nonce'd GET
		 * links (post.php?action=trash, and the method="get" bulk form on
		 * edit.php and upload.php), so the request-layer write block — which
		 * only refuses non-GET methods — never sees those requests. Nothing on
		 * a read-only account needs these, and dropping them costs no screen
		 * rendering: list tables simply omit the Trash and Delete links.
		 */
		'delete_posts',
		'delete_others_posts',
		'delete_published_posts',
		'delete_private_posts',
		'delete_pages',
		'delete_others_pages',
		'delete_published_pages',
		'delete_private_pages',
	);
}

/**
 * Meta capabilities denied outright to the support account.
 *
 * The primitive capabilities above cover posts, pages and attachments. These are
 * the destructive operations WordPress resolves through map_meta_cap against a
 * capability the account must keep in order to READ the screen at all —
 * delete_term resolves to manage_categories, which is also what gates viewing
 * edit-tags.php, and delete_comment resolves through the parent post's
 * edit_post, which gates viewing the post editor. Denying the meta capability
 * leaves the read intact and removes only the write.
 *
 * The user meta capabilities (edit_user, delete_user, promote_user, remove_user)
 * are deliberately NOT here. Their primitives are already stripped in
 * blueworx_readonly_removed_caps(), so managing another user is impossible
 * regardless; denying the meta capability adds nothing and breaks the account's
 * own profile, because core's map_meta_cap special-cases self-editing to always
 * allow it and this filter would override that.
 *
 * @return array Meta capability names.
 */
function blueworx_readonly_denied_meta_caps() {
	return array(
		'delete_post',
		'delete_page',
		'delete_term',
		'delete_comment',
	);
}

/**
 * Denies destructive meta capabilities to the support account.
 *
 * Defence in depth behind blueworx_readonly_gate_write_actions(): a capability
 * denial holds wherever the request reaches, including any code path that
 * bypasses the admin_init screen gate entirely.
 *
 * @param array  $caps    Primitive capabilities required.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User being checked.
 * @return array Primitive capabilities, or do_not_allow.
 */
function blueworx_readonly_deny_meta_caps( $caps, $cap, $user_id ) {
	if ( ! in_array( $cap, blueworx_readonly_denied_meta_caps(), true ) ) {
		return $caps;
	}

	$user = blueworx_readonly_current_user();

	if ( ! $user ) {
		return $caps;
	}

	// Narrowed to the read-only account asking about itself. map_meta_cap also
	// runs for other users (a list table asking "can that user edit this
	// row?"), and a real administrator's own checks must not be affected.
	if ( get_current_user_id() !== (int) $user_id ) {
		return $caps;
	}

	return array( 'do_not_allow' );
}
add_filter( 'map_meta_cap', 'blueworx_readonly_deny_meta_caps', 10, 3 );

/**
 * Builds the support role's capability map from the live administrator role.
 *
 * @return array Capability map (cap => true).
 */
function blueworx_readonly_build_caps() {
	$base = get_role( 'administrator' );
	$caps = ( $base && is_array( $base->capabilities ) ) ? $base->capabilities : array();

	foreach ( blueworx_readonly_removed_caps() as $cap ) {
		unset( $caps[ $cap ] );
	}

	$caps['read'] = true;

	return $caps;
}

/**
 * Admin screens denied to the support account without data access.
 *
 * @return array $pagenow values.
 */
function blueworx_readonly_denied_screens() {
	/** This filter is documented in includes/readonly-access.php */
	$screens = (array) apply_filters(
		'blueworx_support_denied_screens',
		array(
			'users.php',
			'user-edit.php',
			'edit-comments.php',
			// The single-comment editor. edit-comments.php is the list it belongs
			// to; this screen shows the same commenter email and IP one row at a
			// time, and is a different $pagenow, so denying the list alone left
			// the data reachable by ID.
			'comment.php',
			'export.php',
		)
	);

	/**
	 * Filters the screens hidden from read-only accounts.
	 *
	 * Lets a site add the data screens of a plugin this list does not know about.
	 *
	 * @param array $screens $pagenow values.
	 */
	return (array) apply_filters( 'blueworx_readonly_denied_screens', $screens );
}

/**
 * Whether WooCommerce is present on this site.
 *
 * @return bool True when WooCommerce is loaded.
 */
function blueworx_readonly_woocommerce_active() {
	return class_exists( 'WooCommerce' ) || defined( 'WC_PLUGIN_FILE' );
}

/**
 * Whether SureCart is present on this site.
 *
 * @return bool True when SureCart is loaded.
 */
function blueworx_readonly_surecart_active() {
	return class_exists( 'SureCart' ) || defined( 'SURECART_PLUGIN_FILE' );
}

/**
 * Admin page slugs (admin.php?page=…) denied without data access.
 *
 * The support role is a clone of the LIVE administrator role, so it inherits
 * manage_woocommerce and its equivalents from whatever commerce plugin the
 * client runs. Those screens are not $pagenow values — they hang off
 * admin.php?page= — so they need matching of their own, or orders and
 * customers stay fully readable with data access OFF (spec §1.5).
 *
 * Only screens of a plugin that is actually present are listed, so the console
 * never refuses a page the site does not have.
 *
 * @return array Page slugs.
 */
function blueworx_readonly_denied_admin_pages() {
	$pages = array();

	if ( blueworx_readonly_woocommerce_active() ) {
		// HPOS order tables. Order-type variants are addressed as
		// wc-orders--shop_subscription, so this list is prefix-matched.
		$pages[] = 'wc-orders';
	}

	if ( blueworx_readonly_surecart_active() ) {
		$pages[] = 'sc-orders';
		$pages[] = 'sc-customers';
		$pages[] = 'sc-subscriptions';
	}

	/** This filter is documented in includes/readonly-access.php */
	$pages = (array) apply_filters( 'blueworx_support_denied_admin_pages', $pages );

	/**
	 * Filters the admin.php page slugs hidden from read-only accounts.
	 *
	 * Matched as prefixes, so "wc-orders" also covers "wc-orders--<type>".
	 *
	 * @param array $pages Page slugs.
	 */
	return (array) apply_filters( 'blueworx_readonly_denied_admin_pages', $pages );
}

/**
 * Post types (edit.php?post_type=…) denied without data access.
 *
 * Covers the legacy, pre-HPOS WooCommerce order table, which is a normal post
 * list screen rather than a page slug.
 *
 * @return array Post type keys.
 */
function blueworx_readonly_denied_post_types() {
	$types = array();

	if ( blueworx_readonly_woocommerce_active() ) {
		$types[] = 'shop_order';
		$types[] = 'shop_subscription';
	}

	/** This filter is documented in includes/readonly-access.php */
	$types = (array) apply_filters( 'blueworx_support_denied_post_types', $types );

	/**
	 * Filters the post types hidden from read-only accounts.
	 *
	 * @param array $types Post type keys.
	 */
	return (array) apply_filters( 'blueworx_readonly_denied_post_types', $types );
}

/**
 * REST route prefixes denied to the support account without data access.
 *
 * @return array Route prefixes.
 */
function blueworx_readonly_denied_routes() {
	$routes = array( '/wp/v2/users', '/wp/v2/comments' );

	// The same commerce data, reachable over REST rather than through a screen.
	if ( blueworx_readonly_woocommerce_active() ) {
		$routes[] = '/wc/v3/orders';
		$routes[] = '/wc/v3/customers';
		$routes[] = '/wc-analytics/orders';
		$routes[] = '/wc-analytics/customers';
	}

	if ( blueworx_readonly_surecart_active() ) {
		$routes[] = '/surecart/v1/orders';
		$routes[] = '/surecart/v1/customers';
	}

	/** This filter is documented in includes/readonly-access.php */
	$routes = (array) apply_filters( 'blueworx_support_denied_routes', $routes );

	/**
	 * Filters the REST routes hidden from read-only accounts.
	 *
	 * @param array $routes Route prefixes.
	 */
	return (array) apply_filters( 'blueworx_readonly_denied_routes', $routes );
}

/**
 * Whether the screen being requested holds personal data.
 *
 * Four shapes of screen are matched, because the data screens this feature has
 * to withhold are not all addressed the same way: plain $pagenow values
 * (users.php), page slugs behind admin.php (WooCommerce and SureCart order and
 * customer tables), post-type list tables behind edit.php (the legacy
 * WooCommerce order table), and the single-item editor behind post.php, which
 * identifies its subject by ID rather than by post type.
 *
 * @return bool True when the screen must be refused.
 */
function blueworx_readonly_screen_is_denied() {
	global $pagenow;

	$screen = (string) $pagenow;

	if ( in_array( $screen, blueworx_readonly_denied_screens(), true ) ) {
		// The account's own profile is reachable; other users' are not.
		if ( 'user-edit.php' === $screen ) {
			$target = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return get_current_user_id() !== $target;
		}

		return true;
	}

	if ( 'admin.php' === $screen ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		foreach ( blueworx_readonly_denied_admin_pages() as $prefix ) {
			if ( '' !== $page && 0 === strpos( $page, (string) $prefix ) ) {
				return true;
			}
		}

		// WooCommerce Analytics is one SPA behind a single page slug, so the
		// customers report is addressed by its path rather than its own slug.
		// Only that path is refused; the rest of the app stays diagnosable.
		if ( 'wc-admin' === $page ) {
			$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 0 === strpos( $path, '/customers' ) ) {
				return true;
			}
		}

		return false;
	}

	if ( 'edit.php' === $screen || 'post-new.php' === $screen ) {
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return '' !== $post_type && in_array( $post_type, blueworx_readonly_denied_post_types(), true );
	}

	// The single-item editor for the same objects. post.php carries no post_type
	// parameter — the type has to be resolved from the ID — so matching the list
	// screen alone left every denied record readable one at a time, by an ID
	// that is sequential and trivially enumerated.
	if ( 'post.php' === $screen ) {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $post_id > 0 ) {
			$post_type = (string) get_post_type( $post_id );

			return '' !== $post_type && in_array( $post_type, blueworx_readonly_denied_post_types(), true );
		}
	}

	return false;
}

/**
 * Denies personal-data admin screens unless data access is open.
 *
 * A 403 rather than a redirect, so the refusal is unambiguous.
 *
 * @return void
 */
function blueworx_readonly_gate_data_screens() {
	$user = blueworx_readonly_current_user();

	if ( ! $user ) {
		return;
	}

	if ( blueworx_readonly_data_allowed( $user ) ) {
		return;
	}

	if ( ! blueworx_readonly_screen_is_denied() ) {
		return;
	}

	wp_die(
		esc_html__( 'This screen holds personal data and is not available to this account.', 'blueworx-labs-wordpress' ),
		esc_html__( 'Read-only access', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
// Priority 0: some features (e.g. includes/disable-comments.php) install their
// own admin_init handler that redirects or exits before reaching this file's
// default-priority position, which would let a denied screen escape as a
// redirect-to-200 rather than the 403 required here. Running first means this
// denial always wins over another feature's own admin_init handling of the
// same screen.
add_action( 'admin_init', 'blueworx_readonly_gate_data_screens', 0 );

/**
 * Admin screens whose "action" parameter performs a write.
 *
 * WordPress does not confine its writes to POST. Trashing, deleting, approving,
 * activating and upgrading are all driven by nonce'd GET links, and the two
 * list-table filter forms that carry bulk actions (edit.php and upload.php) are
 * method="get", so their bulk submissions arrive as GET too. Every one of those
 * requests passes blueworx_readonly_block_writes(), which only refuses non-GET
 * methods — so each screen that takes a write action over GET has to be named.
 *
 * @return array $pagenow values.
 */
function blueworx_readonly_action_screens() {
	/** This filter is documented in includes/readonly-access.php */
	$screens = (array) apply_filters(
		'blueworx_support_action_screens',
		array(
			'plugins.php',
			'themes.php',
			'post.php',
			'edit.php',
			'upload.php',
			'media.php',
			'comment.php',
			'edit-comments.php',
			'edit-tags.php',
			'term.php',
			'link.php',
			'edit-link-categories.php',
			'users.php',
			'update.php',
			'update-core.php',
			'import.php',
			'export.php',
			'options.php',
			'theme-editor.php',
			'plugin-editor.php',
			'site-health.php',
			'privacy.php',
			'erase-personal-data.php',
			'export-personal-data.php',
		)
	);

	/**
	 * Filters the screens whose GET action parameter is refused.
	 *
	 * @param array $screens $pagenow values.
	 */
	return (array) apply_filters( 'blueworx_readonly_action_screens', $screens );
}

/**
 * Action values that only ever read.
 *
 * An allow-list, not a deny-list of known-destructive values: the set of
 * write actions across core and every plugin is open-ended, and a deny-list
 * loses that race by default. Anything not named here is refused.
 *
 * @return array Action values.
 */
function blueworx_readonly_allowed_actions() {
	/** This filter is documented in includes/readonly-access.php */
	$actions = (array) apply_filters(
		'blueworx_support_readonly_actions',
		array(
			// WordPress's own "no action selected" value on a bulk selector.
			'-1',
			// Renders an editor. The save is a separate, POSTed action
			// (editpost / editedcomment / editedtag), which is refused.
			'edit',
			'editcomment',
			'view',
		)
	);

	/**
	 * Filters the action values read-only accounts are allowed to follow.
	 *
	 * @param array $actions Action values.
	 */
	return (array) apply_filters( 'blueworx_readonly_allowed_actions', $actions );
}

/**
 * Denies write actions carried over GET to the support account.
 *
 * The capabilities behind these screens are deliberately retained on the support
 * role: activate_plugins is what gates VIEWING plugins.php, manage_categories
 * gates viewing edit-tags.php, and reading those screens is a primary diagnostic
 * this feature exists to enable. WordPress gates rendering on the same
 * capabilities it gates writes on, so the read cannot be kept by capability
 * alone.
 *
 * The ACTION is therefore blocked here while the read is left intact: on any
 * screen in blueworx_readonly_action_screens(), an action parameter that is not
 * in blueworx_readonly_allowed_actions() is refused. Both "action" and "action2"
 * are checked, because WordPress submits the bottom bulk-action selector under
 * the second name.
 *
 * Reading the request rather than only $_GET is deliberate. The bulk forms on
 * edit.php and upload.php are method="get", but WP_List_Table::current_action()
 * reads $_REQUEST, so an action arriving by either route must be caught.
 *
 * @return void
 */
function blueworx_readonly_gate_write_actions() {
	global $pagenow;

	$user = blueworx_readonly_current_user();

	if ( ! $user ) {
		return;
	}

	if ( ! in_array( (string) $pagenow, blueworx_readonly_action_screens(), true ) ) {
		return;
	}

	$allowed = blueworx_readonly_allowed_actions();

	foreach ( array( 'action', 'action2' ) as $param ) {
		$value = isset( $_REQUEST[ $param ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_REQUEST[ $param ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		if ( '' === $value || in_array( $value, $allowed, true ) ) {
			continue;
		}

		blueworx_readonly_log_event( $user, 'blocked_write' );

		wp_die(
			esc_html__( 'This account is read-only: this action is refused.', 'blueworx-labs-wordpress' ),
			esc_html__( 'Read-only access', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}
}
// Priority 0, for the same reason as blueworx_readonly_gate_data_screens(): this
// denial must win over any other feature's own admin_init handling of the same
// screen, so it cannot escape as a redirect-to-200.
add_action( 'admin_init', 'blueworx_readonly_gate_write_actions', 0 );

/**
 * Whether a REST route addresses the calling account's own user record.
 *
 * /wp/v2/users is denied by prefix, and /wp/v2/users/me starts with it — so the
 * account was refused its OWN record. That protects nobody: the account already
 * knows who it is. It does break wp-admin, which fetches
 * /wp/v2/users/me?context=edit on every page load for block-editor preferences,
 * so a support session saw a console 403 on every screen.
 *
 * Both spellings are matched: "me", and the account's own numeric ID, which is
 * what the block editor uses once it has resolved the current user.
 *
 * @param string $route Route being dispatched.
 * @param mixed  $user  The calling read-only user.
 * @return bool True when the route is the account's own record.
 */
function blueworx_readonly_route_is_own_record( $route, $user ) {
	if ( '/wp/v2/users/me' === $route ) {
		return true;
	}

	if ( ! preg_match( '#^/wp/v2/users/(\d+)$#', $route, $matches ) ) {
		return false;
	}

	return $user instanceof WP_User && (int) $user->ID === (int) $matches[1];
}

/**
 * Denies personal-data REST routes unless data access is open.
 *
 * @param mixed           $result  Pre-dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed Untouched result, or a WP_Error.
 */
function blueworx_readonly_gate_data_routes( $result, $server, $request ) {
	unset( $server );

	$user = blueworx_readonly_current_user();

	if ( ! $user || blueworx_readonly_data_allowed( $user ) ) {
		return $result;
	}

	$route = (string) $request->get_route();

	if ( blueworx_readonly_route_is_own_record( $route, $user ) ) {
		return $result;
	}

	foreach ( blueworx_readonly_denied_routes() as $prefix ) {
		if ( 0 === strpos( $route, $prefix ) ) {
			return new WP_Error(
				'blueworx_support_no_data',
				__( 'This route returns personal data and is not available to this account.', 'blueworx-labs-wordpress' ),
				array( 'status' => 403 )
			);
		}
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'blueworx_readonly_gate_data_routes', 11, 3 );

/**
 * Whether this request is WordPress's Heartbeat poll.
 *
 * Heartbeat POSTs to admin-ajax.php every 60 seconds from any open wp-admin
 * tab, so it meets the write block on a schedule rather than because anybody
 * did anything.
 *
 * Reading the action off the request is not a nonce check and does not need to
 * be: nothing is authorised on the strength of it. It only decides whether a
 * refusal that has already happened is worth a log entry, so the worst a forged
 * value can do is suppress its own record — while still being refused.
 *
 * @return bool True for a Heartbeat request.
 */
function blueworx_readonly_is_heartbeat_request() {
	if ( ! wp_doing_ajax() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing decision; see docblock.
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

	return 'heartbeat' === $action;
}

/**
 * Stops Heartbeat running at all during a read-only session.
 *
 * The write block already refuses the POST, but a refused POST is still a
 * console 403 once a minute in the session's own diagnostic tool. Dropping the
 * script means the request is never made.
 *
 * @return void
 */
function blueworx_readonly_disable_heartbeat() {
	if ( ! blueworx_readonly_current_user() ) {
		return;
	}

	wp_deregister_script( 'heartbeat' );
}
add_action( 'admin_enqueue_scripts', 'blueworx_readonly_disable_heartbeat', 1 );
add_action( 'wp_enqueue_scripts', 'blueworx_readonly_disable_heartbeat', 1 );

/**
 * Whether this request is aimed at an endpoint that exists only to act.
 *
 * admin-post.php is not a screen. It is the generic "do something" handler, and
 * a plugin is free to hang an action off it and reach it with a plain link —
 * this plugin's own Duplicate row action does exactly that. So a read-only
 * account that is refused every POST could still create a post with one click,
 * on a link the admin rendered for it, carrying a nonce minted for its own
 * session. There is no read on that endpoint worth preserving, so the request
 * method is not consulted: the endpoint itself is refused.
 *
 * Doing it here rather than in the feature that happens to own the action is
 * the point. Any plugin that puts a GET action on admin-post.php has the same
 * shape, and support access is covered by the same line.
 *
 * admin-ajax.php is deliberately NOT listed. Ordinary admin screens issue
 * legitimate GET AJAX reads all over wp-admin, and refusing those would break
 * the very looking-round these accounts exist to do. Its writes arrive as POST
 * and the method check already refuses them.
 *
 * There is no second file to name: admin-post.php serves its own
 * no-privilege actions (admin_post_nopriv_*) from the same address, and a
 * read-only account is signed in, so it never reaches that branch anyway.
 *
 * @return bool True when the endpoint acts rather than reads.
 */
function blueworx_readonly_is_action_endpoint() {
	global $pagenow;

	/**
	 * Filters the admin endpoints refused whatever the request method.
	 *
	 * For endpoints that only ever perform an action. Adding a screen a
	 * read-only account has to be able to READ would refuse the read as well.
	 *
	 * @param array $endpoints $pagenow values.
	 */
	$endpoints = (array) apply_filters(
		'blueworx_readonly_action_endpoints',
		array( 'admin-post.php' )
	);

	return in_array( (string) $pagenow, $endpoints, true );
}

/**
 * Rejects every non-read request made by a read-only account.
 *
 * This — not the capability set — is what makes the account read-only.
 * Third-party plugins routinely write through their own AJAX and REST endpoints
 * without checking a meaningful capability, so a rule that depends on plugin
 * authors behaving correctly is not a safety model. A method-level block does
 * not.
 *
 * Known gap, documented in the console: a plugin that writes in response to a
 * GET request on a screen of its own is not caught here. The generic action
 * endpoint is, by blueworx_readonly_is_action_endpoint().
 *
 * @return void
 */
function blueworx_readonly_block_writes() {
	$user = blueworx_readonly_current_user();

	if ( ! $user ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
		: 'GET';

	// A read method is only actually a read when it is not pointed at an
	// endpoint whose entire purpose is to act on the site.
	if ( in_array( $method, array( 'GET', 'HEAD' ), true ) && ! blueworx_readonly_is_action_endpoint() ) {
		return;
	}

	// Still refused — but not recorded. Heartbeat is machine traffic nobody
	// initiated, so logging it documents nothing and costs the log its real
	// entries. Blocking it remains the point; the log is for actions.
	if ( ! blueworx_readonly_is_heartbeat_request() ) {
		blueworx_readonly_log_event( $user, 'blocked_write' );
	}

	wp_die(
		esc_html__( 'This account is read-only. Nothing on this site can be changed from it.', 'blueworx-labs-wordpress' ),
		esc_html__( 'Read-only access', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
add_action( 'init', 'blueworx_readonly_block_writes', 0 );

/**
 * Refuses a read-only account on XML-RPC.
 *
 * xmlrpc.php reads its credentials out of the request body and authenticates
 * long after "init" has fired — so blueworx_readonly_block_writes() has already
 * run against an anonymous user and returned, and there is no XML-RPC
 * equivalent of the rest_pre_dispatch net. wp.newPost, wp.editPost,
 * wp.uploadFile and wp.newTerm all check capabilities these roles keep, which
 * makes the endpoint a complete way round the guard.
 *
 * The support account escapes it by accident: its password is set to a value
 * nothing can hash to, so no body can authenticate as it. An external viewer
 * chooses a real password, so it does not escape, and both roles are covered
 * here rather than relying on that difference.
 *
 * Refused at the door rather than per method. Nothing these accounts need is
 * only reachable over XML-RPC, and an allow-list of "reading" methods would be
 * the same losing race blueworx_readonly_allowed_actions() exists to avoid.
 *
 * This is not made redundant by the plugin's own xmlrpc function, which closes
 * the endpoint outright: that is a switch site owners are told to turn off for
 * Jetpack or the mobile app, and the read-only guarantee must not depend on an
 * unrelated setting being left alone.
 *
 * @param mixed $user User or error resolved so far.
 * @return mixed The user, or a WP_Error.
 */
function blueworx_readonly_block_xmlrpc( $user ) {
	if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
		return $user;
	}

	if ( ! $user instanceof WP_User ) {
		return $user;
	}

	foreach ( blueworx_readonly_roles() as $slug ) {
		if ( ! blueworx_readonly_user_has_role( $user, $slug ) ) {
			continue;
		}

		blueworx_readonly_log_event( $user, 'blocked_write' );

		return new WP_Error(
			'blueworx_readonly_no_xmlrpc',
			__( 'This account is read-only. Nothing on this site can be changed from it.', 'blueworx-labs-wordpress' )
		);
	}

	return $user;
}
// Priority 30: after core's wp_authenticate_username_password() at 20, so there
// is a resolved user object to test rather than raw credentials.
add_filter( 'authenticate', 'blueworx_readonly_block_xmlrpc', 30 );

/**
 * Second net: refuses non-read REST requests from a read-only account.
 *
 * @param mixed           $result  Pre-dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed Untouched result, or a WP_Error for a write.
 */
function blueworx_readonly_block_rest_writes( $result, $server, $request ) {
	unset( $server );

	$user = blueworx_readonly_current_user();

	if ( ! $user ) {
		return $result;
	}

	if ( in_array( strtoupper( $request->get_method() ), array( 'GET', 'HEAD' ), true ) ) {
		return $result;
	}

	blueworx_readonly_log_event( $user, 'blocked_write' );

	return new WP_Error(
		'blueworx_support_read_only',
		__( 'This account is read-only. Nothing on this site can be changed from it.', 'blueworx-labs-wordpress' ),
		array( 'status' => 403 )
	);
}
add_filter( 'rest_pre_dispatch', 'blueworx_readonly_block_rest_writes', 10, 3 );
