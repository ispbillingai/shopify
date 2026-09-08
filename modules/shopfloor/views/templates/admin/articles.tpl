{**
 * Warehouse articles, from the gestionale.
 *
 * Same shape as the Magazzino screen so the two feel like one place to work,
 * but backed by the article table rather than shop stock. The header carries
 * the figures somebody actually asks about: how many articles, how many are on
 * a shelf, and what the stock is worth.
 *}

<div class="shopfloor" id="shopfloor-articles"
     data-endpoint="{$shopfloor_link|escape:'html':'UTF-8'}&amp;token={$shopfloor_token|escape:'html':'UTF-8'}"
     data-lang="{$shopfloor_lang_json|escape:'html':'UTF-8'}">

    <div class="shopfloor__bar">
        <div>
            <h2 class="shopfloor__title">{$L.articles_title|escape:'html':'UTF-8'}</h2>
            <p class="shopfloor__subtitle">
                {$L.loading_as|escape:'html':'UTF-8'} {$employee_name|escape:'html':'UTF-8'}
            </p>
        </div>

        <div class="shopfloor__bar-right">
            <div class="shopfloor__stat">
                <span class="shopfloor__stat-value" id="shopfloor-art-count">{$summary.articles|intval}</span>
                <span class="shopfloor__stat-label">
                    {$L.articles_total|escape:'html':'UTF-8'} &middot;
                    <span id="shopfloor-art-instock">{$summary.in_stock|intval}</span>
                    {$L.articles_in_stock|escape:'html':'UTF-8'}
                </span>
            </div>
            <div class="shopfloor__stat">
                <span class="shopfloor__stat-value" id="shopfloor-art-value">{$value_display nofilter}</span>
                <span class="shopfloor__stat-label">
                    {$L.stock_value|escape:'html':'UTF-8'} &middot;
                    {$summary.locations|intval} {$L.locations|escape:'html':'UTF-8'}
                </span>
            </div>
        </div>
    </div>

    <div class="shopfloor__split">

        {* ---------------------------------------------------------- find *}
        <section class="shopfloor__panel">
            <label class="shopfloor__label" for="shopfloor-search">
                {$L.scan_label_article|escape:'html':'UTF-8'}
            </label>
            <input type="text" id="shopfloor-search" class="shopfloor__search"
                   autocomplete="off" autofocus
                   placeholder="{$L.scan_placeholder|escape:'html':'UTF-8'}">

            <div class="shopfloor__results" id="shopfloor-results">
                <p class="shopfloor__empty">{$L.results_here|escape:'html':'UTF-8'}</p>
            </div>
        </section>

        {* --------------------------------------------------------- move *}
        <section class="shopfloor__panel shopfloor__panel--ticket">
            <h3 class="shopfloor__panel-title">{$L.load_goods|escape:'html':'UTF-8'}</h3>

            <div id="shopfloor-selected" class="shopfloor__selected" hidden>
                <p class="shopfloor__selected-name" id="shopfloor-selected-name"></p>
                <p class="shopfloor__selected-meta" id="shopfloor-selected-meta"></p>
                <p class="shopfloor__selected-meta" id="shopfloor-selected-where"></p>
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
                {* step any: the gestionale carries fractional quantities *}
                <input type="text" inputmode="decimal" id="shopfloor-quantity"
                       class="shopfloor__input" value="1">

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
        <h3 class="shopfloor__panel-title">{$L.recent_movements|escape:'html':'UTF-8'}</h3>

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
