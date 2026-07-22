/**
 * BlueWorx public marketing widgets (Plan 3a).
 *
 * Progressive enhancement: the templates render each widget's correct default
 * state; this script only rewrites text and toggles classes on interaction.
 * Each init no-ops when its [data-widget] marker is absent, so the one file is
 * safe on every owned page.
 */
( function () {
	'use strict';

	function initBillingToggle() {
		var toggle = document.querySelector( '[data-widget="billing-toggle"]' );
		if ( ! toggle ) {
			return;
		}
		var btns = toggle.querySelectorAll( 'button' );
		if ( btns.length < 2 ) {
			return;
		}

		function apply( annual ) {
			btns[ 0 ].className = annual ? '' : 'on';
			btns[ 1 ].className = annual ? 'on' : '';
			btns[ 0 ].setAttribute( 'aria-pressed', annual ? 'false' : 'true' );
			btns[ 1 ].setAttribute( 'aria-pressed', annual ? 'true' : 'false' );

			var prices = document.querySelectorAll( '.plan-price' );
			for ( var i = 0; i < prices.length; i++ ) {
				var b = prices[ i ].querySelector( 'b' );
				var em = prices[ i ].querySelector( 'em' );
				if ( b ) {
					b.textContent = '$' + ( annual ? prices[ i ].getAttribute( 'data-price-a' ) : prices[ i ].getAttribute( 'data-price-m' ) );
				}
				if ( em ) {
					em.textContent = annual ? em.getAttribute( 'data-sub-a' ) : em.getAttribute( 'data-sub-m' );
				}
			}
		}

		toggle.setAttribute( 'role', 'group' );
		btns[ 0 ].addEventListener( 'click', function () {
			apply( false );
		} );
		btns[ 1 ].addEventListener( 'click', function () {
			apply( true );
		} );
		apply( false );
	}

	function initPricingCalc() {
		// Body added in Task 2.
	}

	function initSavingsCalc() {
		// Body added in Task 3.
	}

	function init() {
		initBillingToggle();
		initPricingCalc();
		initSavingsCalc();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
