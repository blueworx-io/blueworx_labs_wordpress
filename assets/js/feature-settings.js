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

	/**
	 * Switches off every function in one section.
	 *
	 * Client-side only. Nothing is written until Save, so this is undoable by
	 * leaving the screen — which is why it needs no confirmation.
	 *
	 * @param {HTMLElement} button The section's button.
	 */
	function switchSectionOff( button ) {
		var panel = button.closest( '[data-blueworx-panel]' );

		if ( ! panel ) {
			return;
		}

		var toggles = panel.querySelectorAll( '.blueworx-feature-toggle' );

		Array.prototype.forEach.call( toggles, function ( toggle ) {
			if ( ! toggle.checked ) {
				return;
			}

			toggle.checked = false;
			sync( toggle );
			// Anything else listening for a change — a panel, a test — should
			// see this the same way it sees a click.
			toggle.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	}

	/**
	 * Keeps a range field's readout in step with its slider.
	 *
	 * Without this the number beside the slider is whatever the page rendered
	 * with, and dragging silently disagrees with it.
	 */
	function startRanges() {
		var readouts = document.querySelectorAll( '[data-blueworx-range-value]' );

		Array.prototype.forEach.call( readouts, function ( readout ) {
			var input = document.getElementById( readout.getAttribute( 'data-blueworx-range-value' ) );

			if ( ! input ) {
				return;
			}

			// The format comes from PHP, where the translation lives. Guessing it
			// back out of the rendered text breaks on any locale that puts the
			// unit somewhere else.
			var format = readout.getAttribute( 'data-blueworx-range-format' ) || '%s';

			input.addEventListener( 'input', function () {
				readout.textContent = format.replace( '%s', input.value );
			} );
		} );
	}

	/**
	 * Lets a chip untick the box that put it there.
	 *
	 * The tick boxes are the setting; the chips are a view of it. Removing a
	 * chip unticks its box and drops the chip, so the two never disagree.
	 */
	function startChips() {
		var rows = document.querySelectorAll( '[data-blueworx-chips]' );

		Array.prototype.forEach.call( rows, function ( row ) {
			var group = row.getAttribute( 'data-blueworx-chips' );

			row.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '[data-blueworx-chip-for]' );

				if ( ! button ) {
					return;
				}

				event.preventDefault();

				var value = button.getAttribute( 'data-blueworx-chip-for' );
				var box = document.querySelector(
					'input[name="' + group + '[]"][value="' + value + '"]'
				);

				if ( box ) {
					box.checked = false;
					box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}

				button.closest( '.bw-chip' ).remove();
			} );
		} );
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

		var offButtons = document.querySelectorAll( '.blueworx-section-off' );
		Array.prototype.forEach.call( offButtons, function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				switchSectionOff( button );
			} );
		} );

		startRanges();
		startChips();

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
