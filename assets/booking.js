/*
 * Booking Calendar form.
 * Public API for site add-ons (window.bkb):
 *   bkb.setPrice(fn)   fn(state) returns the price text, or '' to hide the price block
 *   bkb.state          current choices: service, location, hours, opts{}, slang, date, time
 *   bkb.config         window.BKB as printed by the plugin
 *   bkb.refresh()      redraw after changing something from outside
 * Events on document: bkb:change and bkb:submitted, with detail = { state: … }.
 * window.BKB_PREFILL (object or query string) set before this script preselects choices.
 */
(function () {
	'use strict';
	var C = window.BKB;
	var root = document.getElementById('bkb');
	if (!C || !root) { return; }
	var T = C.t;
	document.body.classList.add('bk-has-booking');
	var opened = Date.now();
	var RM = window.matchMedia ? matchMedia('(prefers-reduced-motion: reduce)').matches : false;
	var priceFn = null;

	function esc(s) {
		return String(s).replace(/[\x26<>"]/g, function (c) {
			return { '\x26': '\x26amp;', '<': '\x26lt;', '>': '\x26gt;', '"': '\x26quot;' }[c];
		});
	}
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	// With plain permalinks the API address already holds "?rest_route=…", so the query is added with "&".
	function apiUrl(path, query) {
		var u = C.api + path;
		return query ? u + (u.indexOf('?') < 0 ? '?' : '\x26') + query : u;
	}
	function fill(tpl, v) { return String(tpl).replace(/\{(\w+)\}/g, function (m, k) { return v[k] != null ? v[k] : m; }); }
	function fmtMin(m) {
		var h = Math.floor(m / 60), r = m % 60, s = [];
		if (h) { s.push(h + ' ' + T.h_unit); }
		if (r) { s.push(r + ' ' + T.min_unit); }
		return s.join(' ');
	}
	function fmtH(h) { return fmtMin(Math.round(h * 60)); }
	function byId(list, id) { for (var i = 0; i < list.length; i++) { if (String(list[i].id) === String(id)) { return list[i]; } } return null; }

	/* ---------- preselection: URL, then BKB_PREFILL ---------- */
	function parse(str) {
		var o = {};
		String(str || '').replace(/^\?/, '').split('\x26').forEach(function (kv) {
			if (!kv) { return; }
			var i = kv.indexOf('=');
			var k = decodeURIComponent(i < 0 ? kv : kv.slice(0, i));
			o[k] = i < 0 ? '' : decodeURIComponent(kv.slice(i + 1).replace(/\+/g, ' '));
		});
		return o;
	}
	var q = parse(location.search);
	var pre = window.BKB_PREFILL;
	if (typeof pre === 'string') { pre = parse(pre); }
	if (pre ? !(q.service || q.pkg) : false) { q = pre; }
	function pick(list, v) { return byId(list, v) ? String(v) : (list[0] ? String(list[0].id) : ''); }
	var st = {
		service: pick(C.services, q.service != null ? q.service : q.pkg),
		location: pick(C.locations, q.location != null ? q.location : q.loc),
		hours: 0,
		opts: {},
		slang: C.slang,
		date: '',
		time: ''
	};
	var hq = parseFloat(q.hours != null ? q.hours : q.h);
	st.hours = C.durations.indexOf(hq) >= 0 ? hq : C.duration;
	C.options.forEach(function (o) {
		var v = q[o.id];
		if (o.type === 'choice') {
			st.opts[o.id] = byId(o.choices, v) ? v : (byId(o.choices, o.default) ? o.default : (o.choices[0] ? o.choices[0].id : ''));
		} else {
			st.opts[o.id] = v != null ? (v === '1' ? '1' : '0') : (o.default === '1' ? '1' : '0');
		}
	});
	function optOn(o) { return !o.locations.length || o.locations.indexOf(st.location) >= 0; }

	var now = new Date();
	var view = { y: now.getFullYear(), m: now.getMonth() };
	var slots = {};
	var loaded = {};
	var autoAdvance = 3;

	/* ---------- markup ---------- */
	function seg(k, label, opts, extra) {
		return '<div class="bkb-row' + (extra || '') + '" data-row="' + esc(k) + '"><span class="bkb-lbl" id="bkb-l-' + esc(k) + '">' + esc(label) + '</span>' +
			'<div class="bkb-seg" role="group" aria-labelledby="bkb-l-' + esc(k) + '">' + opts.map(function (o) {
				return '<button type="button" data-k="' + esc(k) + '" data-v="' + esc(o[0]) + '" aria-pressed="false">' + esc(o[1]) + '</button>';
			}).join('') + '</div></div>';
	}
	function field(name, label, input, req) {
		return '<div class="bkb-f" data-f="' + name + '"><label for="bkb-' + name + '">' + esc(label) + (req ? ' *' : '') + '</label>' + input + '<div class="bkb-ferr" id="bkb-' + name + '-err"></div></div>';
	}
	var n = 0;
	function head(title) {
		n++;
		return '<div class="bkb-h" role="heading" aria-level="3"><span class="bkb-n">' + pad(n) + '</span>' + esc(title) + '</div>';
	}
	var yn = [['0', T.no], ['1', T.yes]];
	var session = '';
	if (C.services.length > 1) { session += seg('service', T.service, C.services.map(function (s) { return [s.id, s.label]; })); }
	if (C.locations.length > 1) { session += seg('location', T.location, C.locations.map(function (s) { return [s.id, s.label]; })); }
	if (C.durations.length > 1) { session += seg('hours', T.duration, C.durations.map(function (h) { return [h, fmtH(h)]; })); }
	C.options.forEach(function (o) {
		session += seg('o:' + o.id, o.label, o.type === 'choice' ? o.choices.map(function (c) { return [c.id, c.label]; }) : yn, ' bkb-row-opt');
	});
	if (session) { session += '<div class="bkb-dur"></div>'; }
	var F = C.fields;
	var consent = T.consent.replace('%s', esc(C.privacy)).replace('<a ', '<a style="color:inherit;text-decoration:underline" ');

	var html = '<form class="bkb-form" novalidate><div class="bkb-top' + (session ? '' : ' bkb-top-single') + '">';
	if (session) { html += '<section class="bkb-step bkb-session">' + head(T.h_service) + session + '</section>'; }
	html += '<section class="bkb-step" id="bkb-slot">' + head(T.h_date) +
		'<div class="bkb-cal"></div><div class="bkb-times" aria-live="polite"></div>' + (session ? '' : '<div class="bkb-dur"></div>') + '<div class="bkb-tz">' + esc(T.tz) + '</div><div class="bkb-ferr" id="bkb-slot-err"></div></section></div>';
	html += '<section class="bkb-step bkb-details">' + head(T.h_details) + '<div class="bkb-grid2">' +
		field('name', T.name, '<input id="bkb-name" name="name" autocomplete="name" required aria-describedby="bkb-name-err">', true) +
		field('email', T.email, '<input id="bkb-email" name="email" type="email" inputmode="email" autocomplete="email" required aria-describedby="bkb-email-err">', true) +
		(F.phone ? field('phone', T.phone, '<input id="bkb-phone" name="phone" autocomplete="tel">', false) : '') +
		(F.slang.length ? '<div class="bkb-f">' + seg('slang', T.session_lang, F.slang.map(function (c) { return [c.id, c.label]; }), ' bkb-row-stack') + '</div>' : '') +
		'</div>' +
		(F.details.on ? field('details', F.details.label, '<textarea id="bkb-details" name="details" rows="2" placeholder="' + esc(F.details.placeholder) + '"' + (F.details.required ? ' required' : '') + ' aria-describedby="bkb-details-err"></textarea>', F.details.required) : '') +
		(F.message ? field('msg', T.msg, '<textarea id="bkb-msg" name="msg" rows="2"></textarea>', false) : '') +
		'<label class="bkb-hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>' +
		'<div class="bkb-f" data-f="consent"><label class="bkb-check"><input type="checkbox" name="consent" required aria-describedby="bkb-consent-err"><span class="bkb-box" aria-hidden="true"></span><span>' + consent + '</span></label><div class="bkb-ferr" id="bkb-consent-err"></div></div>' +
		'</section>' +
		'<div class="bkb-stub"><div class="bkb-stub-main"><div class="bkb-lbl">' + esc(T.recap) + '</div><div class="bkb-recap" aria-live="polite"></div></div>' +
		'<div class="bkb-stub-side"><div class="bkb-price-box"><div class="bkb-lbl">' + esc(T.price) + '</div><div class="bkb-price"></div></div>' +
		'<button type="submit" class="bkb-submit">' + esc(T.submit) + '</button></div></div>' +
		'<div class="bkb-err" role="alert"></div><div class="bkb-note">' + esc(T.note) + '</div></form>';
	root.innerHTML = html;

	var form = root.querySelector('form');
	var cal = root.querySelector('.bkb-cal');
	var timesEl = root.querySelector('.bkb-times');
	var errEl = root.querySelector('.bkb-err');
	var priceEl = root.querySelector('.bkb-price');
	var priceBox = root.querySelector('.bkb-price-box');
	var durEl = root.querySelector('.bkb-dur');
	var recapEl = root.querySelector('.bkb-recap');

	function dateLabel(ds) {
		var p = ds.split('-');
		var d = new Date(+p[0], +p[1] - 1, +p[2]);
		return T.wd[(d.getDay() + 6) % 7] + ', ' + p[2] + '.' + p[1] + '.' + p[0];
	}
	function endTime(t) {
		var p = t.split(':');
		var m = +p[0] * 60 + +p[1] + Math.round(st.hours * 60) + C.prep;
		return pad(Math.floor(m / 60) % 24) + ':' + pad(m % 60);
	}
	function stateValue(k) {
		if (k.indexOf('o:') === 0) { return st.opts[k.slice(2)]; }
		return st[k];
	}
	function syncSeg() {
		[].forEach.call(root.querySelectorAll('.bkb-seg button'), function (b) {
			b.setAttribute('aria-pressed', String(stateValue(b.getAttribute('data-k'))) === b.getAttribute('data-v') ? 'true' : 'false');
		});
		C.options.forEach(function (o) {
			var row = root.querySelector('[data-row="o:' + o.id + '"]');
			if (row) { row.classList.toggle('is-off', !optOn(o)); }
		});
	}
	// The same sum the server saves with the request (bkb_price_amount), unless a site script sets its own price.
	function priceAmount() {
		var svc = byId(C.services, st.service);
		if (!svc || !(svc.price > 0)) { return null; }
		var sum = svc.price * st.hours;
		C.options.forEach(function (o) {
			if (!optOn(o)) { return; }
			var add = 0;
			if (o.type === 'choice') {
				var c = byId(o.choices, st.opts[o.id]);
				add = c ? (c.price || 0) : 0;
			} else if (st.opts[o.id] === '1') { add = o.price || 0; }
			sum += o.per_hour ? add * st.hours : add;
		});
		return Math.round(sum * 100) / 100;
	}
	function money(a) {
		var M = C.money || {};
		var parts = (a % 1 === 0 ? a.toFixed(0) : a.toFixed(2)).split('.');
		var num = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, M.thou || ' ') + (parts[1] ? (M.dec || '.') + parts[1] : '');
		if (!M.currency) { return num; }
		return M.before ? M.currency + '\u00a0' + num : num + '\u00a0' + M.currency;
	}
	function priceText() {
		if (!priceFn) {
			var a = priceAmount();
			return a === null ? '' : money(a);
		}
		try { return String(priceFn(st) || ''); } catch (e) { return ''; }
	}
	function recapParts() {
		var parts = [];
		var loc = byId(C.locations, st.location);
		if (loc ? C.locations.length > 1 : false) { parts.push(loc.label); }
		var yes = [];
		C.options.forEach(function (o) {
			if (!optOn(o)) { return; }
			if (o.type === 'choice') {
				var c = byId(o.choices, st.opts[o.id]);
				if (c) { parts.push(o.label + ': ' + String(c.label).toLowerCase()); }
			} else if (st.opts[o.id] === '1') { yes.push(o.label); }
		});
		parts.push(fmtH(st.hours) + (C.prep ? ' (' + fill(T.prep_short, { prep: fmtMin(C.prep) }) + ')' : ''));
		return parts.concat(yes);
	}
	function update() {
		syncSeg();
		var p = priceText();
		priceBox.hidden = !p;
		if (priceEl.textContent !== p) {
			priceEl.textContent = p;
			if (!RM) {
				priceEl.classList.remove('bkb-pop');
				void priceEl.offsetWidth;
				priceEl.classList.add('bkb-pop');
			}
		}
		if (durEl) { durEl.textContent = C.prep ? fill(T.dur_note, { prep: fmtMin(C.prep), total: fmtMin(Math.round(st.hours * 60) + C.prep) }) : ''; }
		var when = st.date ? (st.time ? dateLabel(st.date) + ', ' + st.time + '–' + endTime(st.time) : dateLabel(st.date)) : '';
		var svc = byId(C.services, st.service);
		recapEl.innerHTML = (svc ? '<div class="bkb-recap-pkg">' + esc(svc.label) + '</div>' : '') +
			'<div class="bkb-recap-when' + (when ? '' : ' is-empty') + '">' + esc(when || T.f_slot) + '</div>' +
			'<div class="bkb-recap-opts">' + esc(recapParts().join(' · ')) + '</div>';
		try { document.dispatchEvent(new CustomEvent('bkb:change', { detail: { state: st } })); } catch (e) {}
	}

	/* ---------- calendar ---------- */
	function sk() { return st.hours + '|' + st.location; }
	function key() { return view.y + '-' + pad(view.m + 1) + '|' + sk(); }

	function load() {
		var k = key();
		if (loaded[k]) { render(); return; }
		cal.innerHTML = '<div class="bkb-loading">' + esc(T.loading) + '</div>';
		fetch(apiUrl('slots', 'month=' + view.y + '-' + pad(view.m + 1) + '\x26h=' + st.hours + '\x26loc=' + encodeURIComponent(st.location)), { credentials: 'omit' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				loaded[k] = true;
				var s = (j ? j.slots : null) || {};
				var kk = sk();
				Object.keys(s).forEach(function (d) { slots[d + '|' + kk] = s[d]; });
				if (!Object.keys(s).length ? autoAdvance > 0 : false) {
					autoAdvance--;
					step(1);
					return;
				}
				autoAdvance = 0;
				render();
			})
			.catch(function () { cal.innerHTML = '<div class="bkb-ferr">' + esc(T.err) + '</div>'; });
	}

	function step(d) {
		view.m += d;
		if (view.m < 0) { view.m = 11; view.y--; }
		if (view.m > 11) { view.m = 0; view.y++; }
		load();
	}

	function render() {
		var first = new Date(view.y, view.m, 1);
		var lead = (first.getDay() + 6) % 7;
		var days = new Date(view.y, view.m + 1, 0).getDate();
		var isCurrent = view.y === now.getFullYear() ? view.m === now.getMonth() : false;
		var h = '<div class="bkb-cal-head"><button type="button" class="bkb-nav" data-d="-1" aria-label="' + esc(T.prev) + '"' + (isCurrent ? ' disabled' : '') + '>‹</button>' +
			'<span class="bkb-month">' + esc(T.months[view.m]) + ' ' + view.y + '</span>' +
			'<button type="button" class="bkb-nav" data-d="1" aria-label="' + esc(T.next) + '">›</button></div>' +
			'<div class="bkb-days">' + T.wd.map(function (w) { return '<span class="bkb-wd">' + esc(w) + '</span>'; }).join('');
		for (var i = 0; i < lead; i++) { h += '<span></span>'; }
		for (var d = 1; d <= days; d++) {
			var ds = view.y + '-' + pad(view.m + 1) + '-' + pad(d);
			var free = slots[ds + '|' + sk()];
			h += free ? '<button type="button" class="bkb-day" data-date="' + ds + '" aria-pressed="' + (st.date === ds) + '">' + d + '</button>' : '<span class="bkb-day is-off">' + d + '</span>';
		}
		h += '</div>';
		cal.innerHTML = h;
		renderTimes();
	}

	function renderTimes() {
		var list = st.date ? slots[st.date + '|' + sk()] : null;
		if (!st.date) { timesEl.innerHTML = '<div class="bkb-hint">' + esc(T.pick_day) + '</div>'; return; }
		if (!list || !list.length) { timesEl.innerHTML = '<div class="bkb-hint">' + esc(T.no_times) + '</div>'; return; }
		timesEl.innerHTML = '<div class="bkb-lbl">' + esc(T.pick_time) + '</div><div class="bkb-tlist">' + list.map(function (t) {
			return '<button type="button" class="bkb-time" data-time="' + t + '" aria-pressed="' + (st.time === t) + '">' + t + '</button>';
		}).join('') + '</div>';
	}

	/* ---------- inline validation ---------- */
	var touched = false;
	function setErr(name, msg) {
		var box = root.querySelector('[data-f="' + name + '"]');
		var p = document.getElementById('bkb-' + name + '-err');
		if (p) { p.textContent = msg || ''; }
		if (box) {
			box.classList.toggle('is-bad', !!msg);
			var inp = box.querySelector('input,textarea');
			if (inp) { inp.setAttribute('aria-invalid', msg ? 'true' : 'false'); }
		}
		return !msg;
	}
	function check(name) {
		var f = form.elements;
		if (name === 'name') { return setErr('name', f.name.value.trim() ? '' : T.f_req); }
		if (name === 'email') {
			var v = f.email.value.trim();
			return setErr('email', !v ? T.f_req : (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) ? '' : T.f_email));
		}
		if (name === 'details') { return (F.details.on ? F.details.required : false) ? setErr('details', f.details.value.trim() ? '' : T.f_req) : true; }
		if (name === 'consent') { return setErr('consent', f.consent.checked ? '' : T.f_consent); }
		if (name === 'slot') {
			var ok = !!(st.date ? st.time : '');
			document.getElementById('bkb-slot-err').textContent = ok ? '' : T.f_slot;
			return ok;
		}
		return true;
	}

	root.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('button') : null;
		if (!b || !root.contains(b) || b.type === 'submit') { return; }
		var k = b.getAttribute('data-k');
		if (k) {
			var v = b.getAttribute('data-v');
			if (k.indexOf('o:') === 0) { st.opts[k.slice(2)] = v; }
			else { st[k] = k === 'hours' ? parseFloat(v) : v; }
			if (k === 'hours' || k === 'location') { st.time = ''; load(); }
			update();
		} else if (b.classList.contains('bkb-nav')) { step(parseInt(b.getAttribute('data-d'), 10)); }
		else if (b.classList.contains('bkb-day')) {
			st.date = b.getAttribute('data-date');
			st.time = '';
			render();
			update();
			var tl = timesEl.querySelector('.bkb-tlist');
			if (tl ? tl.getBoundingClientRect().top > window.innerHeight - 80 : false) { timesEl.scrollIntoView({ behavior: RM ? 'auto' : 'smooth', block: 'center' }); }
		} else if (b.classList.contains('bkb-time')) {
			st.time = b.getAttribute('data-time');
			renderTimes();
			update();
			if (touched) { check('slot'); }
			errEl.textContent = '';
		}
	});

	form.addEventListener('change', function (e) {
		if (e.target.name === 'consent' ? touched : false) { check('consent'); }
	});
	['name', 'email', 'details'].forEach(function (n) {
		var el = form.elements[n];
		if (!el) { return; }
		el.addEventListener('blur', function () { if (el.value.trim() || touched) { check(n); } });
		el.addEventListener('input', function () { if (el.getAttribute('aria-invalid') === 'true') { check(n); } });
	});
	['name', 'phone', 'details', 'msg'].forEach(function (n) {
		var el = form.elements[n];
		if (el) { el.addEventListener('input', function () { try { document.dispatchEvent(new CustomEvent('bkb:change', { detail: { state: st } })); } catch (x) {} }); }
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		touched = true;
		errEl.textContent = '';
		var order = ['slot', 'name', 'email', 'details', 'consent'];
		var bad = order.filter(function (n) { return !check(n); });
		if (bad.length) {
			var first = bad[0] === 'slot' ? document.getElementById('bkb-slot') : form.elements[bad[0]];
			first.scrollIntoView({ behavior: RM ? 'auto' : 'smooth', block: 'center' });
			if (bad[0] !== 'slot') { setTimeout(function () { first.focus({ preventScroll: true }); }, 300); }
			return;
		}
		var f = form.elements;
		var opts = {};
		C.options.forEach(function (o) { if (optOn(o)) { opts[o.id] = st.opts[o.id]; } });
		var body = {
			date: st.date, time: st.time, service: st.service, location: st.location, hours: st.hours, opts: opts,
			price: priceText().replace(/ /g, ' '),
			name: f.name.value, email: f.email.value.trim(),
			phone: f.phone ? f.phone.value : '', details: f.details ? f.details.value : '', msg: f.msg ? f.msg.value : '',
			slang: st.slang, consent: f.consent.checked ? 1 : 0,
			website: f.website.value, el: Date.now() - opened, ui: C.lang, page: location.origin + location.pathname,
			src: (function () { try { return localStorage.getItem('bkb_src') || ''; } catch (e) { return ''; } })()
		};
		var btn = form.querySelector('.bkb-submit');
		btn.disabled = true;
		btn.textContent = T.sending;
		fetch(apiUrl('request'), { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
			.then(function (res) {
				if (res.s === 200 ? (res.j ? res.j.ok : false) : false) {
					try { document.dispatchEvent(new CustomEvent('bkb:submitted', { detail: { state: st } })); } catch (x) {}
					var p = priceText();
					root.innerHTML = '<div class="bkb-ok" tabindex="-1">' +
						'<div class="bkb-ok-mark" aria-hidden="true">✓</div><div class="bkb-ok-title" role="heading" aria-level="3">' + esc(T.ok_title) + '</div>' +
						'<div class="bkb-stub bkb-stub-ok"><div class="bkb-stub-main"><div class="bkb-recap">' + recapEl.innerHTML + '</div></div>' +
						(p ? '<div class="bkb-stub-side"><div class="bkb-lbl">' + esc(T.price) + '</div><div class="bkb-price">' + esc(p) + '</div></div>' : '') + '</div>' +
						'<div>' + esc(T.ok_mail.replace('%s', body.email)) + '</div><div>' + esc(T.ok_next) + '</div></div>';
					var ok = root.querySelector('.bkb-ok');
					ok.scrollIntoView({ block: 'start' });
					ok.focus({ preventScroll: true });
					return;
				}
				btn.disabled = false;
				btn.textContent = T.submit;
				if (res.s === 409) {
					errEl.textContent = T.err_taken;
					Object.keys(loaded).forEach(function (k) { delete loaded[k]; });
					slots = {};
					st.time = '';
					load();
					update();
					document.getElementById('bkb-slot').scrollIntoView({ behavior: RM ? 'auto' : 'smooth', block: 'start' });
				} else if (res.s === 422) {
					errEl.textContent = T.err_fields;
				} else {
					errEl.textContent = T.err;
				}
			})
			.catch(function () {
				btn.disabled = false;
				btn.textContent = T.submit;
				errEl.textContent = T.err;
			});
	});

	window.bkb = {
		state: st,
		config: C,
		form: form,
		setPrice: function (fn) { priceFn = typeof fn === 'function' ? fn : null; update(); },
		refresh: function () { update(); },
		recap: recapParts,
		dateLabel: dateLabel,
		endTime: endTime
	};

	update();
	load();
})();
