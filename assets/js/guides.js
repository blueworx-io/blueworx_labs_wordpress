/**
 * The two bits of the Guides screen that need a pointer.
 *
 * Both degrade to nothing: the tabs are real links that scroll normally without
 * this file, and the role list renders its full set server-side with the extra
 * pills hidden — so with the script absent you get every role rather than a
 * "+2 more" button that does nothing.
 */
(function () {
  'use strict';

  /* ─── Role pills ─── */

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-blueworx-roles-more]');

    if (!button) {
      return;
    }

    var group = button.closest('[data-blueworx-roles]');

    if (!group) {
      return;
    }

    var expanded = 'true' === button.getAttribute('aria-expanded');
    var hidden = group.querySelectorAll('[data-blueworx-role-extra]');

    Array.prototype.forEach.call(hidden, function (pill) {
      pill.hidden = expanded;
    });

    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    button.textContent = expanded
      ? button.getAttribute('data-more-label')
      : button.getAttribute('data-fewer-label');
  });

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

  bar.addEventListener(
    'click',
    function (event) {
      if (Date.now() < suppressUntil) {
        event.preventDefault();
        event.stopPropagation();
      }
    },
    true
  );
})();
