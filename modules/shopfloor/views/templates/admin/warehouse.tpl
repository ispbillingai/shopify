{*
 * Goods-in.
 *
 * Same search box as the till, because it is the same scanner. What changes is
 * what happens after you pick something: you say how many arrived, or you declare
 * what is really on the shelf.
 *}

<div class="shopfloor" id="shopfloor-warehouse"
     data-endpoint="{$shopfloor_link|escape:'html':'UTF-8'}&amp;token={$shopfloor_token|escape:'html':'UTF-8'}">

    <div class="shopfloor__bar">
        <div>
            <h2 class="shopfloor__title">{l s='Warehouse' mod='shopfloor'}</h2>
            <p class="shopfloor__subtitle">
                {l s='Loading as' mod='shopfloor'} {$employee_name|escape:'html':'UTF-8'}
            </p>
        </div>
        <div class="shopfloor__stat">
            <span class="shopfloor__stat-value" id="shopfloor-units-in">+{$today.units_in|intval}</span>
            <span class="shopfloor__stat-label">
                {l s='units loaded today' mod='shopfloor'} &middot;
                <span id="shopfloor-lines">{$today.lines|intval}</span> {l s='movements' mod='shopfloor'}
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

        {* --------------------------------------------------------- load *}
        <section class="shopfloor__panel shopfloor__panel--ticket">
            <h3 class="shopfloor__panel-title">{l s='Load goods' mod='shopfloor'}</h3>

            <div id="shopfloor-selected" class="shopfloor__selected" hidden>
                <p class="shopfloor__selected-name" id="shopfloor-selected-name"></p>
                <p class="shopfloor__selected-meta" id="shopfloor-selected-meta"></p>
                <p class="shopfloor__selected-stock">
                    {l s='In stock now' mod='shopfloor'}
                    <strong id="shopfloor-selected-stock">0</strong>
                </p>
            </div>

            <p class="shopfloor__empty" id="shopfloor-selected-empty">
                {l s='Pick a product on the left.' mod='shopfloor'}
            </p>

            <div id="shopfloor-form" hidden>
                <div class="shopfloor__mode">
                    <button type="button" class="shopfloor__pay is-selected" data-mode="intake">
                        {l s='Goods arrived' mod='shopfloor'}
                    </button>
                    <button type="button" class="shopfloor__pay" data-mode="correct">
                        {l s='Stock take' mod='shopfloor'}
                    </button>
                </div>

                <label class="shopfloor__label" for="shopfloor-quantity">
                    <span data-label="intake">{l s='How many arrived' mod='shopfloor'}</span>
                    <span data-label="correct" hidden>{l s='How many are actually on the shelf' mod='shopfloor'}</span>
                </label>
                <input type="number" id="shopfloor-quantity" class="shopfloor__input"
                       min="0" step="1" value="1">

                <label class="shopfloor__label" for="shopfloor-note">
                    {l s='Note (delivery number, supplier, reason…)' mod='shopfloor'}
                </label>
                <input type="text" id="shopfloor-note" class="shopfloor__input" maxlength="500">

                <button type="button" class="shopfloor__complete" id="shopfloor-apply">
                    {l s='Save' mod='shopfloor'}
                </button>
            </div>

            <p class="shopfloor__error" id="shopfloor-error"></p>
            <p class="shopfloor__done" id="shopfloor-done"></p>
        </section>
    </div>

    {* ------------------------------------------------------------ log *}
    <section class="shopfloor__panel shopfloor__panel--log">
        <h3 class="shopfloor__panel-title">
            {l s='Recent movements' mod='shopfloor'}
            <a class="shopfloor__link" href="{$stock_link|escape:'html':'UTF-8'}">
                {l s='Full stock page' mod='shopfloor'}
            </a>
        </h3>

        <table class="shopfloor__table" id="shopfloor-log">
            <thead>
                <tr>
                    <th>{l s='When' mod='shopfloor'}</th>
                    <th>{l s='Product' mod='shopfloor'}</th>
                    <th>{l s='SKU' mod='shopfloor'}</th>
                    <th>{l s='Type' mod='shopfloor'}</th>
                    <th class="shopfloor__num">{l s='Change' mod='shopfloor'}</th>
                    <th class="shopfloor__num">{l s='After' mod='shopfloor'}</th>
                    <th>{l s='Who' mod='shopfloor'}</th>
                    <th>{l s='Note' mod='shopfloor'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$movements item=movement}
                    <tr>
                        <td>{$movement.date_display|escape:'html':'UTF-8'} {$movement.time_display|escape:'html':'UTF-8'}</td>
                        <td>{$movement.product_name|escape:'html':'UTF-8'}</td>
                        <td>{$movement.reference|escape:'html':'UTF-8'}</td>
                        <td>{$movement.type|escape:'html':'UTF-8'}</td>
                        <td class="shopfloor__num {if $movement.delta > 0}is-in{else}is-out{/if}">
                            {$movement.delta_display|escape:'html':'UTF-8'}
                        </td>
                        <td class="shopfloor__num">{$movement.quantity_after|intval}</td>
                        <td>{$movement.employee_name|escape:'html':'UTF-8'}</td>
                        <td>{$movement.note|escape:'html':'UTF-8'}</td>
                    </tr>
                {foreachelse}
                    <tr><td colspan="8" class="shopfloor__empty">
                        {l s='Nothing loaded yet.' mod='shopfloor'}
                    </td></tr>
                {/foreach}
            </tbody>
        </table>
    </section>
</div>
