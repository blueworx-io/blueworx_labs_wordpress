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
		var root = document.querySelector( '[data-widget="pricing-calc"]' );
		if ( ! root ) {
			return;
		}
		var out = root.querySelector( '[data-testid="calc-total"]' );
		var base = { essential: 200, growth: 500, advanced: 750 };
		var state = { support: 'growth', updates: 2, sites: 1, hosting: true };

		function clamp( v, min, max ) {
			return Math.max( min, Math.min( max, v ) );
		}
		function render() {
			var total = base[ state.support ] + ( state.updates - 1 ) * 60 + ( state.sites - 1 ) * 120 + ( state.hosting ? 40 : 0 );
			if ( out ) {
				out.textContent = '$' + total;
			}
		}

		var opts = root.querySelectorAll( '.opt-row .opt' );
		for ( var i = 0; i < opts.length; i++ ) {
			( function ( opt ) {
				opt.addEventListener( 'click', function () {
					state.support = opt.getAttribute( 'data-support' );
					for ( var j = 0; j < opts.length; j++ ) {
						opts[ j ].className = 'opt';
					}
					opt.className = 'opt on';
					render();
				} );
			}( opts[ i ] ) );
		}

		var steppers = root.querySelectorAll( '.stepper' );
		for ( var s = 0; s < steppers.length; s++ ) {
			( function ( stepper ) {
				var field = stepper.getAttribute( 'data-field' );
				var min = parseInt( stepper.getAttribute( 'data-min' ), 10 );
				var max = parseInt( stepper.getAttribute( 'data-max' ), 10 );
				var value = stepper.querySelector( 'b' );
				var buttons = stepper.querySelectorAll( 'button' );
				function change( delta ) {
					state[ field ] = clamp( state[ field ] + delta, min, max );
					if ( value ) {
						value.textContent = state[ field ];
					}
					render();
				}
				buttons[ 0 ].addEventListener( 'click', function () {
					change( -1 );
				} );
				buttons[ 1 ].addEventListener( 'click', function () {
					change( 1 );
				} );
			}( steppers[ s ] ) );
		}

		var hosting = root.querySelector( '.toggle-pill' );
		if ( hosting ) {
			hosting.addEventListener( 'click', function () {
				state.hosting = ! state.hosting;
				hosting.className = state.hosting ? 'toggle-pill on' : 'toggle-pill';
				hosting.setAttribute( 'aria-pressed', state.hosting ? 'true' : 'false' );
				render();
			} );
		}
		render();
	}

	function initSavingsCalc() {
		var root = document.querySelector( '[data-widget="savings-calc"]' );
		if ( ! root ) {
			return;
		}
		var hostingCost = 30;
		var toolboxCost = 30;
		var rows = root.querySelectorAll( '.sv-row' );
		var soloOut = root.querySelector( '[data-testid="solo-total"]' );
		var saveOut = root.querySelector( '[data-testid="savings-line"]' );

		function group( n ) {
			return String( n ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
		}
		function render() {
			var solo = hostingCost;
			for ( var i = 0; i < rows.length; i++ ) {
				if ( '1' === rows[ i ].getAttribute( 'data-on' ) ) {
					solo += parseInt( rows[ i ].getAttribute( 'data-price' ), 10 );
				}
			}
			var save = Math.max( 0, solo - toolboxCost );
			if ( soloOut ) {
				soloOut.textContent = solo;
			}
			if ( saveOut ) {
				saveOut.textContent = 'You save $' + group( save ) + '/mo · $' + group( save * 12 ) + '/yr';
			}
		}

		for ( var i = 0; i < rows.length; i++ ) {
			( function ( row ) {
				var pill = row.querySelector( '.toggle-pill' );
				if ( ! pill ) {
					return;
				}
				pill.addEventListener( 'click', function () {
					var on = '1' === row.getAttribute( 'data-on' );
					row.setAttribute( 'data-on', on ? '0' : '1' );
					pill.className = on ? 'toggle-pill' : 'toggle-pill on';
					pill.setAttribute( 'aria-pressed', on ? 'false' : 'true' );
					render();
				} );
			}( rows[ i ] ) );
		}
		render();
	}

	function initFaqAccordion() {
		var lists = document.querySelectorAll( '.faq-list' );
		for ( var i = 0; i < lists.length; i++ ) {
			( function ( list ) {
				var items = list.querySelectorAll( 'details.faq-item' );
				for ( var j = 0; j < items.length; j++ ) {
					items[ j ].addEventListener( 'toggle', function () {
						if ( ! this.open ) {
							return;
						}
						for ( var k = 0; k < items.length; k++ ) {
							if ( items[ k ] !== this && items[ k ].open ) {
								items[ k ].open = false;
							}
						}
					} );
				}
			}( lists[ i ] ) );
		}
	}

	function initAiPipeline() {
		var shell = document.querySelector( '[data-widget="ai-pipeline"]' );
		if ( ! shell ) {
			return;
		}
		var steps = shell.querySelectorAll( '.ai-pipe-step' );
		if ( ! steps.length ) {
			return;
		}
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}
		var active = 0;
		setInterval( function () {
			steps[ active ].className = 'ai-pipe-step';
			active = ( active + 1 ) % steps.length;
			steps[ active ].className = 'ai-pipe-step on';
		}, 1300 );
	}

	function initFeatureTabs() {
		var root = document.querySelector( '[data-widget="feature-tabs"]' );
		if ( ! root ) {
			return;
		}
		var tabs = root.querySelectorAll( '.tab-bar .tab' );
		var legs = root.querySelectorAll( '.af-legend .af-leg' );
		var chart = root.querySelector( '.af-chart' );
		if ( ! tabs.length || ! chart ) {
			return;
		}
		var xs = [ 0, 65, 130, 195, 260, 325, 390, 455, 520 ];
		var areaEl = chart.querySelector( '.af-area' );
		var lineEl = chart.querySelector( '.af-line' );
		var dotEl = chart.querySelector( '.af-dot' );
		var stops = chart.querySelectorAll( '#afGrad stop' );
		var head = root.querySelector( '.af-text h2' );
		var desc = root.querySelector( '.af-text p' );
		var cta = root.querySelector( '.af-text a' );

		function select( idx ) {
			var tab = tabs[ idx ];
			var pts = tab.getAttribute( 'data-pts' ).split( ' ' );
			var color = tab.getAttribute( 'data-color' );
			var d = '';
			var minY = Infinity;
			var dotI = 0;
			var i;
			for ( i = 0; i < pts.length; i++ ) {
				var y = parseFloat( pts[ i ] );
				d += ( i ? 'L' : 'M' ) + xs[ i ] + ',' + y + ( i === pts.length - 1 ? '' : ' ' );
				if ( y < minY ) {
					minY = y;
					dotI = i;
				}
			}
			if ( lineEl ) {
				lineEl.setAttribute( 'd', d );
				lineEl.setAttribute( 'stroke', color );
			}
			if ( areaEl ) {
				areaEl.setAttribute( 'd', d + ' L520,210 L0,210 Z' );
			}
			if ( dotEl ) {
				dotEl.setAttribute( 'cx', xs[ dotI ] );
				dotEl.setAttribute( 'cy', minY );
				dotEl.setAttribute( 'stroke', color );
			}
			for ( i = 0; i < stops.length; i++ ) {
				stops[ i ].setAttribute( 'stop-color', color );
			}
			for ( i = 0; i < tabs.length; i++ ) {
				tabs[ i ].className = i === idx ? 'tab on' : 'tab off';
			}
			for ( i = 0; i < legs.length; i++ ) {
				legs[ i ].className = i === idx ? 'af-leg on' : 'af-leg off';
			}
			if ( head ) {
				head.textContent = tab.getAttribute( 'data-heading' );
			}
			if ( desc ) {
				desc.textContent = tab.getAttribute( 'data-desc' );
			}
			if ( cta && cta.firstChild ) {
				cta.firstChild.nodeValue = tab.getAttribute( 'data-cta' ) + ' ';
			}
		}

		var t;
		for ( t = 0; t < tabs.length; t++ ) {
			( function ( idx ) {
				tabs[ idx ].addEventListener( 'click', function () {
					select( idx );
				} );
			}( t ) );
		}
		for ( t = 0; t < legs.length; t++ ) {
			( function ( idx ) {
				legs[ idx ].addEventListener( 'click', function () {
					select( idx );
				} );
			}( t ) );
		}
	}

	function init() {
		initBillingToggle();
		initPricingCalc();
		initSavingsCalc();
		initFaqAccordion();
		initAiPipeline();
		initFeatureTabs();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
