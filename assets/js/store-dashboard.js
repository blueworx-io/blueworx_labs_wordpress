/**
 * Switching panels in the customer dashboard without reloading the page.
 *
 * Every panel is already on the page — the shop's and other plugins' own
 * components, which come alive when the page loads. So this shows and hides
 * what is there rather than fetching anything.
 *
 * An enhancement, not the mechanism: every nav item is a real link to a real
 * address. With this script absent, blocked or still loading, clicking one
 * navigates and the server draws the same view. Nothing here is required for
 * the page to work.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-blueworx-store]');
	if (!root || !window.history || !window.history.pushState) {
		return;
	}

	var TITLES = {};
	var panels = root.querySelectorAll('.blueworx-store__panel');
	var i;

	// The title and lede for each view, read off the links so the server stays
	// the single source of what a view is called.
	var links = root.querySelectorAll('[data-view-link]');
	for (i = 0; i < links.length; i++) {
		TITLES[links[i].getAttribute('data-view-link')] = {
			title: links[i].getAttribute('data-view-title') || '',
			lede: links[i].getAttribute('data-view-lede') || ''
		};
	}

	function show(key, push, href) {
		var target = null;
		var j;
		for (j = 0; j < panels.length; j++) {
			if (panels[j].getAttribute('data-view') === key) {
				target = panels[j];
				break;
			}
		}
		// Find the match before touching a single `hidden` attribute. On the
		// click path an unmatched key is harmless either way — preventDefault()
		// never fires, so the browser navigates for real. But popstate has no
		// navigation to fall back on: hiding every panel before knowing one
		// matches would leave a member staring at a blank page with only a
		// reload to get out of it.
		if (!target) {
			return false;
		}

		for (j = 0; j < panels.length; j++) {
			panels[j].hidden = panels[j] !== target;
		}

		for (j = 0; j < links.length; j++) {
			var active = links[j].getAttribute('data-view-link') === key;
			// classList.toggle's second argument is unsupported in older
			// browsers; add and remove are not.
			if (active) {
				links[j].classList.add('is-active');
				links[j].setAttribute('aria-current', 'page');
			} else {
				links[j].classList.remove('is-active');
				links[j].removeAttribute('aria-current');
			}
		}

		var head = TITLES[key] || { title: '', lede: '' };
		var titles = root.querySelectorAll('[data-member-title]');
		var ledes = root.querySelectorAll('[data-member-lede]');
		var k;
		if (head.title) {
			for (k = 0; k < titles.length; k++) {
				titles[k].textContent = head.title;
			}
			document.title = head.title;
		}
		for (k = 0; k < ledes.length; k++) {
			ledes[k].textContent = head.lede;
			ledes[k].hidden = !head.lede;
		}

		if (push && href) {
			window.history.pushState({ blueworxStoreView: key }, '', href);
		}
		// The panel is what changed, so that is what a screen reader should be
		// taken to — its own name via role="tabpanel"/aria-labelledby, not the
		// generic "main" landmark around it.
		target.setAttribute('tabindex', '-1');
		target.focus();
		return true;
	}

	root.addEventListener('click', function (event) {
		// Let a middle-click, a modified click or a right-click do what the
		// browser would: these are real links and open in a new tab.
		if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
			return;
		}
		var link = event.target.closest ? event.target.closest('[data-view-link]') : null;
		if (!link) {
			return;
		}
		var key = link.getAttribute('data-view-link');
		if (show(key, true, link.getAttribute('href'))) {
			event.preventDefault();
		}
	});

	window.addEventListener('popstate', function (event) {
		var key = event.state && event.state.blueworxStoreView;
		if (!key) {
			// Arrived back at the address the page was loaded on.
			key = root.getAttribute('data-view-initial') || '';
		}
		if (key) {
			show(key, false, '');
		}
	});
})();
