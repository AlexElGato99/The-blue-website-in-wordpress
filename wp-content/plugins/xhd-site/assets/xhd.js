/* XtremeHD IPTV — small front-end behaviours (replaces the original Alpine.js bits). */
(function () {
	'use strict';
	var d = document, inEditor = d.body.classList.contains('elementor-editor-active');
	function $all(s, root) { return Array.prototype.slice.call((root || d).querySelectorAll(s)); }

	/* Nav: shadow once the page scrolls */
	var nav = d.querySelector('.nav');
	function onScroll() { if (nav) nav.classList.toggle('scrolled', window.scrollY > 20); }
	window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

	/* Reveal on scroll (.rv → .vis), app mockup slide-in */
	var revealEls = $all('.rv, .app-sec, .app-visual');
	if (inEditor || !('IntersectionObserver' in window)) revealEls.forEach(function (el) { el.classList.add('vis'); });
	else {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('vis'); io.unobserve(e.target); } });
		}, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
		revealEls.forEach(function (el) { io.observe(el); });
	}

	/* Stats band: count up from 0 when it scrolls into view ([data-to] = target, [data-suffix] = "+", "/5" …) */
	var counters = $all('[data-to]');
	function countUp(el) {
		var to = parseFloat(el.getAttribute('data-to')), suf = el.getAttribute('data-suffix') || '', dec = (String(el.getAttribute('data-to')).split('.')[1] || '').length;
		var out = el.querySelector('.elementor-heading-title') || el, t0 = null, dur = 1600;
		function fmt(v) { return (dec ? v.toFixed(dec) : Math.round(v).toLocaleString('en-US')) + suf; }
		function step(ts) { if (!t0) t0 = ts; var p = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - p, 3); out.textContent = fmt(to * e); if (p < 1) requestAnimationFrame(step); }
		requestAnimationFrame(step);
	}
	if (counters.length && !inEditor && 'IntersectionObserver' in window) {
		var cio = new IntersectionObserver(function (entries) { entries.forEach(function (e) { if (e.isIntersecting) { countUp(e.target); cio.unobserve(e.target); } }); }, { threshold: 0.4 });
		counters.forEach(function (el) { cio.observe(el); });
	}

	/* Mobile menu (full-screen overlay) */
	var overlay = d.querySelector('.mobile-overlay');
	function setMenu(open) {
		if (!overlay) return;
		overlay.classList.toggle('open', open);
		d.documentElement.style.overflow = open ? 'hidden' : '';
		$all('.nav-mobile-toggle').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); b.classList.toggle('is-open', open); });
	}
	d.addEventListener('click', function (e) {
		var t = e.target.closest ? e.target.closest('.nav-mobile-toggle, .mobile-close, .mobile-overlay a') : null;
		if (!t) return;
		if (t.classList.contains('nav-mobile-toggle')) { e.preventDefault(); setMenu(!overlay.classList.contains('open')); }
		else if (t.classList.contains('mobile-close')) { e.preventDefault(); setMenu(false); }
		else setMenu(false);
	});
	d.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

	/* Current page highlight in the menus */
	var here = location.pathname.replace(/\/+$/, '');
	$all('.nav-links a, .mobile-overlay a:not(.btn):not(.mob-logo)').forEach(function (a) {
		var p = (a.getAttribute('href') || '').replace(/^https?:\/\/[^/]+/, '').replace(/[#?].*$/, '').replace(/\/+$/, '');
		a.classList.toggle('active', p === here);
	});

	/* FAQ accordions: .faq-q toggles .open on its .faq-item */
	d.addEventListener('click', function (e) {
		var q = e.target.closest ? e.target.closest('.faq-q') : null;
		if (!q || q.hasAttribute('data-xset')) return;
		var item = q.closest('.faq-item'); if (!item) return;
		e.preventDefault();
		var open = item.classList.toggle('open');
		q.setAttribute('aria-expanded', open ? 'true' : 'false');
	});
	d.addEventListener('keydown', function (e) {
		if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('faq-q') && e.target.tagName !== 'BUTTON') { e.preventDefault(); e.target.click(); }
	});

	/* Tabs / accordions: tiny replacement for the original Alpine.js state (see build prep.php):
	   [data-xinit] scope with initial state, [data-xset] buttons, [data-xshow] panels, [data-xclass] state classes. */
	var globalScope = { el: d.documentElement, st: {} };
	$all("[data-xinit-global]").forEach(function (e) { try { Object.assign(globalScope.st, JSON.parse(e.getAttribute("data-xinit-global"))); } catch (x) {} });
	function scopeFor(el, key) {
		for (var p = el; p && p !== d; p = p.parentElement) {
			if (p.hasAttribute && p.hasAttribute("data-xinit")) {
				if (!p.__xst) { try { p.__xst = JSON.parse(p.getAttribute("data-xinit")); } catch (x) { p.__xst = {}; } }
				if (key in p.__xst) return { el: p, st: p.__xst };
			}
		}
		return globalScope;
	}
	function val(el) { try { return JSON.parse(el.getAttribute("data-xv")); } catch (x) { return el.getAttribute("data-xv"); } }
	function render(scope, key) {
		var root = scope.el, v = scope.st[key];
		$all("[data-xshow=\"" + key + "\"]", root).forEach(function (p) { if (scopeFor(p, key).el === root) p.classList.toggle("on", val(p) === v); });
		$all("[data-xclass=\"" + key + "\"]", root).forEach(function (b) { if (scopeFor(b, key).el === root) b.classList.toggle(b.getAttribute("data-xc") || "active", val(b) === v); });
		$all(".price-grid", root).forEach(function (g) { if (g.offsetParent) scrollHint(g); });
	}
	d.addEventListener("click", function (e) {
		var b = e.target.closest ? e.target.closest("[data-xset]") : null; if (!b) return;
		e.preventDefault();
		var set = {}; try { set = JSON.parse(b.getAttribute("data-xset")); } catch (x) {}
		var tog = (b.getAttribute("data-xtog") || "").split(" ");
		Object.keys(set).forEach(function (k) {
			var sc = scopeFor(b, k);
			sc.st[k] = (tog.indexOf(k) > -1 && sc.st[k] === set[k]) ? null : set[k];
			render(sc, k);
		});
	});
	d.addEventListener("keydown", function (e) {
		var b = e.target; if ((e.key === "Enter" || e.key === " ") && b.hasAttribute && b.hasAttribute("data-xset") && b.tagName !== "BUTTON" && b.tagName !== "A") { e.preventDefault(); b.click(); }
	});
	/* Pricing horizontal scroll hint (tablet/mobile) */
	function scrollHint(g) {
		var box = g.parentNode, thumb = box && box.querySelector('.pricing-scroll-thumb'); if (!thumb) return;
		var max = g.scrollWidth - g.clientWidth, track = thumb.parentNode.clientWidth - thumb.clientWidth;
		thumb.style.left = (max > 0 ? (g.scrollLeft / max) * track : 0) + 'px';
	}
	$all('.price-grid').forEach(function (g) { g.addEventListener('scroll', function () { scrollHint(g); }, { passive: true }); });

	/* Cookie banner */
	var ck = d.querySelector('.xhd-cookie');
	if (ck && !inEditor) {
		var v = null; try { v = localStorage.getItem('cookie_consent'); } catch (e) {}
		if (!v) ck.classList.add('show');
		ck.addEventListener('click', function (e) {
			var b = e.target.closest('.cookie-decline, .cookie-accept'); if (!b) return;
			e.preventDefault();
			try { localStorage.setItem('cookie_consent', b.classList.contains('cookie-accept') ? 'accepted' : 'declined'); } catch (err) {}
			ck.classList.remove('show');
		});
	}
})();
