<?php
/**
 * My Sites menu for the BlueWorx top bar.
 *
 * The top bar hides WordPress's own toolbar on desktop (see admin-theme.php),
 * and on a multisite network that toolbar is where My Sites and Network Admin
 * live. This puts them back in the top bar, so people can still move between
 * sites. Single sites never see it.
 *
 * @package BlueWorxLabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The links the My Sites menu shows a user.
 *
 * Matches core's toolbar: shown to a super admin, or to anyone on more than one
 * site. A super admin gets Network Admin first.
 *
 * @param int $user_id User ID.
 * @return array[] Each has label, url and current. Empty when there is no menu.
 */
function blueworx_topbar_sites_links( $user_id ) {
	if ( ! is_multisite() ) {
		return array();
	}

	$blogs       = get_blogs_of_user( $user_id );
	$super_admin = is_super_admin( $user_id );

	if ( ! $super_admin && count( $blogs ) < 2 ) {
		return array();
	}

	$links   = array();
	$network = is_network_admin();

	if ( $super_admin ) {
		$links[] = array(
			'label'   => __( 'Network Admin', 'blueworx-labs-wordpress' ),
			'url'     => network_admin_url(),
			'current' => $network,
		);
	}

	foreach ( $blogs as $blog ) {
		$name = trim( (string) $blog->blogname );

		$links[] = array(
			'label'   => '' !== $name ? $name : untrailingslashit( $blog->domain . $blog->path ),
			'url'     => get_admin_url( (int) $blog->userblog_id ),
			'current' => ! $network && get_current_blog_id() === (int) $blog->userblog_id,
		);
	}

	return $links;
}

/**
 * Prints the My Sites menu, when the current user has one.
 *
 * A native <details> disclosure, like the user menu beside it, so it needs no
 * JavaScript.
 *
 * @return void
 */
function blueworx_render_topbar_sites() {
	$links = blueworx_topbar_sites_links( get_current_user_id() );

	if ( empty( $links ) ) {
		return;
	}
	?>
	<details class="blueworx-sites">
		<summary class="blueworx-sites__summary">
			<?php echo wp_kses( blueworx_ds_icon( 'globe', 14 ), blueworx_ds_allowed_html() ); ?>
			<span class="blueworx-sites__label"><?php esc_html_e( 'My Sites', 'blueworx-labs-wordpress' ); ?></span>
			<?php echo wp_kses( blueworx_ds_icon( 'chevron-down', 14 ), blueworx_ds_allowed_html() ); ?>
		</summary>
		<div class="blueworx-sites__menu">
			<?php foreach ( $links as $link ) : ?>
				<a class="blueworx-sites__link" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $link['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	</details>
	<?php
}
