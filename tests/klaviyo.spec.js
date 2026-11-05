/**
 * BlueWorx > Klaviyo.
 *
 * The PHP check covers the marketing team's rules. This covers what only a real
 * site can show: the webhook address refuses a forged delivery and ignores a
 * repeat, the queue sends the right requests in the right order, Klaviyo being
 * down loses nothing, the two forms reach the queue, and the screens work.
 *
 * Klaviyo and SureCart are both faked by tests/fixtures/klaviyo-mock.php. Nothing
 * here can reach either real service.
 */

import { createHmac } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath, URL } from 'node:url';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const SECRET = 'fixture-signing-secret';
const ROUTE = '/?rest_route=/blueworx/v1/klaviyo/surecart';
const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-klaviyo';
const LOG_PATH = '/wp-admin/admin.php?page=blueworx-klaviyo-log';

/**
 * Runs one fixture command and parses its JSON answer.
 *
 * @param {...(string|number)} args Command and its arguments.
 * @return {any} Parsed output.
 */
function fixture(...args) {
  const file = path.join(__dirname, 'fixtures', 'klaviyo.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  const out = execFileSync('php', [file, wpLoad, ...args.map(String)], { encoding: 'utf8' }).trim();

  return JSON.parse(out.split('\n').pop());
}

/**
 * Posts an event to the webhook address, signed the way SureCart signs it.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {object} event The event.
 * @param {object} [options] A wrong secret, or an old timestamp.
 * @return {Promise<import('@playwright/test').APIResponse>}
 */
function deliver(request, event, { secret = SECRET, age = 0 } = {}) {
  const body = JSON.stringify(event);
  const timestamp = String(Math.floor(Date.now() / 1000) - age);
  const signature = createHmac('sha256', secret).update(`${timestamp}.${body}`).digest('hex');

  return request.post(ROUTE, {
    data: body,
    headers: {
      'content-type': 'application/json',
      'x-webhook-signature': signature,
      'x-webhook-timestamp': timestamp,
    },
  });
}

/** The SureCart records the deliveries below refer to. */
const SUBSCRIPTION = {
  id: 'sub1',
  status: 'active',
  created_at: '2026-11-04T09:00:00+00:00',
  current_period_end_at: '2027-11-04T09:00:00+00:00',
  cancel_at_period_end: false,
  customer: { id: 'cus1', email: 'jane+test@example.com', first_name: 'Jane', last_name: 'Smith' },
  price: {
    id: 'price1',
    amount: 251900,
    recurring_interval: 'year',
    product: { id: 'prod1', slug: 'founding-membership', name: 'Founding Membership' },
  },
  current_period: { id: 'per1', status: 'paid', payment_retry_count: 1, checkout: { amount_due: 251900 } },
};

const PURCHASE = {
  id: 'pur1',
  subscription: 'sub1',
  initial_order: {
    id: 'ord1',
    checkout: { paid_amount: 251900, paid_at: '2026-11-04T09:00:00+00:00', metadata: { marketing_opt_in: 'on' } },
  },
};

const RECORDS = {
  purchase: { pur1: PURCHASE, pur2: { ...PURCHASE, id: 'pur2', subscription: null } },
  subscription: { sub1: SUBSCRIPTION },
};

const paths = (calls) => calls.map((call) => `${call.method} ${new URL(call.url).pathname}`);

test.describe('Klaviyo connection', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No test site configured.');
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(() => {
    fixture('setup');
    fixture('records', JSON.stringify(RECORDS));
  });

  test.afterAll(() => {
    fixture('cleanup');
  });

  test.describe('the webhook address', () => {
    test('is not there while the function is off', async ({ request }) => {
      fixture('off');

      const response = await deliver(request, { id: 'evt0', type: 'purchase.created', data: { object: { id: 'pur1' } } });
      expect(response.status()).toBe(404);
    });

    test('refuses a forged delivery, an old one, and an unsigned one', async ({ request }) => {
      const event = { id: 'evt1', type: 'purchase.created', data: { object: { id: 'pur1' } } };

      expect((await deliver(request, event, { secret: 'wrong' })).status()).toBe(401);
      expect((await deliver(request, event, { age: 3600 })).status()).toBe(401);
      expect((await request.post(ROUTE, { data: JSON.stringify(event) })).status()).toBe(401);

      expect(fixture('drain').calls, 'nothing refused reaches Klaviyo').toHaveLength(0);
    });

    test('takes a genuine delivery once, however many times it arrives', async ({ request }) => {
      const event = { id: 'evt2', type: 'purchase.created', data: { object: { id: 'pur1' } } };

      const first = await deliver(request, event);
      expect(first.status()).toBe(200);
      expect((await first.json()).status).toBe('queued');

      const second = await deliver(request, event);
      expect(second.status()).toBe(200);
      expect((await second.json()).status).toBe('repeat');

      const { calls } = fixture('drain');
      expect(calls.filter((call) => call.url.endsWith('/api/events'))).toHaveLength(1);

      // Arriving again after it has been sent is still a repeat.
      expect((await (await deliver(request, event)).json()).status).toBe('repeat');
    });

    test('leaves test orders out until asked, and ignores other events', async ({ request }) => {
      const testOrder = { id: 'evt3', type: 'purchase.created', data: { object: { id: 'pur1', live_mode: false } } };

      expect((await (await deliver(request, testOrder)).json()).status).toBe('test_mode');

      fixture('option', 'send_test_mode', '1');
      expect((await (await deliver(request, testOrder)).json()).status).toBe('queued');

      const other = { id: 'evt4', type: 'product.updated', data: { object: { id: 'x' } } };
      expect((await (await deliver(request, other)).json()).status).toBe('ignored');
    });
  });

  test.describe('the queue', () => {
    test('moves a reserved member to paid, and sends the event and the Members list', async ({ request }) => {
      fixture('profile', JSON.stringify({ email: 'jane+test@example.com', first_name: 'Janet', properties: { founding_status: 'reserved' } }));

      await deliver(request, { id: 'evt5', type: 'purchase.created', data: { object: { id: 'pur1' } } });

      const { calls, jobs, log } = fixture('drain');

      expect(paths(calls)).toEqual([
        'GET /api/profiles',
        'POST /api/profile-import',
        'POST /api/events',
        'POST /api/profile-subscription-bulk-create-jobs',
      ]);

      // A plus sign in an address has to survive the lookup.
      expect(decodeURIComponent(calls[0].url)).toContain('equals(email,"jane+test@example.com")');
      expect(calls[0].url).not.toContain('+test');

      for (const call of calls) {
        expect(call.revision).toBe('2026-10-15');
        expect(call.auth).toBe('Klaviyo-API-Key pk_test_fixture_key_abcd');
      }

      const attributes = calls[1].body.data.attributes;
      expect(attributes.email).toBe('jane+test@example.com');
      expect(attributes.first_name, 'an existing name is kept').toBeUndefined();
      expect(attributes.properties.founding_status).toBe('paid');
      expect(attributes.properties.founding_member).toBe(true);
      expect(attributes.properties.membership_status).toBe('active');
      expect(attributes.properties.billing_interval).toBe('annual');
      expect(attributes.properties.marketing_consent).toBe(true);
      expect(attributes.properties.founding_member_number).toBeUndefined();

      const event = calls[2].body.data.attributes;
      expect(event.metric.data.attributes.name).toBe('Started Membership');
      expect(event.value).toBe(2519);
      expect(event.value_currency).toBe('AUD');
      expect(event.unique_id).toBe('started-sub1');
      expect(event.profile.data.attributes.email).toBe('jane+test@example.com');
      expect(event.properties.plan_name).toBe('Founding Membership');
      expect(event.properties.order_id).toBe('ord1');
      expect(event.properties.membership_start_date).toBe('2027-01-15');

      expect(calls[3].body.data.relationships.list.data.id).toBe('XRYaZh');
      expect(calls[3].body.data.attributes.profiles.data[0].attributes.subscriptions.email.marketing.consent).toBe('SUBSCRIBED');

      expect(jobs, 'the job is gone once sent').toHaveLength(0);
      expect(log[0].label).toBe('Started Membership');
      expect(log[0].outcome).toBe('sent');
    });

    test('creates the profile for someone who never reserved', async ({ request }) => {
      await deliver(request, { id: 'evt6', type: 'purchase.created', data: { object: { id: 'pur1' } } });

      const attributes = fixture('drain').calls[1].body.data.attributes;
      expect(attributes.first_name).toBe('Jane');
      expect(attributes.properties.founding_status).toBe('paid');
    });

    test('a one-off purchase is not a membership', async ({ request }) => {
      await deliver(request, { id: 'evt7', type: 'purchase.created', data: { object: { id: 'pur2' } } });

      const { calls, jobs } = fixture('drain');
      expect(calls).toHaveLength(0);
      expect(jobs).toHaveLength(0);
    });

    test('keeps the work when Klaviyo is down, and sends it when it comes back', async ({ request }) => {
      fixture('mode', 'down');

      const response = await deliver(request, { id: 'evt8', type: 'purchase.created', data: { object: { id: 'pur1' } } });
      expect(response.status(), 'SureCart still gets its answer').toBe(200);

      let state = fixture('drain');
      expect(state.jobs).toHaveLength(1);
      expect(state.jobs[0].attempts).toBe(1);
      expect(state.log[0].outcome).toBe('retrying');
      expect(state.log[0].detail).toContain('503');

      fixture('mode', 'ok');
      fixture('due');

      state = fixture('drain');
      expect(state.jobs).toHaveLength(0);
      expect(state.log[0].outcome).toBe('sent');
      expect(paths(state.calls)).toContain('POST /api/events');
    });

    test('gives up on a refused request without retrying, and says so', async ({ request }) => {
      fixture('mode', 'rejects');
      await deliver(request, { id: 'evt9', type: 'subscription.renewed', data: { object: { id: 'sub1' } } });

      const state = fixture('drain');
      expect(state.jobs).toHaveLength(0);
      expect(state.log[0].outcome).toBe('failed');
      expect(state.log[0].detail).toContain('Bad request');
    });

    test('sends one cancellation event, then only the status when access ends', async ({ request }) => {
      const cancelling = { ...SUBSCRIPTION, cancel_at_period_end: true };
      fixture('records', JSON.stringify({ subscription: { sub1: cancelling } }));

      await deliver(request, { id: 'evt10', type: 'subscription.set_to_cancel', data: { object: { id: 'sub1' } } });

      let { calls } = fixture('drain');
      const first = calls.find((call) => call.url.endsWith('/api/events')).body.data.attributes;
      expect(first.metric.data.attributes.name).toBe('Membership Cancelled');
      expect(first.value).toBeUndefined();
      expect(first.properties.access_ends_at).toBe('2027-11-04');
      expect(calls.find((call) => call.url.endsWith('/api/profile-import')).body.data.attributes.properties.membership_status).toBe('active');

      fixture('records', JSON.stringify({ subscription: { sub1: { ...cancelling, status: 'canceled', ended_at: '2027-11-04T09:00:00+00:00' } } }));
      await deliver(request, { id: 'evt11', type: 'subscription.canceled', data: { object: { id: 'sub1' } } });

      ({ calls } = fixture('drain'));
      expect(calls.filter((call) => call.url.endsWith('/api/events')), 'still one event').toHaveLength(1);

      const last = calls.filter((call) => call.url.endsWith('/api/profile-import')).pop();
      expect(last.body.data.attributes.properties.membership_status).toBe('cancelled');
    });

    test('a cancellation taken back clears the end date', async ({ request }) => {
      await deliver(request, {
        id: 'evt12',
        type: 'subscription.updated',
        data: { object: { id: 'sub1' }, previous_attributes: { cancel_at_period_end: true } },
      });

      const { calls } = fixture('drain');
      expect(calls.filter((call) => call.url.endsWith('/api/events'))).toHaveLength(0);
      expect(calls[0].body.data.meta.patch_properties.unset).toEqual(['membership_end_date']);
    });

    test('notices a plan change, whether SureCart says what changed or not', async ({ request }) => {
      const monthly = {
        id: 'price0',
        recurring_interval: 'month',
        product: { id: 'prod1', slug: 'founding-membership', name: 'Founding Membership' },
      };
      fixture('records', JSON.stringify({ subscription: { sub1: SUBSCRIPTION }, price: { price0: monthly } }));

      await deliver(request, {
        id: 'evt13',
        type: 'subscription.updated',
        data: { object: { id: 'sub1' }, previous_attributes: { price: 'price0' } },
      });

      const event = fixture('drain').calls.find((call) => call.url.endsWith('/api/events')).body.data.attributes;
      expect(event.metric.data.attributes.name).toBe('Membership Plan Changed');
      expect(event.properties.from_plan).toBe('Founding Membership (monthly)');
      expect(event.properties.to_plan).toBe('Founding Membership (annual)');

      // SureCart leaves previous_attributes out; what was last seen fills the gap.
      fixture('records', JSON.stringify({ subscription: { sub1: { ...SUBSCRIPTION, price: { ...monthly, amount: 22900 } } }, price: { price1: SUBSCRIPTION.price } }));
      await deliver(request, { id: 'evt14', type: 'subscription.updated', data: { object: { id: 'sub1' } } });

      const events = fixture('drain').calls.filter((call) => call.url.endsWith('/api/events'));
      expect(events).toHaveLength(2);
      expect(events[1].body.data.attributes.properties.to_plan).toBe('Founding Membership (monthly)');

      // Nothing has changed this time, so nothing is sent.
      await deliver(request, { id: 'evt15', type: 'subscription.updated', data: { object: { id: 'sub1' } } });
      expect(fixture('drain').calls.filter((call) => call.url.endsWith('/api/events'))).toHaveLength(2);
    });

    test('a failed renewal records the attempt once, from either SureCart event', async ({ request }) => {
      const pastDue = { ...SUBSCRIPTION, status: 'past_due' };
      fixture('records', JSON.stringify({ subscription: { sub1: pastDue }, order: { ord9: { id: 'ord9', subscription_for_test: 'sub1' }, ord0: { id: 'ord0' } } }));

      await deliver(request, { id: 'evt16', type: 'order.payment_failed', data: { object: { id: 'ord9' } } });
      await deliver(request, {
        id: 'evt17',
        type: 'subscription.updated',
        data: { object: { id: 'sub1' }, previous_attributes: { status: 'active' } },
      });

      const events = fixture('drain').calls.filter((call) => call.url.endsWith('/api/events')).map((call) => call.body.data.attributes);
      expect(events).toHaveLength(2);
      expect(events[0].metric.data.attributes.name).toBe('Membership Payment Failed');
      expect(events[0].properties.attempt_count).toBe(1);
      expect(events[1].unique_id, 'Klaviyo records one of the two').toBe(events[0].unique_id);

      // A first payment failing at checkout has no subscription: not a renewal.
      fixture('reset');
      fixture('records', JSON.stringify({ order: { ord0: { id: 'ord0' } } }));
      await deliver(request, { id: 'evt18', type: 'order.payment_failed', data: { object: { id: 'ord0' } } });
      expect(fixture('drain').calls).toHaveLength(0);
    });

    test('a renewal SureCart has not confirmed as paid waits', async ({ request }) => {
      fixture('records', JSON.stringify({ subscription: { sub1: { ...SUBSCRIPTION, current_period: { id: 'per2', status: 'draft' } } } }));
      await deliver(request, { id: 'evt19', type: 'subscription.renewed', data: { object: { id: 'sub1' } } });

      let state = fixture('drain');
      expect(state.calls).toHaveLength(0);
      expect(state.jobs).toHaveLength(1);

      fixture('records', JSON.stringify({ subscription: { sub1: SUBSCRIPTION } }));
      fixture('due');

      state = fixture('drain');
      const event = state.calls.find((call) => call.url.endsWith('/api/events')).body.data.attributes;
      expect(event.metric.data.attributes.name).toBe('Membership Payment Succeeded');
      expect(event.value).toBe(2519);
      expect(event.properties.payment_number).toBe(2);
    });

    test('sends a refund with no revenue', async ({ request }) => {
      fixture(
        'records',
        JSON.stringify({
          refund: {
            ref1: {
              id: 'ref1',
              amount: 22900,
              reason: 'requested_by_customer',
              customer: { id: 'cus1', email: 'jane+test@example.com' },
              charge: { checkout: { order: 'ord1', line_items: { data: [{ price: { recurring_interval: 'month', product: { name: 'Founding Membership' } } }] } } },
            },
          },
        })
      );

      await deliver(request, { id: 'evt20', type: 'refund.succeeded', data: { object: { id: 'ref1' } } });

      const event = fixture('drain').calls.find((call) => call.url.endsWith('/api/events')).body.data.attributes;
      expect(event.metric.data.attributes.name).toBe('Membership Refunded');
      expect(event.value).toBeUndefined();
      expect(event.properties.value).toBeUndefined();
      expect(event.properties.amount).toBe(229);
    });
  });

  test.describe('the forms', () => {
    const RESERVATION = {
      'form-id': '4773',
      'srfm-input-3b3cf3ac-lbl-Rmlyc3QgTmFtZQ-first-name': 'Jane',
      'srfm-input-2973f615-lbl-TGFzdCBOYW1l-last-name': 'Smith',
      'srfm-email-a2d5b78d-lbl-RW1haWwgQWRkcmVzcw-email': 'jane+test@example.com',
      'srfm-checkbox-aa-lbl-QWxzbw-also-send-me-padlx-news-events-and-offers': 'on',
    };

    test('a reservation goes on its list, with its event', () => {
      fixture('sureforms', JSON.stringify(RESERVATION));

      const { calls } = fixture('drain');
      expect(paths(calls)).toEqual([
        'GET /api/profiles',
        'POST /api/profile-import',
        'POST /api/events',
        'POST /api/profile-subscription-bulk-create-jobs',
      ]);

      const properties = calls[1].body.data.attributes.properties;
      expect(properties.founding_status).toBe('reserved');
      expect(properties.signup_source).toBe('founding_reservation_form');
      expect(properties.marketing_consent).toBe(true);
      expect(calls[1].body.data.attributes.first_name).toBe('Jane');
      expect(calls[2].body.data.attributes.metric.data.attributes.name).toBe('Submitted Reservation');
      expect(calls[3].body.data.relationships.list.data.id).toBe('SFZx43');
    });

    test('a reservation never overwrites paid, and the box stays optional', () => {
      fixture('profile', JSON.stringify({ properties: { founding_status: 'paid' } }));

      const { 'srfm-checkbox-aa-lbl-QWxzbw-also-send-me-padlx-news-events-and-offers': ticked, ...unticked } = RESERVATION;
      expect(ticked).toBe('on');

      fixture('sureforms', JSON.stringify(unticked));

      const { calls } = fixture('drain');
      const properties = calls[1].body.data.attributes.properties;
      expect(properties.founding_status).toBeUndefined();
      expect(properties.marketing_consent).toBe(false);
      expect(calls[3].body.data.relationships.list.data.id, 'still on the reservations list').toBe('SFZx43');
    });

    test('a different SureForms form is left alone', () => {
      fixture('sureforms-other', JSON.stringify(RESERVATION));
      expect(fixture('drain').calls).toHaveLength(0);
    });

    test('Stay up to date goes on its list, and keeps a founding member a founding member', () => {
      fixture('profile', JSON.stringify({ properties: { founding_member: true, founding_status: 'reserved' } }));

      fixture(
        'elementor',
        'New Form',
        JSON.stringify({
          email: { title: 'Email', value: 'jane+test@example.com' },
          first_name: { title: 'First name', value: 'Jane' },
        })
      );

      const { calls } = fixture('drain');
      expect(paths(calls)).toEqual(['GET /api/profiles', 'POST /api/profile-import', 'POST /api/profile-subscription-bulk-create-jobs']);

      const attributes = calls[1].body.data.attributes;
      expect(attributes.first_name).toBe('Jane');
      expect(attributes.properties.signup_source).toBe('website_info_form');
      expect(attributes.properties.signup_page).toBe('https://padlx.com.au/');
      expect(attributes.properties.consent_text).toBe('Keep me posted.');
      expect(attributes.properties.marketing_consent).toBe(true);
      expect(attributes.properties.founding_member).toBeUndefined();
      expect(attributes.properties.lead_status).toBeUndefined();
      expect(calls[2].body.data.relationships.list.data.id).toBe('Y5fFQA');
    });

    test('a different Elementor form is left alone', () => {
      fixture('elementor', 'Contact', JSON.stringify({ email: { title: 'Email', value: 'jane+test@example.com' } }));
      expect(fixture('drain').calls).toHaveLength(0);
    });
  });

  test.describe('the screens', () => {
    test('saves settings, never shows the key, and tests the connection', async ({ page }) => {
      await login(page);
      await page.goto(SETTINGS_PATH);

      await expect(page.locator('[data-testid="blueworx-klaviyo-webhook-url"]')).toContainText('blueworx/v1/klaviyo/surecart');

      const html = await page.content();
      expect(html).not.toContain('pk_test_fixture_key_abcd');
      expect(html).not.toContain('fixture-signing-secret');
      expect(html).toContain('abcd');
      await expect(page.locator('#blueworx_klaviyo_key')).toHaveValue('');

      await page.fill('#blueworx_klaviyo_list_info', 'ABC123');
      await page.locator('[data-testid="blueworx-klaviyo-test"]').click();
      await page.waitForLoadState('domcontentloaded');

      await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved.');
      await expect(page.locator('body')).toContainText('The key works.');
      await expect(page.locator('body')).toContainText('List ABC123');
      await expect(page.locator('#blueworx_klaviyo_list_info')).toHaveValue('ABC123');

      // Saving without touching the key keeps it.
      await expect(page.locator('body')).toContainText('abcd');
    });

    test('refuses a bad list ID and keeps the old one', async ({ page }) => {
      await login(page);
      await page.goto(SETTINGS_PATH);

      await page.fill('#blueworx_klaviyo_list_members', 'not a list!');
      await page.locator('[data-testid="blueworx-klaviyo-save"]').click();
      await page.waitForLoadState('domcontentloaded');

      await expect(page.locator('.bw-notice--danger')).toContainText('list_members');
      await expect(page.locator('#blueworx_klaviyo_list_members')).toHaveValue('XRYaZh');
    });

    test('the log lists what happened, and can be cleared', async ({ page, request }) => {
      fixture('mode', 'rejects');
      await deliver(request, { id: 'evt21', type: 'purchase.created', data: { object: { id: 'pur1' } } });
      fixture('drain');

      await login(page);
      await page.goto(LOG_PATH);

      const row = page.locator('[data-testid="blueworx-klaviyo-log"] tbody tr').first();
      await expect(row).toContainText('Started Membership');
      await expect(row).toContainText('jane+test@example.com');
      await expect(row).toContainText('Failed');
      await expect(row).toContainText('Bad request');

      await page.getByRole('button', { name: 'Clear log' }).click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('body')).toContainText('Nothing has been sent yet');
    });

    test('with the function off the page explains itself and is not listed', async ({ page }) => {
      fixture('off');

      await login(page);
      await page.goto(SETTINGS_PATH);

      await expect(page.locator('.bw-notice')).toContainText('switched off');
      await expect(page.locator('#adminmenu a[href$="page=blueworx-klaviyo"]')).toHaveCount(0);
    });
  });
});
