/**
 * BlueWorx Edit Menu.
 *
 * Native HTML5 drag-and-drop plus keyboard arrows. No jQuery, no jQuery UI.
 *
 * Drag is an enhancement; the arrow buttons are the accessible route and work
 * with the pointer too. Every move rewrites the row's hidden inputs so the form
 * stays a plain POST.
 */
( function () {
	var editor = document.querySelector( '.bw-menu-editor' );

	if ( ! editor ) {
		return;
	}

	var dragging = null;

	/**
	 * Syncs a row's hidden inputs to whichever group section it now sits in.
	 *
	 * @param {HTMLElement} item Row element.
	 */
	function syncItem( item ) {
		var section = item.closest( '.bw-menu-editor-group' );

		if ( ! section ) {
			return;
		}

		var group = section.getAttribute( 'data-group' );
		var slug = item.getAttribute( 'data-slug' );
		var groupInput = item.querySelector( '.bw-menu-editor-group-input' );
		var hiddenInput = item.querySelector( '.bw-menu-editor-hidden-input' );

		if ( groupInput ) {
			groupInput.name = 'blueworx_admin_menu_groups[' + slug + ']';
			groupInput.value = group;
		}

		// The hidden bucket is expressed by a second input, present only there.
		if ( 'hidden' === group && ! hiddenInput ) {
			var input = document.createElement( 'input' );
			input.type = 'hidden';
			input.className = 'bw-menu-editor-hidden-input';
			input.name = 'blueworx_hidden_admin_menu_items[]';
			input.value = slug;
			item.appendChild( input );
		} else if ( 'hidden' !== group && hiddenInput ) {
			hiddenInput.remove();
		}
	}

	function syncAll() {
		editor.querySelectorAll( '.bw-menu-editor-item' ).forEach( syncItem );
	}

	/**
	 * Gets every group list, in document order.
	 *
	 * @return {HTMLElement[]} Lists.
	 */
	function lists() {
		return Array.prototype.slice.call( editor.querySelectorAll( '.bw-menu-editor-list' ) );
	}

	/**
	 * Moves a row one step, crossing into the adjacent group at a boundary.
	 *
	 * @param {HTMLElement} item      Row element.
	 * @param {number}      direction -1 up, 1 down.
	 */
	function move( item, direction ) {
		var list = item.parentElement;
		var sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;

		if ( sibling ) {
			if ( direction < 0 ) {
				list.insertBefore( item, sibling );
			} else {
				list.insertBefore( sibling, item );
			}
		} else {
			// At a boundary: hop into the neighbouring group.
			var all = lists();
			var index = all.indexOf( list );
			var target = all[ index + direction ];

			if ( ! target ) {
				return;
			}

			if ( direction < 0 ) {
				target.appendChild( item );
			} else {
				target.insertBefore( item, target.firstElementChild );
			}
		}

		syncItem( item );
	}

	editor.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.bw-menu-editor-up, .bw-menu-editor-down' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		var item = button.closest( '.bw-menu-editor-item' );
		// classList.contains takes a class NAME, not a selector — no leading dot.
		move( item, button.classList.contains( 'bw-menu-editor-up' ) ? -1 : 1 );
		button.focus();
	} );

	editor.addEventListener( 'dragstart', function ( event ) {
		var item = event.target.closest( '.bw-menu-editor-item' );

		if ( ! item ) {
			return;
		}

		dragging = item;
		item.classList.add( 'is-dragging' );
		event.dataTransfer.effectAllowed = 'move';
		// Firefox will not start a drag without data set.
		event.dataTransfer.setData( 'text/plain', item.getAttribute( 'data-slug' ) );
	} );

	editor.addEventListener( 'dragend', function () {
		if ( dragging ) {
			dragging.classList.remove( 'is-dragging' );
			dragging = null;
		}

		editor.querySelectorAll( '.is-drop-target' ).forEach( function ( el ) {
			el.classList.remove( 'is-drop-target' );
		} );
	} );

	editor.addEventListener( 'dragover', function ( event ) {
		var list = event.target.closest( '.bw-menu-editor-list' );

		if ( ! list || ! dragging ) {
			return;
		}

		// Required, or the browser refuses the drop.
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
		list.classList.add( 'is-drop-target' );

		var over = event.target.closest( '.bw-menu-editor-item' );

		if ( over && over !== dragging ) {
			var box = over.getBoundingClientRect();
			var after = event.clientY > box.top + box.height / 2;
			list.insertBefore( dragging, after ? over.nextElementSibling : over );
		} else if ( ! over ) {
			list.appendChild( dragging );
		}
	} );

	editor.addEventListener( 'dragleave', function ( event ) {
		var list = event.target.closest( '.bw-menu-editor-list' );

		if ( list && ! list.contains( event.relatedTarget ) ) {
			list.classList.remove( 'is-drop-target' );
		}
	} );

	editor.addEventListener( 'drop', function ( event ) {
		event.preventDefault();

		// Sync every row, not just the dragged one. A drop is cheap and rare, the
		// pass is idempotent, and syncing only `dragging` would miss a row moved
		// by anything other than a real drag — including a programmatic move that
		// dispatches drop without a dragstart, where `dragging` is null and the
		// row's group input would silently keep its old value.
		syncAll();
	} );

	syncAll();
}() );
