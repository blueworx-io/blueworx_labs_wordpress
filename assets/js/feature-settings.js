( function () {
	'use strict';

	function sync( toggle ) {
		var key = toggle.getAttribute( 'data-blueworx-feature' );
		var detail = document.querySelector( '.blueworx-feature-detail[data-blueworx-detail="' + key + '"]' );
		if ( detail ) {
			detail.hidden = ! toggle.checked;
		}
	}

	/**
	 * Shows one section's panel and marks its nav item.
	 *
	 * Every panel stays in the form either way — hidden, not removed. An
	 * unchecked checkbox and a missing one look identical when the form posts,
	 * so a panel that left the DOM would switch its whole section off on save.
	 *
	 * @param {string} id Section id.
	 */
	function showSection( id ) {
		var panels = document.querySelectorAll( '[data-blueworx-panel]' );
		Array.prototype.forEach.call( panels, function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-blueworx-panel' ) !== id;
		} );

		var items = document.querySelectorAll( '[data-blueworx-section]' );
		Array.prototype.forEach.call( items, function ( item ) {
			var active = item.getAttribute( 'data-blueworx-section' ) === id;
			item.classList.toggle( 'is-active', active );
			if ( active ) {
				item.setAttribute( 'aria-current', 'true' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );

		// The save posts this, and the redirect brings the operator back here.
		// Saving from Translation and landing on Security reads as the screen
		// having lost the change, even though it saved.
		var field = document.querySelector( '[data-blueworx-section-field]' );
		if ( field ) {
			field.value = id;
		}
	}

	function start() {
		var toggles = document.querySelectorAll( '.blueworx-feature-toggle' );
		Array.prototype.forEach.call( toggles, function ( toggle ) {
			sync( toggle );
			toggle.addEventListener( 'change', function () {
				sync( toggle );
			} );
		} );

		var items = document.querySelectorAll( '[data-blueworx-section]' );
		Array.prototype.forEach.call( items, function ( item ) {
			item.addEventListener( 'click', function () {
				showSection( item.getAttribute( 'data-blueworx-section' ) );
			} );
		} );

		// A section named in the URL wins, so a guide can link straight to the
		// section it is about rather than to the top of the screen.
		var wanted = new URLSearchParams( window.location.search ).get( 'section' );
		if ( wanted && document.querySelector( '[data-blueworx-panel="' + wanted + '"]' ) ) {
			showSection( wanted );
		}

		// Says the screen is wired up. Without it there is no way to tell a nav
		// that has not been bound yet from one that is bound and ignoring you.
		document.documentElement.setAttribute( 'data-blueworx-sections', 'ready' );
	}

	// Not simply DOMContentLoaded: this file is enqueued in the footer, and if it
	// ever executes after that event has already fired — a deferred script, an
	// asset optimiser, a slow load — a listener added here would never run at
	// all, and the section nav would be dead with nothing on the page to say so.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
