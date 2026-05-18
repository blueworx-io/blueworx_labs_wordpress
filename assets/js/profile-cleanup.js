( function () {
	function hideElement( element ) {
		if ( element ) {
			element.classList.add( 'blueworx-profile-hidden' );
			element.style.display = 'none';
		}
	}

	function textMatches( element, text ) {
		return element && element.textContent.trim().toLowerCase() === text.toLowerCase();
	}

	function isVisible( element ) {
		return element && 'none' !== element.style.display;
	}

	function uniqueElements( elements ) {
		return elements.filter( function ( element, index ) {
			return element && elements.indexOf( element ) === index;
		} );
	}

	function sortByPageOrder( elements ) {
		return elements.sort( function ( first, second ) {
			if ( first === second ) {
				return 0;
			}

			return first.compareDocumentPosition( second ) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
		} );
	}

	function getProfileSectionStarts() {
		var starts = [];
		var courseInfoHeading = null;
		var ldCourseInfo = document.getElementById( 'ld_course_info' );

		document.querySelectorAll( '.wrap h2, .wrap h3' ).forEach( function ( heading ) {
			if (
				heading.closest( 'table, #application-passwords-section, .learndash_user_courses, #ld_course_info, #learndash_delete_user_data' ) ||
				( 'H3' === heading.tagName && ! textMatches( heading, 'Course Info' ) )
			) {
				return;
			}

			if ( textMatches( heading, 'Course Info' ) ) {
				courseInfoHeading = heading;
			}

			starts.push( heading );
		} );

		document.querySelectorAll(
			'#application-passwords-section, .wrap .learndash_user_courses.learndash-binary-selector, #suremembers-add-access-group-select, #learndash_delete_user_data, .wrap .asenha-roles-temporary-container'
		).forEach( function ( section ) {
			starts.push( section );
		} );

		if ( ldCourseInfo && ! courseInfoHeading ) {
			starts.push( ldCourseInfo );
		}

		return sortByPageOrder( uniqueElements( starts ) );
	}

	function getNextSectionStart( currentStart, starts ) {
		var currentIndex = starts.indexOf( currentStart );

		return -1 === currentIndex ? null : starts[ currentIndex + 1 ];
	}

	function wrapProfileSections() {
		var starts = getProfileSectionStarts();

		starts.forEach( function ( start ) {
			var card;
			var next;
			var nextStart = getNextSectionStart( start, starts );
			var isSelfContained = start.matches(
				'#application-passwords-section, .learndash_user_courses.learndash-binary-selector, #suremembers-add-access-group-select, #learndash_delete_user_data, #ld_course_info, .asenha-roles-temporary-container'
			);

			if ( ! isVisible( start ) || start.closest( '.blueworx-profile-card' ) ) {
				return;
			}

			next = start.nextElementSibling;

			if ( 'H2' === start.tagName && ( ! next || ( 'TABLE' !== next.tagName && 'DIV' !== next.tagName ) || ! isVisible( next ) ) ) {
				return;
			}

			card = document.createElement( 'div' );
			card.className = 'blueworx-profile-card';

			start.parentNode.insertBefore( card, start );
			card.appendChild( start );

			if ( isSelfContained ) {
				return;
			}

			while ( next && next !== nextStart ) {
				var current = next;

				if ( current.classList && current.classList.contains( 'submit' ) ) {
					break;
				}

				if ( current.matches && current.matches( '#application-passwords-section, .learndash_user_courses.learndash-binary-selector, #suremembers-add-access-group-select, #learndash_delete_user_data, .asenha-roles-temporary-container' ) ) {
					break;
				}

				next = next.nextElementSibling;
				card.appendChild( current );
			}
		} );
	}

	function hideEmptyProfileCards() {
		document.querySelectorAll( '.blueworx-profile-card' ).forEach( function ( card ) {
			var visibleContent = Array.prototype.slice.call( card.children ).filter( function ( child ) {
				var text = child.textContent.trim();
				var hasFields = child.querySelector( 'input:not([type="hidden"]), select, textarea, button, a, table, .select2, .wp-list-table' );

				return isVisible( child ) && ( '' !== text || hasFields );
			} );

			if ( 0 === visibleContent.length ) {
				hideElement( card );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( 'h2' ).forEach( function ( heading ) {
			if ( textMatches( heading, 'Personal Options' ) ) {
				hideElement( heading.nextElementSibling && 'TABLE' === heading.nextElementSibling.tagName ? heading.nextElementSibling : null );
				hideElement( heading );
			}

			if ( textMatches( heading, 'Elementor - AI' ) ) {
				hideElement( heading.closest( 'tr' ) || heading );
			}

			if ( textMatches( heading, 'Elementor Notes' ) ) {
				hideElement( heading.nextElementSibling && 'TABLE' === heading.nextElementSibling.tagName ? heading.nextElementSibling : null );
				hideElement( heading.closest( '.blueworx-profile-card' ) || heading );
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

		document.querySelectorAll( '.blueworx-profile-card h2' ).forEach( function ( heading ) {
			if ( textMatches( heading, 'Elementor Notes' ) ) {
				hideElement( heading.closest( '.blueworx-profile-card' ) );
			}
		} );

		wrapProfileSections();
		hideEmptyProfileCards();
		window.setTimeout( hideEmptyProfileCards, 250 );
	} );
}() );
