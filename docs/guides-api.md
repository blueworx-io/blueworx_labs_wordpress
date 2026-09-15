# Adding guides from another plugin

The Guides page (BlueWorx > Guides) is extensible. Another plugin adds its own
guides, and its own tab, through two filters. Nothing needs to be registered
first and load order does not matter — both filters run when the page renders.

## Add a guide

```php
add_filter( 'blueworx_guides', function ( $guides ) {
    $guides[] = array(
        'id'    => 'acme-shipping-zones',
        'title' => __( 'Setting up shipping zones', 'acme' ),
        'tab'   => 'acme',
        'body'  => '<p>' . esc_html__( 'Open Acme > Shipping and…', 'acme' ) . '</p>',
    );

    return $guides;
} );
```

| Key     | Required | Notes |
| ------- | -------- | ----- |
| `id`         | yes | Unique, `sanitize_key`-safe. Used as the DOM hook, so keep it stable. |
| `title`      | yes | Plain text. Escaped on output. |
| `tab`        | no  | Tab id. Unknown or omitted lands the guide under **Other**. |
| `body`       | yes | HTML, filtered through `wp_kses_post` on output. |
| `capability` | no  | Who this guide is for. Defaults to the tab's own capability. |

## Add a tab

```php
add_filter( 'blueworx_guide_tabs', function ( $tabs ) {
    $tabs['acme'] = __( 'Acme Plugin', 'acme' );

    return $tabs;
} );
```

Tabs render in array order, so `array_merge` puts yours at the end and
`array_splice` places it mid-list. A tab with no guides in it is not rendered —
you cannot create an empty tab.

## Write it as a task

Every guide on the page is one task: where to go, numbered steps, what happens
next. Build the body with the helper so yours reads the same way:

```php
'body' => blueworx_guide_body( array(
    'where' => __( 'Acme > Shipping', 'acme' ),
    'steps' => array(
        __( 'Press *Add zone*.', 'acme' ),
        __( 'Type the postcodes and press *Save*.', 'acme' ),
    ),
    'then'  => __( 'Orders to those postcodes now get that rate.', 'acme' ),
) ),
```

Text in `*asterisks*` is shown as emphasis — use it for the label on a button.
Everything else is escaped. Guard with `function_exists( 'blueworx_guide_body' )`
and fall back to plain HTML if your plugin can run without BlueWorx.

## Add a product

A product is the top row of tabs — BlueWorx, WordPress, and each plugin the
site runs. Register one, then say which tabs belong to it:

```php
add_filter( 'blueworx_guide_products', function ( $products ) {
    $products['acme'] = __( 'Acme', 'acme' );
    return $products;
} );

add_filter( 'blueworx_guide_tab_products', function ( $map ) {
    $map['acme-shipping'] = 'acme';
    return $map;
} );
```

A tab not in the map is treated as BlueWorx's own, so it is administrator-only.
A product with no guides is not shown.

## What the page does with your input

- **`body` is filtered through `wp_kses_post`.** Script tags, event handlers and
  anything else outside safe post markup are stripped. Do not rely on inline
  JavaScript or styles.
- **`title` is escaped as plain text.** Markup in a title will show as
  characters, not as formatting.
- **An unknown `tab` does not lose the guide.** It moves to **Other**. Losing
  another plugin's content is worse than losing its grouping, so nothing is
  dropped for a missing tab registration.
- **The first registration of an `id` wins.** A guide reusing an id that already
  exists is ignored, so a third party cannot displace a built-in guide.
- **Order within a tab is registration order.** Built-in guides come first.

## Who sees what

The page needs `edit_posts`, so subscribers and shop customers never get the
row. Above that, a guide is shown only to somebody who could act on it:

- **Your guide's `capability`** decides who sees the card. Left unset it is the
  capability its tab describes, which is also what the role pills on the card
  say — so the pills and the gate cannot drift apart.
- **A section can be gated as a whole** through `blueworx_guide_product_capability`.
  BlueWorx's own section is `manage_options`, because every screen it describes
  is. A guide behind a gated section needs both capabilities.
- **A tab with nothing left in it disappears**, and so does a section — you do
  not have to hide them yourself.

A guide with no `capability` and no recognised tab lands under **Other**, which
is treated as ours and therefore `manage_options`. Set `capability` explicitly
if your guide is meant for anyone else.

## What it does not do

This plugin's own guides are additionally hidden when their feature is switched
off. If your guide should only appear in some other circumstance, decide that
inside your filter — return early, or omit the guide:

```php
add_filter( 'blueworx_guides', function ( $guides ) {
    if ( ! acme_shipping_enabled() ) {
        return $guides;
    }

    // …add the guide.
    return $guides;
} );
```
