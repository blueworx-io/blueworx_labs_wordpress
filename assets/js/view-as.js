/**
 * The sidebar's "view as role" menu.
 *
 * Opens and closes the panel, and nothing else. Every option in it is a submit
 * button carrying its own role, so choosing one posts on its own — this file
 * being absent costs you the ability to open the list, not the ability to use
 * it once open.
 *
 * The control is put into the sidebar after this runs (WordPress builds that
 * menu itself and offers nowhere to append to), so everything here is delegated
 * from the document rather than bound to the button.
 */
(function () {
  'use strict';

  var TRIGGER = '[data-blueworx-viewas-trigger]';
  var OPEN = TRIGGER + '[aria-expanded="true"]';

  /**
   * The panel a trigger controls.
   *
   * @param {Element} button The trigger.
   * @return {Element|null} Its panel, or null.
   */
  function panelFor(button) {
    return document.getElementById(button.getAttribute('aria-controls'));
  }

  /**
   * Opens or closes the menu.
   *
   * @param {Element} button The trigger.
   * @param {boolean} open   Whether it should end up open.
   */
  function setOpen(button, open) {
    var panel = panelFor(button);

    if (!panel) {
      return;
    }

    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    panel.hidden = !open;
  }

  /** Closes the menu wherever it is open. */
  function closeAll() {
    Array.prototype.forEach.call(document.querySelectorAll(OPEN), function (button) {
      setOpen(button, false);
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target;

    if (!target || !target.closest) {
      return;
    }

    var button = target.closest(TRIGGER);

    if (button) {
      event.preventDefault();
      setOpen(button, 'true' !== button.getAttribute('aria-expanded'));
      return;
    }

    // A click on an option is left alone — it is a submit button, and swallowing
    // it here would leave a menu that looks like it works and changes nothing.
    if (target.closest('.bw-viewas__menu')) {
      return;
    }

    closeAll();
  });

  document.addEventListener('keydown', function (event) {
    if ('Escape' !== event.key) {
      return;
    }

    var open = document.querySelector(OPEN);

    if (open) {
      setOpen(open, false);
      open.focus();
    }
  });
})();
