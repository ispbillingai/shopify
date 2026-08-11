{**
 * Stizzo Gioielleria custom header
 *}
{$stzImg = "{$urls.theme_assets}img/stizzo"}

{block name='header_banner'}
  <div class="stz-announce">
    Spedizione gratuita per ordini superiori a 59&euro; | Paga come preferisci
  </div>
{/block}

{block name='header_bottom'}
  <div class="stz-header">
    <div class="stz-header__row">
      <div class="stz-header__search">
        <a href="{$urls.pages.search}" title="Cerca">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10.5" cy="10.5" r="7"/><line x1="15.8" y1="15.8" x2="21" y2="21"/></svg>
          <span>Cerca</span>
        </a>
      </div>

      <div class="stz-header__logo">
        {if $page.page_name == 'index'}<h1>{/if}
          <a href="{$urls.base_url}" title="{$shop.name}">
            <img src="{$stzImg}/logo.png" alt="{$shop.name}">
          </a>
        {if $page.page_name == 'index'}</h1>{/if}
      </div>

      <div class="stz-header__actions">
        <a href="{$urls.pages.my_account}">Account</a>
        {if !$configuration.is_catalog}
          <a href="{$urls.pages.cart}?action=show" class="stz-cart-link">
            <span>Carrello</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M3 4h2l2.4 11.2A1.6 1.6 0 0 0 9 16.5h8.2a1.6 1.6 0 0 0 1.56-1.24L20.6 8H6"/></svg>
          </a>
        {/if}
      </div>
    </div>
  </div>

  <nav class="stz-nav">
    <ul>
      {foreach ['Oro 18 Carati', 'Brand', 'Oro 9 Carati', 'Oro&Diamanti', 'Uomo', 'Donna', 'Bambino', 'Stizzo Home Design', 'Borse', 'Accessori', 'Special Events', 'Super Saldi', 'Sacro'] as $item}
        <li><a href="{$urls.pages.search}?s={$item|escape:'url'}">{$item}</a></li>
      {/foreach}
    </ul>
  </nav>
{/block}
