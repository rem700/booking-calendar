// The admin follows the user's profile language: English by default, Russian from the bundled translation.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, wpEval, login, WP_URL } from './lib.mjs';

let browser, page;
const setLocale = (l) => wpEval(`update_user_meta(get_user_by('login','admin')->ID,'locale','${l}');`);

before(async () => {
  seed();
  browser = await chromium.launch();
  page = await browser.newPage();
  await login(page);
});
after(async () => {
  setLocale('');
  await browser?.close();
});

async function settingsTabs() {
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-settings');
  await page.waitForSelector('#bkb-editor button, #bkb-editor a');
  return page.textContent('#bkb-editor');
}

test('English admin by default', async () => {
  setLocale('');
  const tabs = await settingsTabs();
  assert.match(tabs, /Schedule/);
  assert.match(tabs, /Opening hours|Name in e-mails/);
  // Language names stay in their own language (Русский, Українська).
  assert.doesNotMatch(tabs.replace(/Русский|Українська/g, ''), /\p{Script=Cyrillic}/u);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients');
  assert.doesNotMatch(await page.textContent('#wpbody-content'), /\p{Script=Cyrillic}/u);
});

test('Russian admin when the profile language is Russian', async () => {
  setLocale('ru_RU');
  const tabs = await settingsTabs();
  assert.match(tabs, /Расписание/);
  assert.match(tabs, /Название для писем/);
  assert.doesNotMatch(tabs, /Schedule|Name in e-mails/);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients');
  assert.match(await page.textContent('#wpbody-content'), /Клиенты/);
});
