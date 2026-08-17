/*
 * Storefront rail behaviour.
 *
 * Three small jobs, all of them progressive: the controls in the left rail ship
 * hidden and only reveal themselves once this script has confirmed there is
 * something for them to act on. A listing page with no filter module, or a
 * content page with no product grid, therefore shows no dead controls.
 *
 *   1. Column density — the "VIEW 1 2 3" switch, remembered between visits.
 *   2. Filters — reveals the faceted-search panel, if that module is installed.
 *   3. Active category — marks the entry matching the current page.
 */

(function () {
  'use strict';

  var STORE_KEY = 'stz-cols';
  var DEFAULT_COLS = '2';

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  function remembered() {
    try {
      return window.localStorage.getItem(STORE_KEY) || DEFAULT_COLS;
    } catch (error) {
      return DEFAULT_COLS;
    }
  }

  function remember(value) {
    try {
      window.localStorage.setItem(STORE_KEY, value);
    } catch (error) {
      /* Private browsing: the choice simply does not persist. */
    }
  }

  function columnSwitch() {
    var grid = document.querySelector('.products');
    var control = document.querySelector('.js-zr-view');

    if (!grid || !control) {
      return;
    }

    var buttons = control.querySelectorAll('[data-cols]');

    function apply(cols) {
      grid.classList.remove('zr-cols-1', 'zr-cols-2', 'zr-cols-3');
      grid.classList.add('zr-cols-' + cols);

      Array.prototype.forEach.call(buttons, function (button) {
        button.classList.toggle('is-active', button.dataset.cols === cols);
      });
    }

    Array.prototype.forEach.call(buttons, function (button) {
      button.addEventListener('click', function () {
        apply(button.dataset.cols);
        remember(button.dataset.cols);
      });
    });

    apply(remembered());
    control.hidden = false;
  }

  function filterToggle() {
    var button = document.querySelector('.js-zr-filters');

    if (!button) {
      return;
    }

    var panel = document.querySelector(
      '#search_filters, #search_filters_wrapper, .js-search-filters, #js-search-filters'
    );

    if (!panel) {
      return;
    }

    panel.classList.add('zr-filters-panel');

    button.addEventListener('click', function () {
      var open = panel.classList.toggle('is-open');
      button.classList.toggle('is-active', open);
    });

    button.hidden = false;
  }

  function markActiveCategory() {
    var here = window.location.href;
    var items = document.querySelectorAll('.zr-nav li');

    Array.prototype.forEach.call(items, function (item) {
      var link = item.querySelector('a');

      if (link && link.href && here.indexOf(link.href) === 0) {
        item.classList.add('is-active');
      }
    });
  }

  ready(function () {
    columnSwitch();
    filterToggle();
    markActiveCategory();
  });
}());
