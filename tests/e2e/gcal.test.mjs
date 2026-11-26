// Google Calendar sync. Needs tests/e2e/mock-google.php in mu-plugins: nothing goes to Google.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium, seed, wpEval, api, firstSlot, bookableMonth, login, requestBody, mails, clearMail, WP_URL } from './lib.mjs';

const CAL = 'studio@example.test';
let browser, page;

/** Events the test Google holds in a calendar. */
const events = (cal = CAL) => JSON.parse(wpEval(`echo wp_json_encode((object)(get_option('bkb_test_gcal_events',[])['${cal}']??[]));`));
const meta = (id, key) => wpEval(`echo get_post_meta(${id},'${key}',true);`);
const lastId = () => Number(wpEval("$p=get_posts(['post_type'=>'bk_booking','post_status'=>'any','numberposts'=>1,'fields'=>'ids','orderby'=>'ID']);echo $p[0]??0;"));

/** Connected as if the owner had signed in. */
function connect(extra = '') {
  wpEval(`update_option('bkb_gcal',['client_id'=>'cid','client_secret'=>'cs','refresh'=>'rt-1','account'=>'${CAL}','calendar'=>'${CAL}','calendar_name'=>'${CAL}'${extra}],false);`);
}

async function book(over = {}) {
  const s = await firstSlot(bookableMonth());
  const r = await api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: s.date, time: s.time, ...over })) });
  assert.equal(r.status, 200);
  return { id: lastId(), ...s };
}

async function setStatus(id, s) {
  await page.goto(WP_URL + `/wp-admin/post.php?post=${id}&action=edit`);
  await Promise.all([page.waitForNavigation(), page.click(s === 'confirmed' ? 'a.button-primary:has-text("Confirm")' : 'a.button:has-text("Decline")')]);
}

before(async () => {
  seed();
  browser = await chromium.launch();
  page = await browser.newPage();
  await login(page);
});
after(async () => { await browser.close(); seed(); });

test('sign in with Google: the keys, the redirect and the main calendar', async () => {
  seed();
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-gcal');
  const redirect = await page.inputValue('.bkb-steps input');
  assert.equal(redirect, WP_URL + '/wp-admin/admin-post.php');
  await page.fill('#bkb-gcal-id', 'test-client.apps.googleusercontent.com');
  await page.fill('#bkb-gcal-secret', 'test-secret');
  // The site sends the browser to Google; here Google "answers" at once with a code.
  let asked;
  await page.route('**/wp-admin/admin-post.php', async (route) => {
    const res = await route.fetch({ maxRedirects: 0 });
    asked = new URL(res.headers().location);
    route.fulfill({ status: 302, headers: { Location: `${asked.searchParams.get('redirect_uri')}?code=good-code&state=${asked.searchParams.get('state')}` } });
  });
  await Promise.all([page.waitForURL(/bkb_gcal=connected/), page.click('button:has-text("sign in with Google")')]);
  await page.unroute('**/wp-admin/admin-post.php');
  assert.equal(asked.origin + asked.pathname, 'https://accounts.google.com/o/oauth2/v2/auth');
  assert.equal(asked.searchParams.get('client_id'), 'test-client.apps.googleusercontent.com');
  assert.equal(asked.searchParams.get('access_type'), 'offline');
  assert.match(asked.searchParams.get('scope'), /auth\/calendar\.events/);
  const text = await page.textContent('.wrap');
  assert.match(text, /Connected to studio@example\.test/);
  assert.equal(await page.inputValue('#bkb-gcal-cal'), CAL);
  const o = JSON.parse(wpEval("echo wp_json_encode(get_option('bkb_gcal'));"));
  assert.equal(o.refresh, 'rt-1');
});

test('a state that the site did not make is refused', async () => {
  await page.goto(WP_URL + '/wp-admin/admin-post.php?code=good-code&state=bkb_gcal.forged');
  assert.match(page.url(), /bkb_gcal=err/);
  assert.match(await page.textContent('.wrap'), /sign-in link is old/);
});

test('confirming puts the booking in Google; declining removes it', async () => {
  seed(); connect();
  const b = await book();
  assert.deepEqual(events(), {}, 'a request waiting for an answer stays out by default');
  await setStatus(b.id, 'confirmed');
  const ev = Object.values(events());
  assert.equal(ev.length, 1);
  assert.match(ev[0].summary, /^Test Studio: Standard — Test Client$/);
  assert.equal(ev[0].status, 'confirmed');
  assert.equal(ev[0].location, 'Test street 1');
  assert.equal(new Date(ev[0].start.dateTime).getTime() / 1000, Number(meta(b.id, '_bkb_start')));
  assert.equal(ev[0].extendedProperties.private.shootbook, String(b.id));
  assert.match(ev[0].description, /client@example\.test/);
  assert.match(await page.textContent('#bkb_details'), /In Google Calendar\./);
  await setStatus(b.id, 'declined');
  assert.deepEqual(events(), {});
  assert.equal(meta(b.id, '_bkb_gcal_id'), '');
});

test('a new time moves the event, and the old time is free again', async () => {
  seed(); connect();
  const b = await book();
  await setStatus(b.id, 'confirmed');
  const eid = meta(b.id, '_bkb_gcal_id');
  await page.click('#bkb_details summary');
  await page.fill('input[name="time"]', '15:00');
  await Promise.all([page.waitForNavigation(), page.click('#bkb-move-go')]);
  assert.match(await page.textContent('#bkb_details'), /The time is changed/);
  const ev = events()[eid];
  assert.ok(ev, 'the same event');
  assert.equal(new Date(ev.start.dateTime).getTime() / 1000, Number(meta(b.id, '_bkb_start')));
  assert.equal(meta(b.id, '_bkb_time'), '15:00');
  const s = await firstSlot(b.date.slice(0, 7));
  assert.ok(s.slots[b.date].includes(b.time), 'the old start is offered again');
  assert.ok(!s.slots[b.date].includes('15:00'), 'the new one is busy');
});

test('moving onto a busy time needs the tick', async () => {
  seed(); connect();
  const one = await book();
  const two = await book({ email: 'second@example.test' });
  await page.goto(WP_URL + `/wp-admin/post.php?post=${two.id}&action=edit`);
  await page.click('#bkb_details summary');
  await page.fill('input[name="date"]', one.date);
  await page.fill('input[name="time"]', one.time);
  await Promise.all([page.waitForNavigation(), page.click('#bkb-move-go')]);
  assert.match(await page.textContent('#bkb_details'), /This time is busy/);
  await page.click('#bkb_details summary');
  await page.fill('input[name="date"]', one.date);
  await page.fill('input[name="time"]', one.time);
  await page.check('input[name="force"]');
  await Promise.all([page.waitForNavigation(), page.click('#bkb-move-go')]);
  assert.match(await page.textContent('#bkb_details'), /The time is changed/);
});

test('deleting a request removes its event', async () => {
  seed(); connect();
  const b = await book();
  await setStatus(b.id, 'confirmed');
  assert.equal(Object.keys(events()).length, 1);
  wpEval(`wp_trash_post(${b.id});`);
  assert.deepEqual(events(), {});
  wpEval(`wp_untrash_post(${b.id});wp_publish_post(${b.id});`);
  assert.equal(Object.keys(events()).length, 1, 'back from the bin, back in Google');
  wpEval(`wp_delete_post(${b.id},true);`);
  assert.deepEqual(events(), {});
});

test('with the tick, new requests go to Google with "?"', async () => {
  seed(); connect(",'pending'=>true");
  const b = await book();
  const ev = Object.values(events());
  assert.equal(ev.length, 1);
  assert.match(ev[0].summary, /^\? Test Studio/);
  assert.equal(ev[0].status, 'tentative');
  await setStatus(b.id, 'confirmed');
  const now = Object.values(events());
  assert.equal(now.length, 1);
  assert.match(now[0].summary, /^Test Studio/);
});

test('other events of the calendar are busy time; the site\'s own are not counted twice', async () => {
  seed(); connect();
  wpEval("$c=get_option('bkb_config');$c['schedule']['parallel']=true;update_option('bkb_config',$c,false);");
  const b = await book();
  await setStatus(b.id, 'confirmed');
  const s0 = await firstSlot(b.date.slice(0, 7), 1, 'hall-b');
  assert.ok(s0.slots[b.date].includes(b.time), 'hall B is still free while hall A has the booking');
  const d = b.date;
  wpEval(`update_option('bkb_test_gcal_foreign',[['id'=>'x1','iCalUID'=>'x1@google.com','status'=>'confirmed','start'=>['dateTime'=>'${d}T12:00:00+01:00'],'end'=>['dateTime'=>'${d}T14:00:00+01:00']],['id'=>'x2','status'=>'confirmed','transparency'=>'transparent','start'=>['date'=>'${d}'],'end'=>['date'=>'${d}']]],false);bkb_gcal_busy_flush();`);
  const s = await firstSlot(d.slice(0, 7), 1, 'hall-b');
  assert.ok(!s.slots[d].includes('12:00') && !s.slots[d].includes('13:00'), 'a Google event blocks the time');
  assert.ok(s.slots[d].includes('15:00'), 'a free all-day event does not');
});

test('the site\'s events that come back through an iCal address are not busy time', async () => {
  seed(); connect(",'busy'=>false");
  wpEval("$c=get_option('bkb_config');$c['schedule']['parallel']=true;update_option('bkb_config',$c,false);");
  const b = await book();
  await setStatus(b.id, 'confirmed');
  const uid = meta(b.id, '_bkb_gcal_uid');
  assert.match(uid, /@google\.com$/);
  const busy = JSON.parse(wpEval(`add_filter('pre_http_request',function($p,$a,$u){if(false===strpos($u,'own.ics'))return $p;return ['headers'=>[],'body'=>"BEGIN:VCALENDAR\\r\\nBEGIN:VEVENT\\r\\nUID:${uid}\\r\\nDTSTART:20300101T100000Z\\r\\nDTEND:20300101T110000Z\\r\\nEND:VEVENT\\r\\nBEGIN:VEVENT\\r\\nUID:${'shootbook-' + b.id}@127.0.0.1\\r\\nDTSTART:20300101T120000Z\\r\\nDTEND:20300101T130000Z\\r\\nEND:VEVENT\\r\\nBEGIN:VEVENT\\r\\nUID:other\\r\\nDTSTART:20300101T140000Z\\r\\nDTEND:20300101T150000Z\\r\\nEND:VEVENT\\r\\nEND:VCALENDAR\\r\\n",'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]];},1,3);$c=get_option('bkb_config');$c['ics']='http://feed.example.test/own.ics';update_option('bkb_config',$c,false);bkb_cfg_reset();echo wp_json_encode(bkb_busy(strtotime('2030-01-01 00:00 UTC'),strtotime('2030-01-02 00:00 UTC')));`));
  assert.deepEqual(busy.map((x) => x['0']), [Date.UTC(2030, 0, 1, 14) / 1000]);
});

test('when Google cannot be reached, the change waits and is sent later', async () => {
  seed(); connect();
  const b = await book();
  wpEval("update_option('bkb_test_gcal_down',1,false);");
  await setStatus(b.id, 'confirmed');
  assert.equal(meta(b.id, '_bkb_status'), 'confirmed', 'the booking itself is confirmed');
  assert.match(await page.textContent('#bkb_details'), /Not sent to Google yet/);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-gcal');
  assert.match(await page.textContent('.wrap'), /Waiting to be sent: 1/);
  wpEval("delete_option('bkb_test_gcal_down');do_action('bkb_gcal_hourly');");
  assert.equal(Object.keys(events()).length, 1);
  assert.equal(meta(b.id, '_bkb_gcal_dirty'), '');
});

test('choosing another calendar moves the bookings there', async () => {
  seed(); connect();
  const b = await book();
  await setStatus(b.id, 'confirmed');
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-gcal');
  await page.selectOption('#bkb-gcal-cal', 'team@group.calendar.google.com');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Save")')]);
  assert.deepEqual(events(), {});
  assert.equal(Object.keys(events('team@group.calendar.google.com')).length, 1);
  assert.match(await page.textContent('.wrap'), /calendar “Team”/);
});

test('when Google takes the access back, the admin says so', async () => {
  seed(); connect();
  wpEval("update_option('bkb_test_gcal_revoked',1,false);");
  const b = await book();
  await setStatus(b.id, 'confirmed');
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking');
  assert.match(await page.textContent('#wpbody'), /Google Calendar is not connected any more/);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-gcal');
  assert.match(await page.textContent('.wrap'), /no longer gives access/);
  assert.ok(await page.isVisible('#bkb-gcal-id'));
});

test('the calendar address shows confirmed bookings and needs the key', async () => {
  seed();
  const one = await book();
  const two = await book({ email: 'second@example.test' });
  await setStatus(one.id, 'confirmed');
  const url = wpEval('echo bkb_feed_url();');
  const bad = await fetch(url.replace(/key=[^&]+/, 'key=wrong'));
  assert.equal(bad.status, 403);
  const res = await fetch(url);
  assert.equal(res.status, 200);
  assert.match(res.headers.get('content-type'), /text\/calendar/);
  const ics = await res.text();
  assert.match(ics, new RegExp(`UID:shootbook-${one.id}@`));
  assert.doesNotMatch(ics, new RegExp(`UID:shootbook-${two.id}@`));
  assert.match(ics, /SUMMARY:Test Studio: Standard — Test Client/);
  await page.goto(WP_URL + '/wp-admin/edit.php?post_type=bk_booking&page=bkb-gcal');
  assert.equal(await page.inputValue('#bkb-feed-url'), url);
  await Promise.all([page.waitForNavigation(), page.click('a:has-text("Make a new address")')]);
  assert.equal((await fetch(url)).status, 403, 'the old address stops working');
});

test('the client gets a calendar file when confirmed and a cancellation when declined after that', async () => {
  seed();
  const b = await book();
  clearMail();
  await setStatus(b.id, 'confirmed');
  const conf = mails().find((m) => m.to === 'client@example.test');
  const ics = conf.attached['booking.ics'];
  assert.match(ics, /METHOD:PUBLISH/);
  assert.match(ics, new RegExp(`UID:shootbook-${b.id}@`));
  assert.match(ics, /LOCATION:Test street 1/);
  assert.match(ics, /\r\n/);
  clearMail();
  await setStatus(b.id, 'declined');
  const decl = mails().find((m) => m.to === 'client@example.test');
  assert.match(decl.attached['booking.ics'], /METHOD:CANCEL[\s\S]*STATUS:CANCELLED/);
  assert.ok(Number(decl.attached['booking.ics'].match(/SEQUENCE:(\d+)/)[1]) > Number(ics.match(/SEQUENCE:(\d+)/)[1]));
  // A request declined straight away gets no file.
  const c = await book({ email: 'second@example.test' });
  clearMail();
  await setStatus(c.id, 'declined');
  assert.deepEqual(mails().find((m) => m.to === 'second@example.test').attached, []);
});
