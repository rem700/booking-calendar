// Instagram Direct → clients. Needs tests/e2e/mock-instagram.php in the test site's mu-plugins.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { chromium, seed, wpEval, login, mails, clearMail, WP_URL } from './lib.mjs';

const SECRET = 'test-secret';
const HOOK = WP_URL + '/wp-json/bk-booking/v1/instagram';
const OWN = '17841400000000001';
let browser, page;

const php = (code) => wpEval(code).trim();
const clients = () => JSON.parse(php("global $wpdb;echo wp_json_encode($wpdb->get_results('SELECT id,name,instagram,source,stage FROM '.bkb_crm_table('clients').' ORDER BY id',ARRAY_A));"));
const events = (cid) => JSON.parse(php(`echo wp_json_encode(array_map(fn($e)=>[$e['type'],$e['body']],bkb_crm_events(${cid})));`));
const log = () => JSON.parse(php("echo wp_json_encode(get_option('bkb_test_log',[]));"));
const clientId = (ig) => clients().find((c) => c.instagram === ig)?.id;

function hook(payload, secret = SECRET) {
  const body = JSON.stringify(payload);
  const sig = 'sha256=' + createHmac('sha256', secret).update(body).digest('hex');
  return fetch(HOOK, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Hub-Signature-256': sig }, body });
}
const msg = (from, to, mid, text, extra = {}) => ({
  object: 'instagram',
  entry: [{ id: OWN, time: Date.now(), messaging: [{ sender: { id: from }, recipient: { id: to }, timestamp: Date.now(), message: { mid, text, ...extra } }] }],
});

before(async () => {
  seed();
  php("global $wpdb;$wpdb->query('TRUNCATE '.bkb_ig_table('people'));$wpdb->query('TRUNCATE '.bkb_ig_table('messages'));delete_option('bkb_instagram');delete_option('bkb_test_log');delete_option('bkb_test_convs');delete_option('bkb_test_token_state');");
  browser = await chromium.launch();
  page = await browser.newPage();
  await login(page);
});
after(async () => { await browser?.close(); });

test('Meta can verify the webhook address only with the right verify token', async () => {
  const verify = php("echo bkb_ig_get('verify');");
  const ok = await fetch(`${HOOK}?hub.mode=subscribe&hub.verify_token=${verify}&hub.challenge=12345abc`);
  assert.equal(ok.status, 200);
  assert.equal(await ok.text(), '12345abc');
  const bad = await fetch(`${HOOK}?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1`);
  assert.equal(bad.status, 403);
});

test('a wrong token is refused with Instagram’s message, a right one connects the account', async () => {
  php("update_option('bkb_test_token_state','bad');");
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-ig');
  await page.fill('#bkb-ig-secret', SECRET);
  await page.fill('#bkb-ig-token', 'test-token');
  await Promise.all([page.waitForNavigation(), page.click('form button.button-primary')]);
  assert.match(await page.innerText('.wrap'), /Invalid OAuth access token/);
  assert.match(await page.innerText('.wrap'), /Not connected/);

  php("delete_option('bkb_test_token_state');");
  await page.fill('#bkb-ig-secret', SECRET);
  await page.fill('#bkb-ig-token', 'test-token');
  await Promise.all([page.waitForNavigation(), page.click('form button.button-primary')]);
  const text = await page.innerText('.wrap');
  assert.match(text, /Connected to @teststudio/);
  assert.ok(log().some((l) => l.method === 'POST' && l.url.includes('/me/subscribed_apps')), 'subscribed to messages');
  assert.ok(log().every((l) => !l.url.includes('test-token')), 'token not in URLs');
  // The token is not shown back in the page.
  assert.ok(!(await page.content()).includes('test-token'));
});

test('a message without the right signature is refused', async () => {
  const r = await hook(msg('1111', OWN, 'mid.x', 'hi'), 'wrong-secret');
  assert.equal(r.status, 403);
  assert.equal(clients().length, 0);
});

test('a first message makes a new client with the source Instagram and tells the studio', async () => {
  clearMail();
  const r = await hook(msg('1111', OWN, 'mid.1', 'Hello! Is 12 December free?'));
  assert.equal(r.status, 200);
  const c = clients();
  assert.equal(c.length, 1);
  assert.deepEqual([c[0].name, c[0].instagram, c[0].source, c[0].stage], ['Anna K', 'anna.k', 'Instagram', 'new']);
  assert.deepEqual(events(c[0].id), [['ig_in', 'Hello! Is 12 December free?']]);
  const m = mails();
  assert.equal(m.length, 1);
  assert.match(m[0].subject, /New message on Instagram from Anna K/);

  // The same message again (Instagram retries) is not saved twice, and no second e-mail.
  await hook(msg('1111', OWN, 'mid.1', 'Hello! Is 12 December free?'));
  await hook(msg('1111', OWN, 'mid.2', '', { attachments: [{ type: 'image', payload: { url: 'https://x' } }] }));
  assert.equal(clients().length, 1);
  assert.deepEqual(events(c[0].id).map((e) => e[1]), ['[photo]', 'Hello! Is 12 December free?']);
  assert.equal(mails().length, 1);
});

test('a message from someone already in the list goes to their card', async () => {
  php("global $wpdb;$wpdb->insert(bkb_crm_table('clients'),['email'=>'o@example.test','instagram'=>'known.client','name'=>'Olena from the form','phone'=>'','lang'=>'','source'=>'Website','stage'=>'confirmed','lost_reason'=>'','notes'=>'','created'=>bkb_crm_now(),'last_contact'=>bkb_crm_now()]);");
  await hook(msg('2222', OWN, 'mid.3', 'See you tomorrow'));
  const c = clients();
  assert.equal(c.length, 2);
  const o = c.find((x) => x.instagram === 'known.client');
  assert.equal(o.name, 'Olena from the form');
  assert.equal(o.stage, 'confirmed');
  assert.deepEqual(events(o.id)[0], ['ig_in', 'See you tomorrow']);
});

test('an answer written in the Instagram app is saved too and moves a new client to Replied', async () => {
  await hook(msg(OWN, '1111', 'mid.4', 'Yes, it is free', { is_echo: true }));
  const id = clientId('anna.k');
  assert.deepEqual(events(id).slice(0, 2), [['stage', 'Replied'], ['ig_out', 'Yes, it is free']]);
});

test('the card shows the Direct box and sends an answer', async () => {
  const id = clientId('anna.k');
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients&client=' + id);
  assert.match(await page.innerText('.bkb-ig-box'), /You can write until/);
  await page.fill('[data-ig-text]', 'Booked you for 12 December at 11:00');
  await page.click('[data-ig-send]');
  await Promise.all([page.waitForEvent('load'), page.waitForSelector('text=Sent to Direct')]);
  const sent = log().filter((l) => l.method === 'POST' && l.url.endsWith(`/${OWN}/messages`));
  assert.equal(sent.length, 1);
  assert.deepEqual(JSON.parse(sent[0].body), { recipient: { id: '1111' }, message: { text: 'Booked you for 12 December at 11:00' } });
  assert.deepEqual(events(id)[0], ['ig_out', 'Booked you for 12 December at 11:00']);
  // A ready-made reply can go to Direct as well.
  assert.ok(await page.$('[data-ig-reply]'), 'button under ready-made replies');
});

test('after 24 hours without a message from the client the site does not try to write', async () => {
  const id = clientId('anna.k');
  php("global $wpdb;$wpdb->update(bkb_ig_table('people'),['last_in'=>gmdate('Y-m-d H:i:s',time()-2*DAY_IN_SECONDS)],['igsid'=>'1111']);");
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-clients&client=' + id);
  assert.match(await page.innerText('.bkb-ig-box'), /More than 24 hours/);
  assert.equal(await page.$('[data-ig-send]'), null);
  assert.equal(await page.$('[data-ig-reply]'), null);
  const r = php(`$r=bkb_ig_send(${id},'late');echo is_wp_error($r)?'err':'ok';`);
  assert.equal(r, 'err');
});

test('the hourly check saves messages the webhook missed, once', async () => {
  const now = new Date().toISOString().replace(/\.\d+Z$/, '+0000');
  php(`update_option('bkb_test_convs',[['id'=>'c1','updated_time'=>'${now}','messages'=>['data'=>[
    ['id'=>'mid.9','created_time'=>'${now}','from'=>['id'=>'3333','username'=>'late.one'],'to'=>['data'=>[['id'=>'${OWN}']]],'message'=>'Are you open on Sunday?'],
    ['id'=>'mid.1','created_time'=>'${now}','from'=>['id'=>'1111'],'to'=>['data'=>[['id'=>'${OWN}']]],'message'=>'Hello! Is 12 December free?'],
  ]]]]);`);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-ig');
  await Promise.all([page.waitForNavigation(), page.click('text=Check for new messages now')]);
  assert.match(await page.innerText('.wrap'), /New messages saved: 1/);
  const late = clients().find((c) => c.instagram === 'user3333');
  assert.ok(late, 'new client from the hourly check');
  assert.deepEqual(events(late.id), [['ig_in', 'Are you open on Sunday?']]);
  assert.equal(php('echo bkb_ig_sync();'), '0');
});

test('disconnecting forgets the token and the secret, the history stays', async () => {
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-ig');
  await Promise.all([page.waitForNavigation(), page.click('text=Disconnect')]);
  assert.match(await page.innerText('.wrap'), /Not connected/);
  assert.equal(php("$o=bkb_ig_get();echo $o['token'].$o['secret'];"), '');
  assert.ok(events(clientId('anna.k')).length > 3);
  const r = await hook(msg('1111', OWN, 'mid.10', 'hi'));
  assert.equal(r.status, 403);
});
