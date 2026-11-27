/*
 * Shootbook widget for sites made in Framer, Webflow, Tilda or Wix.
 *
 *   <div data-shootbook="https://studio.example/?bkb_embed=1" data-lang="ru"></div>
 *   <script src="https://studio.example/wp-content/plugins/bk-booking/assets/embed.js" async></script>
 *
 * Attributes of the block (all optional):
 *   data-lang      form language; by default the language of the page
 *   data-theme     "inherit" takes the text colour, background and font of the page
 *   data-ink, data-paper, data-accent   colours: text, background, accent (#rrggbb)
 *   data-font, data-head-font           fonts of the text and the headings
 *   data-google-font="1"                load these fonts from Google Fonts
 *   data-mode      "button": a button that opens the form over the page
 *   data-label     the text of that button
 *   data-service, data-location, data-hours, data-pack   preselected choices
 * The block gets the event "shootbook:submitted" when a request is sent.
 */
(function () {
	'use strict';
	if (window.Shootbook) { window.Shootbook.scan(); return; }

	var SRC_KEY = 'shootbook_src';
	var LABELS = { en: 'Book', ru: 'Записаться', uk: 'Записатися', de: 'Buchen', hu: 'Foglalás' };
	var frames = {};
	var n = 0;

	/* The first visit that came from somewhere: utm tags, ad clicks, the site it came from. Kept for 90 days. */
	function source() {
		try {
			var o = JSON.parse(localStorage.getItem(SRC_KEY) || 'null');
			if (o ? Date.now() - o.t < 7776e6 : false) { return JSON.stringify(o); }
			var q = new URLSearchParams(location.search), r = '';
			try { r = document.referrer ? new URL(document.referrer).hostname : ''; } catch (e) {}
			if (r === location.hostname) { r = ''; }
			var v = { s: q.get('utm_source') || '', m: q.get('utm_medium') || '', c: q.get('utm_campaign') || '', r: r, g: q.get('gclid') ? 1 : '', f: q.get('fbclid') ? 1 : '', l: location.pathname, t: Date.now() };
			if (v.s || v.r || v.g || v.f) { localStorage.setItem(SRC_KEY, JSON.stringify(v)); return JSON.stringify(v); }
		} catch (e) {}
		return '';
	}

	function hex(c) {
		var m = String(c || '').match(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)(?:[,\s/]+([\d.]+%?))?/);
		if (!m) { return /^#[0-9a-f]{3,8}$/i.test(c || '') ? c : ''; }
		if (m[4] != null ? parseFloat(m[4]) === 0 : false) { return ''; }
		return '#' + [m[1], m[2], m[3]].map(function (x) { return ('0' + (+x).toString(16)).slice(-2); }).join('');
	}
	function background(el) {
		for (; el ? el.nodeType === 1 : false; el = el.parentNode) {
			var c = hex(getComputedStyle(el).backgroundColor);
			if (c) { return c; }
		}
		return '#ffffff';
	}
	function qs(o) {
		return Object.keys(o).filter(function (k) { return o[k] !== '' && o[k] != null; }).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]);
		}).join('\x26');
	}

	function mount(el) {
		if (el.getAttribute('data-shootbook-ready')) { return; }
		var url = el.getAttribute('data-shootbook') || '';
		if (!/^https?:\/\//.test(url)) { return; }
		el.setAttribute('data-shootbook-ready', '1');
		var d = el.dataset;
		var lang = (d.lang || document.documentElement.lang || navigator.language || '').slice(0, 2).toLowerCase();
		var p = { lang: lang };
		if (d.theme === 'inherit') {
			var cs = getComputedStyle(el);
			p.ink = hex(cs.color);
			p.paper = background(el);
			p.font = cs.fontFamily;
		}
		['ink', 'paper', 'accent'].forEach(function (k) { if (d[k]) { p[k] = d[k]; } });
		if (d.font) { p.font = d.font; }
		if (d.headFont) { p.head = d.headFont; }
		if (d.googleFont === '1') { p.gf = '1'; }
		['service', 'location', 'hours', 'pack'].forEach(function (k) { if (d[k]) { p[k] = d[k]; } });

		var id = 'sb' + (++n);
		var f = document.createElement('iframe');
		f.src = url + (url.indexOf('?') < 0 ? '?' : '\x26') + qs(p) + '#' + qs({ i: id, p: location.origin + location.pathname, s: source() });
		f.title = d.label || LABELS[lang] || LABELS.en;
		f.setAttribute('allow', 'clipboard-write');
		f.style.cssText = 'display:block;width:100%;border:0;height:' + (parseInt(d.height, 10) || 900) + 'px;background:transparent;color-scheme:normal';
		frames[id] = { frame: f, box: el };

		if (d.mode === 'button') {
			frames[id].scroller = button(el, f, d, lang, p);
		} else {
			el.appendChild(f);
		}
	}

	/* A button on the page; the form opens over the page and scrolls with it. */
	function button(el, f, d, lang, p) {
		var b = document.createElement('button');
		b.type = 'button';
		b.textContent = d.label || LABELS[lang] || LABELS.en;
		b.className = 'shootbook-button';
		b.style.cssText = 'font:inherit;font-weight:700;padding:14px 28px;border:0;border-radius:2px;cursor:pointer;background:' + (d.ink || p.ink || '#151517') + ';color:' + (d.paper || (d.theme === 'inherit' ? p.paper : '') || '#F4F1EA');
		el.appendChild(b);
		var wrap = document.createElement('div');
		wrap.setAttribute('role', 'dialog');
		wrap.setAttribute('aria-modal', 'true');
		wrap.setAttribute('aria-label', b.textContent);
		wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.55);overflow:auto;display:none;padding:48px 16px;box-sizing:border-box;-webkit-overflow-scrolling:touch';
		var panel = document.createElement('div');
		panel.style.cssText = 'position:relative;max-width:1100px;margin:0 auto';
		var x = document.createElement('button');
		x.type = 'button';
		x.setAttribute('aria-label', '×');
		x.textContent = '×';
		x.style.cssText = 'position:absolute;right:0;top:-44px;width:40px;height:40px;border:0;background:transparent;color:#fff;font:400 32px/1 sans-serif;cursor:pointer';
		panel.appendChild(x);
		panel.appendChild(f);
		wrap.appendChild(panel);
		var overflow = '';
		function close() {
			wrap.style.display = 'none';
			document.documentElement.style.overflow = overflow;
			b.focus();
		}
		b.addEventListener('click', function () {
			if (!wrap.parentNode) { document.body.appendChild(wrap); }
			overflow = document.documentElement.style.overflow;
			document.documentElement.style.overflow = 'hidden';
			wrap.style.display = 'block';
			wrap.scrollTop = 0;
			x.focus();
		});
		x.addEventListener('click', close);
		wrap.addEventListener('click', function (e) { if (e.target === wrap) { close(); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' ? wrap.style.display === 'block' : false) { close(); } });
		return wrap;
	}

	window.addEventListener('message', function (e) {
		var m = e.data;
		if (!m || typeof m !== 'object' || !m.shootbook) { return; }
		var it = frames[m.id];
		if (!it || it.frame.contentWindow !== e.source) { return; }
		if (m.type === 'height' && m.h > 0) {
			it.frame.style.height = Math.min(20000, Math.ceil(m.h)) + 'px';
			e.source.postMessage({ shootbook: 1, type: 'sized' }, '*');
		} else if (m.type === 'submitted') {
			if (it.scroller) {
				it.scroller.scrollTop = 0;
			} else {
				var top = it.frame.getBoundingClientRect().top;
				if (top < 0 || top > window.innerHeight * 0.6) { window.scrollTo({ top: window.scrollY + top - 80, behavior: 'smooth' }); }
			}
			var ev;
			try { ev = new CustomEvent('shootbook:submitted', { bubbles: true }); } catch (x) { ev = document.createEvent('Event'); ev.initEvent('shootbook:submitted', true, false); }
			it.box.dispatchEvent(ev);
		}
	});

	function scan() {
		var list = document.querySelectorAll('[data-shootbook]');
		for (var i = 0; i < list.length; i++) { mount(list[i]); }
	}
	window.Shootbook = { scan: scan };
	scan();
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', scan); }
	// Builders such as Framer add blocks after the page has loaded.
	if (window.MutationObserver) {
		var t = 0;
		new MutationObserver(function () { clearTimeout(t); t = setTimeout(scan, 50); }).observe(document.documentElement, { childList: true, subtree: true });
	}
})();
