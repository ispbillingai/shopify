{**
 * Product card image.
 *
 * The vendor srcset stops at 336px and declares sizes="25vw", both written for
 * the four-up grid this theme shipped with. This design shows two columns, so a
 * card is ~620px wide on a desktop and the browser was fetching a 336px file for
 * it — soft, and small inside its frame. Larger candidates are added and sizes
 * is told the truth.
 *}
{block name='product_miniature_image'}
  <div class="{$componentName}__image-container thumbnail-container">
    <a href="{$product.url}" class="{$componentName}__image-link outline outline--rounded">
      {if $product.cover}
        <picture>
          {if isset($product.cover.bySize.default_md.sources.avif)}
            <source
              srcset="
                {$product.cover.bySize.default_sm.sources.avif} 216w,
                {$product.cover.bySize.default_md.sources.avif} 261w,
                {$product.cover.bySize.default_lg.sources.avif} 336w{if isset($product.cover.bySize.default_xl.sources.avif)},
                {$product.cover.bySize.default_xl.sources.avif} 400w{/if}{if isset($product.cover.bySize.large_default.sources.avif)},
                {$product.cover.bySize.large_default.sources.avif} 800w{/if}"
              sizes="(min-width: 1101px) 45vw, 50vw"
              type="image/avif"
            >
          {/if}

          {if isset($product.cover.bySize.default_md.sources.webp)}
            <source
              srcset="
                {$product.cover.bySize.default_sm.sources.webp} 216w,
                {$product.cover.bySize.default_md.sources.webp} 261w,
                {$product.cover.bySize.default_lg.sources.webp} 336w{if isset($product.cover.bySize.default_xl.sources.webp)},
                {$product.cover.bySize.default_xl.sources.webp} 400w{/if}{if isset($product.cover.bySize.large_default.sources.webp)},
                {$product.cover.bySize.large_default.sources.webp} 800w{/if}"
              sizes="(min-width: 1101px) 45vw, 50vw"
              type="image/webp"
            >
          {/if}

          <img
            class="{$componentName}__image"
            srcset="
              {$product.cover.bySize.default_sm.url} 216w,
              {$product.cover.bySize.default_md.url} 261w,
              {$product.cover.bySize.default_lg.url} 336w{if isset($product.cover.bySize.default_xl.url)},
              {$product.cover.bySize.default_xl.url} 400w{/if}{if isset($product.cover.bySize.large_default.url)},
              {$product.cover.bySize.large_default.url} 800w{/if}"
            sizes="(min-width: 1101px) 45vw, 50vw"
            src="{$product.cover.bySize.default_md.url}"
            width="{$product.cover.bySize.default_md.width}"
            height="{$product.cover.bySize.default_md.height}"
            loading="lazy"
            alt="{$product.cover.legend}"
            title="{$product.cover.legend}"
            data-full-size-image-url="{$product.cover.bySize.home_default.url}"
          >
        </picture>
      {else}
        <picture>
          {if isset($urls.no_picture_image.bySize.default_md.sources.avif)}
            <source
              srcset="
                {$urls.no_picture_image.bySize.default_sm.sources.avif} 216w,
                {$urls.no_picture_image.bySize.default_md.sources.avif} 261w,
                {$urls.no_picture_image.bySize.default_lg.sources.avif} 336w"
              sizes="(min-width: 1101px) 45vw, 50vw"
              type="image/avif"
            >
          {/if}

          {if isset($urls.no_picture_image.bySize.default_md.sources.webp)}
            <source
              srcset="
                {$urls.no_picture_image.bySize.default_sm.sources.webp} 216w,
                {$urls.no_picture_image.bySize.default_md.sources.webp} 261w,
                {$urls.no_picture_image.bySize.default_lg.sources.webp} 336w"
              sizes="(min-width: 1101px) 45vw, 50vw"
              type="image/webp"
            >
          {/if}

          <img
            class="{$componentName}__image"
            srcset="
              {$urls.no_picture_image.bySize.default_sm.url} 216w,
              {$urls.no_picture_image.bySize.default_md.url} 261w,
              {$urls.no_picture_image.bySize.default_lg.url} 336w"
            sizes="(min-width: 1101px) 45vw, 50vw"
            width="{$urls.no_picture_image.bySize.default_md.width}"
            height="{$urls.no_picture_image.bySize.default_md.height}"
            src="{$urls.no_picture_image.bySize.default_md.url}"
            loading="lazy"
            alt="{l s='No image available' d='Shop.Theme.Catalog'}"
            title="{l s='No image available' d='Shop.Theme.Catalog'}"
            data-full-size-image-url="{$urls.no_picture_image.bySize.home_default.url}"
          >
        </picture>
      {/if}
    </a>
  </div>
{/block}
