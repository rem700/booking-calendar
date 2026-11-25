// Helpers for the end-to-end tests. Settings come from the environment, see tests/README.md.
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
export const { chromium } = require('playwright');

export const WP_URL = (process.env.WP_URL || 'http://127.0.0.1:8080').replace(/\/$/, '');
export const WP_PATH = process.env.WP_PATH || '';
export const MAIL_LOG = process.env.MAIL_LOG || '';
export const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
export const ADMIN_PASS = process.env.WP_ADMIN_PASS || '';

const here = dirname(fileURLToPath(import.meta.url));

/** Reset the test site: plugin settings, a booking page, no requests or clients. */
export function seed() {
  if (!WP_PATH) throw new Error('Set WP_PATH to the test WordPress folder');
  const out = execFileSync('php', [join(here, 'seed.php'), WP_PATH], { env: { ...process.env, WP_URL } }).toString();
  clearMail();
  return JSON.parse(out.trim().split('\n').pop());
}

/** Run PHP inside WordPress and return what it prints. */
export function wpEval(code) {
  const php = `$_SERVER['HTTP_HOST']=parse_url('${WP_URL}',PHP_URL_HOST);require '${WP_PATH}/wp-load.php';${code}`;
  return execFileSync('php', ['-r', php]).toString();
}

export function clearMail() {
  if (MAIL_LOG) writeFileSync(MAIL_LOG, '');
}

/** E-mails caught by the test site, as { to, subject, message, headers }. */
export function mails() {
  if (!MAIL_LOG || !existsSync(MAIL_LOG)) return [];
  return readFileSync(MAIL_LOG, 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l));
}

export async function api(path, init) {
  const res = await fetch(WP_URL + '/wp-json/bk-booking/v1/' + path, init);
  return { status: res.status, body: await res.json() };
}

/** First day in the slots answer and its first time. */
export async function firstSlot(month, h = 1, loc = '') {
  const { body } = await api(`slots?month=${month}&h=${h}&loc=${encodeURIComponent(loc)}`);
  const days = Object.keys(body.slots).sort();
  if (!days.length) return null;
  return { date: days[0], time: body.slots[days[0]][0], slots: body.slots };
}

/** The month (YYYY-MM) that holds the first bookable day. */
export function bookableMonth() {
  const d = new Date(Date.now() + 2 * 86400000);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export async function login(page) {
  await page.goto(WP_URL + '/wp-login.php');
  await page.fill('#user_login', ADMIN_USER);
  await page.fill('#user_pass', ADMIN_PASS);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
}

/** A request sent straight to the REST endpoint, as the form would send it. */
export function requestBody(over = {}) {
  return {
    service: 'standard', location: 'hall-a', hours: 1, opts: {},
    name: 'Test Client', email: 'client@example.test', phone: '+36 1 234 5678',
    msg: 'Hello', ui: 'en', page: WP_URL + '/book/', consent: true, el: 5000, website: '',
    ...over,
  };
}
