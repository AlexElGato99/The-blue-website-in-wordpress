/* XHD Live Events — slide rotation, dots, hero height sync, live score polling. */
(function () {
	'use strict';
	function init(root) {
		if (root.__xhd) return; root.__xhd = 1;
		var isTicker = root.getAttribute('data-xhd-live') === 'ticker';
		var slides = root.querySelectorAll(isTicker ? '.hero-slide' : '.live-match-slide');
		var dots = root.querySelectorAll('.hero-ticker-nav .dot');
		var c = 0, timer, ms = parseInt(root.getAttribute('data-interval'), 10) || 4000;
		function go(n) {
			if (!slides.length) return;
			slides[c].classList.remove('active'); if (dots[c]) dots[c].classList.remove('active');
			c = ((n % slides.length) + slides.length) % slides.length;
			slides[c].classList.add('active'); if (dots[c]) dots[c].classList.add('active');
		}
		function start() { if (slides.length > 1) timer = setInterval(function () { go(c + 1); }, ms); }
		start();
		var nav = root.querySelector('.hero-ticker-nav');
		if (nav) nav.addEventListener('click', function (e) { var b = e.target.closest('.dot'); if (!b) return; clearInterval(timer); go(parseInt(b.getAttribute('data-goto'), 10)); start(); });
		if (isTicker) syncHeight(root);
	}
	/* Desktop: the ticker is as tall as the hero heading + text next to it (as on the original). */
	function syncHeight(tk) {
		var hero = tk.closest('.hero'); if (!hero) return;
		var h = hero.querySelector('h1'), d = hero.querySelector('.hero-desc');
		if (!h || !d) return;
		function sync() { tk.style.height = window.innerWidth > 1024 ? (h.offsetHeight + d.offsetHeight) + 'px' : ''; }
		sync(); var t; window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(sync, 150); });
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(sync);
	}
	/* Live scores: poll while a match is live. */
	function poll() {
		if (!window.XHD_LIVE || !document.querySelector('.st-live, .m-live')) return;
		setInterval(function () {
			if (document.hidden) return;
			fetch(XHD_LIVE.rest, { credentials: 'omit' }).then(function (r) { return r.json(); }).then(function (j) {
				(j.matches || []).forEach(function (m) {
					document.querySelectorAll('[data-mid="' + m.id + '"]').forEach(function (s) {
						var st = s.querySelector('.hero-slide-status, .live-match-status');
						if (st) { st.className = st.className.replace(/\b(st|m)-(live|upcoming|finished)\b/, '$1-' + m.st); if (m.st !== 'upcoming') st.textContent = m.label; }
						var vs = s.querySelector('.hero-slide-vs, .live-match-vs, .hero-slide-score, .live-match-score');
						if (vs && m.score && m.st !== 'upcoming') { vs.className = vs.className.indexOf('hero') === 0 ? 'hero-slide-score' : 'live-match-score'; vs.textContent = m.score; }
					});
				});
			}).catch(function () {});
		}, (XHD_LIVE.poll || 60) * 1000);
	}
	function boot() { document.querySelectorAll('[data-xhd-live]').forEach(init); poll(); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
	/* Elementor editor: (re)init when the widget is rendered */
	window.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend) return;
		['xhd-live-ticker', 'xhd-live-events'].forEach(function (w) {
			elementorFrontend.hooks.addAction('frontend/element_ready/' + w + '.default', function ($el) { var r = $el[0].querySelector('[data-xhd-live]'); if (r) init(r); });
		});
	});
})();
