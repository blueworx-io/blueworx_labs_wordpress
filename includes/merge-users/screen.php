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

	return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
}

/**
 * Names a LearnDash course by its title.
 *
 * @param int $course_id Course ID.
 * @return string Title, or the ID when the course has gone.
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
		blueworx_merge_users_render_preview( $user_id, $kept, $spare );
	} else {
		blueworx_merge_users_render_chooser( $user_id, $search, $other );
	}

	blueworx_close_admin_page();
}

/**
 * Step one: which account, and which to keep.
 *
 * A plain list with a search over it, rather than a live lookup: it works with
 * no script, and up to 200 matches is plenty to find one person by.
 *
 * @param int    $user_id Account the person started from.
 * @param string $search  Search text.
 * @param int    $other   Account already picked, if any.
 * @return void
 */
function blueworx_merge_users_render_chooser( $user_id, $search, $other ) {
	$user  = get_userdata( $user_id );
	$query = array(
		'exclude' => array( $user_id ),
		'orderby' => 'login',
		'number'  => 200,
		'fields'  => array( 'ID', 'user_login', 'user_email' ),
	);

	if ( '' !== $search ) {
		$query['search']         = '*' . $search . '*';
		$query['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
	}

	$options = array();
	foreach ( get_users( $query ) as $row ) {
		$options[ (string) $row->ID ] = sprintf( '%1$s (%2$s)', $row->user_login, $row->user_email );
	}

	// Two forms, so searching never also submits whichever account the list
	// happens to have selected.
	$open = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="bw-fields bw-fields--single">'
		. '<input type="hidden" name="page" value="blueworx-merge-users" />'
		. '<input type="hidden" name="user" value="' . esc_attr( (string) $user_id ) . '" />';

	$body = $open;

	$body .= blueworx_ds_field(
		array(
			'label'   => __( 'Find the other account', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_merge_users_q',
			'control' => blueworx_ds_input(
				array(
					'name'  => 'q',
					'id'    => 'blueworx_merge_users_q',
					'value' => $search,
					'attrs' => array( 'placeholder' => __( 'Username, email or name', 'blueworx-labs-wordpress' ) ),
				)
			) . ' ' . blueworx_ds_button(
				array(
					'label' => __( 'Search', 'blueworx-labs-wordpress' ),
					'type'  => 'submit',
					'icon'  => 'search',
				)
			),
		)
	);

	$body .= '</form>';

	if ( $options ) {
		$body .= $open . blueworx_ds_field(
			array(
				'label'   => __( 'Other account', 'blueworx-labs-wordpress' ),
				'for'     => 'blueworx_merge_users_other',
				'control' => blueworx_ds_select(
					array(
						'name'     => 'other',
						'id'       => 'blueworx_merge_users_other',
						'options'  => $options,
						'selected' => (string) $other,
					)
				),
			)
		);

		$body .= blueworx_ds_radio_group(
			array(
				'name'     => 'keep',
				'legend'   => __( 'Which account do you want to keep?', 'blueworx-labs-wordpress' ),
				'choices'  => array(
					'other' => __( 'The account picked above', 'blueworx-labs-wordpress' ),
					/* translators: %s: username. */
					'this'  => sprintf( __( 'Keep %s', 'blueworx-labs-wordpress' ), $user->user_login ),
				),
				'selected' => 'other',
				'help'     => __( 'The one you do not keep is deleted at the end.', 'blueworx-labs-wordpress' ),
			)
		);

		$body .= '<div>' . blueworx_ds_button(
			array(
				'label'   => __( 'Continue', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
				'icon'    => 'arrow-right',
			)
		) . '</div></form>';
	} else {
		$body .= blueworx_ds_notice(
			array(
				'tone' => 'info',
				'text' => __( 'No other accounts match that search.', 'blueworx-labs-wordpress' ),
			)
		);
	}

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'eyebrow' => __( 'Step 1 of 2', 'blueworx-labs-wordpress' ),
			/* translators: %s: username and email. */
			'title'   => sprintf( __( 'Merge %s with…', 'blueworx-labs-wordpress' ), blueworx_merge_users_label( $user ) ),
			'body'    => $body,
			'class'   => 'blueworx-merge-users__choose',
		)
	);
}

/**
 * Step two: what the merge would change, and the one button that does it.
 *
 * @param int $user_id  Account the person started from, for the way back.
 * @param int $kept_id  Account to keep.
 * @param int $spare_id Account to delete.
 * @return void
 */
function blueworx_merge_users_render_preview( $user_id, $kept_id, $spare_id ) {
	$back  = blueworx_ds_button(
		array(
			'label' => __( 'Back', 'blueworx-labs-wordpress' ),
			'href'  => blueworx_merge_users_url( array( 'user' => $user_id ) ),
			'icon'  => 'arrow-left',
		)
	);
	$check = blueworx_merge_users_check( $kept_id, $spare_id, get_current_user_id() );

	if ( is_wp_error( $check ) ) {
		echo blueworx_ds_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
			array(
				'tone'    => 'danger',
				'title'   => __( 'These accounts cannot be merged.', 'blueworx-labs-wordpress' ),
				'text'    => $check->get_error_message(),
				'actions' => $back,
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
		__( 'Keeping', 'blueworx-labs-wordpress' )                   => esc_html( blueworx_merge_users_label( $kept ) ),
		__( 'Deleting', 'blueworx-labs-wordpress' )                  => esc_html( blueworx_merge_users_label( $spare ) ),
		__( 'Details filled in from the spare', 'blueworx-labs-wordpress' ) => $list( array_map( 'blueworx_merge_users_field_label', $plan['meta_filled'] ) ),
		__( 'Roles added', 'blueworx-labs-wordpress' )               => $list( $plan['roles_added'] ),
		__( 'Posts and pages moving', 'blueworx-labs-wordpress' )    => esc_html( (string) $plan['posts'] ),
		__( 'Comments moving', 'blueworx-labs-wordpress' )           => esc_html( (string) $plan['comments'] ),
		__( 'LearnDash courses added', 'blueworx-labs-wordpress' )   => $list( array_map( 'blueworx_merge_users_course_label', $plan['courses_added'] ) ),
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
		blueworx_merge_users_set_notice(
			array(
				'tone'  => 'danger',
				'title' => __( 'The merge did not happen.', 'blueworx-labs-wordpress' ),
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
}
