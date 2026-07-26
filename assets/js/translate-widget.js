/**
 * On-page translation widget.
 *
 * Translates the current page in the visitor's own browser using the Chrome
 * built-in Translator API. There is no network request and no translation
 * service: if the API is not there, this file does nothing and renders nothing.
 */
(function () {
  'use strict';

  var config = window.blueworxTranslate;

  /**
   * Reports whether this browser exposes the built-in Translator API.
   *
   * Chrome/Edge 138+ only. Everywhere else the widget must render nothing at
   * all — a language button that cannot translate is worse than no button.
   *
   * @return {boolean} True when the API is available.
   */
  function isSupported() {
    return typeof self !== 'undefined' && 'Translator' in self;
  }

  /**
   * Boots the widget.
   */
  function init() {
    if (!config || !config.languages || config.languages.length === 0) {
      return;
    }

    if (!isSupported()) {
      return;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
