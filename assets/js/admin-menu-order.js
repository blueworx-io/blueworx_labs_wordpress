( function ( $ ) {
	$( function () {
		var $list = $( '.blueworx-menu-order-list' );

		if ( ! $list.length ) {
			return;
		}

		$list.sortable( {
			axis: 'y',
			cursor: 'move',
			placeholder: 'blueworx-menu-order-placeholder'
		} );
	} );
}( jQuery ) );
