# Store pages: how to hook in

Store pages dress SureCart's checkout, thank-you and customer dashboard
pages in the BlueWorx look, and keep the four pages SureCart needs present
and published. The feature is on by default. Each page is found through the
id SureCart records for it (`surecart_*_page_id`), so nothing is dressed
until SureCart has made the pages.

Another BlueWorx plugin can add its own panel to the dashboard, change what
a panel shows, say who the site is, add a checkout footer link, or serve the
dashboard at its own address. Five filters and three public functions are
all you need.

## Add a view

`blueworx_store_views( $views )` — filters the dashboard's views, in nav
order. Add one, remove one, or reorder them.

Each entry:

| Key | What it is |
| --- | --- |
| `key` | Unique, used in the URL and as the array key |
| `label` | Nav link text |
| `title` | Heading on the panel itself |
| `lede` | One-line subtitle under the title |
| `icon` | A Lucide icon name the design system ships — see `assets/blueworx-admin-icons.js` for the full list. There is no `icon_svg`, just the name. |
| `where` | `both`, `side` or `bar` — whether it shows in the desktop sidebar, the phone's bottom bar, or both |
| `blocks` | Array of block names, each rendered in its own card |
| `shortcode` | A shortcode that takes the whole panel, instead of blocks |

Add your view only when your own plugin's dependency is there — check for it
before hooking the filter, the way this example checks for a bookings
plugin. `dashboard` always exists and cannot be removed.

```php
add_filter( 'blueworx_store_views', function ( $views ) {
	if ( ! function_exists( 'my_bookings_active' ) ) {
		return $views;
	}
	$views[] = array(
		'key'       => 'bookings',
		'label'     => 'Bookings',
		'title'     => 'Your bookings',
		'lede'      => 'What you have booked, and what is coming up.',
		'icon'      => 'calendar',
		'where'     => 'both',
		'blocks'    => array(),
		'shortcode' => 'my_bookings_panel',
	);
	return $views;
} );
```

## Change a panel

`blueworx_store_panel( $html, $key, $context )` — filters one panel's
rendered body, after this plugin has drawn it. `$key` is the view's key,
`$context` is from `blueworx_store_context()`.

Prepend to `dashboard` for a welcome message; append to `profile` for extra
fields. Returning `''` when nothing else has filled the panel shows the
"nothing here yet" empty state.

```php
add_filter( 'blueworx_store_panel', function ( $html, $key, $context ) {
	if ( 'dashboard' === $key ) {
		return '<p>Welcome back, ' . esc_html( $context['member_name'] ) . '.</p>' . $html;
	}
	if ( 'profile' === $key ) {
		return $html . '<p>Member since 2019.</p>';
	}
	return $html;
}, 10, 3 );
```

## Say who the site is

`blueworx_store_context( $context )` — filters the site and member details
the store pages draw. Use this when your plugin knows better than
WordPress's own answers — a club with its own crest and its own login page.

| Key | Default |
| --- | --- |
| `site_name` | The site's name |
| `logo_url` | Site icon, falling back to the custom logo, or `''` |
| `home_url` | The site's front page |
| `home_label` | "Back to \<site name\>", or "Back to the site" |
| `login_url` | `wp_login_url()`, returning to the current page |
| `logout_url` | `wp_logout_url()`, returning to the home page |
| `member_name` | Signed-in member's display name, or their login, or `''` |
| `member_email` | Signed-in member's email, or `''` |

```php
add_filter( 'blueworx_store_context', function ( $context ) {
	$context['logo_url']   = 'https://example.com/crest.png';
	$context['login_url']  = home_url( '/members/sign-in/' );
	$context['logout_url'] = home_url( '/members/sign-out/' );
	$context['home_label'] = 'Back to the club';
	return $context;
} );
```

## Checkout footer links

`blueworx_store_checkout_links( $links, $context )` — filters the links in
the checkout page's footer. `$links` already has a "Privacy policy" link
when the site has one set under Settings → Privacy and it is published. A
plugin adding its own privacy link should replace that entry rather than
append a second one.

```php
add_filter( 'blueworx_store_checkout_links', function ( $links, $context ) {
	$links[] = array(
		'label' => 'Terms and conditions',
		'href'  => home_url( '/terms/' ),
	);
	return $links;
}, 10, 2 );
```

## Serve the dashboard yourself

`blueworx_store_dashboard_url( $url )` — filters where the dashboard lives.
Return your own URL to claim it: SureCart's own dashboard page then
redirects there, carrying the requested view and any pending action across.
A signed-out visitor is redirected to the claimed address unchanged — the
claimant enforces sign-in, not this plugin.

```php
add_filter( 'blueworx_store_dashboard_url', function ( $url ) {
	return home_url( '/my-account/' );
} );
```

Then, on your own route, draw the screen and load its assets:

```php
add_action( 'wp_enqueue_scripts', function () {
	if ( is_page( 'my-account' ) ) {
		blueworx_store_enqueue_dashboard();
	}
} );

// Wherever you render the page's content:
echo blueworx_store_dashboard_screen( home_url( '/my-account/' ), home_url( '/' ) );
```

`blueworx_store_enqueue_dashboard()` only loads anything while the Store
pages feature itself is switched on — call it freely, it is a no-op when
the feature is off.

`blueworx_store_page_url( $key )` gives you the address of any of the four
store pages, once they exist: `checkout`, `order-confirmation`, `dashboard`,
`shop`. It returns `''` if that page is missing or unpublished.

## Testing your integration

`tests/global-setup.js` writes a small mu-plugin that hooks all five
filters the way another plugin would — a worked example of a whole
integration, including a fixture view, a panel prepend, context overrides,
an extra checkout link, and claiming the dashboard address. Read it before
writing your own.

## Your own CSS

This plugin's classes are `blueworx-store__*` and `blueworx-checkout__*`. A
plugin styling its own panel should use its own class names — don't invent
`bw-` classes, those belong to the design system.
