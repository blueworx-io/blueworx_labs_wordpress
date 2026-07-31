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
    toggle: null,
    list: null,
    status: null,
    currentFlag: null,
    currentName: null,
    translator: null,
    targetCode: null,
  };

  var TRANSLATABLE_ATTRS = ['alt', 'title', 'placeholder', 'aria-label'];

  // Always skipped: markup where translated text would be wrong (code, script)
  // and the widget itself, which must never translate its own controls.
  var BASE_EXCLUDE = [
    'script',
    'style',
    'noscript',
    'code',
    'pre',
    'kbd',
    'samp',
    'textarea',
    '[translate="no"]',
    '.notranslate',
    '.blueworx-no-translate',
    '#blueworx-translate-root',
  ];

  // Four in-flight translate() calls: enough to keep the model busy, few enough
  // that the page fills in progressively instead of stalling on one long batch.
  var CONCURRENCY = 4;

  // node -> { '': originalText, alt: originalAlt, ... }. Weak so a node removed
  // from the document is not pinned in memory by this map.
  var originals = new WeakMap();

  // Every recorded { node, attr } pair, in the order it was translated, so the
  // source language can be restored without a reload.
  var touched = [];

  var excludeSelectorCache = null;

  var STORAGE_KEY = 'blueworxTranslateLang';

  // Elementor popups and AJAX loads drop content in long after load, often in
  // bursts. A quarter second of quiet is enough to batch a burst into one pass.
  var OBSERVER_DEBOUNCE = 250;

  var observer = null;
  var observerTimer = null;

  // Bumped every time a new pass supersedes whatever came before —
  // applyLanguage() and applySource() both increment it. A pass in progress
  // (translateTargets(), including one continued in the background by
  // translatePending()) captures the value at its own start and checks it
  // before writing each result, so a translate() call that resolves after the
  // visitor has already switched away is discarded instead of writing a stale
  // value onto a node whose original may no longer be recorded.
  var generation = 0;

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
   * The flag and the name are written to their own spans rather than as one
   * string: which of the two is visible is a CSS decision (the display style,
   * and the flags-only fallback on narrow screens), and the name stays in the
   * DOM even when hidden so the button keeps an accessible name.
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
        state.currentFlag.textContent = options[i].getAttribute('data-flag') || '';
        state.currentName.textContent = options[i].getAttribute('data-label') || '';
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
   * Builds the joined selector of everything that must not be translated.
   *
   * Admin-supplied selectors are validated one at a time and a malformed one is
   * dropped: a typo in a site setting must not stop the rest of the page from
   * translating.
   *
   * @return {string} Joined CSS selector.
   */
  function excludeSelector() {
    if (excludeSelectorCache !== null) {
      return excludeSelectorCache;
    }

    var selectors = BASE_EXCLUDE.slice();
    var extra = Array.isArray(config.exclude) ? config.exclude : [];

    extra.forEach(function (selector) {
      var candidate = String(selector).trim();

      if (candidate === '') {
        return;
      }

      try {
        document.createDocumentFragment().querySelector(candidate);
        selectors.push(candidate);
      } catch {
        // Malformed selector from the settings screen; skip it.
      }
    });

    excludeSelectorCache = selectors.join(',');

    return excludeSelectorCache;
  }

  /**
   * Reports whether an element sits inside excluded markup.
   *
   * @param {Element} element Element to test.
   * @return {boolean} True when the element must not be translated.
   */
  function isExcluded(element) {
    if (!element || typeof element.closest !== 'function') {
      return true;
    }

    try {
      return element.closest(excludeSelector()) !== null;
    } catch {
      return false;
    }
  }

  /**
   * Records a value's original so it can be restored later.
   *
   * @param {Node}   node  Text node or element.
   * @param {string} attr  Attribute name, or '' for text content.
   * @param {string} value Current value.
   * @return {boolean} False when this pair was already recorded.
   */
  function recordOriginal(node, attr, value) {
    var entry = originals.get(node);

    if (!entry) {
      entry = {};
      originals.set(node, entry);
    }

    if (Object.prototype.hasOwnProperty.call(entry, attr)) {
      return false;
    }

    entry[attr] = value;
    touched.push({ node: node, attr: attr });

    return true;
  }

  /**
   * Collects every not-yet-translated string within a scope.
   *
   * @param {Node} scope Element or document fragment to walk.
   * @return {Array} Targets as { node, attr, text }.
   */
  function collectTargets(scope) {
    var targets = [];
    var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
      acceptNode: function (node) {
        if (node.nodeType === Node.TEXT_NODE) {
          var text = node.nodeValue;

          // Whitespace, digits and punctuation are not worth a model call, and
          // "2026" comes back unchanged at best.
          if (!text || text.trim() === '' || !/\p{L}/u.test(text)) {
            return NodeFilter.FILTER_REJECT;
          }

          return isExcluded(node.parentElement) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
        }

        return isExcluded(node) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
      },
    });

    var node = walker.currentNode;

    while (node) {
      if (node.nodeType === Node.TEXT_NODE) {
        if (recordOriginal(node, '', node.nodeValue)) {
          targets.push({ node: node, attr: '', text: node.nodeValue });
        }
      } else if (node.nodeType === Node.ELEMENT_NODE && !isExcluded(node)) {
        // The explicit isExcluded() check above (rather than relying on the
        // walker's acceptNode filter) matters for one case: a TreeWalker
        // never runs acceptNode() on its own root. Every descendant is
        // already filtered by acceptNode before we see it here, but the
        // scope element itself (document.body at every call site) is not —
        // without this check it would translate its attributes even when
        // excluded.
        TRANSLATABLE_ATTRS.forEach(function (attr) {
          var value = node.getAttribute(attr);

          if (value && value.trim() !== '' && /\p{L}/u.test(value) && recordOriginal(node, attr, value)) {
            targets.push({ node: node, attr: attr, text: value });
          }
        });
      }

      node = walker.nextNode();
    }

    return targets;
  }

  /**
   * Writes a translated value back onto its node.
   *
   * @param {{node: Node, attr: string}} target Target record.
   * @param {string}                     value  Translated value.
   */
  function writeTarget(target, value) {
    if (target.attr === '') {
      target.node.nodeValue = value;
    } else {
      target.node.setAttribute(target.attr, value);
    }
  }

  /**
   * Translates a list of targets, four calls in flight at a time.
   *
   * A single failed call leaves that one string as written and the pass
   * continues: a partial translation beats a blank or reverted page.
   *
   * The translator and the pass's generation are both taken as parameters
   * rather than read from `state` inside the worker: `state.translator` can
   * change — to a different translator, or to null — while a call started by
   * an earlier iteration is still awaiting `translate()`. Passing them in
   * once, at the moment the pass starts, means every worker translates with
   * the translator this pass was given, and — via the generation check below
   * — never writes a result after the pass it belongs to has been superseded
   * by a newer one (a language switch via applyLanguage() or a return to the
   * source language via applySource()).
   *
   * @param {Array}  targets        Targets from collectTargets().
   * @param {Object} translator     Translator this pass must use.
   * @param {number} passGeneration Generation this pass belongs to.
   * @return {Promise} Resolves when every target has been attempted.
   */
  function translateTargets(targets, translator, passGeneration) {
    if (!translator || targets.length === 0) {
      return Promise.resolve();
    }

    var next = 0;

    function worker() {
      if (next >= targets.length || passGeneration !== generation) {
        return Promise.resolve();
      }

      var target = targets[next];
      next += 1;

      return Promise.resolve()
        .then(function () {
          return translator.translate(target.text);
        })
        .then(function (translated) {
          // A newer pass has started since this call was issued; the node
          // this result belongs to may already be back to its source text
          // with no recorded original, so writing it now would strand a
          // stale translation with no way to restore it later.
          if (passGeneration !== generation) {
            return;
          }

          if (typeof translated === 'string' && translated !== '') {
            writeTarget(target, translated);
          }
        })
        .catch(function () {
          // Leave this string as written and keep going.
        })
        .then(worker);
    }

    var workers = [];

    for (var i = 0; i < CONCURRENCY; i += 1) {
      workers.push(worker());
    }

    return Promise.all(workers);
  }

  /**
   * Puts the pill into or out of its busy state.
   *
   * @param {boolean} isBusy  Whether work is in progress.
   * @param {string}  message Status text, announced politely.
   */
  function setBusy(isBusy, message) {
    state.toggle.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    state.toggle.disabled = isBusy;
    state.status.textContent = message || '';
  }

  /**
   * Reads the remembered language.
   *
   * Storage access is wrapped: it throws outright in a browser with cookies and
   * site data blocked, and that must not stop the widget from working for the
   * rest of the visit.
   *
   * @return {string} Stored code, or '' when there is none.
   */
  function readStoredLang() {
    try {
      return window.localStorage.getItem(STORAGE_KEY) || '';
    } catch {
      return '';
    }
  }

  /**
   * Remembers a language for future visits.
   *
   * @param {string} code Language code.
   */
  function writeStoredLang(code) {
    try {
      window.localStorage.setItem(STORAGE_KEY, code);
    } catch {
      // Storage unavailable; the choice simply will not survive this page.
    }
  }

  /**
   * Forgets the remembered language.
   */
  function clearStoredLang() {
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch {
      // Nothing to do: there is no storage to clear.
    }
  }

  /**
   * Puts every translated string back exactly as the page served it.
   *
   * In-memory and synchronous — no reload, no second model call.
   */
  function restoreOriginals() {
    for (var i = touched.length - 1; i >= 0; i -= 1) {
      var record = touched[i];
      var entry = originals.get(record.node);

      if (!entry || !Object.prototype.hasOwnProperty.call(entry, record.attr)) {
        continue;
      }

      if (record.attr === '') {
        record.node.nodeValue = entry[''];
      } else {
        record.node.setAttribute(record.attr, entry[record.attr]);
      }
    }

    touched = [];
    originals = new WeakMap();
  }

  /**
   * Returns the page to the language it was written in.
   */
  function applySource() {
    generation += 1;
    closeList();
    stopObserver();
    restoreOriginals();
    state.translator = null;
    state.targetCode = null;
    document.documentElement.lang = config.source;
    setCurrent(config.source);
    clearStoredLang();
    setBusy(false, '');
  }

  /**
   * Stops watching for new content.
   */
  function stopObserver() {
    if (observer) {
      observer.disconnect();
    }

    if (observerTimer) {
      window.clearTimeout(observerTimer);
      observerTimer = null;
    }
  }

  /**
   * Translates whatever has appeared since the last pass.
   *
   * The observer is disconnected while writing. That is not to stop it from
   * seeing the widget's own writes — the observer only watches
   * `{ childList: true, subtree: true }`, and this pass only mutates
   * `nodeValue` and attributes, so it could never observe its own writes
   * anyway. What the disconnect/reconnect bracket actually prevents is a
   * second, concurrent pass being scheduled by unrelated third-party DOM
   * churn while this one is still writing.
   */
  function translatePending() {
    observerTimer = null;

    // Captured once, up front: state.translator can be reassigned or cleared
    // by applyLanguage()/applySource() while this pass is still running, and
    // the pass must keep using the translator it started with (or stop
    // writing altogether — see the generation check in translateTargets()).
    var translator = state.translator;

    if (!translator || !state.targetCode) {
      return;
    }

    var targets = collectTargets(document.body);

    if (targets.length === 0) {
      return;
    }

    var passGeneration = generation;

    stopObserver();
    // Unlike a foreground applyLanguage() pass, this one is triggered by
    // content simply appearing on the page — but it is exactly as capable of
    // leaving the toggle enabled mid-write, so it gets the same busy state.
    setBusy(true, config.busyLabel);

    translateTargets(targets, translator, passGeneration).then(function () {
      // A newer pass already changed the busy state and observer for
      // whatever it is now doing; this stale continuation must not clobber
      // either.
      if (passGeneration !== generation) {
        return;
      }

      setBusy(false, '');
      startObserver();
    });
  }

  /**
   * Watches the document for content added after load.
   */
  function startObserver() {
    if (!state.translator || !state.targetCode) {
      return;
    }

    if (!observer) {
      observer = new MutationObserver(function () {
        if (observerTimer) {
          window.clearTimeout(observerTimer);
        }

        observerTimer = window.setTimeout(translatePending, OBSERVER_DEBOUNCE);
      });
    }

    observer.observe(document.body, { childList: true, subtree: true });
  }

  /**
   * Translates the whole page into one language.
   *
   * Restores the original text first when a translation is already in effect
   * — collectTargets() only offers nodes it has not already recorded, so
   * switching straight from one target language to another without this
   * would leave the page a mix of both languages. A no-op the first time this
   * runs on a page, since restoreOriginals() has nothing to undo yet.
   *
   * @param {string} code Target language code.
   * @return {Promise} Resolves when the pass has finished.
   */
  function applyLanguage(code) {
    // Supersedes anything already running — a background translatePending()
    // pass, or (in principle) another applyLanguage()/applySource() call —
    // before touching anything else, so nothing started after this point can
    // ever be mistaken for the pass this call is about to begin.
    generation += 1;
    var passGeneration = generation;

    closeList();

    var hadTranslation = false;

    if (state.translator) {
      stopObserver();
      restoreOriginals();
      state.translator = null;
      state.targetCode = null;
      hadTranslation = true;
    }

    setBusy(true, config.busyLabel);

    return Promise.resolve()
      .then(function () {
        return self.Translator.create({
          sourceLanguage: config.source,
          targetLanguage: code,
          monitor: function (monitor) {
            monitor.addEventListener('downloadprogress', function (event) {
              var percent = Math.round((event.loaded || 0) * 100);
              state.status.textContent = percent + '%';
            });
          },
        });
      })
      .then(function (translator) {
        if (passGeneration !== generation) {
          return undefined;
        }

        state.translator = translator;
        // Set early, before the pass finishes: startObserver() and
        // translatePending() both refuse to run without it.
        state.targetCode = code;
        stopObserver();

        return translateTargets(collectTargets(document.body), translator, passGeneration);
      })
      .then(function () {
        if (passGeneration !== generation) {
          return;
        }

        document.documentElement.lang = code;
        setCurrent(code);
        writeStoredLang(code);
        setBusy(false, '');
        startObserver();
      })
      .catch(function () {
        if (passGeneration !== generation) {
          return;
        }

        state.translator = null;
        state.targetCode = null;
        stopObserver();

        if (hadTranslation) {
          // restoreOriginals() above already put the DOM back in the source
          // language; the pill, html[lang], and the remembered choice must
          // all say so too, or a visitor is shown "French" over a page that
          // reads in English — or a reload silently brings French back.
          document.documentElement.lang = config.source;
          setCurrent(config.source);
          clearStoredLang();
        }

        setBusy(false, config.errorLabel);
      });
  }

  /**
   * Moves focus to one option, clamped to the ends of the list.
   *
   * @param {number} index Desired option index.
   */
  function focusOption(index) {
    var options = state.list.querySelectorAll('.blueworx-translate__option');

    if (options.length === 0) {
      return;
    }

    var clamped = Math.max(0, Math.min(index, options.length - 1));
    options[clamped].focus();
  }

  /**
   * Returns the index of the currently focused option, or the selected one.
   *
   * @return {number} Option index.
   */
  function focusedOptionIndex() {
    var options = state.list.querySelectorAll('.blueworx-translate__option');

    for (var i = 0; i < options.length; i += 1) {
      if (options[i] === document.activeElement) {
        return i;
      }
    }

    for (var j = 0; j < options.length; j += 1) {
      if (options[j].getAttribute('aria-selected') === 'true') {
        return j;
      }
    }

    return 0;
  }

  /**
   * Acts on a chosen option.
   *
   * Returns a promise so a keyboard caller can wait for a resulting language
   * change to settle before deciding whether to return focus to the toggle.
   * Deliberately does not touch focus itself: a mouse click must not have
   * focus jerked onto the toggle once a slow download-backed pass finishes,
   * and the toggle is disabled for the whole pass anyway (see setBusy()), so
   * an immediate focus() call here would be a no-op even if wanted.
   *
   * @param {Element} option Option element.
   * @return {Promise} Resolves once any resulting language change has
   *   settled. Already resolved for the two synchronous branches.
   */
  function selectOption(option) {
    if (!option) {
      return Promise.resolve();
    }

    var code = option.getAttribute('data-lang');

    if (code === config.source) {
      applySource();
      return Promise.resolve();
    }

    if (code === state.targetCode) {
      closeList();
      return Promise.resolve();
    }

    return applyLanguage(code);
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

    // The display style is a class, not a branch in this builder: the same
    // markup has to serve the narrow-screen flags-only fallback, which is a
    // media query and cannot be known here.
    var display = config.display || 'text';

    var widget = document.createElement('div');
    widget.className =
      'blueworx-translate blueworx-translate--' + config.position + ' blueworx-translate--display-' + display;

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'blueworx-translate__toggle';
    toggle.id = 'blueworx-translate-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-haspopup', 'listbox');

    // Never painted, always announced. The pill shows the current language and
    // nothing else, but a button whose whole accessible name is "English" — or,
    // in flags-only, nothing at all — does not say what pressing it does. This
    // span is what makes it "Choose language, English".
    var label = document.createElement('span');
    label.className = 'blueworx-translate__label';
    label.textContent = config.toggleLabel;

    var current = document.createElement('span');
    current.className = 'blueworx-translate__current';

    var currentFlag = document.createElement('span');
    currentFlag.className = 'blueworx-translate__flag';
    currentFlag.setAttribute('aria-hidden', 'true');

    var currentName = document.createElement('span');
    currentName.className = 'blueworx-translate__name';

    current.appendChild(currentFlag);
    current.appendChild(currentName);

    toggle.appendChild(label);
    toggle.appendChild(current);

    var list = document.createElement('ul');
    list.className = 'blueworx-translate__list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-labelledby', 'blueworx-translate-toggle');
    list.hidden = true;

    // The source language leads the list: it is how a visitor gets back to the
    // page as written.
    var choices = [
      { code: config.source, label: config.sourceLabel, flag: config.sourceFlag },
    ].concat(languages);

    choices.forEach(function (choice) {
      var option = document.createElement('li');
      option.className = 'blueworx-translate__option';
      option.setAttribute('role', 'option');
      option.setAttribute('tabindex', '-1');
      option.setAttribute('data-lang', choice.code);
      // Read back by setCurrent() when this option becomes the chosen one, so
      // the pill never has to re-derive either value from the option's text.
      option.setAttribute('data-label', choice.label);
      option.setAttribute('data-flag', choice.flag || '');
      option.setAttribute('aria-selected', 'false');

      var optionFlag = document.createElement('span');
      optionFlag.className = 'blueworx-translate__flag';
      optionFlag.setAttribute('aria-hidden', 'true');
      optionFlag.textContent = choice.flag || '';

      var optionName = document.createElement('span');
      optionName.className = 'blueworx-translate__name';
      optionName.textContent = choice.label;

      option.appendChild(optionFlag);
      option.appendChild(optionName);
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

    state.toggle = toggle;
    state.list = list;
    state.status = status;
    state.currentFlag = currentFlag;
    state.currentName = currentName;

    setCurrent(config.source);

    toggle.addEventListener('click', function () {
      if (state.list.hidden) {
        openList();
        focusOption(focusedOptionIndex());
      } else {
        closeList();
      }
    });

    document.addEventListener('click', function (event) {
      if (!state.list.hidden && !widget.contains(event.target)) {
        closeList();
      }
    });

    toggle.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        openList();
        focusOption(focusedOptionIndex());
      }
    });

    list.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        focusOption(focusedOptionIndex() + 1);
        return;
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault();
        focusOption(focusedOptionIndex() - 1);
        return;
      }

      if (event.key === 'Home') {
        event.preventDefault();
        focusOption(0);
        return;
      }

      if (event.key === 'End') {
        event.preventDefault();
        focusOption(Number.MAX_SAFE_INTEGER);
        return;
      }

      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        // Keyboard-only focus return: a mouse click (the list's separate
        // click handler below) never does this. The activeElement check
        // guards against reclaiming focus from a visitor who tabbed or
        // clicked elsewhere while a slow, download-backed pass was running.
        selectOption(event.target.closest('.blueworx-translate__option')).then(function () {
          if (document.activeElement === document.body || widget.contains(document.activeElement)) {
            state.toggle.focus();
          }
        });
        return;
      }

      if (event.key === 'Escape') {
        event.preventDefault();
        closeList();
        state.toggle.focus();
      }
    });

    list.addEventListener('click', function (event) {
      selectOption(event.target.closest('.blueworx-translate__option'));
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

      var stored = readStoredLang();
      var offered = languages.some(function (language) {
        return language.code === stored;
      });

      if (stored === '' || !offered) {
        // A language that is no longer configured, or that this browser can no
        // longer translate, must not leave the visitor stuck on a dead choice.
        clearStoredLang();
        return;
      }

      applyLanguage(stored);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
