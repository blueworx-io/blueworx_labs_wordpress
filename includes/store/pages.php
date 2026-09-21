<?php
/**
 * Whether the shop's own pages are there and reachable, and the repair when
 * they are not.
 *
 * The shop runs the shop. This plugin does not build checkouts, take payments
 * or style SureCart's screens — it makes sure the pages SureCart needs exist,
 * so the links pointing at them go somewhere. That is the whole job here.
 *
 * Which is why the repair does not write these pages itself. It calls
 * SureCart's own seeder, the same one its activation runs, so the pages come
 * out exactly as SureCart makes them and stay right when SureCart changes. The
 * one thing done directly is republishing a page that already exists and has
 * been trashed — the seeder would create a second page beside it rather than
 * bring it back, leaving the club with two checkouts and its links pointing at
 * the wrong one.
 *
 * Nothing here runs on its own. The controller shows a notice and an owner
 * presses the button.
 *
 * Page names and option keys are read from SureCart's source, and the whole
 * flow is verified against a real install — see
 * docs/integrations/surecart-notes.md.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The container key SureCart registers its page seeder under. */
define( 'BLUEWORX_STORE_SEEDER', 'surecart.pages.seeder' );

/** A published page is the only kind a buyer can reach. */
define( 'BLUEWORX_STORE_REACHABLE', 'publish' );

/**
 * Whether SureCart is loaded on this request.
 *
 * Asked inside hooks only: SureCart's folder sorts after this plugin's, so at
 * file scope the answer is always no.
 *
 * @return bool
 */
function blueworx_store_surecart_active() {
	return class_exists( 'SureCart' ) || defined( 'SURECART_PLUGIN_FILE' );
}

/**
 * The pages the shop needs, and what a visitor loses without each.
 *
 * @return array<string,array{label:string,consequence:string}>
 */
function blueworx_store_pages() {
	return array(
		'checkout'           => array(
			'label'       => 'checkout page',
			'consequence' => 'nobody can pay and membership Join buttons fall back to your contact page',
		),
		'order-confirmation' => array(
			'label'       => 'order confirmation page',
			'consequence' => 'anyone who pays sees nothing afterwards',
		),
		'dashboard'          => array(
			'label'       => 'customer dashboard',
			'consequence' => 'members have nowhere to manage what they have paid for',
		),
		'shop'               => array(
			'label'       => 'shop page',
			'consequence' => 'your products have nowhere to be listed',
		),
	);
}

/**
 * The order confirmation page, exactly as SureCart writes it.
 *
 * Its activation seeder makes the checkout, dashboard and shop pages but not
 * this one — only its onboarding does, and a club that never walks through
 * onboarding is left with a permanent warning that anyone who pays will see
 * nothing afterwards. So this plugin makes it, using SureCart's own slug,
 * title and block from Install\InstallService::createPages(). Pure, so what
 * it writes is asserted rather than taken on trust.
 *
 * @return array{slug:string,option:string,title:string,content:string}
 */
function blueworx_store_confirmation_page() {
	return array(
		'slug'    => 'order-confirmation',
		'option'  => 'order-confirmation',
		'title'   => 'Thank you!',
		'content' => '<!-- wp:surecart/order-confirmation --> <!-- /wp:surecart/order-confirmation -->',
	);
}

/**
 * Where SureCart keeps a page's id. Built the way SureCart builds it
 * (PageService::getOptionName), so the two cannot drift.
 *
 * @param string $key Page key.
 * @return string
 */
function blueworx_store_option_name( $key ) {
	return 'surecart_' . $key . '_page_id';
}

/**
 * What state one page is in. Pure — blueworx_store_page_status() below reads the
 * arguments off the site.
 *
 * @param bool   $shop_active Whether SureCart is here at all.
 * @param int    $page_id     The id SureCart has on record, 0 for none.
 * @param string $post_status That page's status, '' when the id points at nothing.
 * @return string
 */
function blueworx_store_decide( $shop_active, $page_id, $post_status ) {
	if ( ! $shop_active ) {
		return 'no-shop';
	}
	if ( $page_id > 0 && BLUEWORX_STORE_REACHABLE === $post_status ) {
		return 'ok';
	}
	if ( $page_id > 0 && '' !== $post_status ) {
		return 'unpublished';
	}
	return 'missing';
}

/**
 * Only the pages with something wrong. Pure.
 *
 * @param array $statuses From blueworx_store_page_statuses().
 * @return array<string,string>
 */
function blueworx_store_problems( $statuses ) {
	return array_filter(
		$statuses,
		static function ( $status ) {
			return in_array( $status, array( 'missing', 'unpublished' ), true );
		}
	);
}

/**
 * Which of those the repair button can actually put right. Pure.
 *
 * All of them, now the confirmation page is made here too: an unpublished
 * page is republished, a missing one is created. Kept as a named answer
 * rather than folded away because the notice is written around the idea
 * that some problems might not be fixable, and a page SureCart stops
 * creating would put that back.
 *
 * @param array $problems From blueworx_store_problems().
 * @param array $pages    From blueworx_store_pages().
 * @return array<string,string>
 */
function blueworx_store_repairable( $problems, $pages ) {
	return array_filter(
		$problems,
		static function ( $status, $key ) use ( $pages ) {
			return 'unpublished' === $status || isset( $pages[ $key ] );
		},
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * The stored id for a page, or 0 when the option is absent or junk.
 *
 * @param string $key Page key.
 * @return int
 */
function blueworx_store_page_id( $key ) {
	if ( ! function_exists( 'get_option' ) ) {
		return 0;
	}
	$stored = get_option( blueworx_store_option_name( $key ), 0 );
	return is_numeric( $stored ) ? max( 0, (int) $stored ) : 0;
}

/**
 * The state of one page on this site.
 *
 * @param string $key Page key.
 * @return string
 */
function blueworx_store_page_status( $key ) {
	$active  = blueworx_store_surecart_active();
	$page_id = $active ? blueworx_store_page_id( $key ) : 0;
	return blueworx_store_decide( $active, $page_id, blueworx_store_post_status( $page_id ) );
}

/**
 * The state of every page.
 *
 * @return array<string,string>
 */
function blueworx_store_page_statuses() {
	$out = array();
	foreach ( array_keys( blueworx_store_pages() ) as $key ) {
		$out[ $key ] = blueworx_store_page_status( $key );
	}
	return $out;
}

/**
 * A page's URL, or '' when there is not a reachable one.
 *
 * '' is the honest answer rather than a failure: for the checkout page it
 * makes every membership tier fall back to its typed price and the contact
 * link, which is what a club with no working shop should show.
 *
 * @param string $key Page key.
 * @return string
 */
function blueworx_store_page_url( $key ) {
	if ( 'ok' !== blueworx_store_page_status( $key ) || ! function_exists( 'get_permalink' ) ) {
		return '';
	}
	$url = get_permalink( blueworx_store_page_id( $key ) );
	return is_string( $url ) ? $url : '';
}

/**
 * Whether SureCart's seeder can be reached to create what is missing. False
 * on a shop whose internals have moved on, in which case the notice says so
 * rather than offering a button that would do nothing.
 *
 * @return bool
 */
function blueworx_store_can_seed() {
	if ( ! blueworx_store_surecart_active() || ! is_callable( array( 'SureCart', 'resolve' ) ) ) {
		return false;
	}
	try {
		return is_object( SureCart::resolve( BLUEWORX_STORE_SEEDER ) );
	} catch ( Throwable $e ) {
		return false;
	}
}

/**
 * Put right what can be put right: republish the pages that are only
 * trashed, then let SureCart seed whatever is still missing.
 *
 * Returns whether every problem it could act on is now resolved.
 *
 * @return bool
 */
function blueworx_store_repair() {
	$problems = blueworx_store_problems( blueworx_store_page_statuses() );

	foreach ( $problems as $key => $status ) {
		if ( 'unpublished' === $status ) {
			blueworx_store_publish_existing( blueworx_store_page_id( $key ) );
		}
	}

	$still_missing = array_filter(
		blueworx_store_problems( blueworx_store_page_statuses() ),
		static function ( $status ) {
			return 'missing' === $status;
		}
	);
	if ( array() !== $still_missing && blueworx_store_can_seed() ) {
		blueworx_store_seed();
	}

	return array() === blueworx_store_repairable( blueworx_store_problems( blueworx_store_page_statuses() ), blueworx_store_pages() );
}

/**
 * Hand page creation back to SureCart. Its seeder is idempotent — it finds
 * or creates, so pages already there are left exactly as they are.
 *
 * @return void
 */
function blueworx_store_seed() {
	try {
		SureCart::resolve( BLUEWORX_STORE_SEEDER )->seed();
	} catch ( Throwable $e ) {
		// A shop that cannot seed is reported by the next status read; there
		// is nothing to say here that the notice will not say better.
		return;
	}
	blueworx_store_create_confirmation();
}

/**
 * Whether the confirmation page is ours to make. Pure.
 *
 * Never created is ours; removed is not. SureCart records a page's id under
 * an option and leaves it there, so an id still on record means the page
 * existed once and somebody took it away — trashed it, or deleted it
 * outright. Putting it back uninvited overrules a club that meant it. No id
 * at all means nothing ever made one, which is the case every club starts
 * in and the one the warning was really about.
 *
 * @param bool   $shop_active Whether SureCart is here at all.
 * @param int    $page_id     The id SureCart has on record, 0 for none.
 * @param string $post_status That page's status, '' when the id points at nothing.
 * @return bool
 */
function blueworx_store_should_create_confirmation( $shop_active, $page_id, $post_status ) {
	if ( ! $shop_active ) {
		return false;
	}
	return 0 === $page_id && '' === $post_status;
}

/**
 * Make it if it is ours to make.
 *
 * Runs on activation and again in the admin, because the usual order is
 * this plugin first and the shop afterwards — a club that adds SureCart next
 * month should not have to find a warning and press a button to finish a job
 * neither of them ever asked to do.
 *
 * Cheap on the sites where it does nothing: a club with no shop is answered
 * before a single option is read.
 *
 * @return void
 */
function blueworx_store_ensure_confirmation() {
	$active  = blueworx_store_surecart_active();
	$page_id = $active ? blueworx_store_page_id( 'order-confirmation' ) : 0;
	if ( ! blueworx_store_should_create_confirmation( $active, $page_id, blueworx_store_post_status( $page_id ) ) ) {
		return;
	}
	blueworx_store_create_confirmation();
}

/**
 * The one page the seeder leaves behind.
 *
 * Written through SureCart's own page service rather than wp_insert_post, so
 * SureCart records the id under the option it looks the page up by — a page
 * created any other way would exist and still not be found. findOrCreate()
 * checks that option first, so a club that already has one keeps it, however
 * they have since renamed or rewritten it, and pressing the button twice
 * makes one page rather than two.
 *
 * @return void
 */
function blueworx_store_create_confirmation() {
	if ( ! is_callable( array( 'SureCart', 'pages' ) ) ) {
		return;
	}
	$page = blueworx_store_confirmation_page();
	try {
		SureCart::pages()->findOrCreate(
			$page['slug'],
			$page['option'],
			$page['title'],
			$page['content'],
			'',
			BLUEWORX_STORE_REACHABLE
		);
	} catch ( Throwable $e ) {
		// Same as the seeder above: the next status read tells the owner.
		return;
	}
}

/**
 * Bring a trashed or draft page back into public view.
 *
 * @param int $page_id Page id.
 * @return bool
 */
function blueworx_store_publish_existing( $page_id ) {
	if ( $page_id <= 0 || ! function_exists( 'wp_update_post' ) ) {
		return false;
	}
	$result = wp_update_post(
		array(
			'ID'          => $page_id,
			'post_status' => BLUEWORX_STORE_REACHABLE,
		)
	);
	return is_numeric( $result ) && (int) $result > 0;
}

/**
 * A page's status, or '' when the id points at nothing.
 *
 * get_post_status() answers false for a missing post and a status string
 * otherwise, including 'trash' — the case a bare permalink lookup silently
 * turned into a dead link.
 *
 * @param int $page_id Page id.
 * @return string
 */
function blueworx_store_post_status( $page_id ) {
	if ( $page_id <= 0 || ! function_exists( 'get_post_status' ) ) {
		return '';
	}
	$status = get_post_status( $page_id );
	return is_string( $status ) ? $status : '';
}
