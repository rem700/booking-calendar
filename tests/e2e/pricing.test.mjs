// Prices from the settings: shown in the form and worked out again on the server.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, wpEval, api, firstSlot, bookableMonth, requestBody, WP_URL } from './lib.mjs';

let browser;
const setCfg = (php) => wpEval(`$c=get_option('bkb_config');${php}update_option('bkb_config',bkb_sanitize_config($c));`);
const lastPrice = () => wpEval("$p=get_posts(['post_type'=>'bk_booking','numberposts'=>1,'fields'=>'ids']);echo get_post_meta($p[0],'_bkb_price',true);");

before(async () => {
  seed();
  setCfg(`$c['services'][0]['price']=50;$c['price']=['currency'=>'€','before'=>false];
    $c['options']=[
      ['id'=>'video','type'=>'yesno','label'=>['en'=>'Video'],'price'=>20,'per_hour'=>true],
      ['id'=>'photographer','type'=>'choice','label'=>['en'=>'Photographer'],'default'=>'own','choices'=>[
        ['id'=>'own','label'=>['en'=>'Own']],['id'=>'ours','label'=>['en'=>'Ours'],'price'=>100]]],
    ];`);
  browser = await chromium.launch();
});
after(async () => { await browser?.close(); });

test('the server works out the price and ignores the one sent by the browser', async () => {
  const s = await firstSlot(bookableMonth(), 2, 'hall-a');
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, hours: 2, price: '1 €', opts: { video: '1', photographer: 'ours' } })) });
  assert.equal(r.status, 200);
  // 2 h × 50 + 2 h × 20 for video + 100 for our photographer.
  assert.equal(lastPrice(), '240 €');
});

test('without a price per hour the price from the browser is kept, as before', async () => {
  setCfg("$c['services'][0]['price']=0;");
  const s = await firstSlot(bookableMonth(), 1, 'hall-b');
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, location: 'hall-b', price: '77 €' })) });
  assert.equal(r.status, 200);
  assert.equal(lastPrice(), '77 €');
  setCfg("$c['services'][0]['price']=50;");
});

test('the form shows the same price and updates it when the choice changes', async () => {
  const page = await browser.newPage();
  await page.goto(WP_URL + '/book/');
  await page.waitForSelector('.bkb-form');
  const price = () => page.textContent('.bkb-price');
  assert.equal(await price(), '50 €');
  await page.click('.bkb-seg button >> text=Ours');
  assert.equal(await price(), '150 €');
  await page.close();
});
