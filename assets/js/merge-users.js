/**
 * Merge users: search as you type, and compare without leaving the page.
 *
 * The screen works without this file — the search box is a form and each
 * result a link. This fetches the same HTML the server draws for those and
 * swaps it in, so nothing reloads between typing a name and picking which
 * account to keep. If a request fails, the plain form and links take over.
 */
(function () {
  'use strict';

  var config = window.blueworxMergeUsers;
  var form = document.querySelector('[data-blueworx-merge-search]');
  var results = document.getElementById('blueworx-merge-users-results');
  var compare = document.getElementById('blueworx-merge-users-compare');

  if (!config || !form || !results || !compare || !window.fetch) {
    return;
  }

  var input = form.querySelector('input[name="q"]');
  var user = form.querySelector('input[name="user"]').value;
  var picked = new URLSearchParams(window.location.search).get('other') || '';
  var timer = null;
  var latest = 0;
  var broken = false;

  /**
   * Fetches one part of the screen.
   *
   * @param {string} part   'results' or 'compare'.
   * @param {Object} params Extra query arguments.
   * @return {Promise<string>} The part's HTML.
   */
  function load(part, params) {
    var query = new URLSearchParams(params);
    query.set('action', 'blueworx_merge_users');
    query.set('nonce', config.nonce);
    query.set('part', part);
    query.set('user', user);

    return fetch(config.ajax + '?' + query.toString(), { credentials: 'same-origin' })
      .then(function (response) {
        return response.json();
      })
      .then(function (body) {
        if (!body || !body.success) {
          throw new Error('failed');
        }

        return body.data.html;
      });
  }

  /**
   * Keeps the address in step, so a reload or Back lands on the same view.
   *
   * @return {void}
   */
  function remember() {
    var url = new URL(window.location.href);
    var text = input.value.trim();

    if (text) {
      url.searchParams.set('q', text);
    } else {
      url.searchParams.delete('q');
    }

    if (picked) {
      url.searchParams.set('other', picked);
    } else {
      url.searchParams.delete('other');
    }

    window.history.replaceState(null, '', url.toString());
  }

  /**
   * Runs the search and shows what it finds. Only the newest answer is shown,
   * so a slow reply to an earlier keystroke never overwrites a later one.
   *
   * @return {void}
   */
  function search() {
    var ticket = ++latest;

    results.setAttribute('aria-busy', 'true');

    load('results', { q: input.value.trim(), other: picked })
      .then(function (html) {
        if (ticket === latest) {
          results.innerHTML = html;
          remember();
        }
      })
      .catch(function () {
        if (ticket === latest) {
          broken = true;
          var note = document.createElement('p');
          note.className = 'bw-field__help';
          note.textContent = config.failed;
          results.replaceChildren(note);
        }
      })
      .then(function () {
        if (ticket === latest) {
          results.removeAttribute('aria-busy');
        }
      });
  }

  // Somebody quick can type before this file has loaded on a slow page. The
  // list on screen is for whatever the address says, so if the box holds
  // something else by now, catch up.
  if (input.value.trim() !== (new URLSearchParams(window.location.search).get('q') || '').trim()) {
    search();
  }

  input.addEventListener('input', function () {
    window.clearTimeout(timer);
    timer = window.setTimeout(search, 250);
  });

  form.addEventListener('submit', function (event) {
    // Once a request has failed, Enter goes back to being a normal search.
    if (broken) {
      return;
    }

    event.preventDefault();
    window.clearTimeout(timer);
    search();
  });

  results.addEventListener('click', function (event) {
    var link = event.target.closest('[data-blueworx-merge-other]');

    // A modified click is somebody opening it in a new tab; leave that alone.
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    event.preventDefault();

    var other = link.getAttribute('data-blueworx-merge-other');

    load('compare', { other: other, q: input.value.trim() })
      .then(function (html) {
        picked = other;
        compare.innerHTML = html;
        remember();
        // Redraw the list so the picked row says so.
        search();

        var pair = compare.querySelector('.blueworx-merge-users__pair');
        if (pair) {
          pair.focus();
        }
      })
      .catch(function () {
        window.location.href = link.href;
      });
  });
})();
