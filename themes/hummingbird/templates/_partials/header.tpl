{**
 * Stizzo storefront chrome.
 *
 * Two layouts from one category list ($zrNav, defined once below):
 *
 *   Desktop (>1100px) — the reference's fixed rails. No header bar; the rails
 *   are pinned to the viewport edges and the content scrolls between them.
 *
 *   Phone (<=1100px) — the rails are hidden entirely and replaced by a compact
 *   sticky bar (menu, logo, search, bag) plus a full-screen drawer. Reflowing
 *   the rails instead would have put the categories above the search and bag,
 *   because that is their source order, and squeezed nine of them into a strip
 *   too small to tap.
 *}
{$stzImg = "{$urls.theme_assets}img/stizzo"}
{$zrNav = ['Vedi tutto', 'Donna', 'Uomo', 'Bambino', 'Oro 18 Carati', 'Oro&Diamanti', 'Brand', 'Borse', 'Accessori']}

{block name='header_banner'}
  <div class="stz-announce">
    Spedizione gratuita per ordini superiori a 59&euro;
  </div>
{/block}

{block name='header_bottom'}

  {* ---------- phone: compact bar ---------- *}
  <div class="zr-bar">
    <button type="button" class="zr-burger js-zr-burger" aria-expanded="false"
            aria-controls="zr-drawer" aria-label="{l s='Menu' d='Shop.Theme.Global'}">
      <span></span><span></span>
    </button>

    <a class="zr-bar__logo" href="{$urls.base_url}" title="{$shop.name}">
      <img src="{$stzImg}/logo.png" alt="{$shop.name}">
    </a>

    <div class="zr-bar__actions">
      <a href="{$urls.pages.search}" aria-label="{l s='Search' d='Shop.Theme.Global'}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7"/><line x1="15.8" y1="15.8" x2="21" y2="21"/></svg>
      </a>
      {if !$configuration.is_catalog}
        <a href="{$urls.pages.cart}?action=show" aria-label="{l s='Bag' d='Shop.Theme.Checkout'}">
          {l s='Bag' d='Shop.Theme.Checkout'}
          <em class="zr-bag__count">{$cart.products_count|default:0}</em>
        </a>
      {/if}
    </div>
  </div>

  {* ---------- phone: drawer ----------
   * Shaped after the reference's phone menu: the audiences as large serif tabs
   * across the top, then the catalogue in numbered sections with the number in
   * its own left column.
   *}
  <div class="zr-drawer" id="zr-drawer" hidden>
    <div class="zr-drawer__tabs">
      {foreach ['Donna', 'Uomo', 'Bambino'] as $tab}
        <a href="{$urls.pages.search}?s={$tab|escape:'url'}">{$tab}</a>
      {/foreach}
    </div>

    <form class="zr-drawer__search" action="{$urls.pages.search}" method="get" role="search">
      <input type="text" name="s" placeholder="{l s='Search' d='Shop.Theme.Global'}"
             aria-label="{l s='Search' d='Shop.Theme.Global'}">
    </form>

    <nav aria-label="{l s='Categories' d='Shop.Theme.Global'}">
      {$zrSections = [
        ['title' => 'Novita',    'items' => ['Vedi tutto', 'Special Events', 'Super Saldi']],
        ['title' => 'Collezione', 'items' => ['Oro 18 Carati', 'Oro 9 Carati', 'Oro&Diamanti', 'Brand', 'Borse', 'Accessori']]
      ]}
      {foreach $zrSections as $section}
        <div class="zr-drawer__section">
          <p class="zr-drawer__num">
            |{($section@iteration)|string_format:"%02d"}| <span>{$section.title}</span>
          </p>
          <ul>
            {foreach $section.items as $item}
              <li>
                <a href="{if $item == 'Vedi tutto'}{$urls.pages.prices_drop}{else}{$urls.pages.search}?s={$item|escape:'url'}{/if}">{$item}</a>
              </li>
            {/foreach}
          </ul>
        </div>
      {/foreach}
    </nav>

    <div class="zr-drawer__foot">
      <a href="{$urls.pages.my_account}">
        {if isset($customer.is_logged) && $customer.is_logged}{$customer.firstname}{else}{l s='Log in' d='Shop.Theme.Actions'}{/if}
      </a>
      <a href="{$urls.pages.contact}">{l s='Help' d='Shop.Theme.Global'}</a>
    </div>
  </div>

  {* ---------- desktop: rails ---------- *}
  <a class="zr-mark" href="{$urls.base_url}" aria-label="{$shop.name}">
    <span></span><span></span>
  </a>

  <aside class="zr-rail zr-rail--left">
    <nav class="zr-rail__mid" aria-label="{l s='Categories' d='Shop.Theme.Global'}">
      <ol class="zr-nav">
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
