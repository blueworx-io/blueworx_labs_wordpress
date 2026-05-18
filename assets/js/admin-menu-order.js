( function ( $ ) {
	$( function () {
		var $lists = $( '.blueworx-menu-order-list' );

		if ( ! $lists.length ) {
			return;
		}

		function refreshInputs() {
			$lists.each( function () {
				var state = $( this ).data( 'blueworx-menu-section' );

				$( this ).find( '.blueworx-menu-order-item' ).each( function () {
					var $item = $( this );
					var slug = $item.data( 'blueworx-menu-item' );

					$item.find( '.blueworx-menu-order-input' ).attr( 'name', 'blueworx_admin_menu_order[]' );
					$item.find( '.blueworx-menu-state-input' )
						.val( slug )
						.removeAttr( 'name' );

					if ( 'toggle' === state ) {
						$item.find( '.blueworx-menu-state-input' ).attr( 'name', 'blueworx_toggled_admin_menu_items[]' );
					}

					if ( 'hidden' === state ) {
						$item.find( '.blueworx-menu-state-input' ).attr( 'name', 'blueworx_hidden_admin_menu_items[]' );
					}
				} );
			} );
		}

		$lists.sortable( {
			connectWith: '.blueworx-menu-order-list',
			cursor: 'move',
			dropOnEmpty: true,
			forcePlaceholderSize: true,
			handle: '.blueworx-menu-order-handle',
			items: '> .blueworx-menu-order-item',
			placeholder: 'blueworx-menu-order-placeholder',
			tolerance: 'pointer',
			update: refreshInputs,
			receive: refreshInputs
		} );

		$( document ).on( 'click', '.blueworx-menu-visibility-toggle', function ( event ) {
			var $button = $( this );
			var $item = $button.closest( '.blueworx-menu-order-item' );
			var $list = $item.closest( '.blueworx-menu-order-list' );
			var state = $list.data( 'blueworx-menu-section' );
			var target = 'hidden' === state ? 'main' : 'hidden';
			var $targetList = $( '.blueworx-menu-order-list[data-blueworx-menu-section="' + target + '"]' );

			if ( $button.is( ':disabled' ) || ! $targetList.length ) {
				return;
			}

			event.preventDefault();
			$item.appendTo( $targetList );
			refreshInputs();
		} );

		refreshInputs();
	} );
}( jQuery ) );
