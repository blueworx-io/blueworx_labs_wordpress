/**
 * The "+N more" dropdown on a role list.
 *
 * Three roles are always in the row; the rest sit in a panel under the button,
 * closed until it is asked for. Its own file rather than part of the Guides
 * script, because page headers carry a role list too and the Guides script is
 * only loaded on Guides — so the button there did nothing at all.
 *
 * Degrades to nothing: with this absent the panel stays closed and the group's
 * title attribute still names every role on hover.
 */
(function () {
  'use strict';

  var OPEN = '[data-blueworx-roles-more][aria-expanded="true"]';

  /**
   * The panel a "+N more" button controls.
   *
   * @param {Element} button The button.
   * @return {Element|null} Its panel, or null.
   */
  function panelFor(button) {
    return document.getElementById(button.getAttribute('aria-controls'));
  }

  /**
   * Opens or closes one dropdown.
   *
   * @param {Element} button The button.
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

  /**
   * Closes every open dropdown but one.
   *
   * Two lists on one screen are common — a page header and a card — and leaving
   * both panels hanging open is a mess rather than a feature.
   *
   * @param {Element} [except] A button to leave alone.
   */
  function closeAll(except) {
    Array.prototype.forEach.call(document.querySelectorAll(OPEN), function (button) {
      if (button !== except) {
        setOpen(button, false);
      }
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target;

    if (!target || !target.closest) {
      return;
    }

    var button = target.closest('[data-blueworx-roles-more]');

    if (button) {
      event.preventDefault();
      closeAll(button);
      setOpen(button, 'true' !== button.getAttribute('aria-expanded'));
      return;
    }

    // A click anywhere else closes it — including inside the panel, which is a
    // list to read rather than a menu to pick from.
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
