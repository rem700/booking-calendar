// The public side: free times, the form, a request and the e-mails it sends.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, api, firstSlot, bookableMonth, requestBody, mails, clearMail, wpEval, WP_URL } from './lib.mjs';

let browser;
before(async () => { seed(); browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('slots: working hours 10–18 give start times 10:00…17:00 for one hour', async () => {
  const s = await firstSlot(bookableMonth());
  assert.ok(s, 'there are free days');
  assert.deepEqual(s.slots[s.date], ['10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00']);
});

test('slots: two hours end no later than closing time', async () => {
  const s = await firstSlot(bookableMonth(), 2);
  assert.equal(s.slots[s.date].at(-1), '16:00');
});

test('slots: today is not offered (book at least one day ahead)', async () => {
  const { body } = await api(`slots?month=${bookableMonth()}`);
  const d = new Date();
  const today = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  assert.equal(body.slots[today], undefined);
});

test('slots: a bad month is refused', async () => {
  const r = await api('slots?month=2026-1');
  assert.equal(r.status, 400);
});

test('request: a form sent faster than 4 seconds counts as spam', async () => {
  const s = await firstSlot(bookableMonth());
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, el: 1000 })) });
  assert.equal(r.status, 400);
});

test('request: the hidden field filled in counts as spam', async () => {
  const s = await firstSlot(bookableMonth());
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, website: 'x' })) });
  assert.equal(r.status, 400);
});

test('request: without consent it is refused', async () => {
  const s = await firstSlot(bookableMonth());
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, consent: false })) });
  assert.equal(r.status, 422);
});

test('request: an unknown service is refused', async () => {
  const s = await firstSlot(bookableMonth());
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, service: 'nope' })) });
  assert.equal(r.status, 422);
});

test('form: pick a day and a time, send, see the thank-you, the time becomes busy, two e-mails go out', async () => {
  clearMail();
  const page = await browser.newPage();
  await page.goto(WP_URL + '/book/');
  await page.waitForSelector('.bkb-form');
  // The first free day may be next month.
  for (let i = 0; i < 2 && !(await page.$('.bkb-day[data-date]')); i++) {
    await page.click('.bkb-nav[data-d="1"]');
    await page.waitForTimeout(500);
  }
  await page.click('.bkb-seg button >> text=Hall B');
  const day = await page.getAttribute('.bkb-day[data-date]', 'data-date');
  await page.click(`.bkb-day[data-date="${day}"]`);
  await page.click('.bkb-time[data-time="12:00"]');
  await page.fill('#bkb-name', 'Anna Form');
  await page.fill('#bkb-email', 'anna@example.test');
  await page.check('input[name="consent"]', { force: true });
  await page.waitForTimeout(4200); // the form must stay open at least 4 seconds
  await page.click('.bkb-submit');
  await page.waitForSelector('.bkb-ok', { timeout: 15000 });
  await page.close();

  const { body } = await api(`slots?month=${day.slice(0, 7)}&h=1&loc=hall-b`);
  assert.ok(!body.slots[day]?.includes('12:00'), '12:00 is no longer offered');

  const m = mails();
  assert.equal(m.length, 2, 'one e-mail to the team, one to the client');
  const team = m.find((x) => [].concat(x.to).includes('team@example.test'));
  const client = m.find((x) => [].concat(x.to).includes('anna@example.test'));
  assert.ok(team && /Anna Form/.test(team.message) && /Hall B/.test(team.message));
  assert.ok(client);

  const status = wpEval("$p=get_posts(['post_type'=>'bk_booking','numberposts'=>1,'fields'=>'ids']);echo get_post_meta($p[0],'_bkb_status',true);");
  assert.equal(status, 'pending');
});

test('request: the same time cannot be taken twice', async () => {
  const s = await firstSlot(bookableMonth(), 1, 'hall-a');
  const send = () => api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time })) });
  assert.equal((await send()).status, 200);
  assert.equal((await send()).status, 409);
});

test('client: a request makes a client card in the CRM at stage "new"', async () => {
  const out = wpEval("global $wpdb;echo $wpdb->get_var(\"SELECT stage FROM {$wpdb->prefix}bkb_clients WHERE email='client@example.test'\");");
  assert.equal(out, 'new');
});

test('source: a visit with utm_source is remembered in the browser', async () => {
  const page = await browser.newPage();
  await page.goto(WP_URL + '/?utm_source=instagram&utm_medium=bio');
  const saved = await page.evaluate(() => JSON.parse(localStorage.getItem('bkb_src') || 'null'));
  assert.equal(saved?.s, 'instagram');
  await page.close();
});
