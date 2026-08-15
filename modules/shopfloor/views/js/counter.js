/*
 * The till.
 *
 * Built around a barcode scanner: it types a code and presses enter. So enter on
 * a single result, or on an exact SKU match, adds straight to the ticket without
 * anyone touching the mouse, and focus returns to the search box after every
 * action.
 */

(function () {
    'use strict';

    var root = document.getElementById('shopfloor-counter');

    if (!root) {
        return;
    }

    var endpoint = root.dataset.endpoint;
    var currency = root.dataset.currency || '';

    var searchInput = document.getElementById('shopfloor-search');
    var resultsBox = document.getElementById('shopfloor-results');
    var ticketBox = document.getElementById('shopfloor-ticket');
    var totalOut = document.getElementById('shopfloor-total');
    var completeBtn = document.getElementById('shopfloor-complete');
    var errorOut = document.getElementById('shopfloor-error');
    var cashBox = document.getElementById('shopfloor-cash');
    var tenderedInput = document.getElementById('shopfloor-tendered');
    var changeOut = document.getElementById('shopfloor-change');
    var receipt = document.getElementById('shopfloor-receipt');

    var ticket = [];
    var lastResults = [];
    var payment = 'cash';
    var busy = false;
    var searchTimer = null;

    // ------------------------------------------------------------- helpers

    function money(amount) {
        return currency + Number(amount || 0).toFixed(2);
    }

    function keyOf(row) {
        return row.id_product + '-' + row.id_product_attribute;
    }

    function total() {
        return ticket.reduce(function (sum, line) {
            return sum + line.price * line.quantity;
        }, 0);
    }

    function showError(message) {
        errorOut.textContent = message || '';
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
                    // A PHP notice or an expired session lands here as HTML.
                    throw new Error('The server did not answer properly. Reload the page and sign in again.');
                }
            });
        });
    }

    // -------------------------------------------------------------- search

    function renderResults(rows) {
        lastResults = rows;
        resultsBox.innerHTML = '';

        if (!rows.length) {
            resultsBox.innerHTML = '<p class="shopfloor__empty">Nothing found.</p>';

            return;
        }

        rows.forEach(function (row) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'shopfloor__result';

            if (row.quantity <= 0) {
                button.disabled = true;
            }

            var left = document.createElement('span');
            var name = document.createElement('span');
            name.className = 'shopfloor__result-name';
            name.textContent = row.label;
            left.appendChild(name);

            if (!row.active) {
                var badge = document.createElement('span');
                badge.className = 'shopfloor__badge';
                badge.textContent = 'not online';
                left.appendChild(badge);
            }

            var meta = document.createElement('span');
            meta.className = 'shopfloor__result-meta';
            meta.textContent = [row.variant, row.reference].filter(Boolean).join(' · ');
            left.appendChild(meta);

            var right = document.createElement('span');
            right.className = 'shopfloor__result-right';

            var price = document.createElement('span');
            price.className = 'shopfloor__result-price';
            price.textContent = money(row.price);
            right.appendChild(price);

            var stock = document.createElement('span');
            stock.className = 'shopfloor__result-stock' + (row.quantity <= 0 ? ' is-empty' : '');
            stock.textContent = row.quantity > 0 ? row.quantity + ' in stock' : 'out of stock';
            right.appendChild(stock);

            button.appendChild(left);
            button.appendChild(right);
            button.addEventListener('click', function () {
                addToTicket(row);
            });

            resultsBox.appendChild(button);
        });
    }

    function search(term) {
        if (term.trim().length < 2) {
            lastResults = [];
            resultsBox.innerHTML = '<p class="shopfloor__empty">Results appear here.</p>';

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

    // -------------------------------------------------------------- ticket

    function addToTicket(row) {
        if (row.quantity <= 0) {
            showError(row.label + ' is out of stock.');

            return;
        }

        var key = keyOf(row);
        var existing = ticket.filter(function (line) {
            return keyOf(line) === key;
        })[0];

        if (existing) {
            if (existing.quantity + 1 > row.quantity) {
                showError('Only ' + row.quantity + ' of ' + row.label + ' in stock.');

                return;
            }

            existing.quantity += 1;
        } else {
            ticket.push({
                id_product: row.id_product,
                id_product_attribute: row.id_product_attribute,
                label: row.label,
                variant: row.variant,
                reference: row.reference,
                price: row.price,
                available: row.quantity,
                quantity: 1
            });
        }

        showError('');
        renderTicket();
        resetSearch();
    }

    function changeQuantity(index, delta) {
        var line = ticket[index];
        var next = line.quantity + delta;

        if (next > line.available) {
            showError('Only ' + line.available + ' of ' + line.label + ' in stock.');

            return;
        }

        if (next <= 0) {
            ticket.splice(index, 1);
        } else {
            line.quantity = next;
        }

        showError('');
        renderTicket();
    }

    function renderTicket() {
        ticketBox.innerHTML = '';

        if (!ticket.length) {
            ticketBox.innerHTML = '<p class="shopfloor__empty">Nothing on the ticket yet.</p>';
        }

        ticket.forEach(function (line, index) {
            var wrapper = document.createElement('div');
            wrapper.className = 'shopfloor__line';

            var name = document.createElement('span');
            name.className = 'shopfloor__line-name';
            name.textContent = line.label;

            var meta = document.createElement('span');
            meta.className = 'shopfloor__line-meta';
            meta.textContent = [line.variant, line.reference, money(line.price)].filter(Boolean).join(' · ');
            name.appendChild(meta);

            var quantity = document.createElement('span');
            quantity.className = 'shopfloor__qty';

            var minus = document.createElement('button');
            minus.type = 'button';
            minus.textContent = '−';
            minus.addEventListener('click', function () {
                changeQuantity(index, -1);
            });

            var count = document.createElement('span');
            count.textContent = line.quantity;

            var plus = document.createElement('button');
            plus.type = 'button';
            plus.textContent = '+';
            plus.addEventListener('click', function () {
                changeQuantity(index, 1);
            });

            quantity.appendChild(minus);
            quantity.appendChild(count);
            quantity.appendChild(plus);

            var lineTotal = document.createElement('span');
            lineTotal.className = 'shopfloor__line-total';
            lineTotal.textContent = money(line.price * line.quantity);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'shopfloor__remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove');
            remove.addEventListener('click', function () {
                ticket.splice(index, 1);
                renderTicket();
            });

            wrapper.appendChild(name);
            wrapper.appendChild(quantity);
            wrapper.appendChild(lineTotal);
            wrapper.appendChild(remove);

            ticketBox.appendChild(wrapper);
        });

        totalOut.textContent = money(total());
        completeBtn.disabled = ticket.length === 0 || busy;
        updateChange();
    }

    function resetSearch() {
        searchInput.value = '';
        searchInput.focus();
        lastResults = [];
        resultsBox.innerHTML = '<p class="shopfloor__empty">Results appear here.</p>';
    }

    // ------------------------------------------------------------- payment

    function updateChange() {
        if (payment !== 'cash') {
            changeOut.textContent = '';

            return;
        }

        var tendered = parseFloat(String(tenderedInput.value).replace(',', '.'));

        if (isNaN(tendered) || tendered <= 0) {
            changeOut.textContent = '';

            return;
        }

        var due = total();

        changeOut.textContent = tendered >= due
            ? 'Change ' + money(tendered - due)
            : 'Short by ' + money(due - tendered);
    }

    // ------------------------------------------------------------ checkout

    function checkout() {
        if (busy || !ticket.length) {
            return;
        }

        busy = true;
        completeBtn.disabled = true;
        showError('');

        call('checkout', {
            lines: JSON.stringify(ticket.map(function (line) {
                return {
                    id_product: line.id_product,
                    id_product_attribute: line.id_product_attribute,
                    quantity: line.quantity
                };
            })),
            payment: payment,
            tendered: tenderedInput.value || '0'
        }, 'POST')
            .then(function (data) {
                if (!data.ok) {
                    showError(data.error);

                    return;
                }

                showReceipt(data);
            })
            .catch(function (error) {
                showError(error.message);
            })
            .then(function () {
                busy = false;
                completeBtn.disabled = ticket.length === 0;
            });
    }

    function showReceipt(data) {
        document.getElementById('shopfloor-receipt-reference').textContent =
            'Order ' + data.reference;

        var lines = document.getElementById('shopfloor-receipt-lines');
        lines.innerHTML = '';

        ticket.forEach(function (line) {
            var row = document.createElement('div');
            row.className = 'shopfloor__receipt-line';

            var left = document.createElement('span');
            left.textContent = line.quantity + ' × ' + line.label;

            var right = document.createElement('span');
            right.textContent = money(line.price * line.quantity);

            row.appendChild(left);
            row.appendChild(right);
            lines.appendChild(row);
        });

        document.getElementById('shopfloor-receipt-total').textContent = data.total_display;

        var changeRow = document.getElementById('shopfloor-receipt-change-row');

        if (data.change > 0) {
            document.getElementById('shopfloor-receipt-change').textContent = data.change_display;
            changeRow.hidden = false;
        } else {
            changeRow.hidden = true;
        }

        document.getElementById('shopfloor-view-order').href = data.order_link;
        document.getElementById('shopfloor-today').querySelector('.shopfloor__stat-value')
            .textContent = data.today.total_display;
        document.getElementById('shopfloor-today-count').textContent = data.today.count;

        receipt.hidden = false;
    }

    function nextCustomer() {
        ticket = [];
        tenderedInput.value = '';
        receipt.hidden = true;
        renderTicket();
        resetSearch();
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

        // A scan is an exact code. Take the exact match if there is one, otherwise
        // a lone result; anything more ambiguous stays on screen for a human to pick.
        var exact = lastResults.filter(function (row) {
            return String(row.reference).toLowerCase() === term
                || String(row.ean13).toLowerCase() === term;
        });

        if (exact.length === 1) {
            addToTicket(exact[0]);
        } else if (lastResults.length === 1) {
            addToTicket(lastResults[0]);
        } else {
            search(searchInput.value);
        }
    });

    Array.prototype.forEach.call(root.querySelectorAll('.shopfloor__pay'), function (button) {
        button.addEventListener('click', function () {
            Array.prototype.forEach.call(root.querySelectorAll('.shopfloor__pay'), function (other) {
                other.classList.remove('is-selected');
            });

            button.classList.add('is-selected');
            payment = button.dataset.payment;
            cashBox.style.display = payment === 'cash' ? '' : 'none';
            updateChange();
            searchInput.focus();
        });
    });

    tenderedInput.addEventListener('input', updateChange);
    completeBtn.addEventListener('click', checkout);
    document.getElementById('shopfloor-next').addEventListener('click', nextCustomer);
    document.getElementById('shopfloor-print').addEventListener('click', function () {
        window.print();
    });

    renderTicket();
    searchInput.focus();
}());
