( function () {
	'use strict';

	function getPrices( card ) {
		try {
			return JSON.parse( card.getAttribute( 'data-prices' ) || '{}' );
		} catch ( error ) {
			return {};
		}
	}

	function getPrice( prices, cycle ) {
		if ( prices[ cycle ] ) {
			return prices[ cycle ];
		}

		var keys = Object.keys( prices );

		return keys.length ? prices[ keys[0] ] : null;
	}

	function updateCard( card, cycle ) {
		var price = getPrice( getPrices( card ), cycle );
		var priceEl = card.querySelector( '.blueworx-surecart-pricing-price' );
		var button = card.querySelector( '.blueworx-surecart-pricing-button' );

		if ( ! price ) {
			return;
		}

		if ( priceEl ) {
			priceEl.textContent = price.label || '';
		}

		if ( button && price.url ) {
			button.setAttribute( 'href', price.url );
		}
	}

	function updateTable( table, cycle ) {
		table.querySelectorAll( '.blueworx-surecart-pricing-card' ).forEach( function ( card ) {
			updateCard( card, cycle );
		} );
	}

	function initTable( table ) {
		if ( table.getAttribute( 'data-blueworx-ready' ) ) {
			return;
		}

		var defaultCycle = table.getAttribute( 'data-default-cycle' ) || 'month';

		table.setAttribute( 'data-blueworx-ready', '1' );
		updateTable( table, defaultCycle );
	}

	function init() {
		document.querySelectorAll( '.blueworx-surecart-pricing-table' ).forEach( initTable );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.blueworx-surecart-pricing-toggle button' );

		if ( ! button ) {
			return;
		}

		var table = button.closest( '.blueworx-surecart-pricing-table' );
		var cycle = button.getAttribute( 'data-cycle' ) || 'month';

		if ( ! table ) {
			return;
		}

		table.querySelectorAll( '.blueworx-surecart-pricing-toggle button' ).forEach( function ( toggleButton ) {
			toggleButton.classList.toggle( 'is-active', toggleButton === button );
		} );

		updateTable( table, cycle );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/blueworx_surecart_pricing_table.default', function () {
			init();
		} );
	}
}() );
