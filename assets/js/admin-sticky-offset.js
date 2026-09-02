/**
 * Keeps a plugin's own sticky header clear of whatever chrome sits above it.
 *
 * An app-like admin screen tends to pin its header with
 * `position: sticky; top: 32px` — the height of WordPress's admin bar, taken as
 * a constant. We hide that bar from 783px up, so the number is wrong on every
 * screen: either our own bar is there and is taller, and the header scrolls up
 * behind it, or nothing is there at all and the header pins below a 32px band of
 * whatever is scrolling past underneath.
 *
 * Both cases are answered the same way — re-pin the header to the height of the
 * chrome that is actually drawn, which is our top bar's height where it is on
 * screen and zero where it is not (a plugin app screen, LatePoint, the block
 * editor in fullscreen).
 *
 * Why this is not CSS, in a re-skin that is otherwise CSS-first: the offset has
 * to be matched by its VALUE. The headers this was written for carry a
 * build-hashed class name (`css-152nnxu`) and a semantic one
 * (`.sc-settings-header-container`), so a stylesheet could only name one of
 * them, would go stale on the plugin's next release, and would do nothing for
 * the screens we cannot see. The assumption is what is wrong, and the assumption
 * is visible in the computed offset.
 *
 * Two things are deliberately left alone:
 *
 * - A header inside a scrolling panel of its own. It pins to that panel, not to
 *   the window, so nothing covers it — moving it would be the bug. This is also
 *   why the problem is desktop-only: below 961px core gives #wpbody and
 *   #wpbody-content `overflow: auto`, which stops anything inside them pinning
 *   to the window at all.
 * - Any offset that is not the admin bar's own height. A header that already
 *   knows about our bar, or pins at 0 inside its own layout, is not making the
 *   assumption this corrects.
 */
( function () {
	/* The two heights WordPress's admin bar takes: 32px wide, 46px narrow. */
	var ADMIN_BAR_HEIGHTS = [ '32px', '46px' ];
	var MARKER = 'bwStickyOffset';

	/*
	 * How long to wait after the DOM stops changing before looking. Not a
	 * flourish: these apps insert the header first and inject the rule that makes
	 * it sticky a moment later, so an element checked the instant it arrives is
	 * still `position: static` and reads as nothing to correct. That is the bug
	 * this delay exists for — it showed up as a header that stayed wrong until
	 * the window was resized, because a resize was the only thing that looked
	 * again.
	 */
	var SETTLE_MS = 200;

	/* An app that animates never goes quiet, so the wait cannot be open-ended. */
	var MAX_WAIT_MS = 800;

	/*
	 * And once more, later. A settling delay only helps if the rule lands inside
	 * it, and there is no upper bound on that: the app may write its styles
	 * through the CSSOM, which changes no markup at all and so cannot be watched
	 * for. Anything skipped as "not sticky" gets looked at a second time, well
	 * after the app has finished.
	 */
	var SECOND_LOOK_MS = 1500;

	var pending = [];
	var settleTimer = null;
	var maxTimer = null;
	var secondLookTimer = null;

	/**
	 * The height of the chrome above the page, as drawn right now.
	 *
	 * Zero where our bar is hidden: a plugin app screen has been given the top of
	 * the window, and there is nothing there for a header to clear.
	 *
	 * @return {string} A CSS length.
	 */
	function chromeHeight() {
		var bar = document.querySelector( '.bw-topbar' );

		if ( ! bar || 'none' === window.getComputedStyle( bar ).display ) {
			return '0px';
		}

		return Math.round( bar.getBoundingClientRect().height ) + 'px';
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
	 * @param {HTMLElement} root   Subtree to check, itself included.
	 * @param {string}      offset The offset to write.
	 * @return {void}
	 */
	function repin( root, offset ) {
		if ( ! root || 1 !== root.nodeType || ! root.isConnected ) {
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
			el.style.top = offset;
		} );
	}

	/**
	 * Looks at everything queued since the last pass.
	 *
	 * @return {void}
	 */
	function flush() {
		window.clearTimeout( settleTimer );
		window.clearTimeout( maxTimer );
		settleTimer = null;
		maxTimer = null;

		var roots = pending;
		var offset = chromeHeight();

		pending = [];
		roots.forEach( function ( root ) {
			repin( root, offset );
		} );

		if ( ! roots.length || null !== secondLookTimer ) {
			return;
		}

		secondLookTimer = window.setTimeout( function () {
			secondLookTimer = null;

			var later = chromeHeight();

			roots.forEach( function ( root ) {
				repin( root, later );
			} );
		}, SECOND_LOOK_MS );
	}

	/**
	 * Queues a subtree, to be looked at once the page settles.
	 *
	 * @param {HTMLElement} root Subtree to check.
	 * @return {void}
	 */
	function queue( root ) {
		pending.push( root );

		window.clearTimeout( settleTimer );
		settleTimer = window.setTimeout( flush, SETTLE_MS );

		if ( null === maxTimer ) {
			maxTimer = window.setTimeout( flush, MAX_WAIT_MS );
		}
	}

	queue( document.body );

	// These headers are rendered by the plugin's own app, after this script has
	// run, and rendered again as it moves between its screens — so noticing a
	// late arrival is most of the job. Only what actually changed is looked at.
	new window.MutationObserver( function ( records ) {
		records.forEach( function ( record ) {
			Array.prototype.forEach.call( record.addedNodes, function ( node ) {
				if ( 1 === node.nodeType ) {
					queue( node );
				}
			} );
		} );
	} ).observe( document.body, { childList: true, subtree: true } );

	// Resizing changes which bar is drawn and how tall it is, and crossing 961px
	// changes whether anything pins to the window at all. Neither shows up as a
	// mutation, so the whole page is looked at again.
	window.addEventListener( 'resize', function () {
		queue( document.body );
	} );
}() );
