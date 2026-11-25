// The admin side: confirming a request, the client card, the settings screen.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, api, firstSlot, bookableMonth, requestBody, mails, clearMail, wpEval, login, WP_URL } from './lib.mjs';

let browser, page, bookingId;

before(async () => {
  seed();
  const s = await firstSlot(bookableMonth(), 1, 'hall-a');
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, email: 'eva@example.test', name: 'Eva Admin' })) });
  assert.equal(r.status, 200);
  bookingId = Number(wpEval("$p=get_posts(['post_type'=>'bk_booking','numberposts'=>1,'fields'=>'ids']);echo $p[0];"));
  browser = await chromium.launch();
  page = await browser.newPage();
  await login(page);
});
after(async () => { await browser?.close(); });

test('requests list shows the new request', async () => {
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking');
  assert.match(await page.textContent('#the-list'), /Eva Admin/);
});

test('confirming a request e-mails the client and moves the client to "confirmed"', async () => {
  clearMail();
  await page.goto(`${WP_URL}/wp-admin/post.php?post=${bookingId}&action=edit`);
  const link = await page.$(`a[href*="action=bkb_status"][href*="s=confirmed"]`);
  assert.ok(link, 'there is a confirm button');
  await Promise.all([page.waitForNavigation(), link.click()]);
  assert.match(page.url(), /bkb_mail=1/);
  assert.equal(wpEval(`echo get_post_meta(${bookingId},'_bkb_status',true);`), 'confirmed');
  const m = mails();
  assert.equal(m.length, 1);
  assert.deepEqual([].concat(m[0].to), ['eva@example.test']);
  const stage = wpEval("global $wpdb;echo $wpdb->get_var(\"SELECT stage FROM {$wpdb->prefix}bkb_clients WHERE email='eva@example.test'\");");
  assert.equal(stage, 'confirmed');
});

test('reply templates under the request: language switch and the e-mail confirmation', async () => {
  await page.goto(`${WP_URL}/wp-admin/post.php?post=${bookingId}&action=edit`);
  const box = page.locator('.bkb-replies').first();
  await box.waitFor();
  const other = box.locator('[data-rl]:not(.button-primary)').first();
  const lang = await other.getAttribute('data-rl');
  await other.click();
  assert.equal(await box.locator(`.bkb-rep-l[data-l="${lang}"]`).first().isVisible(), true);
  const send = box.locator(`.bkb-rep-l[data-l="${lang}"] [data-send]`).first();
  if (await send.count()) {
    const dialog = new Promise((res) => page.once('dialog', async (d) => { res(d.message()); await d.dismiss(); }));
    await send.click();
    assert.equal(await dialog, 'Send this e-mail to eva@example.test?');
  }
});

test('clients list and the client card open', async () => {
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients');
  assert.match(await page.textContent('#wpbody-content'), /Eva Admin/);
  const cid = wpEval("global $wpdb;echo $wpdb->get_var(\"SELECT id FROM {$wpdb->prefix}bkb_clients WHERE email='eva@example.test'\");");
  await page.goto(`${WP_URL}/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients&client=${cid}`);
  assert.match(await page.textContent('#wpbody-content'), /eva@example\.test/);
});

test('settings screen loads with the tabs and saves without changes', async () => {
  const before = wpEval("echo wp_json_encode(get_option('bkb_config'));");
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-settings');
  await page.waitForSelector('form button[type="submit"], form input[type="submit"]');
  await Promise.all([page.waitForNavigation(), page.click('form [type="submit"]')]);
  assert.match(page.url(), /bkb_saved=ok/);
  assert.equal(wpEval("echo wp_json_encode(get_option('bkb_config'));"), before);
});

test('own sources from the settings appear in the manual source list, none by default', async () => {
  const list = () => wpEval("echo implode('|', bkb_crm_manual_sources());");
  assert.doesNotMatch(list(), /IPAR/);
  wpEval("$c=get_option('bkb_config');$c['crm']['sources']=\"IPAR\\n Fair \\n\";update_option('bkb_config',$c);");
  assert.match(list(), /\|IPAR\|Fair\|Other$/);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients&new=1');
  const opts = await page.$$eval('select[name="source"] option', (o) => o.map((x) => x.textContent));
  assert.ok(opts.includes('IPAR'), opts.join(', '));
  seed();
});

test('the morning summary is available as JSON for the admin only', async () => {
  const anon = await fetch(WP_URL + '/wp-json/bk-booking/v1/crm/digest');
  assert.equal(anon.status, 401);
});

test('no PHP notices or warnings in the debug log', async () => {
  const log = wpEval("echo @file_get_contents(WP_DEBUG_LOG) ?: '';");
  const own = log.split('\n').filter((l) => /bk-booking|shootbook/.test(l));
  assert.deepEqual(own, []);
});
