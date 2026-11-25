// Sending to Instagram Direct from the client card and from ready-made replies.
(function () {
	var __ = wp.i18n.__;

	function send(client, text, out, btn, after) {
		if (!text.trim()) { return; }
		btn.disabled = true;
		out.textContent = __('Sending…', 'shootbook');
		var fd = new FormData();
		fd.append('action', 'bkb_ig_send');
		fd.append('_ajax_nonce', bkbIg.nonce);
		fd.append('client', client);
		fd.append('text', text);
		fetch(bkbIg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
			btn.disabled = false;
			if (j.success) {
				out.textContent = __('Sent to Direct', 'shootbook');
				if (after) { after(); }
			} else {
				out.textContent = __('Not sent:', 'shootbook') + ' ' + (j.data || '');
			}
		}).catch(function () {
			btn.disabled = false;
			out.textContent = __('Not sent: no connection to the site', 'shootbook');
		});
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (t.hasAttribute('data-ig-send')) {
			var box = t.closest('.bkb-ig-box');
			var ta = box.querySelector('[data-ig-text]');
			send(box.getAttribute('data-client'), ta.value, box.querySelector('[data-ig-result]'), t, function () {
				ta.value = '';
				setTimeout(function () { location.reload(); }, 800);
			});
		}
		if (t.hasAttribute('data-ig-reply')) {
			var wrap = t.closest('.bkb-rep-l');
			var out = wrap.querySelector('.bkb-rep-sent');
			out.hidden = false;
			send(t.getAttribute('data-ig-reply'), wrap.querySelector('textarea').value, out, t);
		}
	});
})();
