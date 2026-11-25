// Several locations: a request blocks all of them, or only its own one when locations are booked separately.
import { test, before } from 'node:test';
import assert from 'node:assert/strict';
import { seed, wpEval, api, firstSlot, bookableMonth, requestBody } from './lib.mjs';

let slot;
const month = bookableMonth();
const setCfg = (php) => wpEval(`$c=get_option('bkb_config');${php}update_option('bkb_config',bkb_sanitize_config($c));`);
const offered = async (loc) => {
  const { body } = await api(`slots?month=${month}&h=1&loc=${loc}`);
  return (body.slots[slot.date] || []).includes(slot.time);
};
const book = (email, loc = 'hall-a') => api('request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody({ date: slot.date, time: slot.time, email, location: loc })) });

before(async () => {
  seed();
  slot = await firstSlot(month, 1, 'hall-a');
  assert.ok(slot);
  assert.equal((await book('first@example.test')).status, 200);
});

test('by default a request in one hall takes the time in the other hall too', async () => {
  assert.equal(await offered('hall-a'), false);
  assert.equal(await offered('hall-b'), false);
});

test('booked separately: the other hall stays free and can be booked', async () => {
  setCfg("$c['schedule']['parallel']=true;");
  assert.equal(await offered('hall-a'), false);
  assert.equal(await offered('hall-b'), true);
  assert.equal((await book('second@example.test', 'hall-b')).status, 200);
  assert.equal(await offered('hall-b'), false);
});

test('a hall for two shoots at once takes a second request, not a third', async () => {
  setCfg("$c['schedule']['parallel']=true;$c['locations'][0]['capacity']=2;");
  assert.equal(await offered('hall-a'), true);
  assert.equal((await book('third@example.test')).status, 200);
  assert.equal(await offered('hall-a'), false);
  assert.equal((await book('fourth@example.test')).status, 409);
});

test('a request without a location still takes the time everywhere', async () => {
  seed();
  setCfg("$c['schedule']['parallel']=true;");
  assert.equal((await book('old@example.test')).status, 200);
  wpEval("$p=get_posts(['post_type'=>'bk_booking','numberposts'=>1,'fields'=>'ids']);update_post_meta($p[0],'_bkb_location','');");
  assert.equal(await offered('hall-b'), false);
});
