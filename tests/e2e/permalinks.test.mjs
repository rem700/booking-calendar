// The form on a site with plain permalinks (no /wp-json/ addresses).
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, wpEval, WP_URL } from './lib.mjs';

let browser, pageId;
before(async () => {
  seed();
  wpEval("update_option('permalink_structure','');");
  pageId = wpEval("echo get_page_by_path('book')->ID;");
  browser = await chromium.launch();
});
after(async () => {
  await browser?.close();
  wpEval("update_option('permalink_structure','/%postname%/');");
});

test('the calendar shows free days with plain permalinks', async () => {
  const page = await browser.newPage();
  await page.goto(`${WP_URL}/?page_id=${pageId}`);
  await page.waitForSelector('.bkb-form');
  let found = false;
  for (let i = 0; i < 3 && !found; i++) {
    found = await page.waitForSelector('.bkb-day[data-date]', { timeout: 3000 }).then(() => true, () => false);
    if (!found) await page.click('.bkb-nav[data-d="1"]');
  }
  assert.ok(found, 'free days are shown');
  await page.close();
});
