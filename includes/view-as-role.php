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
 * Whether this user may use the switch at all.
 *
 * Administrators only, and never a BlueWorx support agent. A support session is
 * read-only by virtue of a capability set this plugin controls; letting it
 * swap that set for another role's would replace the guarantee with a different
 * one nobody has reviewed.
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

	/*
	 * manage_options alone, and nothing narrower. Whatever is tested here must be
	 * a capability that SURVIVES the switch, or the bar carrying the way out would
	 * disappear the moment somebody used it — and manage_options is exactly the
	 * one blueworx_view_as_filter_caps() carries over for that reason.
	 */
	return current_user_can( 'manage_options' );
}

/**
 * Gets the roles that can be viewed as.
 *
 * Administrator is excluded: it is what the person doing this already is, so
 * the option would do nothing, and offering it invites the reading that this is
 * a way to BECOME an administrator.
 *
 * @return array Role labels keyed by slug.
 */
function blueworx_view_as_role_choices() {
	$choices = array();

	foreach ( wp_roles()->roles as $slug => $role ) {
		if ( 'administrator' === $slug ) {
			continue;
		}

		$choices[ $slug ] = translate_user_role( $role['name'] );
	}

	natcasesort( $choices );

	return $choices;
}

/**
 * Gets the role the current user is viewing as.
 *
 * Deliberately does NOT call blueworx_view_as_available(). That function asks
 * current_user_can(), current_user_can() fires `user_has_cap`, and this is what
 * the `user_has_cap` callback reads — the three would call each other until PHP
 * ran out of stack. It answers from stored state alone; nothing here needs a
 * permission check, because the capability filter can only ever narrow.
 *
 * @return string Role slug, or an empty string.
 */
function blueworx_view_as_current_role() {
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
 * Two capabilities survive the narrowing — `read`, so wp-admin loads at all,
 * and `manage_options`, so the control that ends the session stays reachable.
 * Both are only ever CARRIED OVER, never added: if the real user did not hold
 * them, they are not granted here. A leftover meta value on a non-administrator
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

	foreach ( array( 'read', 'manage_options' ) as $keep ) {
		$filtered[ $keep ] = ! empty( $allcaps[ $keep ] );
	}

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
	} else {
		update_user_meta( get_current_user_id(), BLUEWORX_VIEW_AS_META, $role );
	}

	$referer = wp_get_referer();

	wp_safe_redirect( $referer ? $referer : admin_url() );
	exit;
}

/**
 * Renders the switch, and the way out of it, in the admin footer.
 *
 * A bar rather than a toolbar node: the point of the feature is that you can
 * always tell you are in it and always get out in one click, and the toolbar is
 * one of the things a viewed role may not be able to see.
 *
 * @return void
 */
function blueworx_view_as_render_bar() {
	if ( ! blueworx_view_as_available() ) {
		return;
	}

	$current = blueworx_view_as_current_role();
	$choices = blueworx_view_as_role_choices();
	?>
	<div class="blueworx-view-as<?php echo '' === $current ? '' : ' is-active'; ?>">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_view_as" />
			<?php wp_nonce_field( 'blueworx_view_as' ); ?>
			<?php if ( '' === $current ) : ?>
				<label for="blueworx_view_as_role"><?php esc_html_e( 'View the admin as:', 'blueworx-labs-wordpress' ); ?></label>
				<select id="blueworx_view_as_role" name="blueworx_view_as_role">
					<option value=""><?php esc_html_e( 'Yourself', 'blueworx-labs-wordpress' ); ?></option>
					<?php foreach ( $choices as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-small"><?php esc_html_e( 'Switch', 'blueworx-labs-wordpress' ); ?></button>
			<?php else : ?>
				<strong>
					<?php
					printf(
						/* translators: %s: role name. */
						esc_html__( 'You are viewing this site as: %s', 'blueworx-labs-wordpress' ),
						esc_html( $choices[ $current ] )
					);
					?>
				</strong>
				<input type="hidden" name="blueworx_view_as_role" value="" />
				<button type="submit" class="button button-small"><?php esc_html_e( 'Back to yourself', 'blueworx-labs-wordpress' ); ?></button>
			<?php endif; ?>
		</form>
	</div>
	<style>
		.blueworx-view-as{position:fixed;left:0;right:0;bottom:0;z-index:99999;display:flex;gap:8px;align-items:center;
			padding:8px 16px;background:#1d2327;color:#fff;font-size:13px;}
		.blueworx-view-as.is-active{background:#8a2b06;}
		.blueworx-view-as form{display:flex;gap:8px;align-items:center;margin:0;}
	</style>
	<?php
}

if ( blueworx_feature_enabled( 'view_as_role' ) ) {
	add_filter( 'user_has_cap', 'blueworx_view_as_filter_caps' );
	add_action( 'admin_post_blueworx_view_as', 'blueworx_view_as_handle' );
	add_action( 'admin_footer', 'blueworx_view_as_render_bar' );
}
