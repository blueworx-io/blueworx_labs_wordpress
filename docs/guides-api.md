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
| `id`    | yes      | Unique, `sanitize_key`-safe. Used as the DOM hook, so keep it stable. |
| `title` | yes      | Plain text. Escaped on output. |
| `tab`   | no       | Tab id. Unknown or omitted lands the guide under **Other**. |
| `body`  | yes      | HTML, filtered through `wp_kses_post` on output. |

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

## What it does not do

There is no capability argument. The page itself requires `manage_options`, and
this plugin's own guides are additionally hidden when their feature is switched
off. If your guide should only appear in some circumstances, decide that inside
your filter — return early, or omit the guide:

```php
add_filter( 'blueworx_guides', function ( $guides ) {
    if ( ! acme_shipping_enabled() ) {
        return $guides;
    }

    // …add the guide.
    return $guides;
} );
```
