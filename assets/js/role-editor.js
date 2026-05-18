( function ( $ ) {
	$( function () {
		$( '.blueworx-role-card' ).each( function () {
			var $card = $( this );
			var role = $card.data( 'blueworx-role' );

			$card.find( '.blueworx-role-toggle' ).on( 'click', function () {
				var $button = $( this );
				var expanded = 'true' === $button.attr( 'aria-expanded' );

				$button.attr( 'aria-expanded', expanded ? 'false' : 'true' );
				$card.toggleClass( 'blueworx-role-card-collapsed', expanded );
			} );

			$card.find( '.blueworx-role-panel' ).each( function ( panelIndex ) {
				var $panel = $( this );
				var panelType = $panel.data( 'blueworx-role-panel' );
				var panelClass = 'blueworx-role-panel-sort-' + panelIndex + '-' + role;
				var groupTemplates = {};

				$panel.find( '.blueworx-role-group' ).each( function () {
					var $group = $( this );
					var key = $group.data( 'blueworx-group' );

					if ( groupTemplates[ key ] ) {
						return;
					}

					groupTemplates[ key ] = $group.clone();
					groupTemplates[ key ].find( '.blueworx-role-item' ).remove();
				} );

				function sortByLabel( first, second, labelName ) {
					var firstLabel = $( first ).data( labelName ).toString().toLowerCase();
					var secondLabel = $( second ).data( labelName ).toString().toLowerCase();

					return firstLabel.localeCompare( secondLabel );
				}

				function mergeDuplicateGroups() {
					$panel.find( '.blueworx-role-group-list' ).each( function () {
						var seen = {};

						$( this ).children( '.blueworx-role-group' ).each( function () {
							var $group = $( this );
							var key = $group.data( 'blueworx-group' );

							if ( seen[ key ] ) {
								seen[ key ].find( '.blueworx-role-item-list' ).append( $group.find( '.blueworx-role-item' ) );
								$group.remove();
								return;
							}

							seen[ key ] = $group;
						} );
					} );
				}

				function restoreMissingGroups() {
					$panel.find( '.blueworx-role-group-list' ).each( function () {
						var $list = $( this );

						$.each( groupTemplates, function ( key, $template ) {
							if ( $list.children( '.blueworx-role-group[data-blueworx-group="' + key + '"]' ).length ) {
								return;
							}

							$list.append( $template.clone() );
						} );
					} );
				}

				function removeEmptyGroups() {
					$panel.find( '.blueworx-role-group' ).each( function () {
						var $group = $( this );

						if ( ! $group.find( '.blueworx-role-item' ).length ) {
							$group.remove();
						}
					} );
				}

				function refreshInputs() {
					mergeDuplicateGroups();
					removeEmptyGroups();
					initializeGroupSortables();
					initializeItemSortables();

					$panel.find( '.blueworx-role-group-list' ).each( function () {
						var groups = $( this ).children( '.blueworx-role-group' ).get();

						groups.sort( function ( first, second ) {
							return sortByLabel( first, second, 'blueworx-group-label' );
						} );

						$( this ).append( groups );
					} );

					$panel.find( '.blueworx-role-item-list' ).each( function () {
						var items = $( this ).children( '.blueworx-role-item' ).get();

						items.sort( function ( first, second ) {
							return sortByLabel( first, second, 'blueworx-item-label' );
						} );

						$( this ).append( items );
					} );

					$panel.find( '.blueworx-role-item' ).each( function () {
						var $item = $( this );
						var item = $item.data( 'blueworx-item' );
						var state = $item.closest( '.blueworx-role-column' ).data( 'blueworx-role-state' );
						var $input = $item.find( '.blueworx-role-item-input' );

						$input.val( item ).removeAttr( 'name' );

						if ( 'capabilities' === panelType && 'allowed' === state ) {
							$input.attr( 'name', 'blueworx_role_caps[' + role + '][]' );
						}

						if ( 'pages' === panelType && ( 'allowed' === state || 'view_only' === state ) ) {
							$input.attr( 'name', 'blueworx_role_pages[' + role + '][' + state + '][]' );
						}
					} );
				}

				function prepareDropTargets() {
					restoreMissingGroups();
					initializeGroupSortables();
					initializeItemSortables();
				}

				function initializeGroupSortables() {
					$panel.find( '.blueworx-role-group-list' ).not( '.ui-sortable' ).addClass( panelClass + '-groups' ).sortable( {
						connectWith: '.' + panelClass + '-groups',
						cursor: 'move',
						dropOnEmpty: true,
						forcePlaceholderSize: true,
						handle: '.blueworx-role-group-handle',
						items: '> .blueworx-role-group',
						placeholder: 'blueworx-menu-order-placeholder',
						tolerance: 'pointer',
						start: prepareDropTargets,
						stop: refreshInputs,
						update: refreshInputs,
						receive: refreshInputs
					} );
				}

				function initializeItemSortables() {
					$.each( groupTemplates, function ( key ) {
						var $itemLists = $panel.find( '.blueworx-role-item-list[data-blueworx-group="' + key + '"]' );
						var itemListClass = panelClass + '-items-' + key.toString().replace( /[^a-zA-Z0-9_-]/g, '-' );

						$itemLists.not( '.ui-sortable' ).addClass( itemListClass ).sortable( {
							connectWith: '.' + itemListClass,
							cursor: 'move',
							dropOnEmpty: true,
							forcePlaceholderSize: true,
							handle: '.blueworx-role-item-handle',
							items: '> .blueworx-role-item',
							placeholder: 'blueworx-menu-order-placeholder',
							tolerance: 'pointer',
							start: prepareDropTargets,
							stop: refreshInputs,
							update: refreshInputs,
							receive: refreshInputs
						} );
					} );
				}

				initializeGroupSortables();
				initializeItemSortables();
				refreshInputs();
			} );
		} );
	} );
}( jQuery ) );
