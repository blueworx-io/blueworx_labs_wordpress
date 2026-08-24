/**
 * The mobile admin menu: an off-canvas drawer behind a hamburger.
 *
 * Below 900px the sidebar slides in over the page rather than sitting beside it.
 * WordPress's own responsive menu toggle lives in the black admin bar, which the
 * BlueWorx top bar replaces — so this file has to provide the way in before the
 * stylesheet is allowed to take the native bar away.
 *
 * That is what the `bw-drawer-ready` class on <html> is for. The stylesheet only
 * hides #wpadminbar, and only shows the BlueWorx bar on a phone, while that
 * class is present. If this script fails to load or throws, the class is never
 * set, WordPress's own bar and toggle stay exactly where they were, and the
 * worst case is a phone that looks unstyled rather than a phone that cannot
 * reach the admin menu at all.
 */
(function () {
  'use strict';

  var OPEN = 'bw-drawer-open';
  var BREAKPOINT = 900;

  var root = document.documentElement;
  var toggle = document.querySelector('[data-blueworx-drawer-toggle]');
  var scrim = document.querySelector('[data-blueworx-drawer-scrim]');
  var menu = document.getElementById('adminmenumain');

  if (!toggle || !scrim || !menu) {
    return;
  }

  /**
   * Whether the drawer layout is the one in force.
   *
   * @return {boolean} True below the breakpoint.
   */
  function isDrawer() {
    return window.innerWidth <= BREAKPOINT;
  }

  /**
   * Opens or closes the drawer.
   *
   * @param {boolean} open Whether it should end up open.
   */
  function setOpen(open) {
    root.classList.toggle(OPEN, open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    scrim.hidden = !open;

    // Focus moves into the drawer so a keyboard or screen reader lands on the
    // menu it just opened, and back to the button when it closes — otherwise
    // focus is left on a button behind a scrim.
    if (open) {
      var first = menu.querySelector('a');
      if (first) {
        first.focus();
      }
    } else {
      toggle.focus();
    }
  }

  toggle.addEventListener('click', function (event) {
    event.preventDefault();
    setOpen(!root.classList.contains(OPEN));
  });

  scrim.addEventListener('click', function () {
    setOpen(false);
  });

  // Choosing anything closes the drawer. Without this the menu stays over the
  // page while the next screen loads behind it, which reads as a stuck overlay.
  menu.addEventListener('click', function (event) {
    if (event.target.closest('a') && isDrawer()) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if ('Escape' === event.key && root.classList.contains(OPEN)) {
      setOpen(false);
    }
  });

  // Growing past the breakpoint with the drawer open would otherwise leave the
  // scrim covering a desktop layout that no longer has a drawer.
  window.addEventListener('resize', function () {
    if (!isDrawer() && root.classList.contains(OPEN)) {
      setOpen(false);
    }
  });

  scrim.hidden = true;
  toggle.setAttribute('aria-expanded', 'false');
  root.classList.add('bw-drawer-ready');
})();
