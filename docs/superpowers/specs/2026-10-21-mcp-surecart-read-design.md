# SureCart in the Claude connector (read-only)

## Why

The plugin's Claude connector (includes/mcp/) can read and edit pages, posts and terms, and work with Forge. It knows nothing about the shop. Luke, the team and clients want to ask Claude anything about a SureCart store — products, prices, orders, subscriptions, customers, settings, how it looks — and get a real answer.

Changing the store is out for now. A wrong price or a touched subscription costs real money, and "Claude touched something it wasn't supposed to" is the failure to avoid. So Claude reads, and when someone wants a change it tells them where in SureCart to make it and what to change, with a direct link where possible.

## Goals

- Anything about the shop and its management can be read. No restriction on which kinds of store data are covered: products, prices and variants, collections, orders, checkouts, invoices, charges, refunds, subscriptions, customers, coupons and promotions, upsells, tax and shipping setup, store settings, and the store's look (brand colours, logo and similar).
- Sales and subscription questions can be answered: totals over a period, recent orders, cancellations, who bought what.
- Every record Claude returns carries a link to the SureCart admin screen where it can be changed, so Claude can say "open this, change that".
- Works for Luke and the team on their sites, and for clients on their own sites.

## Non-goals

- Any write to SureCart. No create, update, delete, refund, cancel, or settings change, and no tool that could trigger one.
- WooCommerce or any other shop plugin.
- Changes to the customer-facing store pages (includes/store/).

## Anti-goals (a failure even if it works)

- A tool that can change SureCart in any way, including by accident through a generic "call any route" tool. Every SureCart call these tools make is a read (GET), fixed in code, not chosen by the caller.
- Someone seeing store data they couldn't already see in WordPress.
- Customer personal data reaching an account that's been set up to have it withheld (see Access).

## Access

- The tools act as the WordPress user who connected Claude, as every connector tool does today. A user only gets store data their account can already reach in SureCart's own admin.
- Each connection only sees the store on that one site.
- Who can connect stays as it is today: administrators only (Luke's decision). Clients connect as administrators of their own site.
- Read-only accounts (the support account and BlueWorx: External viewers) can't connect today, so this is a backstop, tested in PHP rather than end to end: if one of those accounts with data access off ever reaches these tools, every record that carries customer details is refused. That means customers, orders, checkouts and abandoned checkouts, invoices, charges, refunds, subscriptions, and any customer names or emails in a sales summary. Ask the shared read-only guard (includes/readonly-access.php) whether data access is on rather than re-deriving it, but don't widen the guard's list of admin screens and routes; that would change what support staff see in SureCart's own admin. Today that list covers SureCart orders and customers only, not subscriptions. *(My call: Luke said "no restrictions on the read", which I've taken to mean no limit on which store data is covered, not overriding these existing switches.)*

## Closing the existing back door

SureCart keeps some records as WordPress content: product copies, checkout forms, the cart, and collections (as terms). The connector's existing create, edit and trash tools, and its term tools, can reach these today, so Claude can already change the shop. Take SureCart's post types and taxonomies out of the existing write tools. Reading them through the content tools can stay. *(My call, following "keep SureCart read-only": nothing should be able to change the shop.)*

## How it fits

- Follow the Forge pattern (includes/mcp/forge.php): a new includes/mcp/surecart.php with its own definitions and handlers, merged in by server.php. Tools are always registered, and when SureCart isn't active a call says so rather than the tool vanishing. The connector's opening instructions mention the shop only when SureCart is active.
- Every tool is annotated read-only, the same as the existing read tools.
- No new dependencies. Read through SureCart's own WordPress layer (its REST routes run in-process, or its PHP models), not by calling SureCart's cloud API with a token of our own.
- Shape responses to what someone asking about a shop needs: amounts as readable money with currency, dates, statuses, names. Not raw API dumps.
- Lists are paged and filterable (status, date range, search, customer, product) so large stores don't return everything at once.

## Test store

https://demo.305media.co.uk runs SureCart against a real store and has the BlueWorx plugin with Support Access. Use a support key from Luke; never commit it. Checked 2026-10-21: products and brand settings read fine, orders return 403 until Luke opens data access for the session. Ask him to open it when checking money amounts and customer-bearing records. CI has no SureCart, so PHP tests stub SureCart's responses using shapes taken from this store.

## Left to the builder

- The exact tool set. A small number of broad tools (for example list/get by record type, store settings, a sales summary) is preferred over one tool per record type, but either is fine if the anti-goals hold.
- Whether sales and subscription totals come from SureCart's own stats or are worked out from orders.
- Which store settings are worth returning, and the admin URL for each record type.

## Done when

- Asking Claude about products, prices, orders, subscriptions, customers, settings or the store's look on a SureCart site gets an accurate answer with a link to where it can be changed.
- No connector tool can change SureCart data, including SureCart's records stored as WordPress content.
- A read-only account with data access off is refused every customer-bearing record through these tools.
- Answers checked against the demo store.
- On a site without SureCart, the tools say the shop isn't available.
- PHP tests cover the tools, the SureCart-off case and the read-only refusal; the read-only and support-access Playwright suites still pass. Minor version bump and changelog entry.
