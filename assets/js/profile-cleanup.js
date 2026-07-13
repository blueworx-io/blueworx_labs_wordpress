( function () {
	function hideElement( element ) {
		if ( element ) {
			element.style.display = 'none';
		}
	}

	function textMatches( element, text ) {
		return element && element.textContent.trim().toLowerCase() === text.toLowerCase();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( window.blueworxProfileCleanup ) {
			document.querySelectorAll( 'h2, h3' ).forEach( function ( heading ) {
				if ( textMatches( heading, 'Personal Options' ) ) {
					hideElement( heading.nextElementSibling && 'TABLE' === heading.nextElementSibling.tagName ? heading.nextElementSibling : null );
					hideElement( heading );
				}

				if ( textMatches( heading, 'Elementor - AI' ) ) {
					hideElement( heading.closest( 'tr' ) || heading );
				}

				if ( textMatches( heading, 'Elementor Notes' ) ) {
					hideElement( heading.nextElementSibling && 'TABLE' === heading.nextElementSibling.tagName ? heading.nextElementSibling : null );
					hideElement( heading );
				}
			} );

			document.querySelectorAll( 'label[for="elementor_enable_ai"], #elementor_enable_ai' ).forEach( function ( element ) {
				hideElement( element.closest( 'tr' ) );
			} );

			var notesHeading = document.getElementById( 'e-notes' );

			if ( notesHeading ) {
				hideElement( notesHeading.nextElementSibling && 'TABLE' === notesHeading.nextElementSibling.tagName ? notesHeading.nextElementSibling : null );
				hideElement( notesHeading );
			}

			document.querySelectorAll( '.user-syntax-highlighting-wrap, .user-admin-color-wrap, .user-comment-shortcuts-wrap, .show-admin-bar, .user-language-wrap' ).forEach( hideElement );
		}

		if ( window.blueworxHideApplicationPasswords ) {
			document.querySelectorAll( '.application-passwords, #application-passwords-section' ).forEach( hideElement );
		}
	} );
}() );
