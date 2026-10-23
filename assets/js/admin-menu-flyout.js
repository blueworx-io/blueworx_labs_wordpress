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
 * This script only MEASURES. It never opens or closes anything. WordPress's own
 * hover handling (hoverIntent in wp-admin/js/common.js) decides when a fly-out
 * opens and closes, by adding and removing `opensub` on the item. An earlier
 * version opened the fly-out itself, on the first mouseover, and shut it on the
 * first mouseout. That threw away core's grace period: moving the pointer from
 * an item towards its fly-out, the slightest diagonal crossed the neighbouring
 * item and swapped menus, and crossing the gap beside the item shut it outright.
 * Writing the offset as an inline `top` also made core believe every fly-out was
 * already open, so core stopped managing them at all.
 *
 * The offset goes into a custom property, --blueworx-flyout-top, which the
 * stylesheet only reads while the item is actually open. So a measurement left
 * behind on a closed item can never hold its fly-out on the screen.
 */
( function () {
	var menu = document.getElementById( 'adminmenu' );

	if ( ! menu ) {
		return;
	}

	var wrap = document.getElementById( 'adminmenuwrap' );

	/**
	 * Measures where an item's fly-out should sit and hands that to the CSS.
	 *
	 * @param {HTMLElement} item Top-level menu list item.
	 */
	function place( item ) {
		var submenu = item.querySelector( ':scope > .wp-submenu' );

		// Only the expanded sidebar's fly-outs are fixed. Folded, auto-folded
		// (783px to 960px) and the phone drawer all leave core's own positioning
		// in charge, and an offset measured against the viewport would throw an
		// absolutely-positioned fly-out far down the page. Asking the element
		// covers every one of those states at once.
		if ( ! submenu || 'fixed' !== window.getComputedStyle( submenu ).position ) {
			return;
		}

		// Line up with the item's link, not the list item. The first item of each
		// group carries that group's heading ("CONTENT", "SITE") as a ::before
		// inside its list item, so the list item starts a heading's height above
		// the row you are pointing at, and the fly-out opened beside the heading.
		var anchor = item.querySelector( ':scope > a.menu-top' ) || item;
		var top = anchor.getBoundingClientRect().top;
		var height = submenu.offsetHeight;

		// Near the foot of the screen, lift it so its last item stays on screen.
		if ( top + height > window.innerHeight ) {
			top = window.innerHeight - height;
		}

		// But never above the foot of the top bar. The bar is fixed and paints
		// over the fly-out, so a fly-out lifted to the top of the window opened
		// with its first items underneath the bar, visible round the edges but
		// impossible to click.
		var floor = wrap ? wrap.getBoundingClientRect().top : 0;

		top = Math.max( floor, top );

		submenu.style.setProperty( '--blueworx-flyout-top', Math.round( top ) + 'px' );
	}

	/**
	 * Re-measures every fly-out that is open now.
	 */
	function placeOpen() {
		var open = menu.querySelectorAll( ':scope > li.menu-top.opensub, :scope > li.menu-top:focus-within' );

		Array.prototype.forEach.call( open, place );
	}

	// Core opens a fly-out by adding `opensub` to its item. Measure at that
	// moment: the observer runs before the browser paints, so the fly-out never
	// shows at the wrong height first.
	if ( 'MutationObserver' in window ) {
		new window.MutationObserver( function ( records ) {
			records.forEach( function ( record ) {
				var item = record.target;

				if ( item.parentNode === menu && item.classList.contains( 'opensub' ) ) {
					place( item );
				}
			} );
		} ).observe( menu, { subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
	}

	// A focused top-level link shows its fly-out through CSS alone, with no
	// `opensub`, so keyboard focus is measured separately. A fly-out reachable
	// only by mouse is not reachable at all for keyboard users.
	menu.addEventListener( 'focusin', function ( event ) {
		var item = event.target && event.target.closest
			? event.target.closest( '#adminmenu > li.menu-top' )
			: null;

		if ( item ) {
			place( item );
		}
	} );

	// The fly-out is `position: fixed`, so it does not move with #adminmenuwrap's
	// own scroll the way an absolutely-positioned fly-out would. Without this,
	// scrolling the sidebar while hovering a parent leaves the fly-out floating
	// over whatever is now at its old screen position instead of tracking the
	// item it belongs to.
	if ( wrap ) {
		wrap.addEventListener( 'scroll', placeOpen, { passive: true } );
	}

	window.addEventListener( 'resize', placeOpen, { passive: true } );
}() );
