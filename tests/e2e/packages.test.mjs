// Packages: a set number of hours at their own price, chosen in the form next to the durations.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, wpEval, api, bookableMonth, requestBody, clearMail, mails, login, WP_URL } from './lib.mjs';

let browser;
const setCfg = (php) => wpEval(`$c=get_option('bkb_config');${php}update_option('bkb_config',bkb_sanitize_config($c));`);
const last = (k) => wpEval(`$p=get_posts(['post_type'=>'bk_booking','numberposts'=>1,'fields'=>'ids']);echo wp_json_encode(get_post_meta($p[0],'_bkb_${k}',true));`);
const post = (over) => api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody(over)) });
const slots = async (q) => (await api(`slots?month=${bookableMonth()}&${q}`)).body.slots;

before(async () => {
  seed();
  setCfg(`$c['services'][0]['price']=50;$c['price']=['currency'=>'€','before'=>false];
    $c['packages']=[
      ['id'=>'five','label'=>['en'=>'5 hours package'],'hours'=>5,'price'=>200,'locations'=>['hall-a']],
      ['id'=>'three','label'=>['en'=>'3 hours'],'hours'=>3],
    ];
    $c['options']=[['id'=>'video','type'=>'yesno','label'=>['en'=>'Video'],'price'=>20,'per_hour'=>true]];`);
  browser = await chromium.launch();
});
after(async () => { await browser?.close(); seed(); });

test('the calendar looks for the whole package length', async () => {
  const s = await slots('h=1&loc=hall-a&pack=five');
  const day = Object.keys(s).sort()[0];
  // Open 10–18: five hours in a row can start at 13:00 at the latest.
  assert.deepEqual(s[day], ['10:00', '11:00', '12:00', '13:00']);
  // Not offered in hall B, so the plain length is used there.
  const b = await slots('h=1&loc=hall-b&pack=five');
  assert.equal(b[Object.keys(b).sort()[0]].length, 8);
});

test('a request with a package takes its hours and price, options on top', async () => {
  clearMail();
  const s = await slots('loc=hall-a&pack=five');
  const date = Object.keys(s).sort()[0];
  const r = await post({ date, time: '13:00', hours: 1, pack: 'five', price: '1\u00a0€', opts: { video: '1' } });
  assert.equal(r.status, 200);
  assert.equal(JSON.parse(last('pack')), 'five');
  assert.equal(Number(JSON.parse(last('hours'))), 5);
  // 200 for the package + 5 h × 20 for video.
  assert.equal(JSON.parse(last('price')), '300\u00a0€');
  const start = JSON.parse(last('start'));
  assert.equal(JSON.parse(last('end')) - start, 5 * 3600);
  const team = mails().find((m) => JSON.stringify(m.to).includes('team@example.test'));
  assert.match(team.message, /Package: 5 hours package/);
  // The hours are taken: the same day has no time for the package any more.
  const again = await slots('loc=hall-a&pack=five');
  assert.ok(!again[date] || !again[date].includes('13:00'));
});

test('a package without a price counts its hours at the price of the service', async () => {
  const s = await slots('loc=hall-b&pack=three');
  const date = Object.keys(s).sort()[0];
  const r = await post({ date, time: s[date][0], location: 'hall-b', pack: 'three' });
  assert.equal(r.status, 200);
  assert.equal(JSON.parse(last('price')), '150\u00a0€');
});

test('a package that is not offered at the location is refused', async () => {
  const s = await slots('loc=hall-b');
  const date = Object.keys(s).sort().pop();
  const r = await post({ date, time: s[date][0], location: 'hall-b', pack: 'five' });
  assert.equal(r.status, 422);
  const u = await post({ date, time: s[date][0], location: 'hall-b', pack: 'nope' });
  assert.equal(u.status, 422);
});

test('the form offers packages next to the durations, only where they apply', async () => {
  const page = await browser.newPage();
  await page.goto(WP_URL + '/book/');
  await page.waitForSelector('.bkb-form');
  const price = () => page.textContent('.bkb-price');
  const five = page.locator('.bkb-seg button[data-v="p:five"]');
  assert.equal(await price(), '50\u00a0€');
  await five.click();
  assert.equal(await five.getAttribute('aria-pressed'), 'true');
  assert.equal(await page.locator('.bkb-seg button[data-k="hours"][data-v="1"]').getAttribute('aria-pressed'), 'false');
  assert.equal(await price(), '200\u00a0€');
  assert.match(await page.textContent('.bkb-recap-opts'), /5 hours package, 5 h/);
  // Hall B has no such package: it is hidden and the usual length comes back.
  await page.click('.bkb-seg button >> text=Hall B');
  assert.equal(await five.isVisible(), false);
  assert.equal(await price(), '50\u00a0€');
  await page.click('.bkb-seg button[data-v="p:three"]');
  assert.equal(await price(), '150\u00a0€');
  await page.close();
});

test('the form sends the package and the request is saved with it', async () => {
  const page = await browser.newPage();
  await page.goto(WP_URL + '/book/');
  await page.waitForSelector('.bkb-form');
  await page.click('.bkb-seg button[data-v="p:five"]');
  await page.waitForSelector('.bkb-day');
  await page.locator('.bkb-day').last().click();
  await page.locator('.bkb-time').first().click();
  await page.fill('#bkb-name', 'Package Client');
  await page.fill('#bkb-email', 'pack@example.test');
  await page.check('input[name=consent]', { force: true });
  await page.waitForTimeout(4100);
  await page.click('.bkb-submit');
  await page.waitForSelector('.bkb-ok');
  assert.equal(JSON.parse(last('pack')), 'five');
  assert.equal(JSON.parse(last('price')), '200\u00a0€');
  await page.close();
});

test('packages are added in the settings', async () => {
  const page = await browser.newPage();
  await login(page);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-settings');
  await page.click('.nav-tab[data-tab="packages"]');
  await page.click('[data-act="add"][data-path="packages"]');
  const card = page.locator('.bkb-card').last();
  await card.locator('[data-p="packages.2.hours"]').fill('8');
  await card.locator('[data-p="packages.2.price"]').fill('350');
  // The title of the card follows the id, so the card is drawn again after it.
  await card.locator('[data-p="packages.2.id"]').fill('ten');
  await card.locator('[data-p="packages.2.id"]').press('Tab');
  await card.locator('[data-optloc="packages.2"][value="hall-b"]').check();
  await Promise.all([page.waitForNavigation(), page.click('#bkb-settings-form [type=submit]')]);
  const p = JSON.parse(wpEval("echo wp_json_encode(bkb_cfg('packages'));"));
  assert.deepEqual(p[2], { id: 'ten', label: [], hours: 8, price: 350, locations: ['hall-b'] });
  await page.close();
});
