/*
 * Shootbook widget, the part inside the iframe.
 * Reads the page address and the visit source that embed.js put after "#", tells the page
 * outside how tall the form is, and asks it to scroll to the form after sending.
 */
(function () {
	'use strict';
	if (window.parent === window) { return; }
	var C = window.BKB || {};
	var h = {};
	String(location.hash).replace(/^#/, '').split('\x26').forEach(function (kv) {
		var i = kv.indexOf('=');
		if (i > 0) { h[kv.slice(0, i)] = decodeURIComponent(kv.slice(i + 1)); }
	});
	var id = h.i || '';
	if (/^https?:\/\//.test(h.p || '')) { C.page = h.p; }
	if (h.s != null) { C.src = h.s; }

	function send(m) {
		m.shootbook = 1;
		m.id = id;
		try { window.parent.postMessage(m, '*'); } catch (e) {}
	}
	var last = 0;
	function size() {
		var root = document.getElementById('bkb');
		var px = Math.ceil(root ? root.getBoundingClientRect().bottom + window.scrollY : document.documentElement.scrollHeight);
		if (px !== last) { last = px; send({ type: 'height', h: px }); }
	}
	if (window.ResizeObserver) { new ResizeObserver(size).observe(document.body); }
	window.addEventListener('load', size);
	setInterval(size, 1000); // fonts and images that change the size without a resize event

	// The page outside makes the frame as tall as the form: then the frame does not need its own scroll bar.
	window.addEventListener('message', function (e) {
		if (e.source === window.parent && e.data && e.data.shootbook && e.data.type === 'sized') {
			document.documentElement.classList.add('bkb-sized');
		}
	});
	document.addEventListener('bkb:submitted', function () { send({ type: 'submitted' }); });
	size();
})();
