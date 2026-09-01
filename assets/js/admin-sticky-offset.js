/**
 * Keeps a plugin's own sticky header clear of the BlueWorx top bar.
 *
 * An app-like admin screen tends to pin its header with
 * `position: sticky; top: 32px` — the height of WordPress's admin bar, taken as
 * a constant. From 961px up we hide that bar and draw our own 64px one in the
 * same band, at a higher z-index, so a header pinned at 32px scrolls up behind
 * ours and loses its top half. Measured on the live site at SureCart's
 * Commerce > Dashboard and Commerce > Settings: 32px of each header covered.
 *
 * Why this is not CSS, in a re-skin that is otherwise CSS-first: the offset has
 * to be matched by its VALUE. The two headers this was written for carry a
 * build-hashed class name (`css-152nnxu`) and a semantic one
 * (`.sc-settings-header-container`) respectively, so a stylesheet could only
 * name one of them, would go stale on the plugin's next release, and would do
 * nothing at all for the screens we cannot see. The assumption is what is wrong,
 * and the assumption is visible in the computed offset.
 *
 * Two things are deliberately left alone:
 *
 * - A header inside a scrolling panel of its own. It pins to that panel, not to
 *   the window, so nothing covers it — moving it down would be the bug. This is
 *   also why the whole problem is desktop-only: below 961px core gives #wpbody
 *   and #wpbody-content `overflow: auto`, which stops anything inside them
 *   pinning to the window at all.
 * - Any offset that is not the admin bar's own height. A header that already
 *   knows about our bar, or pins at 0 inside its own layout, is not making the
 *   assumption this corrects.
 */
( function () {
	/* The two heights WordPress's admin bar takes: 32px wide, 46px narrow. */
	var ADMIN_BAR_HEIGHTS = [ '32px', '46px' ];
	var OFFSET = 'var(--bwt-topbar-h)';
	var MARKER = 'bwStickyOffset';

	var pending = [];
	var frame = null;
	var resizeTimer = null;

	/**
	 * Whether our top bar is actually drawn.
	 *
	 * It is hidden on the screens that take the whole window over — the block
	 * editor in fullscreen, LatePoint — and there is nothing to clear there.
	 *
	 * @return {boolean} True when the bar is on screen.
	 */
	function barIsDrawn() {
		var bar = document.querySelector( '.bw-topbar' );

		return !! bar && 'none' !== window.getComputedStyle( bar ).display;
	}

	/**
	 * Whether an element pins to the window rather than to a panel of its own.
	 *
	 * The walk stops before <body>: overflow set on the body propagates to the
	 * viewport instead of making the body a scroll container, so counting it
	 * would wrongly disqualify every element on the page.
	 *
	 * @param {HTMLElement} el Sticky element.
	 * @return {boolean} True when the window is what it sticks to.
	 */
	function pinsToWindow( el ) {
		for ( var node = el.parentElement; node && node !== document.body; node = node.parentElement ) {
			var styles = window.getComputedStyle( node );

			if ( 'visible' !== styles.overflowY || 'visible' !== styles.overflowX ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Re-pins any admin-bar-height sticky offsets inside a subtree.
	 *
	 * @param {HTMLElement} root Subtree to check, itself included.
	 * @return {void}
	 */
	function repin( root ) {
		if ( ! root || 1 !== root.nodeType ) {
			return;
		}

		var candidates = [ root ].concat( Array.prototype.slice.call( root.querySelectorAll( '*' ) ) );
		var found = [];

		candidates.forEach( function ( el ) {
			if ( el.dataset[ MARKER ] ) {
				return;
			}

			var styles = window.getComputedStyle( el );

			if ( 'sticky' !== styles.position ) {
				return;
			}

			if ( -1 === ADMIN_BAR_HEIGHTS.indexOf( styles.top ) ) {
				return;
			}

			if ( ! pinsToWindow( el ) ) {
				return;
			}

			found.push( el );
		} );

		// Read every offset first, write after: setting one inline style
		// invalidates the styles still to be read.
		found.forEach( function ( el ) {
			el.dataset[ MARKER ] = '1';
			el.style.top = OFFSET;
		} );
	}

	/**
	 * Runs the queued checks once a frame, rather than once a mutation.
	 *
	 * @return {void}
	 */
	function flush() {
		frame = null;

		var roots = pending;
		pending = [];

		if ( ! barIsDrawn() ) {
			return;
		}

		roots.forEach( repin );
	}

	/**
	 * Queues a subtree for the next frame.
	 *
	 * @param {HTMLElement} root Subtree to check.
	 * @return {void}
	 */
	function queue( root ) {
		pending.push( root );

		if ( null === frame ) {
			frame = window.requestAnimationFrame( flush );
		}
	}

	queue( document.body );

	// These headers are rendered by the plugin's own app, after this script has
	// run, and re-rendered as it moves between its screens — so noticing a late
	// arrival is most of the job. Only what actually changed is looked at.
	new window.MutationObserver( function ( records ) {
		records.forEach( function ( record ) {
			Array.prototype.forEach.call( record.addedNodes, function ( node ) {
				if ( 1 === node.nodeType ) {
					queue( node );
				}
			} );
		} );
	} ).observe( document.body, { childList: true, subtree: true } );

	// A header rendered below 961px pins to nothing and is skipped; widening the
	// window is what turns it into a candidate, and no mutation reports that.
	window.addEventListener( 'resize', function () {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( function () {
			queue( document.body );
		}, 150 );
	} );
}() );
