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

	// Canonical section keys the design cares about. The actual heading TEXT is
	// core's, and therefore translated, so it is never matched literally here —
	// PHP passes the translated strings in data.sections and we resolve heading
	// text back to one of these keys via that map.
	var RIGHT_COLUMN = [ 'accountManagement' ];

	// Sections the design drops. Left in the form (hidden) so their inputs and
	// any nonce they carry still post.
	var DROP = [ 'personalOptions' ];

	// English fallbacks, used only if a heading is not in data.sections — e.g. a
	// core string that changed wording, on an English install.
	var FALLBACK_SECTIONS = {
		'personal options': 'personalOptions',
		'name': 'name',
		'contact info': 'contactInfo',
		'about yourself': 'aboutYourself',
		'about the user': 'aboutTheUser',
		'account management': 'accountManagement'
	};

	// Lower-cased heading text -> canonical key, built from the translated
	// strings first so a localised site resolves correctly, with the English
	// fallbacks filling any gap.
	var SECTION_KEYS = ( function () {
		var lookup = {};
		var key;

		for ( key in FALLBACK_SECTIONS ) {
			if ( Object.prototype.hasOwnProperty.call( FALLBACK_SECTIONS, key ) ) {
				lookup[ key ] = FALLBACK_SECTIONS[ key ];
			}
		}

		for ( key in data.sections || {} ) {
			if ( Object.prototype.hasOwnProperty.call( data.sections, key ) ) {
				lookup[ String( data.sections[ key ] ).trim().toLowerCase() ] = key;
			}
		}

		return lookup;
	}() );

	var RETITLE = data.cardTitles || {};
	var SUBTITLE = data.cardSubs || {};

	// Fields that share a row, in pairs. Anything not listed stays full width —
	// including fields added by other plugins, which is why this is an explicit
	// list rather than an nth-child rule.
	var PAIRED = [ 'first_name', 'last_name', 'nickname', 'display_name' ];

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
				( data.postsUrl ? '<a class="bwx-btn bwx-btn-ghost" href="' + encodeURI( data.postsUrl ) + '">' + escapeHtml( data.viewLabel ) + '</a>' : '' ) +
				'<button type="button" class="bwx-btn bwx-btn-primary" id="bw-profile-save">' + escapeHtml( data.saveLabel ) + '</button>' +
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

		// The native submit stays in the form and stays a real, clickable control
		// — the hero's Save Changes button proxies it via nativeSubmit.click() —
		// it just should not be visible now that the hero button is the one users
		// see. Hidden (not removed, not disabled) so the programmatic click still
		// fires the browser's native form-submit activation.
		if ( submit ) {
			submit.style.display = 'none';
		}

		function startCard( heading ) {
			var raw = heading.textContent.trim();
			// Unrecognised sections (plugin-added, or core wording we don't map)
			// still get a card — they just keep their own title and no subtitle.
			var key = SECTION_KEYS[ raw.toLowerCase() ] || '';

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

			if ( SUBTITLE[ key ] ) {
				var sub = document.createElement( 'p' );
				sub.className = 'bw-profile-card-sub';
				sub.textContent = SUBTITLE[ key ];
				card.appendChild( sub );
			}

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
				// The card's own <h2> replaces this one at the same level, so
				// heading order is preserved and nothing is lost to assistive
				// tech. display:none takes the original out of the accessibility
				// tree entirely — that is intended here, precisely because it
				// would otherwise be announced twice. Hidden rather than removed
				// only so we never destroy markup core might look for.
				node.style.display = 'none';
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

		// Tag each field row with the id of the input it holds, so CSS can pair
		// specific fields without depending on their position — a plugin adding a
		// profile field must not shuffle the pairing.
		grid.querySelectorAll( '.form-table tr' ).forEach( function ( row ) {
			var field = row.querySelector( 'input[id], select[id], textarea[id]' );

			if ( ! field ) {
				return;
			}

			row.setAttribute( 'data-bw-field', field.id );

			if ( PAIRED.indexOf( field.id ) !== -1 ) {
				row.classList.add( 'bw-profile-field-half' );
			}
		} );

		form.insertBefore( grid, form.firstChild );

		if ( data.deleteUrl ) {
			var danger = document.createElement( 'section' );
			danger.className = 'bw-profile-card bw-profile-danger';
			danger.innerHTML =
				'<h2 class="bw-profile-card-title">' + escapeHtml( data.deleteLabel ) + '</h2>' +
				'<p class="bw-profile-card-sub">' + escapeHtml( data.dangerSub ) + '</p>' +
				'<a class="bwx-btn bwx-btn-danger" href="' + encodeURI( data.deleteUrl ) + '">' +
					escapeHtml( data.deleteLabel ) +
				'</a>';
			rightCol.appendChild( danger );
		}

		if ( data.usersUrl ) {
			var back = document.createElement( 'a' );
			back.className = 'bw-profile-back';
			back.href = data.usersUrl;
			back.textContent = '← ' + data.backLabel;
			wrap.insertBefore( back, form );
		}

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
