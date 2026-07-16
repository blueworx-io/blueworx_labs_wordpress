/**
 * Profile screen redesign (profile.php / user-edit.php).
 *
 * Restructures WordPress's native profile form into the BlueWorx two-column
 * card layout with a dark hero header, WITHOUT recreating any inputs: every
 * native field, nonce and hidden input is MOVED, never cloned, so core's save
 * handler still receives exactly what it expects. Sections the design drops are
 * left in the form but hidden, so nothing that carries state is destroyed.
 *
 * Enqueued only when the admin re-skin (admin_theme) is on. Data comes from the
 * localised `blueworxProfile` object printed by blueworx_enqueue_admin_assets().
 */
( function () {
	var data = window.blueworxProfile;

	if ( ! data ) {
		return;
	}

	// Section-heading text (lower-cased) -> which column the card belongs in.
	var RIGHT_COLUMN = [ 'account management', 'account', 'security' ];

	// Section-heading text (lower-cased) -> friendlier card title.
	var RETITLE = {
		'name': 'Profile Details',
		'contact info': 'Contact',
		'about yourself': 'About',
		'about the user': 'About',
		'account management': 'Account & Security'
	};

	// Sections the design drops. Left in the form (hidden) so their inputs and
	// any nonce they carry still post.
	var DROP = [ 'personal options' ];

	function escapeHtml( value ) {
		return String( value == null ? '' : value ).replace( /[&<>"']/g, function ( character ) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;'
			}[ character ];
		} );
	}

	function buildHero() {
		var meta = [];

		if ( data.handle ) {
			meta.push( '@' + data.handle );
		}

		if ( data.memberSince ) {
			meta.push( 'Member since ' + data.memberSince );
		}

		if ( data.posts ) {
			meta.push( data.posts );
		}

		var hero = document.createElement( 'div' );
		hero.className = 'bw-profile-hero';
		hero.innerHTML =
			'<div class="bw-profile-hero-id">' +
				'<span class="bw-profile-avatar" aria-hidden="true">' + escapeHtml( data.initials ) + '</span>' +
				'<div class="bw-profile-hero-text">' +
					'<div class="bw-profile-hero-name">' + escapeHtml( data.name ) +
						( data.role ? ' <span class="bw-profile-role">' + escapeHtml( data.role ) + '</span>' : '' ) +
					'</div>' +
					( meta.length ? '<div class="bw-profile-hero-meta">' + escapeHtml( meta.join( ' · ' ) ) + '</div>' : '' ) +
				'</div>' +
			'</div>' +
			'<div class="bw-profile-hero-actions">' +
				( data.postsUrl ? '<a class="bw-btn bw-btn-ghost" href="' + encodeURI( data.postsUrl ) + '">' + escapeHtml( data.viewLabel ) + '</a>' : '' ) +
				'<button type="button" class="bw-btn bw-btn-primary" id="bw-profile-save">' + escapeHtml( data.saveLabel ) + '</button>' +
			'</div>';

		return hero;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.querySelector( '.wrap' );
		var form = document.getElementById( 'your-profile' );

		if ( ! wrap || ! form || wrap.classList.contains( 'bw-profile' ) ) {
			return;
		}

		wrap.classList.add( 'bw-profile' );

		var grid = document.createElement( 'div' );
		grid.className = 'bw-profile-grid';

		var leftCol = document.createElement( 'div' );
		leftCol.className = 'bw-profile-col';

		var rightCol = document.createElement( 'div' );
		rightCol.className = 'bw-profile-col';

		grid.appendChild( leftCol );
		grid.appendChild( rightCol );

		var submit = form.querySelector( 'p.submit' );
		var currentBody = null;

		function startCard( heading ) {
			var raw = heading.textContent.trim();
			var key = raw.toLowerCase();

			if ( DROP.indexOf( key ) !== -1 ) {
				currentBody = null;
				return;
			}

			var card = document.createElement( 'section' );
			card.className = 'bw-profile-card';

			var title = document.createElement( 'h2' );
			title.className = 'bw-profile-card-title';
			title.textContent = RETITLE[ key ] || raw;
			card.appendChild( title );

			var body = document.createElement( 'div' );
			body.className = 'bw-profile-card-body';
			card.appendChild( body );

			( RIGHT_COLUMN.indexOf( key ) !== -1 ? rightCol : leftCol ).appendChild( card );
			currentBody = body;
		}

		// Snapshot the form's direct children before we start moving them.
		Array.prototype.slice.call( form.children ).forEach( function ( node ) {
			if ( node === submit ) {
				return;
			}

			if ( /^H2$/i.test( node.tagName ) ) {
				startCard( node );
				return;
			}

			if ( currentBody ) {
				currentBody.appendChild( node );
			} else if ( node.tagName && 'INPUT' !== node.tagName ) {
				// Content before the first heading or under a dropped section:
				// hide it but leave it in the form so any hidden input it wraps
				// still submits. Bare hidden <input>s are left visible-less as-is.
				node.style.display = 'none';
			}
		} );

		form.insertBefore( grid, form.firstChild );
		wrap.insertBefore( buildHero(), form );

		// The hero Save button proxies core's native submit control.
		var nativeSubmit = form.querySelector( 'p.submit input[type="submit"]' ) || document.getElementById( 'submit' );
		var heroSave = document.getElementById( 'bw-profile-save' );

		if ( heroSave && nativeSubmit ) {
			heroSave.addEventListener( 'click', function () {
				nativeSubmit.click();
			} );
		}
	} );
}() );
