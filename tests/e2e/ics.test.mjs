// Busy times from an iCal feed.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { writeFileSync, rmSync } from 'node:fs';
import { seed, wpEval, firstSlot, bookableMonth, WP_PATH, WP_URL } from './lib.mjs';

const file = WP_PATH + '/bkb-test-busy.ics';
let day;

before(async () => {
  seed();
  day = (await firstSlot(bookableMonth())).date;
  const d = day.replace(/-/g, '');
  writeFileSync(file, [
    'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//test//EN',
    'BEGIN:VEVENT', 'UID:busy-1', `DTSTART;TZID=Europe/Budapest:${d}T120000`, `DTEND;TZID=Europe/Budapest:${d}T140000`, 'SUMMARY:Busy', 'END:VEVENT',
    'END:VCALENDAR', '',
  ].join('\r\n'));
  wpEval(`$c=get_option('bkb_config');$c['ics']='${WP_URL}/bkb-test-busy.ics';update_option('bkb_config',$c,false);global $wpdb;$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%bkb\\\\_ics\\\\_%'");`);
});
after(() => { rmSync(file, { force: true }); seed(); });

test('a busy event 12:00–14:00 removes the start times that overlap it', async () => {
  const s = await firstSlot(day.slice(0, 7));
  assert.deepEqual(s.slots[day], ['10:00', '11:00', '14:00', '15:00', '16:00', '17:00']);
});
