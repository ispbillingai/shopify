{**
 * Product flags — "New", "Out-of-Stock", "-20%".
 *
 * The vendor markup carries a `badge` class, and the theme paints that class
 * inside a CSS @layer with !important:
 *
 *     .product-flags .badge:not(.discount) { background-color: <blue> !important }
 *
 * Cascade layers invert !important precedence — a layered !important beats an
 * unlayered one whatever its specificity — so no rule in our own stylesheet can
 * take that blue pill off, however many classes it names. Only inline styles or
 * a layer of our own would win, and both are worse than simply not opting in.
 *
 * So the flags keep their type class and lose `badge`. The theme's rule stops
 * matching, and stizzo.css styles them as the plain uppercase words the design
 * calls for. `product-flags` and `js-product-flags` stay, because the theme's
 * JavaScript looks for them.
 *}
{if !empty($product.flags)}
  {block name='product_flags'}
    <ul class="product-flags js-product-flags">
      {foreach from=$product.flags item=flag}
        <li class="product-flag product-flag--{$flag.type}">{$flag.label}</li>
      {/foreach}
    </ul>
  {/block}
{/if}
