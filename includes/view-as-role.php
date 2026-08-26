<?php
/**
 * View the admin as another role.
 *
 * Replaces the Admin and Site Enhancements `view_admin_as_role` module. The
 * other half of that ASE pair — assigning a user more than one role — is
 * already covered by includes/user-roles.php and is not duplicated here.
 *
 * The rule this file is built around: viewing as a role may only ever REMOVE
 * capabilities. It never grants one. The switch is applied by filtering
 * `user_has_cap` down to the target role's own capability map, so a
 * capability the real user does not hold cannot appear no matter what the
 * target role says.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The user meta key holding the role currently being viewed as.
 */
const BLUEWORX_VIEW_AS_META = '_blueworx_view_as_role';

/**
 * What the real person can do, before a preview narrows anything.
 *
 * Read off the user object rather than through current_user_can(), which is
 * exactly what blueworx_view_as_filter_caps() is narrowing. Asking the filtered
 * question would make the control vanish the moment somebody previewed a role
 * that cannot do whatever the control is gated on — the trap that used to force
 * this feature to be administrators-only.
 *
 * @return array Capabilities the real user holds, keyed by name.
 */
function blueworx_view_as_real_caps() {
	$user = wp_get_current_user();

	if ( ! $user || ! $user->exists() ) {
		return array();
	}

	return (array) $user->allcaps;
}

/**
 * Whether this user may use the switch at all.
 *
 * Anyone who edits the site, and never a BlueWorx support agent. A support
 * session is read-only by virtue of a capability set this plugin controls;
 * letting it swap that set for another role's would replace the guarantee with
 * a different one nobody has reviewed.
 *
 * @return bool True when the switch is available.
 */
function blueworx_view_as_available() {
	if ( ! blueworx_feature_enabled( 'view_as_role' ) ) {
		return false;
	}

	if ( function_exists( 'blueworx_support_is_support_user' ) && blueworx_support_is_support_user() ) {
		return false;
	}

	$caps = blueworx_view_as_real_caps();

	return ! empty( $caps['edit_posts'] );
}

/**
 * Gets the roles this user may view as, most capable first.
 *
 * Downwards only. A role is offered when the real user already holds every
 * capability that role grants, so an editor is offered author, contributor and
 * subscriber and never the other way about. That is the same rule the
 * capability filter enforces — this just stops the menu offering something the
 * filter would refuse to honour.
 *
 * Administrator is excluded outright, and so is whatever the user already is:
 * their own level is not a preview, it is the "My own view" option.
 *
 * @return array Role labels keyed by slug, ordered by how much each one can do.
 */
function blueworx_view_as_role_choices() {
	$mine  = blueworx_view_as_real_caps();
	$user  = wp_get_current_user();
	$owned = ( $user && $user->exists() ) ? (array) $user->roles : array();

	$choices = array();
	$weight  = array();

	foreach ( wp_roles()->roles as $slug => $role ) {
		if ( 'administrator' === $slug || in_array( $slug, $owned, true ) ) {
			continue;
		}

		$caps = array_keys( array_filter( (array) $role['capabilities'] ) );

		foreach ( $caps as $cap ) {
			if ( empty( $mine[ $cap ] ) ) {
				continue 2;
			}
		}

		$choices[ $slug ] = translate_user_role( $role['name'] );
		$weight[ $slug ]  = count( $caps );
	}

	// Most capable first, so the menu reads from your own level downwards. Two
	// roles that can do the same amount fall back to their names, or the order
	// would depend on however the site happens to have registered them.
	uksort(
		$choices,
		function ( $a, $b ) use ( $weight, $choices ) {
			if ( $weight[ $a ] === $weight[ $b ] ) {
				return strnatcasecmp( $choices[ $a ], $choices[ $b ] );
			}

			return $weight[ $b ] - $weight[ $a ];
		}
	);

	return $choices;
}

/**
 * Gets the role the current user is viewing as.
 *
 * Answers from stored state alone, and asks no permission question of its own.
 * This is what the `user_has_cap` callback reads, so anything here that went
 * back through current_user_can() would fire that filter again and the two
 * would call each other until PHP ran out of stack. Nothing here needs the
 * check anyway: the capability filter can only ever narrow.
 *
 * @return string Role slug, or an empty string.
 */
function blueworx_view_as_current_role() {
	// No control, no preview. The control lives in the re-skinned sidebar, so
	// switching the admin theme off would otherwise leave somebody narrowed to a
	// role with nothing on screen to undo it and — now that a preview really does
	// take the settings screens away — no way back at all.
	if ( ! function_exists( 'blueworx_admin_theme_enabled' ) || ! blueworx_admin_theme_enabled() ) {
		return '';
	}

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '';
	}

	$role = (string) get_user_meta( $user_id, BLUEWORX_VIEW_AS_META, true );

	if ( '' === $role || 'administrator' === $role || ! get_role( $role ) ) {
		return '';
	}

	return $role;
}

/**
 * Narrows the current user's capabilities to those of the viewed role.
 *
 * Intersection, not replacement: a capability is granted only when BOTH the
 * viewed role and the real user hold it. That is what makes this incapable of
 * escalating — the result is always a subset of what the user could already do.
 *
 * One capability survives the narrowing: `read`, without which wp-admin will
 * not load at all and the preview would be of the login screen. `manage_options`
 * used to survive too, so that an administrator mid-preview could still reach
 * the settings screen the way out lived on. The way out is now the sidebar
 * control, which is present on every admin screen and gated on the real user's
 * capabilities, so the exception has been dropped: previewing a role now hides
 * the settings screens exactly as that role would find them hidden.
 *
 * `read` is only ever CARRIED OVER, never added: if the real user did not hold
 * it, it is not granted here. A leftover meta value on a non-administrator
 * therefore narrows their access and cannot widen it.
 *
 * @param array $allcaps Capabilities the user holds.
 * @return array Filtered capabilities.
 */
function blueworx_view_as_filter_caps( $allcaps ) {
	$role_slug = blueworx_view_as_current_role();

	if ( '' === $role_slug ) {
		return $allcaps;
	}

	$role = get_role( $role_slug );

	if ( ! $role ) {
		return $allcaps;
	}

	$filtered = array();

	foreach ( (array) $allcaps as $cap => $granted ) {
		$filtered[ $cap ] = $granted && ! empty( $role->capabilities[ $cap ] );
	}

	$filtered['read'] = ! empty( $allcaps['read'] );

	return $filtered;
}

/**
 * Applies or clears the switch from a toolbar link.
 *
 * @return void
 */
function blueworx_view_as_handle() {
	blueworx_require_post_request();

	if ( ! blueworx_view_as_available() ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_view_as' );

	$role = isset( $_POST['blueworx_view_as_role'] ) ? sanitize_key( wp_unslash( $_POST['blueworx_view_as_role'] ) ) : '';

	if ( '' === $role || ! isset( blueworx_view_as_role_choices()[ $role ] ) ) {
		delete_user_meta( get_current_user_id(), BLUEWORX_VIEW_AS_META );

		// Leaving a preview gives every capability back, so the screen they were
		// on is theirs to return to.
		$referer = wp_get_referer();

		wp_safe_redirect( $referer ? $referer : admin_url() );
		exit;
	}

	update_user_meta( get_current_user_id(), BLUEWORX_VIEW_AS_META, $role );

	// Entering one goes to the dashboard rather than back where they were. The
	// screen they started from is very often one the previewed role cannot open,
	// and WordPress answers that with a bare permissions notice — no sidebar, and
	// so no way back out of the preview.
	wp_safe_redirect( admin_url() );
	exit;
}

/**
 * The role switcher for the sidebar.
 *
 * A single control that both says where you are and takes you somewhere else:
 * a button reading "My own view" or "Viewing as <Role>", opening a list of the
 * roles you may preview. It replaces two separate things — a pill in the top
 * bar that only appeared once you were already inside a role, and a bar pinned
 * across the bottom of the screen that was the only way in. One of them told
 * you nothing until it was too late to matter, and the other covered the save
 * bar of whatever screen it landed on.
 *
 * Every option is a submit button carrying its own role, so choosing one posts
 * without any script involved. The script only opens and closes the panel.
 *
 * @return string HTML, or an empty string when this user has no switch.
 */
function blueworx_view_as_sidebar_control() {
	if ( ! blueworx_view_as_available() ) {
		return '';
	}

	$choices = blueworx_view_as_role_choices();

	if ( empty( $choices ) ) {
		return '';
	}

	$current = blueworx_view_as_current_role();
	$active  = isset( $choices[ $current ] );

	$label = $active
		? sprintf(
			/* translators: %s: role name. */
			__( 'Viewing as %s', 'blueworx-labs-wordpress' ),
			$choices[ $current ]
		)
		: __( 'My own view', 'blueworx-labs-wordpress' );

	// "My own view" first, then the roles this user may drop down to. Leaving a
	// preview is choosing your own view again — there is no separate way out to
	// go looking for.
	$options = array( '' => __( 'My own view', 'blueworx-labs-wordpress' ) ) + $choices;
	$items   = '';

	foreach ( $options as $slug => $name ) {
		$selected = ( '' === $slug ) ? ! $active : ( $active && $slug === $current );

		$items .= sprintf(
			'<button type="submit" class="bw-viewas__option%1$s" name="blueworx_view_as_role" value="%2$s" role="menuitemradio" aria-checked="%3$s">%4$s</button>',
			$selected ? ' is-selected' : '',
			esc_attr( $slug ),
			$selected ? 'true' : 'false',
			esc_html( $name )
		);
	}

	// The panel sits after the button in the markup and above it on screen, so
	// tabbing out of the button lands in the list rather than past it.
	return sprintf(
		'<form class="bw-viewas" method="post" action="%1$s">%2$s'
			. '<input type="hidden" name="action" value="blueworx_view_as" />'
			. '<div class="bw-viewas__wrap">'
			. '<button type="button" class="bw-viewas__trigger%3$s" aria-haspopup="menu" aria-expanded="false" aria-controls="bw-viewas-menu" data-blueworx-viewas-trigger>'
			. '%4$s<span class="bw-viewas__label">%5$s</span>%6$s'
			. '</button>'
			. '<div class="bw-viewas__menu" id="bw-viewas-menu" role="menu" aria-label="%7$s" hidden>%8$s</div>'
			. '</div></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_view_as', '_wpnonce', true, false ),
		$active ? ' is-active' : '',
		blueworx_ds_icon( 'eye', 18 ),
		esc_html( $label ),
		blueworx_ds_icon( 'chevron-down', 14, 'bw-viewas__chev' ),
		esc_attr__( 'View the admin as another role', 'blueworx-labs-wordpress' ),
		$items
	);
}

/**
 * Puts the switcher in the sidebar, above the Log Out row.
 *
 * Injected the same way that row is, and for the same reason: WordPress builds
 * the menu itself and offers nowhere to append to it. Printed only with the
 * admin theme on, because the block it belongs to — the dark column, the rule
 * above Log Out — is the theme's.
 *
 * @return void
 */
function blueworx_view_as_print_sidebar_control() {
	$markup = blueworx_view_as_sidebar_control();

	if ( '' === $markup ) {
		return;
	}
	?>
	<script>
		( function () {
			var menu = document.getElementById( 'adminmenu' );

			if ( ! menu || menu.querySelector( '.bw-viewas' ) ) {
				return;
			}

			var item = document.createElement( 'li' );
			item.className = 'bw-viewasrow';
			item.innerHTML = <?php echo wp_json_encode( $markup ); ?>;

			var logout = menu.querySelector( '.bw-logout' );

			if ( logout ) {
				menu.insertBefore( item, logout );
			} else {
				menu.appendChild( item );
			}
		}() );
	</script>
	<?php
}

if ( blueworx_feature_enabled( 'view_as_role' ) ) {
	add_filter( 'user_has_cap', 'blueworx_view_as_filter_caps' );
	add_action( 'admin_post_blueworx_view_as', 'blueworx_view_as_handle' );

	if ( function_exists( 'blueworx_admin_theme_enabled' ) && blueworx_admin_theme_enabled() ) {
		// After the Log Out row, which is appended at the default priority: this
		// script looks that row up so it can insert itself above it.
		add_action( 'admin_footer', 'blueworx_view_as_print_sidebar_control', 11 );
	}
}
