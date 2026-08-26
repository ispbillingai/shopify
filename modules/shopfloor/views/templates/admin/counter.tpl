{**
 * The till.
 *
 * Left: find something. Right: the ticket you are building. The search box holds
 * focus throughout, because the usual input is a barcode scanner typing a code
 * and pressing enter.
 *
 * Copy comes from $L (modules/shopfloor/lang/<iso>.php), chosen by the signed-in
 * employee's language. data-lang carries the same map to counter.js for the parts
 * of the screen the browser builds.
 *}

<div class="shopfloor" id="shopfloor-counter"
     data-endpoint="{$shopfloor_link|escape:'html':'UTF-8'}&amp;token={$shopfloor_token|escape:'html':'UTF-8'}"
     data-currency="{$currency_sign|escape:'html':'UTF-8'}"
     data-lang="{$shopfloor_lang_json|escape:'html':'UTF-8'}">

    <div class="shopfloor__bar">
        <div>
            <h2 class="shopfloor__title">{$L.counter_title|escape:'html':'UTF-8'}</h2>
            <p class="shopfloor__subtitle">
                {$L.serving_as|escape:'html':'UTF-8'} {$employee_name|escape:'html':'UTF-8'}
            </p>
        </div>
        <div class="shopfloor__stat" id="shopfloor-today">
            <span class="shopfloor__stat-value">{$today.total_display nofilter}</span>
            <span class="shopfloor__stat-label">
                {$L.taken_today|escape:'html':'UTF-8'} &middot;
                <span id="shopfloor-today-count">{$today.count|intval}</span> {$L.sales|escape:'html':'UTF-8'}
            </span>
        </div>
    </div>

    <div class="shopfloor__split">

        {* ---------------------------------------------------------- find *}
        <section class="shopfloor__panel">
            <label class="shopfloor__label" for="shopfloor-search">
                {$L.scan_label|escape:'html':'UTF-8'}
            </label>
            <input type="text" id="shopfloor-search" class="shopfloor__search"
                   autocomplete="off" autofocus
                   placeholder="{$L.scan_placeholder|escape:'html':'UTF-8'}">

            <div class="shopfloor__results" id="shopfloor-results">
                <p class="shopfloor__empty">{$L.results_here|escape:'html':'UTF-8'}</p>
            </div>
        </section>

        {* -------------------------------------------------------- ticket *}
        <section class="shopfloor__panel shopfloor__panel--ticket">
            <h3 class="shopfloor__panel-title">{$L.ticket|escape:'html':'UTF-8'}</h3>

            <div class="shopfloor__ticket" id="shopfloor-ticket">
                <p class="shopfloor__empty">{$L.ticket_empty|escape:'html':'UTF-8'}</p>
            </div>

            <div class="shopfloor__total">
                <span>{$L.total|escape:'html':'UTF-8'}</span>
                <span id="shopfloor-total">{$currency_sign|escape:'html':'UTF-8'}0.00</span>
            </div>

            <div class="shopfloor__payment">
                <button type="button" class="shopfloor__pay is-selected" data-payment="cash">
                    {$L.cash|escape:'html':'UTF-8'}
                </button>
                <button type="button" class="shopfloor__pay" data-payment="card">
                    {$L.card|escape:'html':'UTF-8'}
                </button>
            </div>

            <div class="shopfloor__cash" id="shopfloor-cash">
                <label class="shopfloor__label" for="shopfloor-tendered">
                    {$L.cash_received|escape:'html':'UTF-8'}
                </label>
                <input type="text" inputmode="decimal" id="shopfloor-tendered"
                       class="shopfloor__input" placeholder="0.00">
                <p class="shopfloor__change" id="shopfloor-change"></p>
            </div>

            <button type="button" class="shopfloor__complete" id="shopfloor-complete" disabled>
                {$L.complete_sale|escape:'html':'UTF-8'}
            </button>

            <p class="shopfloor__error" id="shopfloor-error"></p>
        </section>
    </div>

    {* --------------------------------------------------------- receipt *}
    <div class="shopfloor__receipt" id="shopfloor-receipt" hidden>
        <div class="shopfloor__receipt-card">
            <h3 class="shopfloor__receipt-title">{$L.sale_completed|escape:'html':'UTF-8'}</h3>
            <p class="shopfloor__receipt-reference" id="shopfloor-receipt-reference"></p>

            <div class="shopfloor__receipt-lines" id="shopfloor-receipt-lines"></div>

            <div class="shopfloor__receipt-total">
                <span>{$L.paid|escape:'html':'UTF-8'}</span>
                <span id="shopfloor-receipt-total"></span>
            </div>
            <div class="shopfloor__receipt-change" id="shopfloor-receipt-change-row" hidden>
                <span>{$L.change|escape:'html':'UTF-8'}</span>
                <span id="shopfloor-receipt-change"></span>
            </div>

            <div class="shopfloor__receipt-actions">
                <button type="button" class="shopfloor__complete" id="shopfloor-next">
                    {$L.next_customer|escape:'html':'UTF-8'}
                </button>
                <button type="button" class="shopfloor__secondary" id="shopfloor-print">
                    {$L.print|escape:'html':'UTF-8'}
                </button>
                <a class="shopfloor__secondary" id="shopfloor-view-order" href="#" target="_blank">
                    {$L.open_order|escape:'html':'UTF-8'}
                </a>
            </div>
        </div>
    </div>
</div>
