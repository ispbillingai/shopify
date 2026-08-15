{*
 * The till.
 *
 * Left: find something. Right: the ticket you are building. The search box holds
 * focus throughout, because the usual input is a barcode scanner typing a code
 * and pressing enter.
 *}

<div class="shopfloor" id="shopfloor-counter"
     data-endpoint="{$shopfloor_link|escape:'html':'UTF-8'}&amp;token={$shopfloor_token|escape:'html':'UTF-8'}"
     data-currency="{$currency_sign|escape:'html':'UTF-8'}">

    <div class="shopfloor__bar">
        <div>
            <h2 class="shopfloor__title">{l s='Counter sales' mod='shopfloor'}</h2>
            <p class="shopfloor__subtitle">
                {l s='Serving as' mod='shopfloor'} {$employee_name|escape:'html':'UTF-8'}
            </p>
        </div>
        <div class="shopfloor__stat" id="shopfloor-today">
            <span class="shopfloor__stat-value">{$today.total_display nofilter}</span>
            <span class="shopfloor__stat-label">
                {l s='taken today' mod='shopfloor'} &middot;
                <span id="shopfloor-today-count">{$today.count|intval}</span> {l s='sales' mod='shopfloor'}
            </span>
        </div>
    </div>

    <div class="shopfloor__split">

        {* ---------------------------------------------------------- find *}
        <section class="shopfloor__panel">
            <label class="shopfloor__label" for="shopfloor-search">
                {l s='Scan a barcode, or type a SKU or product name' mod='shopfloor'}
            </label>
            <input type="text" id="shopfloor-search" class="shopfloor__search"
                   autocomplete="off" autofocus
                   placeholder="{l s='Scan or search…' mod='shopfloor'}">

            <div class="shopfloor__results" id="shopfloor-results">
                <p class="shopfloor__empty">{l s='Results appear here.' mod='shopfloor'}</p>
            </div>
        </section>

        {* -------------------------------------------------------- ticket *}
        <section class="shopfloor__panel shopfloor__panel--ticket">
            <h3 class="shopfloor__panel-title">{l s='Ticket' mod='shopfloor'}</h3>

            <div class="shopfloor__ticket" id="shopfloor-ticket">
                <p class="shopfloor__empty">{l s='Nothing on the ticket yet.' mod='shopfloor'}</p>
            </div>

            <div class="shopfloor__total">
                <span>{l s='Total' mod='shopfloor'}</span>
                <span id="shopfloor-total">{$currency_sign|escape:'html':'UTF-8'}0.00</span>
            </div>

            <div class="shopfloor__payment">
                <button type="button" class="shopfloor__pay is-selected" data-payment="cash">
                    {l s='Cash' mod='shopfloor'}
                </button>
                <button type="button" class="shopfloor__pay" data-payment="card">
                    {l s='Card' mod='shopfloor'}
                </button>
            </div>

            <div class="shopfloor__cash" id="shopfloor-cash">
                <label class="shopfloor__label" for="shopfloor-tendered">
                    {l s='Cash received' mod='shopfloor'}
                </label>
                <input type="text" inputmode="decimal" id="shopfloor-tendered"
                       class="shopfloor__input" placeholder="0.00">
                <p class="shopfloor__change" id="shopfloor-change"></p>
            </div>

            <button type="button" class="shopfloor__complete" id="shopfloor-complete" disabled>
                {l s='Complete sale' mod='shopfloor'}
            </button>

            <p class="shopfloor__error" id="shopfloor-error"></p>
        </section>
    </div>

    {* --------------------------------------------------------- receipt *}
    <div class="shopfloor__receipt" id="shopfloor-receipt" hidden>
        <div class="shopfloor__receipt-card">
            <h3 class="shopfloor__receipt-title">{l s='Sale completed' mod='shopfloor'}</h3>
            <p class="shopfloor__receipt-reference" id="shopfloor-receipt-reference"></p>

            <div class="shopfloor__receipt-lines" id="shopfloor-receipt-lines"></div>

            <div class="shopfloor__receipt-total">
                <span>{l s='Paid' mod='shopfloor'}</span>
                <span id="shopfloor-receipt-total"></span>
            </div>
            <div class="shopfloor__receipt-change" id="shopfloor-receipt-change-row" hidden>
                <span>{l s='Change' mod='shopfloor'}</span>
                <span id="shopfloor-receipt-change"></span>
            </div>

            <div class="shopfloor__receipt-actions">
                <button type="button" class="shopfloor__complete" id="shopfloor-next">
                    {l s='Next customer' mod='shopfloor'}
                </button>
                <button type="button" class="shopfloor__secondary" id="shopfloor-print">
                    {l s='Print' mod='shopfloor'}
                </button>
                <a class="shopfloor__secondary" id="shopfloor-view-order" href="#" target="_blank">
                    {l s='Open order' mod='shopfloor'}
                </a>
            </div>
        </div>
    </div>
</div>
