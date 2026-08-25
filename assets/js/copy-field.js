/**
 * Copy buttons for BlueWorx admin screens.
 *
 * Any button carrying data-blueworx-copy="<id>" copies the value of the field
 * with that id and says so on itself. One implementation rather than one per
 * screen: the support panel alone has two of these, and the awkward part is
 * the same for both.
 *
 * Two copy paths, because wp-admin is routinely served over plain HTTP on
 * staging and local sites, where navigator.clipboard does not exist at all:
 * the async Clipboard API when it is available, and the legacy
 * document.execCommand('copy') selection when it is not.
 */
(function () {
  'use strict';

  /**
   * Copies via the legacy selection path.
   *
   * A hidden field cannot be selected, so one is unhidden for the length of
   * the copy and hidden again immediately — never on screen for a rendered
   * frame. The support prompt is hidden like this because it is multi-line and
   * belongs in a textarea the fallback can reach, not on the page.
   *
   * @param {HTMLInputElement|HTMLTextAreaElement} field Field to copy from.
   * @return {boolean} True when the copy was accepted.
   */
  function copyBySelection(field) {
    var wasHidden = field.hidden;

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

    field.hidden = wasHidden;

    return copied;
  }

  /**
   * Reports the outcome on the button itself.
   *
   * @param {HTMLElement}                          button    The copy button.
   * @param {HTMLInputElement|HTMLTextAreaElement} field     Field copied from.
   * @param {string}                               idleLabel Label to return to.
   * @param {boolean}                              copied    Whether it worked.
   * @return {void}
   */
  function report(button, field, idleLabel, copied) {
    var copiedLabel = button.getAttribute('data-copied-label') || 'Copied';

    button.textContent = copied ? copiedLabel : idleLabel;

    if (!copied) {
      // Nothing was copied, so say so rather than silently claiming success:
      // the operator would otherwise paste whatever was in the clipboard
      // before and wonder why the session cannot connect. Leaving the field
      // selected gives them a manual copy to fall back on.
      field.hidden = false;
      field.removeAttribute('aria-hidden');
      field.select();
      return;
    }

    window.clearTimeout(button.blueworxCopyTimer);
    button.blueworxCopyTimer = window.setTimeout(function () {
      button.textContent = idleLabel;
    }, 2000);
  }

  /**
   * Wires one copy button to its field.
   *
   * @param {HTMLElement} button Button carrying data-blueworx-copy.
   * @return {void}
   */
  function bind(button) {
    var field = document.getElementById(button.getAttribute('data-blueworx-copy'));

    if (!field) {
      return;
    }

    var idleLabel = button.textContent;

    button.addEventListener('click', function () {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(field.value).then(
          function () {
            report(button, field, idleLabel, true);
          },
          function () {
            report(button, field, idleLabel, copyBySelection(field));
          }
        );
        return;
      }

      report(button, field, idleLabel, copyBySelection(field));
    });
  }

  /**
   * Binds every copy button on the screen.
   *
   * @return {void}
   */
  function start() {
    var buttons = document.querySelectorAll('[data-blueworx-copy]');
    Array.prototype.forEach.call(buttons, bind);
  }

  // Not simply DOMContentLoaded: this file is enqueued in the footer, and if it
  // ever executes after that event has already fired — a deferred script, an
  // asset optimiser, a slow load — a listener added here would never run at
  // all, and the copy buttons would be dead with nothing on the page to say so.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

/**
 * The support key's "I have copied it" confirmation.
 *
 * The key is shown exactly once. Nothing here can stop somebody navigating
 * away, but it does stop them doing it without noticing — which is the failure
 * this screen actually sees.
 */
( function () {
	'use strict';

	function start() {
		var box = document.querySelector( '[data-blueworx-key-copied]' );
		var done = document.querySelector( '[data-blueworx-key-done]' );
		var wrap = document.querySelector( '[data-blueworx-key-confirm]' );

		if ( ! box || ! done || ! wrap ) {
			return;
		}

		box.addEventListener( 'change', function () {
			done.disabled = ! box.checked;
		} );

		done.addEventListener( 'click', function () {
			if ( ! box.checked ) {
				return;
			}

			// The reveal and its confirmation go together. What stays is the
			// state list, which never showed the key in the first place.
			var reveal = document.querySelector( '#bw-support-key' );
			var field = reveal ? reveal.closest( '.bw-copyfield' ) : null;
			var notice = wrap.parentElement ? wrap.parentElement.querySelector( '.bw-notice--warning' ) : null;

			if ( field ) {
				field.remove();
			}

			if ( notice ) {
				notice.remove();
			}

			wrap.remove();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
