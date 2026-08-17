{**
 * Stizzo storefront header — editorial minimal.
 *
 * Wordmark hard left, a short row of tiny uppercase actions opposite, and the
 * categories on their own line under a hairline. No search field on show: the
 * reference keeps search as a word until you ask for it.
 *}
{$stzImg = "{$urls.theme_assets}img/stizzo"}

{block name='header_banner'}
  <div class="stz-announce">
    Spedizione gratuita per ordini superiori a 59&euro;
  </div>
{/block}

{block name='header_bottom'}
  <div class="stz-header">
    <div class="stz-header__row">
      <div class="stz-header__logo">
        {if $page.page_name == 'index'}<h1>{/if}
          <a href="{$urls.base_url}" title="{$shop.name}">
            <img src="{$stzImg}/logo.png" alt="{$shop.name}">
          </a>
        {if $page.page_name == 'index'}</h1>{/if}
      </div>

      <div class="stz-header__actions">
        <a href="{$urls.pages.search}" title="Cerca">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7"/><line x1="15.8" y1="15.8" x2="21" y2="21"/></svg>
          <span>Cerca</span>
        </a>
        <a href="{$urls.pages.my_account}">Accedi</a>
        {if !$configuration.is_catalog}
          <a href="{$urls.pages.cart}?action=show" class="stz-cart-link">
            <span>Carrello</span>
          </a>
        {/if}
      </div>
    </div>
  </div>

  <nav class="stz-nav">
    <ul>
      {foreach ['Donna', 'Uomo', 'Bambino', 'Oro 18 Carati', 'Oro 9 Carati', 'Oro&Diamanti', 'Brand', 'Borse', 'Accessori', 'Special Events', 'Super Saldi'] as $item}
        <li><a href="{$urls.pages.search}?s={$item|escape:'url'}">{$item}</a></li>
      {/foreach}
    </ul>
  </nav>
{/block}
