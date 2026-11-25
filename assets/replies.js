// Reply templates under a request and in the client card: language switch, copy, send by e-mail.
(function () {
	'use strict';
	var __ = wp.i18n.__, sprintf = wp.i18n.sprintf;
	document.addEventListener('click', function (e) {
		var t = e.target;
		var box = t.closest ? t.closest('.bkb-replies') : null;
		if (!box) { return; }
		if (t.hasAttribute('data-rl')) {
			var l = t.getAttribute('data-rl');
			box.querySelectorAll('[data-rl]').forEach(function (b) { b.classList.toggle('button-primary', b === t); });
			box.querySelectorAll('.bkb-rep-l').forEach(function (d) { d.hidden = d.getAttribute('data-l') !== l; });
			return;
		}
		var wrap = t.closest('.bkb-rep-l');
		if (!wrap) { return; }
		var ta = wrap.querySelector('textarea');
		if (t.hasAttribute('data-copy')) {
			var ok = wrap.querySelector('.bkb-rep-ok');
			var done = function () { ok.hidden = false; setTimeout(function () { ok.hidden = true; }, 1500); };
			if (navigator.clipboard) { navigator.clipboard.writeText(ta.value).then(done, function () { ta.select(); document.execCommand('copy'); done(); }); }
			else { ta.select(); document.execCommand('copy'); done(); }
		}
		if (t.hasAttribute('data-send')) {
			var to = t.getAttribute('data-send');
			if (!window.confirm(sprintf(/* translators: %s: e-mail address */ __('Send this e-mail to %s?', 'shootbook'), to))) { return; }
			var msg = wrap.querySelector('.bkb-rep-sent');
			t.disabled = true;
			msg.hidden = false;
			msg.textContent = __('Sending…', 'shootbook');
			var fd = new FormData();
			fd.append('action', 'bkb_send_reply');
			fd.append('_ajax_nonce', box.getAttribute('data-nonce'));
			fd.append('client', box.getAttribute('data-client'));
			fd.append('booking', box.getAttribute('data-booking'));
			fd.append('to', to);
			fd.append('title', t.getAttribute('data-title'));
			fd.append('text', ta.value);
			fetch(box.getAttribute('data-ajax'), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				msg.textContent = j.success ? __('E-mail sent and saved in the client history', 'shootbook') : sprintf(/* translators: %s: reason */ __('Not sent: %s', 'shootbook'), j.data || __('error', 'shootbook'));
				msg.style.color = j.success ? '#1a7f37' : '#b32d2e';
				t.disabled = !!j.success;
			}, function () { msg.textContent = sprintf(__('Not sent: %s', 'shootbook'), __('no connection to the site', 'shootbook')); msg.style.color = '#b32d2e'; t.disabled = false; });
			return;
		}
		if (t.hasAttribute('data-mail')) {
			e.preventDefault();
			location.href = 'mailto:' + t.getAttribute('data-mail') + '?subject=' + encodeURIComponent(t.getAttribute('data-subj')) + '\x26body=' + encodeURIComponent(ta.value);
		}
	});
})();
