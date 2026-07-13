( function () {
	'use strict';

	function sync( toggle ) {
		var key = toggle.getAttribute( 'data-blueworx-feature' );
		var detail = document.querySelector( '.blueworx-feature-detail[data-blueworx-detail="' + key + '"]' );
		if ( detail ) {
			detail.hidden = ! toggle.checked;
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggles = document.querySelectorAll( '.blueworx-feature-toggle' );
		Array.prototype.forEach.call( toggles, function ( toggle ) {
			sync( toggle );
			toggle.addEventListener( 'change', function () {
				sync( toggle );
			} );
		} );
	} );
}() );
