/**
 * Multi-role selection on the add-user and edit-user screens.
 *
 * Replaces the native single <select name="role"> with a checkbox list built
 * from that same select's options, so the choices offered are exactly the roles
 * WordPress was already willing to grant — this file never invents one.
 *
 * If anything here fails the native select is left untouched and keeps working
 * the core way, which is why the select is only disabled once the replacement
 * is in the document.
 */
(function () {
  'use strict';

  var config = window.blueworxUserRoles;

  /**
   * Builds one checkbox row.
   *
   * @param {string}  value    Role slug.
   * @param {string}  label    Role display name.
   * @param {boolean} checked  Whether the user already holds this role.
   * @return {HTMLElement} List item ready to append.
   */
  function buildRow(value, label, checked) {
    var item = document.createElement('li');
    item.className = 'blueworx-role-choice';

    var field = document.createElement('label');

    var input = document.createElement('input');
    input.type = 'checkbox';
    input.name = config.field + '[]';
    input.value = value;
    input.checked = checked;

    var text = document.createElement('span');
    text.textContent = label;

    field.appendChild(input);
    field.appendChild(text);
    item.appendChild(field);

    return item;
  }

  /**
   * Swaps the Role dropdown for a checkbox list.
   */
  function replaceRoleSelect() {
    var select = document.getElementById('role');

    if (!select || !config || select.getAttribute('data-blueworx-roles') === 'done') {
      return;
    }

    var selected = Array.isArray(config.selected) ? config.selected : [];

    // On the add-user screen there is nothing saved yet, so the select's own
    // current value — the site's default role — is what should start ticked.
    if (selected.length === 0 && select.value) {
      selected = [select.value];
    }

    var choices = [];

    for (var i = 0; i < select.options.length; i += 1) {
      var option = select.options[i];

      // The empty option is core's "— No role for this site —". As a checkbox
      // it would be a box meaning "no boxes", so it is dropped and the same
      // outcome is reached by clearing every box; the hint below says so.
      if (option.value === '') {
        continue;
      }

      choices.push({ value: option.value, label: option.text });
    }

    if (choices.length === 0) {
      return;
    }

    // Sorted here as well as server-side, and that is not belt and braces:
    // wp_dropdown_roles() prints array_reverse( get_editable_roles() ), so the
    // options arrive in the exact opposite of the order the editable_roles
    // filter put them in. Sorting the rendered control is the only place that
    // survives that reversal.
    choices.sort(function (a, b) {
      return a.label.localeCompare(b.label);
    });

    var list = document.createElement('ul');
    list.className = 'blueworx-role-choices';
    list.id = 'blueworx-user-roles';

    choices.forEach(function (choice) {
      list.appendChild(buildRow(choice.value, choice.label, selected.indexOf(choice.value) !== -1));
    });

    var help = document.createElement('p');
    help.className = 'description blueworx-role-help';
    help.textContent = config.help;

    var wrapper = document.createElement('div');
    wrapper.appendChild(list);
    wrapper.appendChild(help);

    select.parentNode.insertBefore(wrapper, select);

    // Disabled rather than removed: a disabled control is not submitted, so
    // edit_user() sees no 'role' and leaves the roles to the save handler —
    // while the element itself stays in the DOM for any other script (core's
    // own, or a plugin's) that expects to find #role there.
    select.disabled = true;
    select.style.display = 'none';
    select.setAttribute('data-blueworx-roles', 'done');

    // The row's <label for="role"> would move focus to a hidden control.
    var caption = document.querySelector('label[for="role"]');

    if (caption) {
      caption.removeAttribute('for');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', replaceRoleSelect);
  } else {
    replaceRoleSelect();
  }
})();
