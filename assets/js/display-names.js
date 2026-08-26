/**
 * Friendlier plugin names in the headings on a plugin's own screens.
 *
 * The sidebar, the Plugins screen and the browser tab are all renamed in PHP.
 * These plugins draw their own screens in the browser after WordPress has
 * finished with the page, so a heading inside one is the only place with no
 * filter to hang the rename off, and the only reason this file exists.
 *
 * It is loaded on those plugins' pages alone, only ever rewrites text inside a
 * heading, and only where the old name stands as a word of its own. Nothing it
 * changes is saved anywhere: switch the feature off and the original name is
 * back on the next page load.
 */
(function () {
  'use strict';

  var HEADINGS = 'h1, h2, .wp-heading-inline';
  var pairs = Array.isArray(window.blueworxDisplayNames) ? window.blueworxDisplayNames : [];

  if (!pairs.length) {
    return;
  }

  /**
   * Escapes a name for use inside a regular expression.
   *
   * @param {string} text The name.
   * @return {string} The name, with anything meaningful to a regex quoted.
   */
  function quote(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  var rules = pairs.map(function (pair) {
    return { find: new RegExp('\\b' + quote(pair[0]) + '\\b', 'g'), replace: pair[1] };
  });

  /**
   * Rewrites one piece of text.
   *
   * @param {string} text The text as rendered.
   * @return {string} The text, renamed where it matched.
   */
  function rename(text) {
    return rules.reduce(function (carry, rule) {
      return carry.replace(rule.find, rule.replace);
    }, text);
  }

  /**
   * Renames every heading inside a subtree.
   *
   * Only text nodes are touched, so a heading carrying a link, a count or an
   * icon keeps all of it — the name is the only thing that changes.
   *
   * @param {Element} root Where to look.
   */
  function apply(root) {
    var headings = root.querySelectorAll(HEADINGS);

    Array.prototype.forEach.call(headings, function (heading) {
      var walker = document.createTreeWalker(heading, NodeFilter.SHOW_TEXT, null);
      var node;

      while (walker.nextNode()) {
        node = walker.currentNode;

        var renamed = rename(node.nodeValue);

        if (renamed !== node.nodeValue) {
          node.nodeValue = renamed;
        }
      }
    });
  }

  /**
   * Starts watching, once there is a body to watch.
   */
  function start() {
    apply(document.body);

    // These screens render, re-render and navigate without reloading, so a
    // single pass would catch whichever heading happened to exist first. The
    // work is batched to a frame so a busy render costs one pass, not hundreds.
    var queued = false;

    new MutationObserver(function () {
      if (queued) {
        return;
      }

      queued = true;

      window.requestAnimationFrame(function () {
        queued = false;
        apply(document.body);
      });
    }).observe(document.body, { childList: true, subtree: true, characterData: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
