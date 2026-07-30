/**
 * Copies the BlueWorx support access prompt for Claude Code.
 *
 * The prompt lives in a hidden <textarea> next to the button rather than in a
 * data- attribute: it is multi-line, and a textarea is also what the
 * execCommand fallback needs to have on the page anyway.
 *
 * Two copy paths, because wp-admin is routinely served over plain HTTP on
 * staging and local sites, where navigator.clipboard does not exist at all:
 * the async Clipboard API when it is available, and the legacy
 * document.execCommand('copy') selection when it is not.
 */
(function () {
  'use strict';

  var button = document.querySelector('[data-testid="bw-support-copy-prompt"]');
  var field = document.querySelector('[data-testid="bw-support-prompt"]');

  if (!button || !field) {
    return;
  }

  var idleLabel = button.textContent;
  var copiedLabel = button.getAttribute('data-copied-label') || 'Copied';
  var resetTimer = null;

  /**
   * Copies via the legacy selection path.
   *
   * The textarea is hidden, and a hidden field cannot be selected, so it is
   * unhidden for the length of the copy and hidden again immediately. It is
   * never on screen for a rendered frame.
   *
   * @return {boolean} True when the copy was accepted.
   */
  function copyBySelection() {
    field.hidden = false;
    field.select();
    field.setSelectionRange(0, field.value.length);

    var copied = false;

    try {
      copied = document.execCommand('copy');
    } catch {
      // The failure itself is the whole signal — the caller reports "not
      // copied" either way, so there is nothing to read off the error.
      copied = false;
    }

    field.hidden = true;

    return copied;
  }

  /**
   * Reports the outcome on the button itself.
   *
   * @param {boolean} copied Whether the copy succeeded.
   * @return {void}
   */
  function report(copied) {
    button.textContent = copied ? copiedLabel : idleLabel;

    if (!copied) {
      // Nothing was copied, so say so rather than silently claiming success:
      // the operator would otherwise paste whatever was in the clipboard
      // before and wonder why the session cannot connect.
      field.hidden = false;
      field.removeAttribute('aria-hidden');
      field.select();
      return;
    }

    window.clearTimeout(resetTimer);
    resetTimer = window.setTimeout(function () {
      button.textContent = idleLabel;
    }, 2000);
  }

  button.addEventListener('click', function () {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(field.value).then(
        function () {
          report(true);
        },
        function () {
          report(copyBySelection());
        }
      );
      return;
    }

    report(copyBySelection());
  });
})();
