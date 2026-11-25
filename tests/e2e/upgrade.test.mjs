// Updating a site from 2.3 keeps it as it was: Russian admin and the IPAR source.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, wpEval, login, WP_URL } from './lib.mjs';

let browser, page;
const cfg = (k) => wpEval(`echo wp_json_encode(get_option('bkb_config')${k});`);

before(async () => {
  seed();
  // A site saved by 2.3: no version stored, no admin language, no own sources.
  wpEval("delete_option('bkb_version');$c=get_option('bkb_config');unset($c['admin_lang'],$c['crm']['sources']);update_option('bkb_config',$c);");
  wpEval('');
  browser = await chromium.launch();
  page = await browser.newPage();
  await login(page);
});
after(async () => {
  seed();
  await browser?.close();
});

test('the update fixes the admin language to Russian and keeps IPAR', () => {
  assert.equal(cfg("['admin_lang']"), '"ru"');
  assert.equal(cfg("['crm']['sources']"), '"IPAR"');
  assert.equal(wpEval("echo get_option('bkb_version');"), wpEval('echo BKB_VER;'));
});

test('with an English profile the admin is still in Russian', async () => {
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-settings');
  await page.waitForSelector('#bkb-editor button, #bkb-editor a');
  assert.match(await page.textContent('#bkb-editor'), /Расписание/);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients&new=1');
  const opts = await page.$$eval('select[name="source"] option', (o) => o.map((x) => x.textContent));
  assert.ok(opts.includes('IPAR'));
  assert.ok(opts.includes('Другое'), opts.join(', '));
});

test('it runs once: a removed IPAR does not come back', () => {
  wpEval("$c=get_option('bkb_config');$c['crm']['sources']='';update_option('bkb_config',$c);");
  wpEval('');
  assert.equal(cfg("['crm']['sources']"), '""');
});
