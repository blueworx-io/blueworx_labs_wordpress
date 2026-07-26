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

  var state = {
    root: null,
    toggle: null,
    list: null,
    status: null,
    current: null,
    translator: null,
    targetCode: null,
  };

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
   * Filters the configured languages down to the ones this browser will accept.
   *
   * The browser is the authority, not the admin setting: a pair it reports as
   * unavailable is dropped from the menu rather than offered and then failing.
   *
   * @return {Promise<Array>} Usable languages, in configured order.
   */
  function availableLanguages() {
    var checks = config.languages.map(function (language) {
      return Promise.resolve()
        .then(function () {
          return self.Translator.availability({
            sourceLanguage: config.source,
            targetLanguage: language.code,
          });
        })
        .then(function (availability) {
          return availability === 'unavailable' ? null : language;
        })
        .catch(function () {
          return null;
        });
    });

    return Promise.all(checks).then(function (results) {
      return results.filter(Boolean);
    });
  }

  /**
   * Sets the language shown on the pill and selected in the list.
   *
   * @param {string} code Language code, or the source code for "original".
   */
  function setCurrent(code) {
    state.targetCode = code === config.source ? null : code;

    var options = state.list.querySelectorAll('.blueworx-translate__option');

    for (var i = 0; i < options.length; i += 1) {
      var isActive = options[i].getAttribute('data-lang') === code;
      options[i].setAttribute('aria-selected', isActive ? 'true' : 'false');

      if (isActive) {
        state.current.textContent = options[i].textContent;
      }
    }
  }

  /**
   * Opens the language list.
   */
  function openList() {
    state.list.hidden = false;
    state.toggle.setAttribute('aria-expanded', 'true');
  }

  /**
   * Closes the language list.
   */
  function closeList() {
    state.list.hidden = true;
    state.toggle.setAttribute('aria-expanded', 'false');
  }

  /**
   * Builds the widget inside the root element.
   *
   * @param {Array} languages Usable languages.
   */
  function buildWidget(languages) {
    var root = document.getElementById('blueworx-translate-root');

    if (!root) {
      return;
    }

    var widget = document.createElement('div');
    widget.className = 'blueworx-translate blueworx-translate--' + config.position;

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'blueworx-translate__toggle';
    toggle.id = 'blueworx-translate-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-haspopup', 'listbox');

    var label = document.createElement('span');
    label.className = 'blueworx-translate__label';
    label.textContent = config.label;

    var current = document.createElement('span');
    current.className = 'blueworx-translate__current';

    toggle.appendChild(label);
    toggle.appendChild(current);

    var list = document.createElement('ul');
    list.className = 'blueworx-translate__list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-labelledby', 'blueworx-translate-toggle');
    list.hidden = true;

    // The source language leads the list: it is how a visitor gets back to the
    // page as written.
    var choices = [{ code: config.source, label: config.sourceLabel }].concat(languages);

    choices.forEach(function (choice) {
      var option = document.createElement('li');
      option.className = 'blueworx-translate__option';
      option.setAttribute('role', 'option');
      option.setAttribute('tabindex', '-1');
      option.setAttribute('data-lang', choice.code);
      option.setAttribute('aria-selected', 'false');
      option.textContent = choice.label;
      list.appendChild(option);
    });

    var status = document.createElement('span');
    status.className = 'blueworx-translate__status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');

    widget.appendChild(list);
    widget.appendChild(toggle);
    widget.appendChild(status);
    root.appendChild(widget);

    state.root = widget;
    state.toggle = toggle;
    state.list = list;
    state.status = status;
    state.current = current;

    setCurrent(config.source);

    toggle.addEventListener('click', function () {
      if (state.list.hidden) {
        openList();
      } else {
        closeList();
      }
    });

    document.addEventListener('click', function (event) {
      if (!state.list.hidden && !widget.contains(event.target)) {
        closeList();
      }
    });
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

    availableLanguages().then(function (languages) {
      if (languages.length === 0) {
        return;
      }

      buildWidget(languages);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
