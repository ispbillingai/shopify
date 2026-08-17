{**
 * Stizzo storefront chrome — the fixed-rail layout of the Zara reference.
 *
 * There is no header bar. Two rails are pinned to the viewport edges and the
 * content scrolls between them:
 *
 *   left   menu mark (top) · numbered categories + filters (centred) · view toggle (bottom)
 *   right  search (top) · bag, account, help (centred)
 *
 * The rails are pointer-events:none so their empty space never swallows a click
 * on the page behind; only the links themselves are interactive.
 *}

{block name='header_banner'}
  <div class="stz-announce">
    Spedizione gratuita per ordini superiori a 59&euro;
  </div>
{/block}

{block name='header_bottom'}
  <a class="zr-mark" href="{$urls.base_url}" aria-label="{$shop.name}">
    <span></span><span></span>
  </a>

  <aside class="zr-rail zr-rail--left">
    <nav class="zr-rail__mid" aria-label="{l s='Categories' d='Shop.Theme.Global'}">
      <ol class="zr-nav">
        {$zrNav = ['Vedi tutto', 'Donna', 'Uomo', 'Bambino', 'Oro 18 Carati', 'Oro&Diamanti', 'Brand', 'Borse', 'Accessori']}
        {foreach $zrNav as $item}
          <li>
            <span class="zr-nav__num">|{($item@iteration)|string_format:"%02d"}|</span>
            <a href="{if $item@first}{$urls.pages.prices_drop}{else}{$urls.pages.search}?s={$item|escape:'url'}{/if}">{$item}</a>
          </li>
        {/foreach}
      </ol>

      <button type="button" class="zr-filters js-zr-filters" hidden>{l s='Filters' d='Shop.Theme.Global'}</button>
    </nav>

    <div class="zr-rail__bottom zr-view js-zr-view" hidden>
      <span class="zr-view__label">View</span>
      <span class="zr-view__opts">
        <button type="button" data-cols="1">1</button>
        <button type="button" data-cols="2">2</button>
        <button type="button" data-cols="3">3</button>
      </span>
    </div>
  </aside>

  <aside class="zr-rail zr-rail--right">
    <div class="zr-rail__top">
      <form class="zr-search" action="{$urls.pages.search}" method="get" role="search">
        <input type="text" name="s" placeholder="{l s='Search' d='Shop.Theme.Global'}"
               aria-label="{l s='Search' d='Shop.Theme.Global'}">
      </form>
    </div>

    <div class="zr-rail__mid">
      {if !$configuration.is_catalog}
        <a href="{$urls.pages.cart}?action=show">
          {l s='Bag' d='Shop.Theme.Checkout'}
          <em class="zr-bag__count">{$cart.products_count|default:0}</em>
        </a>
      {/if}
      <a href="{$urls.pages.my_account}">
        {if isset($customer.is_logged) && $customer.is_logged}{$customer.firstname}{else}{l s='Log in' d='Shop.Theme.Actions'}{/if}
      </a>
      <a href="{$urls.pages.contact}">{l s='Help' d='Shop.Theme.Global'}</a>
    </div>
  </aside>
{/block}
