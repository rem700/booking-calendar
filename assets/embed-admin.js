/* The widget page in the admin: builds the code from the choices and shows a preview. */
(function () {
	'use strict';
	var E = window.BKB_EMBED;
	if (!E) { return; }
	var $ = function (id) { return document.getElementById(id); };
	var LABELS = { en: 'Book', ru: 'Записаться', uk: 'Записатися', de: 'Buchen', hu: 'Foglalás' };
	['ink', 'paper', 'accent'].forEach(function (k) { $('bkb-e-' + k).value = E.style[k].length === 4 ? E.style[k].replace(/^#(.)(.)(.)$/, '#$1$1$2$2$3$3') : E.style[k].slice(0, 7); });

	function radio(name) { var r = document.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }
	function attr(k, v) { return v ? ' data-' + k + '="' + String(v).replace(/[&"<>]/g, function (c) { return { '&': '&amp;', '"': '&quot;', '<': '&lt;', '>': '&gt;' }[c]; }) + '"' : ''; }

	function build() {
		var mode = radio('bkb-e-mode');
		var theme = radio('bkb-e-theme');
		var lang = $('bkb-e-lang').value;
		document.querySelector('.bkb-e-label').hidden = mode !== 'button';
		document.querySelector('.bkb-e-colors').hidden = theme !== 'own';
		document.querySelector('.bkb-e-inherit').hidden = theme !== 'inherit';
		if (mode === 'button' && !$('bkb-e-label').value) { $('bkb-e-label').placeholder = LABELS[lang || E.lang] || LABELS.en; }
		var a = attr('shootbook', E.form) + attr('lang', lang);
		if (theme === 'inherit') { a += attr('theme', 'inherit'); }
		if (theme === 'own') { a += attr('ink', $('bkb-e-ink').value) + attr('paper', $('bkb-e-paper').value) + attr('accent', $('bkb-e-accent').value); }
		var font = $('bkb-e-font').value.replace(/[^\w\s,'\-]/g, '').trim();
		a += attr('font', font) + attr('google-font', font && $('bkb-e-gf').checked ? '1' : '');
		if (mode === 'button') { a += attr('mode', 'button') + attr('label', $('bkb-e-label').value.trim()); }
		var code = '<div' + a + '></div>\n<script src="' + E.script + '" async></' + 'script>';
		$('bkb-e-code').value = code;
		var u = E.form + (lang ? '&lang=' + lang : '');
		if (theme === 'own') { ['ink', 'paper', 'accent'].forEach(function (k) { u += '&' + k + '=' + encodeURIComponent($('bkb-e-' + k).value); }); }
		if (font) { u += '&font=' + encodeURIComponent(font) + ($('bkb-e-gf').checked ? '&gf=1' : ''); }
		$('bkb-e-url').value = u;
		preview(code);
	}

	var timer = 0;
	function preview(code) {
		clearTimeout(timer);
		timer = setTimeout(function () {
			var box = $('bkb-e-preview');
			box.innerHTML = code.split('\n')[0];
			if (window.Shootbook) { window.Shootbook.scan(); return; }
			var s = document.createElement('script');
			s.src = E.script;
			document.body.appendChild(s);
		}, 300);
	}

	document.querySelectorAll('.bkb-embed-admin input, .bkb-embed-admin select').forEach(function (el) {
		if (el.closest('form')) { return; }
		el.addEventListener('input', build);
		el.addEventListener('change', build);
	});
	$('bkb-e-copy').addEventListener('click', function () {
		var t = $('bkb-e-code');
		var done = function () { document.querySelector('.bkb-e-done').textContent = E.copied; };
		if (navigator.clipboard) {
			navigator.clipboard.writeText(t.value).then(done, function () { t.select(); document.execCommand('copy'); done(); });
		} else {
			t.select();
			document.execCommand('copy');
			done();
		}
	});
	build();
})();
