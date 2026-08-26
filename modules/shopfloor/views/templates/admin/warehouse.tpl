{**
 * Goods-in.
 *
 * Same search box as the till, because it is the same scanner. What changes is
 * what happens after you pick something: you say how many arrived, or you declare
 * what is really on the shelf.
 *
 * Copy comes from $L (modules/shopfloor/lang/<iso>.php), chosen by the signed-in
 * employee's language.
 *}

<div class="shopfloor" id="shopfloor-warehouse"
     data-endpoint="{$shopfloor_link|escape:'html':'UTF-8'}&amp;token={$shopfloor_token|escape:'html':'UTF-8'}"
     data-lang="{$shopfloor_lang_json|escape:'html':'UTF-8'}">

    <div class="shopfloor__bar">
        <div>
            <h2 class="shopfloor__title">{$L.warehouse_title|escape:'html':'UTF-8'}</h2>
            <p class="shopfloor__subtitle">
                {$L.loading_as|escape:'html':'UTF-8'} {$employee_name|escape:'html':'UTF-8'}
            </p>
        </div>
        <div class="shopfloor__stat">
            <span class="shopfloor__stat-value" id="shopfloor-units-in">+{$today.units_in|intval}</span>
            <span class="shopfloor__stat-label">
                {$L.units_loaded_today|escape:'html':'UTF-8'} &middot;
                <span id="shopfloor-lines">{$today.lines|intval}</span> {$L.movements|escape:'html':'UTF-8'}
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

        {* --------------------------------------------------------- load *}
        <section class="shopfloor__panel shopfloor__panel--ticket">
            <h3 class="shopfloor__panel-title">{$L.load_goods|escape:'html':'UTF-8'}</h3>

            <div id="shopfloor-selected" class="shopfloor__selected" hidden>
                <p class="shopfloor__selected-name" id="shopfloor-selected-name"></p>
                <p class="shopfloor__selected-meta" id="shopfloor-selected-meta"></p>
                <p class="shopfloor__selected-stock">
                    {$L.in_stock_now|escape:'html':'UTF-8'}
                    <strong id="shopfloor-selected-stock">0</strong>
                </p>
            </div>

            <p class="shopfloor__empty" id="shopfloor-selected-empty">
                {$L.pick_product_left|escape:'html':'UTF-8'}
            </p>

            <div id="shopfloor-form" hidden>
                <div class="shopfloor__mode">
                    <button type="button" class="shopfloor__pay is-selected" data-mode="intake">
                        {$L.goods_arrived|escape:'html':'UTF-8'}
                    </button>
                    <button type="button" class="shopfloor__pay" data-mode="correct">
                        {$L.stock_take|escape:'html':'UTF-8'}
                    </button>
                </div>

                <label class="shopfloor__label" for="shopfloor-quantity">
                    <span data-label="intake">{$L.how_many_arrived|escape:'html':'UTF-8'}</span>
                    <span data-label="correct" hidden>{$L.how_many_shelf|escape:'html':'UTF-8'}</span>
                </label>
                <input type="number" id="shopfloor-quantity" class="shopfloor__input"
                       min="0" step="1" value="1">

                <label class="shopfloor__label" for="shopfloor-note">
                    {$L.note_label|escape:'html':'UTF-8'}
                </label>
                <input type="text" id="shopfloor-note" class="shopfloor__input" maxlength="500">

                <button type="button" class="shopfloor__complete" id="shopfloor-apply">
                    {$L.save|escape:'html':'UTF-8'}
                </button>
            </div>

            <p class="shopfloor__error" id="shopfloor-error"></p>
            <p class="shopfloor__done" id="shopfloor-done"></p>
        </section>
    </div>

    {* ------------------------------------------------------------ log *}
    <section class="shopfloor__panel shopfloor__panel--log">
        <h3 class="shopfloor__panel-title">
            {$L.recent_movements|escape:'html':'UTF-8'}
            <a class="shopfloor__link" href="{$stock_link|escape:'html':'UTF-8'}">
                {$L.full_stock_page|escape:'html':'UTF-8'}
            </a>
        </h3>

        <table class="shopfloor__table" id="shopfloor-log">
            <thead>
                <tr>
                    <th>{$L.col_when|escape:'html':'UTF-8'}</th>
                    <th>{$L.col_product|escape:'html':'UTF-8'}</th>
                    <th>{$L.col_sku|escape:'html':'UTF-8'}</th>
                    <th>{$L.col_type|escape:'html':'UTF-8'}</th>
                    <th class="shopfloor__num">{$L.col_change|escape:'html':'UTF-8'}</th>
                    <th class="shopfloor__num">{$L.col_after|escape:'html':'UTF-8'}</th>
                    <th>{$L.col_who|escape:'html':'UTF-8'}</th>
                    <th>{$L.col_note|escape:'html':'UTF-8'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$movements item=movement}
                    <tr>
                        <td>{$movement.date_display|escape:'html':'UTF-8'} {$movement.time_display|escape:'html':'UTF-8'}</td>
                        <td>{$movement.product_name|escape:'html':'UTF-8'}</td>
                        <td>{$movement.reference|escape:'html':'UTF-8'}</td>
                        <td>{$movement.type_display|escape:'html':'UTF-8'}</td>
                        <td class="shopfloor__num {if $movement.delta > 0}is-in{else}is-out{/if}">
                            {$movement.delta_display|escape:'html':'UTF-8'}
                        </td>
                        <td class="shopfloor__num">{$movement.quantity_after|intval}</td>
                        <td>{$movement.employee_name|escape:'html':'UTF-8'}</td>
                        <td>{$movement.note|escape:'html':'UTF-8'}</td>
                    </tr>
                {foreachelse}
                    <tr><td colspan="8" class="shopfloor__empty">
                        {$L.nothing_loaded|escape:'html':'UTF-8'}
                    </td></tr>
                {/foreach}
            </tbody>
        </table>
    </section>
</div>
