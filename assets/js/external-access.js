/**
 * The External access screen.
 *
 * One job: ask before a form that cannot be undone is submitted. The question
 * lives on the form as data-blueworx-confirm, so the wording comes from PHP
 * where the translation is, and no handler is written into the markup.
 *
 * If this file never loads, the forms still work — they simply submit without
 * asking. A confirmation is a guard against a misclick, not a permission
 * check; the real gate is the nonce and the capability check on the server.
 */
( function () {
	'use strict';

	function bind( form ) {
		var question = form.getAttribute( 'data-blueworx-confirm' );

		if ( ! question ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( question ) ) {
				event.preventDefault();
			}
		} );
	}

	function start() {
		var forms = document.querySelectorAll( 'form[data-blueworx-confirm]' );

		Array.prototype.forEach.call( forms, bind );
	}

	// Not simply DOMContentLoaded: this file is enqueued in the footer, and if
	// it ever executes after that event has already fired — a deferred script,
	// an asset optimiser, a slow load — a listener added here would never run
	// at all, and the confirmation would be silently gone.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
