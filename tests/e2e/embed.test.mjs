// The form on a site that is not WordPress (Framer, Webflow, Tilda, Wix): embed.js puts it in an iframe.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { chromium, seed, login, mails, clearMail, wpEval, api, firstSlot, bookableMonth, requestBody, WP_URL } from './lib.mjs';

let browser, server, SITE;
const FORM = WP_URL + '/?bkb_embed=1';
const SCRIPT = WP_URL + '/wp-content/plugins/bk-booking/assets/embed.js';
const pages = {
  '/': `<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Studio</title>
    <style>body{margin:0;background:#1d2b3a;color:#f2efe6;font-family:Georgia,serif}header{height:900px}</style></head>
    <body><header>Studio</header><div id="w" data-shootbook="${FORM}" data-theme="inherit"></div><footer style="height:600px"></footer>
    <script src="${SCRIPT}" async></script></body></html>`,
  '/button': `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Studio</title></head>
    <body><div data-shootbook="${FORM}" data-mode="button" data-label="Book a session"></div>
    <script src="${SCRIPT}" async></script></body></html>`,
  // Builders add blocks after the page has loaded.
  '/late': `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Studio</title></head>
    <body><script src="${SCRIPT}"></script>
    <script>setTimeout(function(){var d=document.createElement('div');d.setAttribute('data-shootbook','${FORM}');d.setAttribute('data-lang','ru');document.body.appendChild(d);},300);</script></body></html>`,
};

before(async () => {
  seed();
  browser = await chromium.launch();
  server = createServer((req, res) => {
    const html = pages[req.url.split('?')[0]];
    res.writeHead(html ? 200 : 404, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(html || '');
  });
  await new Promise((r) => server.listen(0, 'localhost', r));
  SITE = `http://localhost:${server.address().port}`;
});
after(async () => { await browser?.close(); server?.close(); });

function allow(sites) {
  wpEval(`bkb_embed_save(${JSON.stringify(sites)});`);
}

test('the form page has no theme and may be shown in a frame on any site by default', async () => {
  const res = await fetch(FORM + '&lang=ru&ink=%23ff0000&font=Inter');
  assert.equal(res.status, 200);
  assert.equal(res.headers.get('content-security-policy'), 'frame-ancestors *');
  assert.equal(res.headers.get('x-frame-options'), null);
  assert.match(res.headers.get('x-robots-tag') || '', /noindex/);
  const html = await res.text();
  assert.match(html, /<html lang="ru">/);
  assert.match(html, /--ink:#ff0000;/);
  assert.match(html, /--bkb-body:Inter;/);
  assert.doesNotMatch(html, /wp-block-library|admin-bar/);
});

test('colours and fonts from the code cannot break out of the style', async () => {
  const html = await (await fetch(FORM + '&ink=red;background:url(x)&font=' + encodeURIComponent('x;}</style><script>alert(1)</script>'))).text();
  assert.doesNotMatch(html, /url\(x\)/);
  assert.doesNotMatch(html, /<script>alert/);
});

test('on another site: the frame is as tall as the form, takes the page colours, and a request comes with the page and the source', async () => {
  allow(SITE);
  clearMail();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto(SITE + '/?utm_source=framer_test');
  const frameEl = await page.waitForSelector('#w iframe');
  const f = await frameEl.contentFrame();
  await f.waitForSelector('button.bkb-day');
  await page.waitForFunction(() => parseInt(document.querySelector('#w iframe').style.height, 10) > 1000);
  const h = await frameEl.evaluate((e) => parseInt(e.style.height, 10));
  const inner = await f.evaluate(() => Math.ceil(document.getElementById('bkb').getBoundingClientRect().bottom));
  assert.ok(Math.abs(h - inner) <= 2, `frame ${h}px, form ${inner}px`);
  assert.equal(await f.evaluate(() => document.documentElement.lang), 'ru');
  const style = await f.evaluate(() => document.getElementById('bkb').getAttribute('style'));
  assert.match(style, /--ink:#f2efe6;--cream:#1d2b3a;/);

  await f.click('button.bkb-day');
  await f.click('.bkb-time');
  await f.fill('#bkb-name', 'Framer Client');
  await f.fill('#bkb-email', 'framer@example.test');
  await f.check('input[name=consent]', { force: true });
  await page.waitForTimeout(4200);
  await f.click('.bkb-submit');
  await f.waitForSelector('.bkb-ok');
  await page.waitForFunction(() => parseInt(document.querySelector('#w iframe').style.height, 10) < 1000);
  await page.close();

  const saved = JSON.parse(wpEval(`$p=get_posts(['post_type'=>'bk_booking','numberposts'=>1,'meta_key'=>'_bkb_email','meta_value'=>'framer@example.test']);echo wp_json_encode(['page'=>get_post_meta($p[0]->ID,'_bkb_page',true),'src'=>get_post_meta($p[0]->ID,'_bkb_src',true),'ui'=>get_post_meta($p[0]->ID,'_bkb_ui',true)]);`));
  assert.equal(saved.page, SITE + '/');
  assert.equal(JSON.parse(saved.src).s, 'framer_test');
  assert.equal(saved.ui, 'ru');
  assert.equal(mails().length, 2);
});

test('a site that is not in the list cannot show the form, and its address is not used in e-mails', async () => {
  allow('https://iparstudio.com');
  const res = await fetch(FORM);
  assert.equal(res.headers.get('content-security-policy'), "frame-ancestors 'self' https://iparstudio.com https://www.iparstudio.com");
  const page = await browser.newPage();
  await page.goto(SITE + '/');
  await page.waitForSelector('#w iframe');
  await page.waitForTimeout(1500);
  const f = page.frames().find((x) => x !== page.mainFrame());
  const shown = await f.evaluate(() => !!document.getElementById('bkb')).catch(() => false);
  assert.equal(shown, false);
  await page.close();

  const s = await firstSlot(bookableMonth());
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, email: 'other@example.test', page: SITE + '/' })) });
  assert.equal(r.status, 200);
  const saved = wpEval(`$p=get_posts(['post_type'=>'bk_booking','numberposts'=>1,'meta_key'=>'_bkb_email','meta_value'=>'other@example.test']);echo get_post_meta($p[0]->ID,'_bkb_page',true);`);
  assert.equal(saved, WP_URL + '/');
  allow('');
});

test('button view: the form opens over the page and closes with Escape', async () => {
  const page = await browser.newPage({ viewport: { width: 390, height: 800 } });
  await page.goto(SITE + '/button');
  const btn = await page.waitForSelector('.shootbook-button');
  assert.equal(await btn.textContent(), 'Book a session');
  await btn.click();
  const f = await (await page.waitForSelector('[role=dialog] iframe')).contentFrame();
  await f.waitForSelector('button.bkb-day');
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), 390);
  await page.keyboard.press('Escape');
  assert.equal(await page.evaluate(() => document.querySelector('[role=dialog]').style.display), 'none');
  await page.close();
});

test('a block added after the page has loaded gets the form too', async () => {
  const page = await browser.newPage();
  await page.goto(SITE + '/late');
  const f = await (await page.waitForSelector('[data-shootbook] iframe')).contentFrame();
  await f.waitForSelector('button.bkb-day');
  assert.equal(await f.evaluate(() => document.documentElement.lang), 'ru');
  await page.close();
});

test('admin: the widget page gives the code, a preview and saves the list of sites', async () => {
  const page = await browser.newPage();
  await login(page);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-embed');
  const code = await page.inputValue('#bkb-e-code');
  assert.ok(code.includes(`data-shootbook="${FORM}"`));
  assert.ok(code.includes('assets/embed.js'));
  await page.check('input[name=bkb-e-mode][value=button]');
  await page.fill('#bkb-e-label', 'Записаться');
  await page.selectOption('#bkb-e-lang', 'ru');
  const code2 = await page.inputValue('#bkb-e-code');
  assert.ok(code2.includes('data-lang="ru"') && code2.includes('data-mode="button"') && code2.includes('data-label="Записаться"'));
  await page.waitForSelector('#bkb-e-preview .shootbook-button');
  await page.fill('#bkb-e-sites', 'iparstudio.com\nnot a site');
  await Promise.all([page.waitForNavigation(), page.click('#bkb-embed-sites button[type=submit]')]);
  assert.equal(await page.inputValue('#bkb-e-sites'), 'https://iparstudio.com');
  await page.close();
  allow('');
});
