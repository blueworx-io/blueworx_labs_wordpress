<?php
/**
 * The Merge users screen.
 *
 * A Merge link on each row of the Users list opens it. The first step picks the
 * other account and which of the two to keep; the second shows everything the
 * merge would change and asks once. The work itself is merge.php's.
 *
 * Only administrators get any of it, and never a read-only account: the link,
 * the screen and the handler each check on their own, so hiding the link is
 * never the only thing in the way.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current person may merge accounts.
 *
 * @return bool True when the function is on and they are a full administrator.
 */
function blueworx_merge_users_allowed() {
	return blueworx_feature_enabled( 'merge_users' )
		&& ! is_multisite()
		&& current_user_can( 'delete_users' )
		&& current_user_can( 'edit_users' )
		&& null === blueworx_readonly_current_user();
}

/**
 * The screen's address.
 *
 * @param array $args Query arguments to add.
 * @return string URL.
 */
function blueworx_merge_users_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=blueworx-merge-users' ) );
}

/**
 * Registers the screen, reachable from the Users list but not listed in the menu.
 *
 * @return void
 */
function blueworx_merge_users_register_page() {
	add_submenu_page(
		null,
		esc_html__( 'Merge users', 'blueworx-labs-wordpress' ),
		esc_html__( 'Merge users', 'blueworx-labs-wordpress' ),
		'delete_users',
		'blueworx-merge-users',
		'blueworx_merge_users_render_page'
	);
}

/**
 * Adds a Merge link to each row of the Users list.
 *
 * @param array   $actions Row action links.
 * @param WP_User $user    The user the row is for.
 * @return array Links, with Merge added for anyone allowed to use it.
 */
function blueworx_merge_users_row_action( $actions, $user ) {
	if ( ! $user instanceof WP_User || ! blueworx_merge_users_allowed() ) {
		return $actions;
	}

	$actions['blueworx_merge'] = sprintf(
		'<a href="%1$s" aria-label="%2$s">%3$s</a>',
		esc_url( blueworx_merge_users_url( array( 'user' => $user->ID ) ) ),
		/* translators: %s: username. */
		esc_attr( sprintf( __( 'Merge %s with another account', 'blueworx-labs-wordpress' ), $user->user_login ) ),
		esc_html__( 'Merge', 'blueworx-labs-wordpress' )
	);

	return $actions;
}

/**
 * Keeps a message for the next page this person sees.
 *
 * @param array $notice blueworx_ds_notice() arguments.
 * @return void
 */
function blueworx_merge_users_set_notice( $notice ) {
	set_transient( 'blueworx_merge_notice_' . get_current_user_id(), $notice, 60 );
}

/**
 * Reads and clears the pending message.
 *
 * @return array Notice arguments, or an empty array.
 */
function blueworx_merge_users_take_notice() {
	$key    = 'blueworx_merge_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	delete_transient( $key );

	return is_array( $notice ) ? $notice : array();
}

/**
 * Names an account the way the screen shows it.
 *
 * @param WP_User $user Account.
 * @return string "username (email)".
 */
function blueworx_merge_users_label( $user ) {
	return sprintf( '%1$s (%2$s)', $user->user_login, $user->user_email );
}

/**
 * Names a profile field in plain words, where WordPress has a name for it.
 *
 * @param string $key Meta key.
 * @return string Label, or the key itself for anything a plugin added.
 */
function blueworx_merge_users_field_label( $key ) {
	$labels = array(
		'first_name'  => __( 'First name', 'blueworx-labs-wordpress' ),
		'last_name'   => __( 'Last name', 'blueworx-labs-wordpress' ),
		'nickname'    => __( 'Nickname', 'blueworx-labs-wordpress' ),
		'description' => __( 'Biographical info', 'blueworx-labs-wordpress' ),
		'phone'       => __( 'Phone', 'blueworx-labs-wordpress' ),
	);

	if ( isset( $labels[ $key ] ) ) {
		return $labels[ $key ];
	}

	// LearnDash keeps groups and course access as one key per group or course.
	$patterns = array(
		/* translators: %s: group name. */
		'/^learndash_group_users_(\d+)$/'    => __( 'LearnDash group: %s', 'blueworx-labs-wordpress' ),
		/* translators: %s: group name. */
		'/^learndash_group_leaders_(\d+)$/'  => __( 'LearnDash group leader: %s', 'blueworx-labs-wordpress' ),
		/* translators: %s: course name. */
		'/^course_(\d+)_access_from$/'       => __( 'Course access: %s', 'blueworx-labs-wordpress' ),
		/* translators: %s: course name. */
		'/^course_completed_(\d+)$/'         => __( 'Course completed: %s', 'blueworx-labs-wordpress' ),
		/* translators: %s: course name. */
		'/^learndash_course_expired_(\d+)$/' => __( 'Course access ended: %s', 'blueworx-labs-wordpress' ),
	);

	foreach ( $patterns as $pattern => $label ) {
		if ( preg_match( $pattern, $key, $match ) ) {
			return sprintf( $label, blueworx_merge_users_course_label( (int) $match[1] ) );
		}
	}

	return $key;
}

/**
 * Names a LearnDash course or group by its title.
 *
 * @param int $course_id Course or group ID.
 * @return string Title, or the ID when it has gone.
 */
function blueworx_merge_users_course_label( $course_id ) {
	$title = get_the_title( $course_id );

	return '' !== $title ? $title : '#' . $course_id;
}

/**
 * Renders the screen: the chooser, the preview, or the result.
 *
 * @return void
 */
function blueworx_merge_users_render_page() {
	if ( ! blueworx_merge_users_allowed() ) {
		wp_die( esc_html__( 'You do not have permission to merge accounts.', 'blueworx-labs-wordpress' ), 403 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen routing; the merge itself is a nonce-checked POST.
	$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
	$other   = isset( $_GET['other'] ) ? absint( $_GET['other'] ) : 0;
	$keep    = isset( $_GET['keep'] ) ? sanitize_key( wp_unslash( $_GET['keep'] ) ) : '';
	$merged  = isset( $_GET['merged'] ) ? absint( $_GET['merged'] ) : 0;
	$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	blueworx_open_admin_page(
		array(
			'eyebrow' => __( 'Users', 'blueworx-labs-wordpress' ),
			'title'   => __( 'Merge users', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Turn two accounts for the same person into one. Everything moves to the account you keep, and the other one is deleted.', 'blueworx-labs-wordpress' ),
		)
	);

	$notice = blueworx_merge_users_take_notice();
	if ( $notice ) {
		echo wp_kses( blueworx_ds_notice( $notice ), blueworx_ds_allowed_html() );
	}

	if ( $merged ) {
		blueworx_merge_users_render_done( $merged );
	} elseif ( ! get_userdata( $user_id ) ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'warning',
					'text' => __( 'Start from the Merge link on a row of the Users list.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	} elseif ( $other && in_array( $keep, array( 'this', 'other' ), true ) ) {
		$kept  = 'this' === $keep ? $user_id : $other;
		$spare = 'this' === $keep ? $other : $user_id;
		blueworx_merge_users_render_preview( $user_id, $kept, $spare, $search );
	} else {
		blueworx_merge_users_render_chooser( $user_id, $search, $other );
	}

	blueworx_close_admin_page();
}

/**
 * Step one: find the other account, then pick which of the two to keep.
 *
 * The search runs as you type, and the side-by-side view opens in place, through
 * merge-users.js, swapping in the same HTML the functions below draw. Without the
 * script the search box is still a plain form and each result a plain link, so
 * nothing here depends on it.
 *
 * @param int    $user_id Account the person started from.
 * @param string $search  Search text.
 * @param int    $other   Account already picked, if any.
 * @return void
 */
function blueworx_merge_users_render_chooser( $user_id, $search, $other ) {
	$user = get_userdata( $user_id );

	$body = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="bw-fields bw-fields--single" role="search" data-blueworx-merge-search>'
		. '<input type="hidden" name="page" value="blueworx-merge-users" />'
		. '<input type="hidden" name="user" value="' . esc_attr( (string) $user_id ) . '" />'
		. blueworx_ds_field(
			array(
				'label'   => __( 'Find the other account', 'blueworx-labs-wordpress' ),
				'for'     => 'blueworx_merge_users_q',
				'control' => '<div class="bw-inputwrap">' . blueworx_ds_icon( 'search', 16, 'bw-inputwrap__icon' ) . blueworx_ds_input(
					array(
						'type'  => 'search',
						'name'  => 'q',
						'id'    => 'blueworx_merge_users_q',
						'value' => $search,
						'attrs' => array(
							'placeholder'   => __( 'Username, email or name', 'blueworx-labs-wordpress' ),
							'autocomplete'  => 'off',
							'aria-controls' => 'blueworx-merge-users-results',
						),
					)
				) . '</div>',
			)
		)
		// Inside the form only so the field grid spaces it from the box. It
		// holds links, never fields, so nothing in it is ever submitted.
		. '<div id="blueworx-merge-users-results" class="blueworx-merge-users__results" aria-live="polite">'
		. blueworx_merge_users_results( $user_id, $search, $other )
		. '</div>'
		. '</form>';

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'eyebrow' => __( 'Step 1 of 2', 'blueworx-labs-wordpress' ),
			/* translators: %s: username and email. */
			'title'   => sprintf( __( 'Merge %s with…', 'blueworx-labs-wordpress' ), blueworx_merge_users_label( $user ) ),
			'body'    => $body,
			'class'   => 'blueworx-merge-users__choose',
		)
	);

	echo '<div id="blueworx-merge-users-compare" class="blueworx-merge-users__compare">'
		. ( $other ? blueworx_merge_users_compare( $user_id, $other, $search ) : '' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the design system helpers, which escape everything they emit.
		. '</div>';
}

/**
 * The accounts matching a search, each with a way to compare it.
 *
 * @param int    $user_id Account the person started from.
 * @param string $search  Search text.
 * @param int    $other   Account already picked, if any.
 * @return string HTML.
 */
function blueworx_merge_users_results( $user_id, $search, $other ) {
	if ( '' === $search ) {
		return '<p class="bw-field__help">' . esc_html__( 'Start typing and matching accounts show up here.', 'blueworx-labs-wordpress' ) . '</p>';
	}

	$limit = 20;
	$found = get_users(
		array(
			'exclude'        => array( $user_id ),
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'orderby'        => 'login',
			// One more than is shown, to know whether to say there are more.
			'number'         => $limit + 1,
		)
	);

	if ( ! $found ) {
		return blueworx_ds_notice(
			array(
				'tone' => 'info',
				'text' => __( 'No other accounts match that search.', 'blueworx-labs-wordpress' ),
			)
		);
	}

	$rows = '';
	foreach ( array_slice( $found, 0, $limit ) as $row ) {
		$picked = (int) $other === (int) $row->ID;
		$action = $picked
			? blueworx_ds_badge( __( 'Picked', 'blueworx-labs-wordpress' ), 'accent', false, 'check' )
			: blueworx_ds_button(
				array(
					'label' => __( 'Compare', 'blueworx-labs-wordpress' ),
					'size'  => 'sm',
					'href'  => blueworx_merge_users_url(
						array(
							'user'  => $user_id,
							'other' => $row->ID,
							'q'     => $search,
						)
					),
					'attrs' => array(
						'data-blueworx-merge-other' => (string) $row->ID,
						/* translators: %s: username. */
						'aria-label'                => sprintf( __( 'Compare with %s', 'blueworx-labs-wordpress' ), $row->user_login ),
					),
				)
			);

		$rows .= '<tr' . ( $picked ? ' aria-current="true"' : '' ) . '>'
			. '<td><span class="bw-table__primary">' . esc_html( $row->user_login ) . '</span>'
			. '<span class="bw-table__sub">' . esc_html( $row->display_name . ' · ' . $row->user_email ) . '</span></td>'
			. '<td>' . esc_html( blueworx_merge_users_roles_label( $row ) ) . '</td>'
			. '<td>' . esc_html( blueworx_merge_users_joined( $row ) ) . '</td>'
			. '<td>' . $action . '</td>'
			. '</tr>';
	}

	$html = '<div class="bw-tablescroll"><table class="bw-table"><thead><tr>'
		. '<th scope="col">' . esc_html__( 'Account', 'blueworx-labs-wordpress' ) . '</th>'
		. '<th scope="col">' . esc_html__( 'Role', 'blueworx-labs-wordpress' ) . '</th>'
		. '<th scope="col">' . esc_html__( 'Joined', 'blueworx-labs-wordpress' ) . '</th>'
		. '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Action', 'blueworx-labs-wordpress' ) . '</span></th>'
		. '</tr></thead><tbody>' . $rows . '</tbody></table></div>';

	if ( count( $found ) > $limit ) {
		/* translators: %d: number of accounts shown. */
		$html .= '<p class="bw-field__help">' . esc_html( sprintf( __( 'Showing the first %d. Keep typing to narrow it down.', 'blueworx-labs-wordpress' ), $limit ) ) . '</p>';
	}

	return $html;
}

/**
 * The two accounts side by side, each with the button that keeps it.
 *
 * @param int    $user_id  Account the person started from.
 * @param int    $other_id Account they picked.
 * @param string $search   Search text, carried on so Back finds the same list.
 * @return string HTML.
 */
function blueworx_merge_users_compare( $user_id, $other_id, $search ) {
	$user  = get_userdata( $user_id );
	$other = get_userdata( $other_id );

	if ( ! $user || ! $other || (int) $user->ID === (int) $other->ID ) {
		return blueworx_ds_notice(
			array(
				'tone' => 'warning',
				'text' => __( 'That account no longer exists. Search for it again.', 'blueworx-labs-wordpress' ),
			)
		);
	}

	$facts = array(
		'this'  => blueworx_merge_users_facts( $user ),
		'other' => blueworx_merge_users_facts( $other ),
	);

	// A row only earns its place when either account has something in it, so a
	// site without LearnDash or SureCart is not shown two rows of "None".
	$optional = array();
	foreach ( array( 'courses', 'surecart' ) as $key ) {
		if ( $facts['this'][ $key ] || $facts['other'][ $key ] ) {
			$optional[] = $key;
		}
	}

	$cards = blueworx_merge_users_account_card( $user, 'this', __( 'Started here', 'blueworx-labs-wordpress' ), $facts['this'], $optional, $user_id, $other->ID, $search )
		. blueworx_merge_users_account_card( $other, 'other', __( 'Picked', 'blueworx-labs-wordpress' ), $facts['other'], $optional, $user_id, $other->ID, $search );

	return blueworx_ds_card(
		array(
			'title' => __( 'Which one do you want to keep?', 'blueworx-labs-wordpress' ),
			'note'  => __( 'Everything moves to the one you keep, and the other is deleted at the end. You see exactly what will change before anything happens.', 'blueworx-labs-wordpress' ),
			'body'  => '<div class="bw-fields">' . $cards . '</div>',
			'class' => 'blueworx-merge-users__pair',
			'attrs' => array( 'tabindex' => '-1' ),
		)
	);
}

/**
 * One side of the comparison.
 *
 * Drawn by hand rather than with blueworx_ds_card(), which always makes an h2:
 * these sit inside a card of their own, so their titles are h3.
 *
 * @param WP_User $account  Account this card is for.
 * @param string  $keep     'this' or 'other', as the next step reads it.
 * @param string  $eyebrow  Which of the two it is.
 * @param array   $facts    From blueworx_merge_users_facts().
 * @param array   $optional Optional rows to show.
 * @param int     $user_id  Account the person started from.
 * @param int     $other_id Account they picked.
 * @param string  $search   Search text.
 * @return string HTML.
 */
function blueworx_merge_users_account_card( $account, $keep, $eyebrow, $facts, $optional, $user_id, $other_id, $search ) {
	$none = __( 'None', 'blueworx-labs-wordpress' );
	$rows = array(
		__( 'Name', 'blueworx-labs-wordpress' )            => esc_html( $account->display_name ),
		__( 'Email', 'blueworx-labs-wordpress' )           => esc_html( $account->user_email ),
		__( 'Role', 'blueworx-labs-wordpress' )            => esc_html( blueworx_merge_users_roles_label( $account ) ),
		__( 'Joined', 'blueworx-labs-wordpress' )          => esc_html( blueworx_merge_users_joined( $account ) ),
		__( 'Posts and pages', 'blueworx-labs-wordpress' ) => esc_html( number_format_i18n( $facts['posts'] ) ),
		__( 'Comments', 'blueworx-labs-wordpress' )        => esc_html( number_format_i18n( $facts['comments'] ) ),
	);

	if ( in_array( 'courses', $optional, true ) ) {
		$rows[ __( 'LearnDash courses', 'blueworx-labs-wordpress' ) ] = esc_html( number_format_i18n( $facts['courses'] ) );
	}

	if ( in_array( 'surecart', $optional, true ) ) {
		$rows[ __( 'SureCart customer', 'blueworx-labs-wordpress' ) ] = esc_html( $facts['surecart'] ? implode( ', ', $facts['surecart'] ) : $none );
	}

	$title_id = 'blueworx-merge-users-' . $keep;

	$form = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">'
		. '<input type="hidden" name="page" value="blueworx-merge-users" />'
		. '<input type="hidden" name="user" value="' . esc_attr( (string) $user_id ) . '" />'
		. '<input type="hidden" name="other" value="' . esc_attr( (string) $other_id ) . '" />'
		. '<input type="hidden" name="keep" value="' . esc_attr( $keep ) . '" />'
		. ( '' !== $search ? '<input type="hidden" name="q" value="' . esc_attr( $search ) . '" />' : '' )
		. blueworx_ds_button(
			array(
				'label'   => __( 'Keep this one', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
				'icon'    => 'check',
				'attrs'   => array(
					/* translators: %s: username. */
					'aria-label' => sprintf( __( 'Keep this one, %s', 'blueworx-labs-wordpress' ), $account->user_login ),
				),
			)
		)
		. '</form>';

	return '<section class="bw-card bw-card--sunken" aria-labelledby="' . esc_attr( $title_id ) . '">'
		. '<div class="bw-card__head"><div class="bw-card__titles">'
		. '<p class="bw-card__eyebrow">' . esc_html( $eyebrow ) . '</p>'
		. '<h3 class="bw-card__title" id="' . esc_attr( $title_id ) . '">' . esc_html( $account->user_login ) . '</h3>'
		. '</div></div>'
		. '<div class="bw-card__body">' . blueworx_ds_description_list( $rows ) . '</div>'
		. '<div class="bw-card__foot">' . $form . '</div>'
		. '</section>';
}

/**
 * What an account holds, counted for the comparison.
 *
 * @param WP_User $account Account.
 * @return array 'posts', 'comments', 'courses' (counts) and 'surecart' (IDs).
 */
function blueworx_merge_users_facts( $account ) {
	global $wpdb;

	$courses  = get_user_meta( $account->ID, '_sfwd-course_progress', true );
	$enrolled = is_array( $courses ) ? array_map( 'intval', array_keys( $courses ) ) : array();

	// Access to a course the person never opened is a key of its own, with no
	// progress record behind it.
	foreach ( array_keys( get_user_meta( $account->ID ) ) as $key ) {
		if ( preg_match( '/^course_(\d+)_access_from$/', $key, $match ) ) {
			$enrolled[] = (int) $match[1];
		}
	}

	return array(
		'posts'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type <> 'revision'", $account->ID ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The same count the merge itself moves; no API gives one across every post type.
		'comments' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d", $account->ID ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Same.
		'courses'  => count( array_unique( $enrolled ) ),
		'surecart' => array_values( blueworx_merge_users_surecart_ids( $account->ID ) ),
	);
}

/**
 * An account's roles, by the names WordPress shows for them.
 *
 * @param WP_User $account Account.
 * @return string Comma-separated role names, or "None".
 */
function blueworx_merge_users_roles_label( $account ) {
	$names = wp_roles()->get_names();
	$roles = array();

	foreach ( (array) $account->roles as $role ) {
		$roles[] = isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
	}

	return $roles ? implode( ', ', $roles ) : __( 'None', 'blueworx-labs-wordpress' );
}

/**
 * When an account was made, in the site's date format.
 *
 * @param WP_User $account Account.
 * @return string Date.
 */
function blueworx_merge_users_joined( $account ) {
	return date_i18n( get_option( 'date_format' ), strtotime( $account->user_registered ) );
}

/**
 * Answers the screen's background requests: a search, or the comparison.
 *
 * Read-only, so a GET, but still behind the same checks as the screen and a
 * nonce of its own, so another site cannot make an admin's browser list users.
 *
 * @return void
 */
function blueworx_merge_users_ajax() {
	if ( ! blueworx_merge_users_allowed() ) {
		wp_send_json_error( null, 403 );
	}

	check_ajax_referer( 'blueworx_merge_users_search', 'nonce' );

	$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
	$other   = isset( $_GET['other'] ) ? absint( $_GET['other'] ) : 0;
	$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$part    = isset( $_GET['part'] ) ? sanitize_key( wp_unslash( $_GET['part'] ) ) : '';

	if ( ! get_userdata( $user_id ) ) {
		wp_send_json_error( null, 404 );
	}

	wp_send_json_success(
		array(
			'html' => 'compare' === $part
				? blueworx_merge_users_compare( $user_id, $other, $search )
				: blueworx_merge_users_results( $user_id, $search, $other ),
		)
	);
}

/**
 * Loads the search-as-you-type script on the screen.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function blueworx_merge_users_enqueue( $hook_suffix ) {
	if ( 'admin_page_blueworx-merge-users' !== $hook_suffix || ! blueworx_merge_users_allowed() ) {
		return;
	}

	wp_enqueue_script(
		'blueworx-merge-users',
		BLUEWORX_LABS_URL . 'assets/js/merge-users.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/merge-users.js' ),
		true
	);

	wp_localize_script(
		'blueworx-merge-users',
		'blueworxMergeUsers',
		array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'blueworx_merge_users_search' ),
			'failed' => __( 'The search did not load. Press Enter to search the slower way.', 'blueworx-labs-wordpress' ),
		)
	);
}

/**
 * Step two: what the merge would change, and the one button that does it.
 *
 * @param int    $user_id  Account the person started from, for the way back.
 * @param int    $kept_id  Account to keep.
 * @param int    $spare_id Account to delete.
 * @param string $search   Search text, so Back returns to the same list.
 * @return void
 */
function blueworx_merge_users_render_preview( $user_id, $kept_id, $spare_id, $search = '' ) {
	$back  = blueworx_ds_button(
		array(
			'label' => __( 'Back', 'blueworx-labs-wordpress' ),
			'href'  => blueworx_merge_users_url(
				array_filter(
					array(
						'user'  => $user_id,
						'other' => $user_id === $kept_id ? $spare_id : $kept_id,
						'q'     => $search,
					)
				)
			),
			'icon'  => 'arrow-left',
		)
	);
	$check = blueworx_merge_users_check( $kept_id, $spare_id, get_current_user_id() );

	if ( is_wp_error( $check ) ) {
		$actions = $back;
		$data    = $check->get_error_data();

		// A clash is sorted out in SureCart, so open each customer from here.
		if ( is_array( $data ) && ! empty( $data['clashes'] ) ) {
			foreach ( $data['clashes'] as $pair ) {
				foreach ( $pair as $customer ) {
					$actions .= ' ' . blueworx_ds_button(
						array(
							/* translators: %s: SureCart customer ID. */
							'label' => sprintf( __( 'Open %s in SureCart', 'blueworx-labs-wordpress' ), $customer ),
							'href'  => admin_url( 'admin.php?page=sc-customers&action=edit&id=' . rawurlencode( $customer ) ),
							'icon'  => 'external-link',
						)
					);
				}
			}
		}

		echo blueworx_ds_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
			array(
				'tone'    => 'danger',
				'title'   => __( 'These accounts cannot be merged.', 'blueworx-labs-wordpress' ),
				'text'    => $check->get_error_message(),
				'actions' => $actions,
			)
		);

		return;
	}

	$plan  = blueworx_merge_users_preview( $kept_id, $spare_id );
	$kept  = $plan['kept'];
	$spare = $plan['spare'];
	$none  = esc_html__( 'Nothing', 'blueworx-labs-wordpress' );
	$list  = static function ( $items ) use ( $none ) {
		return $items ? esc_html( implode( ', ', $items ) ) : $none;
	};

	$surecart = array();
	foreach ( $plan['surecart'] as $mode => $id ) {
		$surecart[] = $id . ( 'live' === $mode ? '' : ' (' . $mode . ')' );
	}

	$rows = array(
		__( 'Keeping', 'blueworx-labs-wordpress' )         => esc_html( blueworx_merge_users_label( $kept ) ),
		__( 'Deleting', 'blueworx-labs-wordpress' )        => esc_html( blueworx_merge_users_label( $spare ) ),
		__( 'Details filled in from the spare', 'blueworx-labs-wordpress' ) => $list( array_map( 'blueworx_merge_users_field_label', $plan['meta_filled'] ) ),
		__( 'Roles added', 'blueworx-labs-wordpress' )     => $list( $plan['roles_added'] ),
		__( 'Posts and pages moving', 'blueworx-labs-wordpress' ) => esc_html( (string) $plan['posts'] ),
		__( 'Comments moving', 'blueworx-labs-wordpress' ) => esc_html( (string) $plan['comments'] ),
		__( 'LearnDash courses added', 'blueworx-labs-wordpress' ) => $list( array_map( 'blueworx_merge_users_course_label', $plan['courses_added'] ) ),
		__( 'LearnDash progress taken from the spare', 'blueworx-labs-wordpress' ) => $list( array_map( 'blueworx_merge_users_course_label', $plan['courses_progress_from_spare'] ) ),
		__( 'SureCart customer afterwards', 'blueworx-labs-wordpress' ) => $list( $surecart ),
	);

	if ( in_array( 'administrator', $plan['roles_added'], true ) ) {
		echo blueworx_ds_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
			array(
				'tone' => 'warning',
				/* translators: %s: username. */
				'text' => sprintf( __( '%s will become an administrator, because the account being deleted is one.', 'blueworx-labs-wordpress' ), $kept->user_login ),
			)
		);
	}

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'eyebrow' => __( 'Step 2 of 2', 'blueworx-labs-wordpress' ),
			'title'   => __( 'What will happen', 'blueworx-labs-wordpress' ),
			'note'    => __( 'The kept account\'s own details always win. The spare only fills gaps. Sign-ins, passwords and access settings are never carried across.', 'blueworx-labs-wordpress' ),
			'body'    => blueworx_ds_description_list( $rows ),
			'actions' => blueworx_ds_button(
				array(
					'label' => __( 'Swap which one is kept', 'blueworx-labs-wordpress' ),
					'size'  => 'sm',
					'href'  => blueworx_merge_users_url(
						array(
							'user'  => $user_id,
							'other' => $user_id === $kept->ID ? $spare->ID : $kept->ID,
							'keep'  => $user_id === $kept->ID ? 'other' : 'this',
							'q'     => $search,
						)
					),
				)
			),
			'class'   => 'blueworx-merge-users__preview',
		)
	);

	$form  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bw-fields bw-fields--single">';
	$form .= '<input type="hidden" name="action" value="blueworx_merge_users" />';
	$form .= '<input type="hidden" name="kept" value="' . esc_attr( (string) $kept->ID ) . '" />';
	$form .= '<input type="hidden" name="spare" value="' . esc_attr( (string) $spare->ID ) . '" />';
	$form .= '<input type="hidden" name="user" value="' . esc_attr( (string) $user_id ) . '" />';
	$form .= wp_nonce_field( 'blueworx_merge_users_' . $kept->ID . '_' . $spare->ID, '_wpnonce', true, false );

	$form .= blueworx_ds_radio_group(
		array(
			'name'     => 'email_from',
			'legend'   => __( 'Which email should the kept account have?', 'blueworx-labs-wordpress' ),
			'choices'  => array(
				/* translators: 1: email address, 2: username. */
				'kept'  => sprintf( __( '%1$s (from %2$s)', 'blueworx-labs-wordpress' ), $kept->user_email, $kept->user_login ),
				/* translators: 1: email address, 2: username. */
				'spare' => sprintf( __( '%1$s (from %2$s)', 'blueworx-labs-wordpress' ), $spare->user_email, $spare->user_login ),
			),
			'selected' => 'kept',
			'help'     => __( 'Nobody is emailed about the merge.', 'blueworx-labs-wordpress' ),
		)
	);

	$form .= blueworx_ds_notice(
		array(
			'tone'  => 'danger',
			/* translators: %s: username. */
			'title' => sprintf( __( '%s will be deleted.', 'blueworx-labs-wordpress' ), $spare->user_login ),
			'text'  => __( 'This cannot be undone.', 'blueworx-labs-wordpress' ),
		)
	);

	$form .= '<div>' . blueworx_ds_button(
		array(
			'label'   => __( 'Merge accounts', 'blueworx-labs-wordpress' ),
			'variant' => 'danger',
			'type'    => 'submit',
		)
	) . ' ' . $back . '</div></form>';

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'title' => __( 'Confirm', 'blueworx-labs-wordpress' ),
			'body'  => $form,
			'class' => 'blueworx-merge-users__confirm',
		)
	);
}

/**
 * The result: done, and where to go next.
 *
 * @param int $kept_id Account kept.
 * @return void
 */
function blueworx_merge_users_render_done( $kept_id ) {
	$kept = get_userdata( $kept_id );

	if ( ! $kept ) {
		return;
	}

	echo blueworx_ds_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'tone'    => 'success',
			'title'   => __( 'Accounts merged.', 'blueworx-labs-wordpress' ),
			/* translators: %s: username. */
			'text'    => sprintf( __( 'Everything is now on %s.', 'blueworx-labs-wordpress' ), blueworx_merge_users_label( $kept ) ),
			'actions' => blueworx_ds_button(
				array(
					/* translators: %s: username. */
					'label' => sprintf( __( 'Open %s', 'blueworx-labs-wordpress' ), $kept->user_login ),
					'href'  => get_edit_user_link( $kept->ID ),
				)
			) . ' ' . blueworx_ds_button(
				array(
					'label' => __( 'Back to Users', 'blueworx-labs-wordpress' ),
					'href'  => admin_url( 'users.php' ),
				)
			),
		)
	);
}

/**
 * Runs a confirmed merge.
 *
 * @return void
 */
function blueworx_merge_users_handle() {
	if ( ! blueworx_merge_users_allowed() ) {
		wp_die( esc_html__( 'You do not have permission to merge accounts.', 'blueworx-labs-wordpress' ), 403 );
	}

	$kept  = isset( $_POST['kept'] ) ? absint( $_POST['kept'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified on the next line, against these two IDs.
	$spare = isset( $_POST['spare'] ) ? absint( $_POST['spare'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same.
	check_admin_referer( 'blueworx_merge_users_' . $kept . '_' . $spare );

	$user_id    = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : $kept;
	$email_from = isset( $_POST['email_from'] ) && 'spare' === $_POST['email_from'] ? 'spare' : 'kept';
	$result     = blueworx_merge_users_run( $kept, $spare, $email_from, get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		$data    = $result->get_error_data();
		$started = is_array( $data ) && ! empty( $data['started'] );

		blueworx_merge_users_set_notice(
			array(
				'tone'  => $started ? 'warning' : 'danger',
				'title' => $started
					? __( 'The merge stopped part-way.', 'blueworx-labs-wordpress' )
					: __( 'The merge did not happen.', 'blueworx-labs-wordpress' ),
				'text'  => $result->get_error_message(),
			)
		);

		wp_safe_redirect( blueworx_merge_users_url( array( 'user' => get_userdata( $user_id ) ? $user_id : $kept ) ) );
		exit;
	}

	wp_safe_redirect( blueworx_merge_users_url( array( 'merged' => $kept ) ) );
	exit;
}

if ( blueworx_feature_enabled( 'merge_users' ) ) {
	add_action( 'admin_menu', 'blueworx_merge_users_register_page' );
	add_filter( 'user_row_actions', 'blueworx_merge_users_row_action', 10, 2 );
	add_action( 'admin_post_blueworx_merge_users', 'blueworx_merge_users_handle' );
	add_action( 'wp_ajax_blueworx_merge_users', 'blueworx_merge_users_ajax' );
	add_action( 'admin_enqueue_scripts', 'blueworx_merge_users_enqueue' );
}
