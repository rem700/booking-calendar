(function () {
	'use strict';
	var A = window.BKB_ADMIN;
	var __ = wp.i18n.__, sprintf = wp.i18n.sprintf;
	var root = document.getElementById('bkb-editor');
	var form = document.getElementById('bkb-settings-form');
	if (!A || !root || !form) { return; }
	var S = JSON.parse(JSON.stringify(A.config));
	var tab = 'general';
	try { tab = sessionStorage.getItem('bkb_tab') || 'general'; } catch (e) {}
	var textLang = null;

	var DAYS = [__('Mon', 'shootbook'), __('Tue', 'shootbook'), __('Wed', 'shootbook'), __('Thu', 'shootbook'), __('Fri', 'shootbook'), __('Sat', 'shootbook'), __('Sun', 'shootbook')];
	var TABS = [
		['general', __('General', 'shootbook')], ['schedule', __('Schedule', 'shootbook')], ['services', __('Services', 'shootbook')], ['locations', __('Locations', 'shootbook')],
		['options', __('Options', 'shootbook')], ['fields', __('Form fields', 'shootbook')], ['texts', __('Texts', 'shootbook')], ['replies', __('Reply templates', 'shootbook')], ['style', __('Appearance', 'shootbook')], ['io', __('Import and export', 'shootbook')]
	];

	function esc(s) {
		return String(s == null ? '' : s).replace(/[\x26<>"]/g, function (c) {
			return { '\x26': '\x26amp;', '<': '\x26lt;', '>': '\x26gt;', '"': '\x26quot;' }[c];
		});
	}
	function langs() { return (S.languages || []).length ? S.languages : ['en']; }
	function get(path) {
		return path.split('.').reduce(function (o, k) { return o == null ? undefined : o[k]; }, S);
	}
	function set(path, val) {
		var ks = path.split('.');
		var o = S;
		for (var i = 0; i < ks.length - 1; i++) {
			if (o[ks[i]] == null || typeof o[ks[i]] !== 'object') { o[ks[i]] = /^\d+$/.test(ks[i + 1]) ? [] : {}; }
			o = o[ks[i]];
		}
		o[ks[ks.length - 1]] = val;
	}
	function hm(h) {
		if (h == null || h === '') { return ''; }
		var m = Math.round(parseFloat(h) * 60);
		return (m / 60 < 10 ? '0' : '') + Math.floor(m / 60) + ':' + (m % 60 < 10 ? '0' : '') + (m % 60);
	}
	function fromHm(v) {
		if (!v) { return null; }
		var p = v.split(':');
		return parseInt(p[0], 10) + (parseInt(p[1] || '0', 10) / 60);
	}

	/* ---------- small builders ---------- */
	function row(label, control, help) {
		return '<tr><th scope="row">' + esc(label) + '</th><td>' + control + (help ? '<p class="description">' + help + '</p>' : '') + '</td></tr>';
	}
	function text(path, attrs) {
		return '<input type="text" class="regular-text" data-p="' + path + '" value="' + esc(get(path)) + '"' + (attrs || '') + '>';
	}
	function area(path, rows, ph) {
		return '<textarea class="large-text" rows="' + (rows || 3) + '" data-p="' + path + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '>' + esc(get(path)) + '</textarea>';
	}
	function num(path, min, max, step) {
		return '<input type="number" class="small-text" data-p="' + path + '" data-t="num" min="' + min + '" max="' + max + '" step="' + (step || 1) + '" value="' + esc(get(path)) + '">';
	}
	function check(path, label) {
		return '<label><input type="checkbox" data-p="' + path + '" data-t="bool"' + (get(path) ? ' checked' : '') + '> ' + esc(label) + '</label>';
	}
	function labels(path, ph) {
		return '<div class="bkb-labels">' + langs().map(function (l) {
			return '<label><span>' + l.toUpperCase() + '</span><input type="text" data-p="' + path + '.' + l + '" value="' + esc(get(path + '.' + l)) + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '></label>';
		}).join('') + '</div>';
	}
	function listTools(path, i, n) {
		return '<span class="bkb-tools">' +
			'<button type="button" class="button-link" data-act="up" data-path="' + path + '" data-i="' + i + '"' + (i === 0 ? ' disabled' : '') + ' title="' + __('Move up', 'shootbook') + '">↑</button>' +
			'<button type="button" class="button-link" data-act="down" data-path="' + path + '" data-i="' + i + '"' + (i === n - 1 ? ' disabled' : '') + ' title="' + __('Move down', 'shootbook') + '">↓</button>' +
			'<button type="button" class="button-link bkb-del" data-act="del" data-path="' + path + '" data-i="' + i + '">' + __('Delete', 'shootbook') + '</button></span>';
	}
	function addBtn(path, label) {
		return '<p><button type="button" class="button" data-act="add" data-path="' + path + '">' + esc(label) + '</button></p>';
	}
	function idInput(path) {
		return '<label class="bkb-id"><span>' + __('Code', 'shootbook') + '</span><input type="text" data-p="' + path + '" data-t="id" value="' + esc(get(path)) + '" placeholder="' + __('Latin letters, no spaces', 'shootbook') + '"></label>';
	}

	/* ---------- tabs ---------- */
	var R = {};

	R.general = function () {
		var st = (A.icsStatus || []).map(function (n, i) {
			return sprintf(__('Calendar %d:', 'shootbook'), i + 1) + ' ' + (n < 0 ? '<b style="color:#b32d2e">' + __('could not be loaded', 'shootbook') + '</b>' : '<b style="color:#1a7f37">' + sprintf(__('connected, events: %d', 'shootbook'), n) + '</b>');
		}).join('<br>');
		return '<table class="form-table">' +
			row(__('Name in e-mails', 'shootbook'), text('site_name'), __('Used in e-mail subjects and in the calendar. Empty: the site title.', 'shootbook')) +
			row(__('Sender address', 'shootbook'), text('from', ' placeholder="booking@example.com"'), __('Site e-mails to clients and to you are sent from this address. Client replies also come to it. An address on the site domain works best. Empty: the default WordPress address.', 'shootbook')) +
			row(__('Send requests to', 'shootbook'), text('notify', ' placeholder="name@example.com, second@example.com"'), __('Several addresses can be separated by commas. If no sender address is set, clients reply to the first one.', 'shootbook')) +
			row(__('Calendars (iCal)', 'shootbook'), area('ics', 3, 'https://calendar.google.com/calendar/ical/…/basic.ics'), __('Secret iCal addresses, one per line. Time that is busy in them cannot be booked on the site. Visitors never see these addresses.', 'shootbook') + (st ? '<br>' + st : '')) +
			row(__('Contacts for the client', 'shootbook'), text('contact'), __('For example “Instagram: @name”. Added to the client’s calendar event.', 'shootbook')) +
			row(__('E-mail signature', 'shootbook'), area('signature', 3), __('The last lines of e-mails to the client. Empty: the name.', 'shootbook')) +
			row(__('Form languages', 'shootbook'), Object.keys(A.langs).map(function (l) {
				return '<label class="bkb-inline"><input type="checkbox" data-lang="' + l + '"' + (langs().indexOf(l) >= 0 ? ' checked' : '') + '> ' + esc(A.langs[l]) + '</label>';
			}).join(''), __('Service names, options and texts are filled in for these languages. The form language follows the page language.', 'shootbook')) +
			row(__('Privacy policy', 'shootbook'), labels('privacy', __('link', 'shootbook')), __('Link in the consent checkbox. Empty: the privacy policy page from the WordPress settings.', 'shootbook')) +
			row(__('Keep requests', 'shootbook'), sprintf(__('%s days after the booking date', 'shootbook'), num('retention_days', 30, 3650))) +
			row(__('Client reminder', 'shootbook'), check('reminder', __('Send an e-mail the day before a confirmed booking', 'shootbook'))) +
			row(__('Morning digest', 'shootbook'), check('crm.digest', __('Send a digest to the request address: new requests, who is waiting for a reply, bookings for today and tomorrow, tasks', 'shootbook')) + ' ' + sprintf(__('at %s:00', 'shootbook'), num('crm.digest_hour', 0, 23)), __('If there is nothing to report, no e-mail is sent.', 'shootbook')) +
			row(__('Request source', 'shootbook'), check('crm.source', __('Remember where the visitor came to the site from', 'shootbook')), __('UTM tags in the link or the site the visitor came from (Instagram, Google…). Shown in the client card and in the report. No personal data is stored until the person sends a request.', 'shootbook')) +
			row(__('Own sources', 'shootbook'), area('crm.sources', 3), __('One per line. Added to the list of sources when you add a client by hand, next to Instagram, Facebook, phone and the others.', 'shootbook')) +
			'</table>';
	};

	R.schedule = function () {
		var s = S.schedule;
		var days = DAYS.map(function (d, i) {
			var k = 'schedule.days.' + (i + 1);
			var v = get(k);
			var open = Array.isArray(v);
			return '<tr><td>' + d + '</td><td><label><input type="checkbox" data-day="' + k + '"' + (open ? ' checked' : '') + '> ' + __('open', 'shootbook') + '</label></td>' +
				'<td><input type="time" data-p="' + k + '.0" data-t="time" value="' + (open ? hm(v[0]) : '') + '"' + (open ? '' : ' disabled') + '> — ' +
				'<input type="time" data-p="' + k + '.1" data-t="time" value="' + (open ? hm(v[1]) : '') + '"' + (open ? '' : ' disabled') + '></td></tr>';
		}).join('');
		return '<table class="form-table">' +
			row(__('Opening hours', 'shootbook'), '<table class="bkb-days">' + days + '</table>', __('A booking, including preparation, must end before closing time. Hours of a single location can be changed on the Locations tab.', 'shootbook')) +
			row(__('Start times', 'shootbook'), '<select data-p="schedule.step" data-t="num">' + [15, 20, 30, 45, 60, 90, 120].map(function (m) {
				return '<option value="' + m + '"' + (+s.step === m ? ' selected' : '') + '>' + (m < 60 ? sprintf(__('every %d min', 'shootbook'), m) : sprintf(__('every %s h', 'shootbook'), m / 60)) + '</option>';
			}).join('') + '</select>') +
			row(__('Durations to choose from', 'shootbook'), '<input type="text" class="regular-text" data-p="schedule.durations" data-t="list" value="' + esc((s.durations || []).join(', ')) + '">', __('In hours, separated by commas, for example “1, 2, 3” or “0.5, 1”.', 'shootbook')) +
			row(__('Default duration', 'shootbook'), sprintf(__('%s h', 'shootbook'), num('schedule.default_duration', 0.25, 24, 0.25))) +
			row(__('Preparation', 'shootbook'), sprintf(__('%s min', 'shootbook'), num('schedule.prep', 0, 480, 5)), __('Added to the chosen duration and also takes up time (changing, setting up the light and so on). 0: no preparation.', 'shootbook')) +
			row(__('Booking window', 'shootbook'), sprintf(__('no sooner than in %1$s days and no further than %2$s days ahead', 'shootbook'), num('schedule.lead_days', 0, 60), num('schedule.horizon_days', 7, 730))) +
			'</table>';
	};

	R.services = function () {
		var list = S.services || [];
		return '<p class="description">' + __('What can be booked. If there is only one service or no choice is needed, leave the list empty.', 'shootbook') + '</p>' +
			list.map(function (x, i) {
				var p = 'services.' + i;
				return '<div class="bkb-card"><div class="bkb-card-head"><b>' + esc(x.id || __('New service', 'shootbook')) + '</b>' + listTools('services', i, list.length) + '</div>' +
					idInput(p + '.id') + '<div class="bkb-sub">' + __('Title', 'shootbook') + '</div>' + labels(p + '.label', x.id) + '</div>';
			}).join('') + addBtn('services', __('Add service', 'shootbook'));
	};

	R.locations = function () {
		var list = S.locations || [];
		return '<p class="description">' + __('Where the booking takes place. If there is only one location, leave the list empty. A location can have its own hours, for example the hours of a partner studio.', 'shootbook') + '</p>' +
			list.map(function (x, i) {
				var p = 'locations.' + i;
				var days = DAYS.map(function (d, j) {
					var k = p + '.days.' + (j + 1);
					var v = get(k);
					var mode = v === undefined ? 'gen' : (Array.isArray(v) ? 'own' : 'off');
					return '<tr><td>' + d + '</td><td><select data-locday="' + k + '"><option value="gen"' + (mode === 'gen' ? ' selected' : '') + '>' + __('as in the schedule', 'shootbook') + '</option><option value="own"' + (mode === 'own' ? ' selected' : '') + '>' + __('own hours', 'shootbook') + '</option><option value="off"' + (mode === 'off' ? ' selected' : '') + '>' + __('closed', 'shootbook') + '</option></select></td>' +
						'<td>' + (mode === 'own' ? '<input type="time" data-p="' + k + '.0" data-t="time" value="' + hm(v[0]) + '"> — <input type="time" data-p="' + k + '.1" data-t="time" value="' + hm(v[1]) + '">' : '') + '</td></tr>';
				}).join('');
				return '<div class="bkb-card"><div class="bkb-card-head"><b>' + esc(x.id || __('New location', 'shootbook')) + '</b>' + listTools('locations', i, list.length) + '</div>' +
					idInput(p + '.id') + '<div class="bkb-sub">' + __('Title', 'shootbook') + '</div>' + labels(p + '.label', x.id) +
					'<div class="bkb-sub">' + __('Address', 'shootbook') + '</div>' + text(p + '.address') + '<p class="description">' + __('Added to the confirmation e-mail and to the calendar event. Empty: no address.', 'shootbook') + '</p>' +
					'<details><summary>' + __('Opening hours of this location', 'shootbook') + '</summary><table class="bkb-days">' + days + '</table></details></div>';
			}).join('') + addBtn('locations', __('Add location', 'shootbook'));
	};

	R.options = function () {
		var list = S.options || [];
		var locs = (S.locations || []).map(function (l) { return l.id; });
		return '<p class="description">' + __('An extra choice in the form: “yes or no” (for example, video) or one of several options (for example, your own photographer or ours).', 'shootbook') + '</p>' +
			list.map(function (x, i) {
				var p = 'options.' + i;
				var only = x.locations || [];
				var ch = (x.choices || []).map(function (c, j) {
					var cp = p + '.choices.' + j;
					return '<div class="bkb-choice">' + idInput(cp + '.id') + labels(cp + '.label', c.id) + listTools(p + '.choices', j, x.choices.length) + '</div>';
				}).join('');
				return '<div class="bkb-card"><div class="bkb-card-head"><b>' + esc(x.id || __('New option', 'shootbook')) + '</b>' + listTools('options', i, list.length) + '</div>' +
					idInput(p + '.id') +
					'<div class="bkb-sub">' + __('Type', 'shootbook') + '</div><select data-p="' + p + '.type" data-rerender="1"><option value="yesno"' + (x.type !== 'choice' ? ' selected' : '') + '>' + __('yes / no', 'shootbook') + '</option><option value="choice"' + (x.type === 'choice' ? ' selected' : '') + '>' + __('one of several', 'shootbook') + '</option></select>' +
					'<div class="bkb-sub">' + __('Title', 'shootbook') + '</div>' + labels(p + '.label', x.id) +
					'<div class="bkb-sub">' + __('Note (optional)', 'shootbook') + '</div>' + labels(p + '.note') +
					(x.type === 'choice' ? '<div class="bkb-sub">' + __('Choices', 'shootbook') + '</div>' + ch + addBtn(p + '.choices', __('Add choice', 'shootbook')) +
						'<div class="bkb-sub">' + __('Default', 'shootbook') + '</div><select data-p="' + p + '.default">' + (x.choices || []).map(function (c) {
							return '<option value="' + esc(c.id) + '"' + (c.id === x.default ? ' selected' : '') + '>' + esc(c.id) + '</option>';
						}).join('') + '</select>'
						: '<div class="bkb-sub">' + __('Default', 'shootbook') + '</div><select data-p="' + p + '.default"><option value="0"' + (x.default !== '1' ? ' selected' : '') + '>' + __('no', 'shootbook') + '</option><option value="1"' + (x.default === '1' ? ' selected' : '') + '>' + __('yes', 'shootbook') + '</option></select>') +
					(locs.length ? '<div class="bkb-sub">' + __('Available at', 'shootbook') + '</div>' + locs.map(function (l) {
						return '<label class="bkb-inline"><input type="checkbox" data-optloc="' + p + '" value="' + esc(l) + '"' + (only.indexOf(l) >= 0 ? ' checked' : '') + '> ' + esc(l) + '</label>';
					}).join('') + '<p class="description">' + __('Nothing checked: everywhere.', 'shootbook') + '</p>' : '') +
					'</div>';
			}).join('') + addBtn('options', __('Add option', 'shootbook'));
	};

	R.fields = function () {
		var sl = (S.fields.slang && S.fields.slang.choices) || [];
		return '<table class="form-table">' +
			row(__('Name and e-mail', 'shootbook'), __('always, required', 'shootbook')) +
			row(__('Phone', 'shootbook'), check('fields.phone.on', __('Show (optional field)', 'shootbook'))) +
			row(__('Details field', 'shootbook'), check('fields.details.on', __('Show', 'shootbook')) + '<br>' + check('fields.details.required', __('Required', 'shootbook')) +
				'<div class="bkb-sub">' + __('Label', 'shootbook') + '</div>' + labels('fields.details.label') + '<div class="bkb-sub">' + __('Placeholder', 'shootbook') + '</div>' + labels('fields.details.placeholder'),
				__('For example “Guests: height and build of each” or “What needs to be done”.', 'shootbook')) +
			row(__('Message', 'shootbook'), check('fields.message.on', __('Show the “Anything else” field', 'shootbook'))) +
			row(__('Session language', 'shootbook'), check('fields.slang.on', __('Ask which language the session should be in', 'shootbook')) +
				sl.map(function (c, j) {
					var cp = 'fields.slang.choices.' + j;
					return '<div class="bkb-choice">' + idInput(cp + '.id') + labels(cp + '.label', c.id) +
						'<div class="bkb-sub">' + __('Hide on pages in', 'shootbook') + '</div>' + langs().map(function (l) {
							return '<label class="bkb-inline"><input type="checkbox" data-hide="' + cp + '" value="' + l + '"' + ((c.hide || []).indexOf(l) >= 0 ? ' checked' : '') + '> ' + l.toUpperCase() + '</label>';
						}).join('') + listTools('fields.slang.choices', j, sl.length) + '</div>';
				}).join('') + addBtn('fields.slang.choices', __('Add language', 'shootbook'))) +
			'</table>';
	};

	R.texts = function () {
		var ls = langs();
		if (!textLang || ls.indexOf(textLang) < 0) { textLang = ls[0]; }
		var def = A.texts[textLang] || A.texts.en;
		var own = (S.texts && S.texts[textLang]) || {};
		var names = {
			h_service: __('Step 1 heading', 'shootbook'), h_date: __('Step 2 heading', 'shootbook'), h_details: __('Step 3 heading', 'shootbook'), service: __('“Service” label', 'shootbook'), location: __('“Location” label', 'shootbook'),
			duration: __('“Duration” label', 'shootbook'), dur_note: __('Preparation note ({prep}, {total})', 'shootbook'), prep_short: __('Preparation in the summary ({prep})', 'shootbook'), tz: __('Time zone note', 'shootbook'),
			note: __('Below the button', 'shootbook'), submit: __('Button', 'shootbook'), consent: __('Consent (%s is the link)', 'shootbook'), ok_mail: __('After sending (%s is the e-mail)', 'shootbook'), ok_next: __('After sending, second line', 'shootbook'),
			m_ack_subj: __('“Received” e-mail: subject', 'shootbook'), m_ack: __('“Received” e-mail', 'shootbook'), m_conf_subj: __('“Confirmed” e-mail: subject', 'shootbook'), m_conf: __('“Confirmed” e-mail', 'shootbook'),
			m_decl_subj: __('“Declined” e-mail: subject', 'shootbook'), m_decl: __('“Declined” e-mail', 'shootbook'), m_rem_subj: __('Reminder: subject', 'shootbook'), m_rem: __('Reminder', 'shootbook'), m_where: __('Address line ({address})', 'shootbook'), m_gcal: __('Calendar line ({url})', 'shootbook')
		};
		return '<p>' + __('Language:', 'shootbook') + ' ' + ls.map(function (l) {
			return '<button type="button" class="button' + (l === textLang ? ' button-primary' : '') + '" data-textlang="' + l + '">' + esc(A.langs[l]) + '</button>';
		}).join(' ') + '</p><p class="description">' + __('An empty field uses the default text, shown in grey. E-mails can use {name}, {summary}, {where}, {calendar}, {book_url}, {site}, {signature}.', 'shootbook') + '</p>' +
			'<table class="form-table bkb-texts">' + Object.keys(def).map(function (k) {
				var long = /^m_(ack|conf|decl|rem)$/.test(k) || k === 'consent';
				var p = 'texts.' + textLang + '.' + k;
				return row(names[k] || k, long ?
					'<textarea class="large-text" rows="6" data-p="' + p + '" placeholder="' + esc(def[k]) + '">' + esc(own[k]) + '</textarea>' :
					'<input type="text" class="large-text" data-p="' + p + '" placeholder="' + esc(def[k]) + '" value="' + esc(own[k]) + '">');
			}).join('') + '</table>';
	};

	R.replies = function () {
		if (!Array.isArray(S.replies)) { S.replies = JSON.parse(JSON.stringify(A.replies || [])); }
		var vars = ['name', 'date', 'time', 'end', 'service', 'location', 'address', 'hours', 'prep', 'price', 'book_url', 'review_url', 'site'];
		var list = S.replies;
		return '<p class="description">' + __('Message templates for the client. They are shown under each request and in the client card: in the client’s language, with a translation into your language, with the date, price and so on already filled in. Press “Copy” and paste into Instagram or an e-mail.', 'shootbook') + '</p>' +
			'<p class="description">' + __('Placeholders:', 'shootbook') + ' ' + vars.map(function (v) { return '<code>{' + v + '}</code>'; }).join(' ') + '. ' + __('If a value is missing (for example, the request has no price), the line with that placeholder is left out of the message.', 'shootbook') + '</p>' +
			'<table class="form-table">' +
			row(__('Review link', 'shootbook'), text('review_url', ' placeholder="https://g.page/r/…/review"'), __('Used for <code>{review_url}</code>, for example a link to a Google review.', 'shootbook')) +
			row(__('Booking page', 'shootbook'), text('book_url', ' placeholder="https://…/book/"'), __('For <code>{book_url}</code> when the client did not come through the form. Otherwise the page the request was sent from is used.', 'shootbook')) +
			'</table>' +
			list.map(function (r, i) {
				var p = 'replies.' + i;
				return '<div class="bkb-card"><div class="bkb-card-head"><b>' + esc(r.title || __('New reply', 'shootbook')) + '</b>' + listTools('replies', i, list.length) + '</div>' +
					'<p><label>' + __('Title', 'shootbook') + ' <input type="text" class="regular-text" data-p="' + p + '.title" value="' + esc(r.title) + '"></label></p>' +
					(['ru'].concat(langs().filter(function (l) { return l !== 'ru'; }))).map(function (l) {
						return '<p><label><b>' + esc(A.langs[l] || l) + '</b><br><textarea class="large-text" rows="11" data-p="' + p + '.text.' + l + '">' + esc(get(p + '.text.' + l)) + '</textarea></label></p>';
					}).join('') + '</div>';
			}).join('') + addBtn('replies', __('Add reply', 'shootbook')) +
			'<p><button type="button" class="button-link" data-act="replies-reset">' + __('Restore default replies', 'shootbook') + '</button></p>';
	};

	R.style = function () {
		function color(path, label) {
			return row(label, '<input type="color" data-p="' + path + '" value="' + esc(get(path)) + '"> <code>' + esc(get(path)) + '</code>');
		}
		return '<table class="form-table">' +
			color('style.paper', __('Form background', 'shootbook')) + color('style.ink', __('Text and selected buttons', 'shootbook')) + color('style.accent', __('Accent (the “Send” button)', 'shootbook')) +
			row(__('Heading font', 'shootbook'), text('style.heading_font'), __('A font already loaded on the site, for example “Unbounded, sans-serif”. Empty: same as the site.', 'shootbook')) +
			row(__('Text font', 'shootbook'), text('style.body_font'), __('Empty: same as the site.', 'shootbook')) +
			'</table>';
	};

	R.io = function () {
		return '<h2>' + __('Export', 'shootbook') + '</h2><p>' + __('A file with all settings except the calendar addresses. It can be loaded on another site.', 'shootbook') + '</p><p><a class="button" href="' + esc(A.exportUrl) + '">' + __('Download settings (JSON)', 'shootbook') + '</a></p>' +
			'<h2>' + __('Import', 'shootbook') + '</h2><p>' + __('Paste the file contents and press “Load”, then “Save settings”. The calendar addresses stay unchanged.', 'shootbook') + '</p>' +
			'<textarea id="bkb-import" class="large-text code" rows="10"></textarea><p><button type="button" class="button" data-act="import">' + __('Load', 'shootbook') + '</button> <span id="bkb-import-msg"></span></p>';
	};

	/* ---------- render and events ---------- */
	function render() {
		root.innerHTML = '<nav class="nav-tab-wrapper">' + TABS.map(function (t) {
			return '<a href="#" class="nav-tab' + (t[0] === tab ? ' nav-tab-active' : '') + '" data-tab="' + t[0] + '">' + esc(t[1]) + '</a>';
		}).join('') + '</nav><div class="bkb-tab">' + R[tab]() + '</div>';
	}

	function blank(path) {
		if (path === 'services') { return { id: '', label: {} }; }
		if (path === 'locations') { return { id: '', label: {}, address: '', days: {} }; }
		if (path === 'options') { return { id: '', type: 'yesno', label: {}, note: {}, default: '0', locations: [], choices: [] }; }
		if (path === 'fields.slang.choices') { return { id: '', label: {}, hide: [] }; }
		if (path === 'replies') { return { id: '', title: '', text: {} }; }
		return { id: '', label: {} };
	}

	root.addEventListener('click', function (e) {
		var t = e.target;
		if (t.getAttribute('data-tab')) {
			e.preventDefault();
			tab = t.getAttribute('data-tab');
			try { sessionStorage.setItem('bkb_tab', tab); } catch (x) {}
			render();
			return;
		}
		if (t.getAttribute('data-textlang')) { textLang = t.getAttribute('data-textlang'); render(); return; }
		var act = t.getAttribute('data-act');
		if (!act) { return; }
		if (act === 'replies-reset') {
			if (window.confirm(__('Replace the replies with the default ones? Your edits to the replies will be lost after saving.', 'shootbook'))) { S.replies = JSON.parse(JSON.stringify(A.replies || [])); render(); }
			return;
		}
		if (act === 'import') {
			var msg = document.getElementById('bkb-import-msg');
			try {
				var c = JSON.parse(document.getElementById('bkb-import').value);
				var ics = S.ics;
				S = c;
				S.ics = ics;
				msg.textContent = __('Loaded. Check the tabs and press “Save settings”.', 'shootbook');
			} catch (x) { msg.textContent = __('This is not JSON.', 'shootbook'); }
			return;
		}
		var path = t.getAttribute('data-path');
		var list = get(path);
		if (!Array.isArray(list)) { list = []; set(path, list); }
		var i = parseInt(t.getAttribute('data-i'), 10);
		if (act === 'add') { list.push(blank(path)); }
		if (act === 'del') {
			if (!window.confirm(__('Delete?', 'shootbook'))) { return; }
			list.splice(i, 1);
		}
		if (act === 'up' && i > 0) { list.splice(i - 1, 0, list.splice(i, 1)[0]); }
		if (act === 'down' && i < list.length - 1) { list.splice(i + 1, 0, list.splice(i, 1)[0]); }
		render();
	});

	function onField(e) {
		var t = e.target;
		if (t.hasAttribute('data-lang')) {
			var ls = Object.keys(A.langs).filter(function (l) { return root.querySelector('[data-lang="' + l + '"]').checked; });
			S.languages = ls.length ? ls : ['en'];
			return;
		}
		if (t.hasAttribute('data-day')) {
			var k = t.getAttribute('data-day');
			set(k, t.checked ? [10, 18] : null);
			render();
			return;
		}
		if (t.hasAttribute('data-locday')) {
			var lk = t.getAttribute('data-locday');
			var parts = lk.split('.');
			var dayKey = parts.pop();
			var days = get(parts.join('.'));
			if (!days || Array.isArray(days)) { days = {}; set(parts.join('.'), days); }
			if (t.value === 'gen') { delete days[dayKey]; }
			else { days[dayKey] = t.value === 'own' ? [10, 18] : null; }
			render();
			return;
		}
		if (t.hasAttribute('data-optloc')) {
			var op = t.getAttribute('data-optloc');
			set(op + '.locations', [].map.call(root.querySelectorAll('[data-optloc="' + op + '"]:checked'), function (x) { return x.value; }));
			return;
		}
		if (t.hasAttribute('data-hide')) {
			var hp = t.getAttribute('data-hide');
			set(hp + '.hide', [].map.call(root.querySelectorAll('[data-hide="' + hp + '"]:checked'), function (x) { return x.value; }));
			return;
		}
		var p = t.getAttribute('data-p');
		if (!p) { return; }
		var ty = t.getAttribute('data-t');
		var v = t.value;
		if (ty === 'bool') { v = t.checked; }
		else if (ty === 'num') { v = v === '' ? '' : parseFloat(v); }
		else if (ty === 'time') { v = fromHm(v); }
		else if (ty === 'list') { v = v.split(/[,;\s]+/).map(parseFloat).filter(function (x) { return x > 0; }); }
		else if (ty === 'id') { v = v.replace(/[^\w\- .]/g, ''); }
		if (/^texts\./.test(p) && v === '') {
			var tp = p.split('.');
			if (S.texts && S.texts[tp[1]]) { delete S.texts[tp[1]][tp[2]]; }
			return;
		}
		set(p, v);
		if (t.getAttribute('data-rerender') || (e.type === 'change' && ty === 'id')) { render(); }
	}
	root.addEventListener('input', onField);
	root.addEventListener('change', onField);

	form.addEventListener('submit', function () {
		document.getElementById('bkb-config-json').value = JSON.stringify(S);
	});

	render();
})();
