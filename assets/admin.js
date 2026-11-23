(function () {
	'use strict';
	var A = window.BKB_ADMIN;
	var root = document.getElementById('bkb-editor');
	var form = document.getElementById('bkb-settings-form');
	if (!A || !root || !form) { return; }
	var S = JSON.parse(JSON.stringify(A.config));
	var tab = 'general';
	try { tab = sessionStorage.getItem('bkb_tab') || 'general'; } catch (e) {}
	var textLang = null;

	var DAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
	var TABS = [
		['general', 'Основное'], ['schedule', 'Расписание'], ['services', 'Услуги'], ['locations', 'Места'],
		['options', 'Опции'], ['fields', 'Поля формы'], ['texts', 'Тексты'], ['style', 'Оформление'], ['io', 'Импорт и экспорт']
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
			'<button type="button" class="button-link" data-act="up" data-path="' + path + '" data-i="' + i + '"' + (i === 0 ? ' disabled' : '') + ' title="Выше">↑</button>' +
			'<button type="button" class="button-link" data-act="down" data-path="' + path + '" data-i="' + i + '"' + (i === n - 1 ? ' disabled' : '') + ' title="Ниже">↓</button>' +
			'<button type="button" class="button-link bkb-del" data-act="del" data-path="' + path + '" data-i="' + i + '">Удалить</button></span>';
	}
	function addBtn(path, label) {
		return '<p><button type="button" class="button" data-act="add" data-path="' + path + '">' + esc(label) + '</button></p>';
	}
	function idInput(path) {
		return '<label class="bkb-id"><span>Код</span><input type="text" data-p="' + path + '" data-t="id" value="' + esc(get(path)) + '" placeholder="латиницей, без пробелов"></label>';
	}

	/* ---------- tabs ---------- */
	var R = {};

	R.general = function () {
		var st = (A.icsStatus || []).map(function (n, i) {
			return 'Календарь ' + (i + 1) + ': ' + (n < 0 ? '<b style="color:#b32d2e">не удалось загрузить</b>' : '<b style="color:#1a7f37">подключён, событий: ' + n + '</b>');
		}).join('<br>');
		return '<table class="form-table">' +
			row('Название для писем', text('site_name'), 'Подставляется в темы писем и в календарь. Пусто — название сайта.') +
			row('Куда присылать заявки', text('notify', ' placeholder="name@example.com, second@example.com"'), 'Можно несколько адресов через запятую. Первый адрес клиенты видят как адрес для ответа.') +
			row('Календари (iCal)', area('ics', 3, 'https://calendar.google.com/calendar/ical/…/basic.ics'), 'Секретные адреса iCal, по одному в строке. Занятое в них время на сайте недоступно. Адреса не показываются посетителям.' + (st ? '<br>' + st : '')) +
			row('Контакты для клиента', text('contact'), 'Например «Instagram: @name». Попадает в событие календаря клиента.') +
			row('Подпись в письмах', area('signature', 3), 'Последние строки писем клиенту. Пусто — название.') +
			row('Языки формы', Object.keys(A.langs).map(function (l) {
				return '<label class="bkb-inline"><input type="checkbox" data-lang="' + l + '"' + (langs().indexOf(l) >= 0 ? ' checked' : '') + '> ' + esc(A.langs[l]) + '</label>';
			}).join(''), 'Для этих языков заполняются названия услуг, опций и тексты. Язык формы берётся из языка страницы.') +
			row('Политика конфиденциальности', labels('privacy', 'ссылка'), 'Ссылка в галочке согласия. Пусто — страница политики из настроек WordPress.') +
			row('Хранить заявки', num('retention_days', 30, 3650) + ' дней после даты записи') +
			row('Напоминание клиенту', check('reminder', 'Отправлять письмо накануне подтверждённой записи')) +
			'</table>';
	};

	R.schedule = function () {
		var s = S.schedule;
		var days = DAYS.map(function (d, i) {
			var k = 'schedule.days.' + (i + 1);
			var v = get(k);
			var open = Array.isArray(v);
			return '<tr><td>' + d + '</td><td><label><input type="checkbox" data-day="' + k + '"' + (open ? ' checked' : '') + '> работаем</label></td>' +
				'<td><input type="time" data-p="' + k + '.0" data-t="time" value="' + (open ? hm(v[0]) : '') + '"' + (open ? '' : ' disabled') + '> — ' +
				'<input type="time" data-p="' + k + '.1" data-t="time" value="' + (open ? hm(v[1]) : '') + '"' + (open ? '' : ' disabled') + '></td></tr>';
		}).join('');
		return '<table class="form-table">' +
			row('Часы работы', '<table class="bkb-days">' + days + '</table>', 'Запись вместе с подготовкой должна закончиться до конца рабочего времени. Для отдельных мест часы можно поменять во вкладке «Места».') +
			row('Время начала', '<select data-p="schedule.step" data-t="num">' + [15, 20, 30, 45, 60, 90, 120].map(function (m) {
				return '<option value="' + m + '"' + (+s.step === m ? ' selected' : '') + '>каждые ' + (m < 60 ? m + ' мин' : (m / 60) + ' ч') + '</option>';
			}).join('') + '</select>') +
			row('Длительность на выбор', '<input type="text" class="regular-text" data-p="schedule.durations" data-t="list" value="' + esc((s.durations || []).join(', ')) + '">', 'В часах через запятую, например «1, 2, 3» или «0.5, 1».') +
			row('Длительность по умолчанию', num('schedule.default_duration', 0.25, 24, 0.25) + ' ч') +
			row('Подготовка', num('schedule.prep', 0, 480, 5) + ' мин', 'Добавляется к выбранной длительности и тоже занимает время (облачение, настройка света и т. п.). 0 — без подготовки.') +
			row('Записаться можно', 'не раньше чем через ' + num('schedule.lead_days', 0, 60) + ' дн. и не дальше чем на ' + num('schedule.horizon_days', 7, 730) + ' дн. вперёд') +
			'</table>';
	};

	R.services = function () {
		var list = S.services || [];
		return '<p class="description">Что можно забронировать. Если услуга одна или выбор не нужен, список можно оставить пустым.</p>' +
			list.map(function (x, i) {
				var p = 'services.' + i;
				return '<div class="bkb-card"><div class="bkb-card-head"><b>' + esc(x.id || 'Новая услуга') + '</b>' + listTools('services', i, list.length) + '</div>' +
					idInput(p + '.id') + '<div class="bkb-sub">Название</div>' + labels(p + '.label', x.id) + '</div>';
			}).join('') + addBtn('services', 'Добавить услугу');
	};

	R.locations = function () {
		var list = S.locations || [];
		return '<p class="description">Где проходит запись. Если место одно, список можно оставить пустым. У места можно задать свои часы, например часы студии-партнёра.</p>' +
			list.map(function (x, i) {
				var p = 'locations.' + i;
				var days = DAYS.map(function (d, j) {
					var k = p + '.days.' + (j + 1);
					var v = get(k);
					var mode = v === undefined ? 'gen' : (Array.isArray(v) ? 'own' : 'off');
					return '<tr><td>' + d + '</td><td><select data-locday="' + k + '"><option value="gen"' + (mode === 'gen' ? ' selected' : '') + '>как в расписании</option><option value="own"' + (mode === 'own' ? ' selected' : '') + '>свои часы</option><option value="off"' + (mode === 'off' ? ' selected' : '') + '>не работаем</option></select></td>' +
						'<td>' + (mode === 'own' ? '<input type="time" data-p="' + k + '.0" data-t="time" value="' + hm(v[0]) + '"> — <input type="time" data-p="' + k + '.1" data-t="time" value="' + hm(v[1]) + '">' : '') + '</td></tr>';
				}).join('');
				return '<div class="bkb-card"><div class="bkb-card-head"><b>' + esc(x.id || 'Новое место') + '</b>' + listTools('locations', i, list.length) + '</div>' +
					idInput(p + '.id') + '<div class="bkb-sub">Название</div>' + labels(p + '.label', x.id) +
					'<div class="bkb-sub">Адрес</div>' + text(p + '.address') + '<p class="description">Попадает в письмо о подтверждении и в событие календаря. Пусто — без адреса.</p>' +
					'<details><summary>Часы работы этого места</summary><table class="bkb-days">' + days + '</table></details></div>';
			}).join('') + addBtn('locations', 'Добавить место');
	};

	R.options = function () {
		var list = S.options || [];
		var locs = (S.locations || []).map(function (l) { return l.id; });
		return '<p class="description">Дополнительный выбор в форме: «да/нет» (например, видео) или один вариант из нескольких (например, свой или наш фотограф).</p>' +
			list.map(function (x, i) {
				var p = 'options.' + i;
				var only = x.locations || [];
				var ch = (x.choices || []).map(function (c, j) {
					var cp = p + '.choices.' + j;
					return '<div class="bkb-choice">' + idInput(cp + '.id') + labels(cp + '.label', c.id) + listTools(p + '.choices', j, x.choices.length) + '</div>';
				}).join('');
				return '<div class="bkb-card"><div class="bkb-card-head"><b>' + esc(x.id || 'Новая опция') + '</b>' + listTools('options', i, list.length) + '</div>' +
					idInput(p + '.id') +
					'<div class="bkb-sub">Вид</div><select data-p="' + p + '.type" data-rerender="1"><option value="yesno"' + (x.type !== 'choice' ? ' selected' : '') + '>да / нет</option><option value="choice"' + (x.type === 'choice' ? ' selected' : '') + '>один из вариантов</option></select>' +
					'<div class="bkb-sub">Название</div>' + labels(p + '.label', x.id) +
					'<div class="bkb-sub">Пояснение (необязательно)</div>' + labels(p + '.note') +
					(x.type === 'choice' ? '<div class="bkb-sub">Варианты</div>' + ch + addBtn(p + '.choices', 'Добавить вариант') +
						'<div class="bkb-sub">По умолчанию</div><select data-p="' + p + '.default">' + (x.choices || []).map(function (c) {
							return '<option value="' + esc(c.id) + '"' + (c.id === x.default ? ' selected' : '') + '>' + esc(c.id) + '</option>';
						}).join('') + '</select>'
						: '<div class="bkb-sub">По умолчанию</div><select data-p="' + p + '.default"><option value="0"' + (x.default !== '1' ? ' selected' : '') + '>нет</option><option value="1"' + (x.default === '1' ? ' selected' : '') + '>да</option></select>') +
					(locs.length ? '<div class="bkb-sub">Где доступна</div>' + locs.map(function (l) {
						return '<label class="bkb-inline"><input type="checkbox" data-optloc="' + p + '" value="' + esc(l) + '"' + (only.indexOf(l) >= 0 ? ' checked' : '') + '> ' + esc(l) + '</label>';
					}).join('') + '<p class="description">Ничего не отмечено — везде.</p>' : '') +
					'</div>';
			}).join('') + addBtn('options', 'Добавить опцию');
	};

	R.fields = function () {
		var sl = (S.fields.slang && S.fields.slang.choices) || [];
		return '<table class="form-table">' +
			row('Имя и e-mail', 'всегда, обязательные') +
			row('Телефон', check('fields.phone.on', 'Показывать (необязательное поле)')) +
			row('Поле «Подробности»', check('fields.details.on', 'Показывать') + '<br>' + check('fields.details.required', 'Обязательное') +
				'<div class="bkb-sub">Подпись</div>' + labels('fields.details.label') + '<div class="bkb-sub">Подсказка в поле</div>' + labels('fields.details.placeholder'),
				'Например «Гости: рост и комплекция каждого» или «Что нужно сделать».') +
			row('Сообщение', check('fields.message.on', 'Показывать поле «Что-то ещё»')) +
			row('Язык записи', check('fields.slang.on', 'Спрашивать, на каком языке проводить') +
				sl.map(function (c, j) {
					var cp = 'fields.slang.choices.' + j;
					return '<div class="bkb-choice">' + idInput(cp + '.id') + labels(cp + '.label', c.id) +
						'<div class="bkb-sub">Не показывать на страницах на языке</div>' + langs().map(function (l) {
							return '<label class="bkb-inline"><input type="checkbox" data-hide="' + cp + '" value="' + l + '"' + ((c.hide || []).indexOf(l) >= 0 ? ' checked' : '') + '> ' + l.toUpperCase() + '</label>';
						}).join('') + listTools('fields.slang.choices', j, sl.length) + '</div>';
				}).join('') + addBtn('fields.slang.choices', 'Добавить язык')) +
			'</table>';
	};

	R.texts = function () {
		var ls = langs();
		if (!textLang || ls.indexOf(textLang) < 0) { textLang = ls[0]; }
		var def = A.texts[textLang] || A.texts.en;
		var own = (S.texts && S.texts[textLang]) || {};
		var names = {
			h_service: 'Заголовок шага 1', h_date: 'Заголовок шага 2', h_details: 'Заголовок шага 3', service: 'Подпись «Услуга»', location: 'Подпись «Место»',
			duration: 'Подпись «Длительность»', dur_note: 'Пояснение про подготовку ({prep}, {total})', prep_short: 'Подготовка в сводке ({prep})', tz: 'Про часовой пояс',
			note: 'Под кнопкой', submit: 'Кнопка', consent: 'Согласие (%s — ссылка)', ok_mail: 'После отправки (%s — e-mail)', ok_next: 'После отправки, вторая строка',
			m_ack_subj: 'Письмо «получили»: тема', m_ack: 'Письмо «получили»', m_conf_subj: 'Письмо «подтверждено»: тема', m_conf: 'Письмо «подтверждено»',
			m_decl_subj: 'Письмо «отклонено»: тема', m_decl: 'Письмо «отклонено»', m_rem_subj: 'Напоминание: тема', m_rem: 'Напоминание', m_where: 'Строка с адресом ({address})', m_gcal: 'Строка с календарём ({url})'
		};
		return '<p>Язык: ' + ls.map(function (l) {
			return '<button type="button" class="button' + (l === textLang ? ' button-primary' : '') + '" data-textlang="' + l + '">' + esc(A.langs[l]) + '</button>';
		}).join(' ') + '</p><p class="description">Пустое поле — используется стандартный текст (он виден серым). В письмах можно использовать {name}, {summary}, {where}, {calendar}, {book_url}, {site}, {signature}.</p>' +
			'<table class="form-table bkb-texts">' + Object.keys(def).map(function (k) {
				var long = /^m_(ack|conf|decl|rem)$/.test(k) || k === 'consent';
				var p = 'texts.' + textLang + '.' + k;
				return row(names[k] || k, long ?
					'<textarea class="large-text" rows="6" data-p="' + p + '" placeholder="' + esc(def[k]) + '">' + esc(own[k]) + '</textarea>' :
					'<input type="text" class="large-text" data-p="' + p + '" placeholder="' + esc(def[k]) + '" value="' + esc(own[k]) + '">');
			}).join('') + '</table>';
	};

	R.style = function () {
		function color(path, label) {
			return row(label, '<input type="color" data-p="' + path + '" value="' + esc(get(path)) + '"> <code>' + esc(get(path)) + '</code>');
		}
		return '<table class="form-table">' +
			color('style.paper', 'Фон формы') + color('style.ink', 'Текст и выбранные кнопки') + color('style.accent', 'Акцент (кнопка «Отправить»)') +
			row('Шрифт заголовков', text('style.heading_font'), 'Шрифт, уже подключённый на сайте, например «Unbounded, sans-serif». Пусто — как на сайте.') +
			row('Шрифт текста', text('style.body_font'), 'Пусто — как на сайте.') +
			'</table>';
	};

	R.io = function () {
		return '<h2>Экспорт</h2><p>Файл со всеми настройками, кроме адресов календарей. Его можно загрузить на другом сайте.</p><p><a class="button" href="' + esc(A.exportUrl) + '">Скачать настройки (JSON)</a></p>' +
			'<h2>Импорт</h2><p>Вставьте содержимое файла и нажмите «Загрузить», затем «Сохранить настройки». Адреса календарей останутся прежними.</p>' +
			'<textarea id="bkb-import" class="large-text code" rows="10"></textarea><p><button type="button" class="button" data-act="import">Загрузить</button> <span id="bkb-import-msg"></span></p>';
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
		if (act === 'import') {
			var msg = document.getElementById('bkb-import-msg');
			try {
				var c = JSON.parse(document.getElementById('bkb-import').value);
				var ics = S.ics;
				S = c;
				S.ics = ics;
				msg.textContent = 'Загружено. Проверьте вкладки и нажмите «Сохранить настройки».';
			} catch (x) { msg.textContent = 'Это не JSON.'; }
			return;
		}
		var path = t.getAttribute('data-path');
		var list = get(path);
		if (!Array.isArray(list)) { list = []; set(path, list); }
		var i = parseInt(t.getAttribute('data-i'), 10);
		if (act === 'add') { list.push(blank(path)); }
		if (act === 'del') {
			if (!window.confirm('Удалить?')) { return; }
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
