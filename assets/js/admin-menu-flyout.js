/**
 * Sidebar fly-out submenus for the expanded admin menu.
 *
 * The expanded sidebar scrolls independently (#adminmenuwrap carries
 * overflow-y: auto in admin-theme.css), and that scroll container clips the
 * absolutely-positioned fly-outs core renders for non-current items — so
 * hovering a parent showed nothing and the item had to be clicked.
 *
 * Plain overflow does not clip position: fixed descendants, so the CSS makes the
 * fly-out fixed and this script supplies the one thing fixed positioning loses:
 * the vertical offset, which must be read from the item's live bounding rect.
 *
 * Hover and focus are handled together: a fly-out reachable only by mouse is not
 * reachable at all for keyboard users.
 */
( function () {
	var menu = document.getElementById( 'adminmenu' );

	if ( ! menu ) {
		return;
	}

	/**
	 * Positions an item's fly-out beside it, flipping up near the viewport foot.
	 *
	 * @param {HTMLElement} item Menu list item.
	 */
	function place( item ) {
		if ( document.body.classList.contains( 'folded' ) ) {
			return;
		}

		var submenu = item.querySelector( '.wp-submenu' );

		if ( ! submenu || ! item.classList.contains( 'wp-not-current-submenu' ) ) {
			return;
		}

		var rect = item.getBoundingClientRect();

		// Measure before deciding: the height is only known once it is laid out.
		submenu.style.top = rect.top + 'px';

		var height = submenu.offsetHeight;

		if ( rect.top + height > window.innerHeight ) {
			submenu.style.top = Math.max( 0, window.innerHeight - height ) + 'px';
		}
	}

	/**
	 * Clears an inline offset so the item returns to its stylesheet position.
	 *
	 * @param {HTMLElement} item Menu list item.
	 */
	function clear( item ) {
		var submenu = item.querySelector( '.wp-submenu' );

		if ( submenu ) {
			submenu.style.top = '';
		}
	}

	/**
	 * Resolves the menu item an event happened inside.
	 *
	 * @param {Event} event DOM event.
	 * @return {HTMLElement|null} The item, or null.
	 */
	function itemFor( event ) {
		return event.target && event.target.closest
			? event.target.closest( '#adminmenu > li.menu-top' )
			: null;
	}

	menu.addEventListener( 'mouseover', function ( event ) {
		var item = itemFor( event );

		if ( item ) {
			place( item );
		}
	} );

	menu.addEventListener( 'mouseout', function ( event ) {
		var item = itemFor( event );

		if ( item && ! item.contains( event.relatedTarget ) ) {
			clear( item );
		}
	} );

	menu.addEventListener( 'focusin', function ( event ) {
		var item = itemFor( event );

		if ( item ) {
			place( item );
		}
	} );

	menu.addEventListener( 'focusout', function ( event ) {
		var item = itemFor( event );

		if ( item && ! item.contains( event.relatedTarget ) ) {
			clear( item );
		}
	} );

	/**
	 * Re-places the currently open fly-out when the sidebar scrolls.
	 *
	 * The fly-out is `position: fixed`, so it does not move with #adminmenuwrap's
	 * own scroll the way an absolutely-positioned fly-out would. Without this,
	 * scrolling the sidebar while hovering a parent leaves the fly-out floating
	 * over whatever is now at its old screen position instead of tracking the
	 * item it belongs to.
	 */
	var adminmenuwrap = document.getElementById( 'adminmenuwrap' );

	if ( adminmenuwrap ) {
		adminmenuwrap.addEventListener(
			'scroll',
			function () {
				var current = menu.querySelector( '#adminmenu > li.menu-top .wp-submenu[style*="top"]' );
				var item = current ? current.closest( '#adminmenu > li.menu-top' ) : null;

				if ( item ) {
					place( item );
				}
			},
			{ passive: true }
		);
	}
}() );
