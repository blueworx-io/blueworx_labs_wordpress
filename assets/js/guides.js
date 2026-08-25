/**
 * The two bits of the Guides screen that need a pointer.
 *
 * Degrades to nothing: the tabs are real links that scroll normally without this
 * file, and every topic's cards are already on the page.
 */
(function () {
  'use strict';

  /* ─── Drag-scrollable tabs ─── */

  var bar = document.querySelector('[data-blueworx-guide-tabs]');

  if (!bar) {
    return;
  }

  var down = false;
  var moved = false;
  var startX = 0;
  var startScroll = 0;
  var suppressUntil = 0;

  bar.addEventListener('pointerdown', function (event) {
    // Left button / touch / pen only. A right-click is a context menu, not a drag.
    if (event.button && 0 !== event.button) {
      return;
    }

    down = true;
    moved = false;
    startX = event.clientX;
    startScroll = bar.scrollLeft;
  });

  bar.addEventListener('pointermove', function (event) {
    if (!down) {
      return;
    }

    var delta = event.clientX - startX;

    // Four pixels of slop: a tap never travels zero, and treating one as a drag
    // would stop the tab under the finger from activating.
    if (!moved && Math.abs(delta) > 4) {
      moved = true;
      bar.classList.add('is-dragging');
    }

    if (moved) {
      // Deliberately no setPointerCapture(): capturing retargets the eventual
      // click to the bar, and the anchor inside it never sees it — the tabs stop
      // working entirely.
      bar.scrollLeft = startScroll - delta;
      event.preventDefault();
    }
  });

  function end() {
    if (!down) {
      return;
    }

    down = false;
    bar.classList.remove('is-dragging');

    if (moved) {
      // The click lands after pointerup. Suppress just that one, then let the
      // bar go back to being a set of links.
      suppressUntil = Date.now() + 60;
    }
  }

  bar.addEventListener('pointerup', end);
  bar.addEventListener('pointercancel', end);
  bar.addEventListener('pointerleave', end);

  /* ─── Switching topic ───
     Every topic's cards are already on the page, with all but one hidden, so a
     tab swaps them rather than fetching the screen again. The address still
     changes, so the tab stays something you can bookmark or be sent, and Back
     goes where it looks like it should. */

  var panels = document.querySelectorAll('[data-blueworx-guide-panel]');

  function showTab(tab) {
    var found = false;

    Array.prototype.forEach.call(panels, function (panel) {
      var mine = panel.getAttribute('data-blueworx-guide-panel') === tab;

      panel.hidden = !mine;

      if (mine) {
        found = true;
      }
    });

    // An address for a topic that is not on this page — a stale link, or a tab
    // from another section. Say so, and the caller lets the browser navigate.
    if (!found) {
      return false;
    }

    Array.prototype.forEach.call(bar.querySelectorAll('.bw-tab'), function (link) {
      var mine = link.getAttribute('data-blueworx-guide-tab') === tab;

      link.classList.toggle('is-active', mine);

      if (mine) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });

    return true;
  }

  function tabFromUrl() {
    var match = /[?&]tab=([^&]*)/.exec(window.location.search);

    return match ? decodeURIComponent(match[1]) : '';
  }

  bar.addEventListener(
    'click',
    function (event) {
      if (Date.now() < suppressUntil) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      // Anything but a plain left click is the browser's to handle: a modifier
      // means a new tab or window, and taking that away is worse than a reload.
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      var link = event.target.closest ? event.target.closest('.bw-tab') : null;

      if (!link) {
        return;
      }

      if (!showTab(link.getAttribute('data-blueworx-guide-tab'))) {
        return;
      }

      event.preventDefault();

      if (window.history && window.history.pushState) {
        window.history.pushState({ blueworxGuideTab: true }, '', link.href);
      }
    },
    true
  );

  window.addEventListener('popstate', function () {
    var tab = tabFromUrl();

    if (tab) {
      showTab(tab);
    }
  });
})();
