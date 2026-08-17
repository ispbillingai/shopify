{**
 * Product card, restructured for the Zara reference.
 *
 * The vendor card stacks flags *over* the image and ends with a full-width
 * "Add to cart" button. The reference puts the flag, the name and the price in
 * a quiet block underneath the photograph, with a single "+" pushed to the far
 * right as the only affordance. Reordering with CSS alone is not possible —
 * the flags live inside the image container — so the markup is rebuilt here.
 *
 * Quick view is dropped entirely; the reference has no such control.
 *}
{$componentName = 'product-miniature'}

{block name='product_miniature_item'}
  <article
    class="{$componentName} js-{$componentName}"
    data-id-product="{$product.id_product}"
    data-id-product-attribute="{$product.id_product_attribute}"
  >
    <div class="{$componentName}__inner">
      {block name='product_miniature_top'}
        <div class="{$componentName}__top">
          {include file='catalog/_partials/miniatures/product-image.tpl'}
        </div>
      {/block}

      {block name='product_miniature_bottom'}
        <div class="{$componentName}__bottom">
          <div class="{$componentName}__infos">
            {block name='product_flags'}
              {include file='catalog/_partials/product-flags.tpl'}
            {/block}

            {block name='product_name'}
              <a class="{$componentName}__title" href="{$product.url}" aria-label="{l s='View product %product_name%' sprintf=['%product_name%' => $product.name] d='Shop.Theme.Catalog'}">{$product.name}</a>
            {/block}

            {if $product.show_price}
              <div class="{$componentName}__prices">
                {block name='product_price'}
                  {hook h='displayProductPriceBlock' product=$product type="before_price"}

                  <div class="{$componentName}__price" aria-label="{l s='Price' d='Shop.Theme.Catalog'}">
                    {capture name='custom_price'}{hook h='displayProductPriceBlock' product=$product type='custom_price' hook_origin='products_list'}{/capture}
                    {if '' !== $smarty.capture.custom_price}
                      {$smarty.capture.custom_price nofilter}
                    {else}
                      {$product.price}
                    {/if}
                  </div>
                {/block}

                {block name='product_discount_price'}
                  {if $product.has_discount}
                    <div class="{$componentName}__discount-price">
                      <span class="{$componentName}__regular-price" aria-label="{l s='Regular price' d='Shop.Theme.Catalog'}">{$product.regular_price}</span>
                    </div>
                  {/if}
                {/block}
              </div>
            {/if}

            {block name='product_variants'}
              {if $product.main_variants}
                <div class="{$componentName}__variants">
                  {include file='catalog/_partials/variant-links.tpl' variants=$product.main_variants}
                </div>
              {/if}
            {/block}
          </div>

          <div class="{$componentName}__actions">
            {if $product.add_to_cart_url}
              <form class="{$componentName}__form" action="{$urls.pages.cart}" method="post">
                <input type="hidden" value="{$product.id_product}" name="id_product">
                <input type="hidden" value="1" name="qty">
                <input type="hidden" name="token" value="{$static_token}">

                <button
                  data-button-action="add-to-cart"
                  class="{$componentName}__add"
                  aria-label="{l s='Add to cart %product_name%' sprintf=['%product_name%' => $product.name] d='Shop.Theme.Actions'}"
                  title="{l s='Add to cart' d='Shop.Theme.Actions'}"
                  data-ps-ref="add-to-cart"
                >+</button>
              </form>
            {else}
              <a href="{$product.url}" class="{$componentName}__add" aria-label="{l s='View product %product_name%' sprintf=['%product_name%' => $product.name] d='Shop.Theme.Catalog'}">+</a>
            {/if}
          </div>
        </div>
      {/block}
    </div>
  </article>
{/block}
