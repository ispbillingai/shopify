/*
 * Goods-in.
 *
 * Pick something, say how many, save. Two modes that look similar and mean very
 * different things, so the button label and the field label both change with the
 * mode — "5 arrived" must never be entered where "there are 5" was meant.
 */

(function () {
    'use strict';

    var root = document.getElementById('shopfloor-warehouse');

    if (!root) {
        return;
    }

    var endpoint = root.dataset.endpoint;
    var productEditBase = root.dataset.productEdit || '';

    /*
     * Copy for the parts of the screen the browser builds. The map arrives on
     * the root element's data-lang, put there by the template, so the strings
     * come from the same lang/<iso>.php file as the server-rendered ones and
     * there is no second place to translate.
     */
    var L = (function () {
        try {
            return JSON.parse(root.dataset.lang || '{}');
        } catch (error) {
            return {};
        }
    }());

    function t(key, fallback, replace) {
        var text = L[key] || fallback;

        if (replace) {
            Object.keys(replace).forEach(function (token) {
                text = text.split(token).join(replace[token]);
            });
        }

        return text;
    }


    var searchInput = document.getElementById('shopfloor-search');
    var resultsBox = document.getElementById('shopfloor-results');
    var selectedBox = document.getElementById('shopfloor-selected');
    var selectedEmpty = document.getElementById('shopfloor-selected-empty');
    var formBox = document.getElementById('shopfloor-form');
    var quantityInput = document.getElementById('shopfloor-quantity');
    var noteInput = document.getElementById('shopfloor-note');
    var applyBtn = document.getElementById('shopfloor-apply');
    var errorOut = document.getElementById('shopfloor-error');
    var doneOut = document.getElementById('shopfloor-done');

    var selected = null;
    var mode = 'intake';
    var busy = false;
    var searchTimer = null;
    var lastResults = [];

    // ------------------------------------------------------------- helpers

    function showError(message) {
        errorOut.textContent = message || '';

        if (message) {
            doneOut.textContent = '';
        }
    }

    function showDone(message) {
        doneOut.textContent = message || '';

        if (message) {
            errorOut.textContent = '';
        }
    }

    function call(action, params, method) {
        var url = endpoint + '&ajax=1&action=' + encodeURIComponent(action);
        var options = { method: method || 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } };

        if (options.method === 'POST') {
            var body = new FormData();

            Object.keys(params).forEach(function (name) {
                body.append(name, params[name]);
            });

            options.body = body;
        } else {
            Object.keys(params).forEach(function (name) {
                url += '&' + encodeURIComponent(name) + '=' + encodeURIComponent(params[name]);
            });
        }

        return fetch(url, options).then(function (response) {
            return response.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (error) {
                    throw new Error(t('err_server', 'The server did not answer properly. Reload the page and sign in again.'));
                }
            });
        });
    }

    // -------------------------------------------------------------- search

    function renderResults(rows) {
        lastResults = rows;
        resultsBox.innerHTML = '';

        if (!rows.length) {
            resultsBox.innerHTML = '<p class="shopfloor__empty"></p>';
            resultsBox.firstChild.textContent = t('nothing_found', 'Nothing found.');

            return;
        }

        rows.forEach(function (row) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'shopfloor__result';

            var left = document.createElement('span');
            var name = document.createElement('span');
            name.className = 'shopfloor__result-name';
            name.textContent = row.label;
            left.appendChild(name);

            if (!row.active) {
                var badge = document.createElement('span');
                badge.className = 'shopfloor__badge';
                badge.textContent = t('not_online', 'not online');
                left.appendChild(badge);
            }

            var meta = document.createElement('span');
            meta.className = 'shopfloor__result-meta';
            meta.textContent = [row.variant, row.reference].filter(Boolean).join(' · ');
            left.appendChild(meta);

            var right = document.createElement('span');
            right.className = 'shopfloor__result-right';

            var stock = document.createElement('span');
            stock.className = 'shopfloor__result-stock' + (row.quantity <= 0 ? ' is-empty' : '');
            stock.textContent = row.quantity + ' ' + t('in_stock', 'in stock');
            right.appendChild(stock);

            button.appendChild(left);
            button.appendChild(right);
            button.addEventListener('click', function () {
                select(row);
            });

            resultsBox.appendChild(button);
        });
    }

    function search(term) {
        if (term.trim().length < 2) {
            lastResults = [];
            resultsBox.innerHTML = '<p class="shopfloor__empty"></p>';
            resultsBox.firstChild.textContent = t('results_here', 'Results appear here.');

            return;
        }

        call('search', { q: term })
            .then(function (data) {
                if (data.ok) {
                    renderResults(data.rows);
                }
            })
            .catch(function (error) {
                showError(error.message);
            });
    }

    // ------------------------------------------------------------ selection

    function select(row) {
        selected = row;

        document.getElementById('shopfloor-selected-name').textContent = row.label;
        document.getElementById('shopfloor-selected-meta').textContent =
            [row.variant, row.reference].filter(Boolean).join(' · ');
        document.getElementById('shopfloor-selected-stock').textContent = row.quantity;

        // Deep link into PrestaShop's product form for this exact product.
        var editLink = document.getElementById('shopfloor-edit-product');

        if (editLink && productEditBase) {
            editLink.href = productEditBase
                + (productEditBase.indexOf('?') === -1 ? '?' : '&')
                + 'id_product=' + encodeURIComponent(row.id_product)
                + '&updateproduct';
        }

        selectedBox.hidden = false;
        selectedEmpty.hidden = true;
        formBox.hidden = false;

        showError('');
        showDone('');

        quantityInput.value = mode === 'intake' ? 1 : row.quantity;
        quantityInput.focus();
        quantityInput.select();
    }

    function setMode(next) {
        mode = next;

        Array.prototype.forEach.call(root.querySelectorAll('[data-mode]'), function (button) {
            button.classList.toggle('is-selected', button.dataset.mode === next);
        });

        Array.prototype.forEach.call(root.querySelectorAll('[data-label]'), function (label) {
            label.hidden = label.dataset.label !== next;
        });

        if (selected) {
            quantityInput.value = next === 'intake' ? 1 : selected.quantity;
        }

        quantityInput.focus();
        quantityInput.select();
    }

    // --------------------------------------------------------------- save

    function apply() {
        if (busy || !selected) {
            return;
        }

        var quantity = parseInt(quantityInput.value, 10);

        if (isNaN(quantity) || quantity < 0) {
            showError(t('err_enter_quantity', 'Enter a quantity.'));

            return;
        }

        busy = true;
        applyBtn.disabled = true;
        showError('');

        call(mode === 'intake' ? 'intake' : 'correct', {
            id_product: selected.id_product,
            id_product_attribute: selected.id_product_attribute,
            quantity: quantity,
            note: noteInput.value || ''
        }, 'POST')
            .then(function (data) {
                if (!data.ok) {
                    showError(data.error);

                    return;
                }

                selected = data.row;
                document.getElementById('shopfloor-selected-stock').textContent = data.quantity_after;

                showDone(
                    selected.label + ': ' + data.quantity_before + ' → ' + data.quantity_after
                    + ' (' + (data.delta > 0 ? '+' : '') + data.delta + ')'
                );

                document.getElementById('shopfloor-units-in').textContent = '+' + data.today.units_in;
                document.getElementById('shopfloor-lines').textContent = data.today.lines;

                renderLog(data.movements);

                noteInput.value = '';
                quantityInput.value = mode === 'intake' ? 1 : data.quantity_after;
                searchInput.focus();
                searchInput.select();
            })
            .catch(function (error) {
                showError(error.message);
            })
            .then(function () {
                busy = false;
                applyBtn.disabled = false;
            });
    }

    function renderLog(movements) {
        var body = document.querySelector('#shopfloor-log tbody');

        if (!body) {
            return;
        }

        body.innerHTML = '';

        if (!movements.length) {
            var empty = document.createElement('tr');
            var cell = document.createElement('td');
            cell.colSpan = 8;
            cell.className = 'shopfloor__empty';
            cell.textContent = t('nothing_loaded', 'Nothing loaded yet.');
            empty.appendChild(cell);
            body.appendChild(empty);

            return;
        }

        movements.forEach(function (movement) {
            var row = document.createElement('tr');

            [
                movement.date_display + ' ' + movement.time_display,
                movement.product_name,
                movement.reference,
                movement.type_display || movement.type
            ].forEach(function (value) {
                var cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });

            var delta = document.createElement('td');
            delta.className = 'shopfloor__num ' + (Number(movement.delta) > 0 ? 'is-in' : 'is-out');
            delta.textContent = movement.delta_display;
            row.appendChild(delta);

            var after = document.createElement('td');
            after.className = 'shopfloor__num';
            after.textContent = movement.quantity_after;
            row.appendChild(after);

            [movement.employee_name, movement.note].forEach(function (value) {
                var cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });

            body.appendChild(row);
        });
    }

    // -------------------------------------------------------------- wiring

    searchInput.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            search(searchInput.value);
        }, 220);
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        window.clearTimeout(searchTimer);

        var term = searchInput.value.trim().toLowerCase();

        var exact = lastResults.filter(function (row) {
            return String(row.reference).toLowerCase() === term
                || String(row.ean13).toLowerCase() === term;
        });

        if (exact.length === 1) {
            select(exact[0]);
        } else if (lastResults.length === 1) {
            select(lastResults[0]);
        } else {
            search(searchInput.value);
        }
    });

    quantityInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            apply();
        }
    });

    Array.prototype.forEach.call(root.querySelectorAll('[data-mode]'), function (button) {
        button.addEventListener('click', function () {
            setMode(button.dataset.mode);
        });
    });

    applyBtn.addEventListener('click', apply);

    searchInput.focus();
}());
