/**
 * Puts the BlueWorx eyebrow and page-access row above a core screen's heading.
 *
 * The heading belongs to WordPress and there is no hook between the opening
 * .wrap and the h1 inside it, so this is the only place the markup can be put.
 * It is built and escaped in PHP — see blueworx_core_screen_header_html() —
 * and handed over as a string. Nothing is composed here.
 */
( function () {
	'use strict';

	function start() {
		var html = window.blueworxCorePagehead;

		if ( ! html ) {
			return;
		}

		// The first heading inside .wrap. Some screens open with an h2 instead,
		// and a screen with neither has nothing to sit above.
		var heading = document.querySelector( '.wrap > h1, .wrap > h2' );

		if ( ! heading || ! heading.parentNode ) {
			return;
		}

		// Once only. Screens that re-render their own header would otherwise
		// stack a second copy on top of the first.
		if ( heading.parentNode.querySelector( '.bw-core-pagehead' ) ) {
			return;
		}

		var block = document.createElement( 'div' );
		block.className = 'bw-core-pagehead';
		block.innerHTML = html;

		heading.parentNode.insertBefore( block, heading );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
