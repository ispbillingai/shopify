{*
 * Search results.
 *
 * The rail categories all link here, so this page is what a shopper actually
 * lands on when they pick "Donna" or "Bambino" — which makes a bare
 * "Search results for …" heading the wrong thing to greet them with. It now
 * opens on the campaign image for whatever was searched, with the term set over
 * it, and falls back to a general image for a free-text search.
 *
 * The lookup is an exact lowercase match rather than a substring test, because
 * PrestaShop's Smarty security policy does not expose strpos() to templates.
 * Every term the rail can send is a key here; anything else takes the default.
 *}
{extends file='catalog/listing/product-list.tpl'}

{block name='product_list'}
  {include file='catalog/_partials/products.tpl' listing=$listing}
{/block}

{block name='error_content'}
  <p>{l s='Search again what you are looking for.' d='Shop.Theme.Catalog'}</p>
{/block}

{block name='product_list_header'}
  {$zrImg = "{$urls.theme_assets}img/stizzo"}

  {$zrBanners = [
    'donna'               => 'tile-donna.webp',
    'uomo'                => 'tile-uomo.jpg',
    'gioielli uomo'       => 'hero-5.png',
    'bambino'             => 'tile-bimbo.jpg',
    'orecchini'           => 'hero-1.png',
    'orecchini a cerchio' => 'hero-1.png',
    'collana'             => 'hero-2.png',
    'collane donna'       => 'hero-2.png',
    'bracciale'           => 'hero-3.png',
    'bracciali'           => 'hero-3.png',
    'anello'              => 'hero-4.png',
    'anelli'              => 'hero-4.png',
    'oro 18 carati'       => 'hero-4.png',
    'oro 9 carati'        => 'hero-2.png',
    'oro&diamanti'        => 'hero-4.png',
    'swarovski'           => 'swarovski-hero.png',
    'brand'               => 'hero-6.png',
    'borse'               => 'chisiamo.jpg',
    'accessori'           => 'hero-3.png',
    'special events'      => 'quote-bg.jpg',
    'super saldi'         => 'hero-6.png',
    'sacro'               => 'hero-2.png',
    'novita\''            => 'hero-6.png',
    'vedi tutto'          => 'hero-6.png'
  ]}

  {$zrKey = $search_string|lower|trim}
  {$zrBanner = 'hero-6.png'}
  {if isset($zrBanners[$zrKey])}
    {$zrBanner = $zrBanners[$zrKey]}
  {/if}

  {if empty($search_string)}
    {assign var='title' value={l s='Nothing to search for' d='Shop.Theme.Catalog'}}
  {else}
    {if $listing.products|count}
      {assign var='title' value=$search_string}
    {else}
      {assign var='title' value={l s='No search results for "%search_term%"' sprintf=['%search_term%' => $search_string] d='Shop.Theme.Catalog'}}
    {/if}
  {/if}

  <div id="js-product-list-header">
    {if !empty($search_string) && $listing.products|count}
      <section class="zr-banner">
        <img src="{$zrImg}/{$zrBanner}" alt="{$search_string}">
        <div class="zr-banner__caption">
          <h1>{$title}</h1>
        </div>
      </section>
    {else}
      {include file='components/page-title-section.tpl' title=$title}
    {/if}
  </div>
{/block}
