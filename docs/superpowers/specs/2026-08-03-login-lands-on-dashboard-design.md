# Login lands on the dashboard

## The problem

On a site with LatePoint installed, signing in to the backend drops you on the
LatePoint screen instead of the WordPress dashboard. LatePoint hooks core's
`login_redirect` filter and nothing in this plugin competes with it, so it wins
every time.

## What it does

A new feature, `login_redirect`, sends anyone who can reach the admin area to
the dashboard when they sign in — unless they asked for somewhere specific.

Registered under Security & Access, on by default. Default-on is right here:
the behaviour it restores is core WordPress's own, so an existing install gets
what it expected in the first place rather than a surprise.

## How it works

One callback on the `login_redirect` filter, at a late priority so it runs
after LatePoint's. Core passes the filter both the redirect as it currently
stands — LatePoint's, by that point — and the destination originally requested,
which is what makes this a clean read rather than string-matching somebody
else's URL.

The rules, in order:

1. Not a real user (a `WP_Error` from a failed sign-in): leave it alone.
2. The person cannot reach the admin area — no `edit_posts` and no
   `manage_options`: leave it alone. LatePoint customers keep the LatePoint
   flow, which is the whole point of that plugin.
3. A destination was explicitly requested: honour it. This keeps deep links,
   "please log in to view this page" bounces, and password-reset return paths
   working.
4. Otherwise: the dashboard.

The capability used for rule 2 is filterable via
`blueworx_login_redirect_capabilities`, so a site with an unusual role that
belongs in the backend can say so without editing the plugin.

## Deliberately not covered

Single sign-on runs its own redirect through the `blueworx_sso_login_redirect`
filter and never reaches `login_redirect`. It is left as it is; an SSO sign-in
already lands where its own settings say.

## Risk

If LatePoint redirects on the `wp_login` action rather than the filter, it
short-circuits before any filter runs and this will not beat it. The filter is
the documented and far more common approach, so it is what we build; if it
proves insufficient on a live site the fix is a second hook, not a redesign.

## Testing

A Playwright spec that signs in as an administrator and asserts the dashboard,
and asserts the redirect is left alone when the feature is switched off.
