{**
 * Stizzo storefront footer — editorial minimal.
 *
 * The icon strip the old footer carried (van, pin, tag, star) is gone: the
 * reference has no such badges. What remains is four columns of small uppercase
 * links and a hairline above the legal line.
 *}
{$stzImg = "{$urls.theme_assets}img/stizzo"}

{block name='footer_main'}
  <div class="footer stz-footer">
    <div class="stz-footer__grid">
      <div>
        <h4>Stizzo</h4>
        <p class="stz-store-text">Siamo ossessionati dal design e dal prodotto. Senza compromessi nello stile, nella qualit&agrave; e nelle prestazioni di ogni gioiello che realizziamo.</p>
      </div>

      <div>
        <h4>Aiuto</h4>
        <ul>
          <li><a href="{$urls.base_url}content/1-spedizione-e-resi">Spedizione e resi</a></li>
          <li><a href="{$urls.base_url}content/2-privacy-policy">Privacy policy</a></li>
          <li><a href="{$urls.pages.contact}">Contattaci</a></li>
        </ul>
      </div>

      <div>
        <h4>Azienda</h4>
        <ul>
          <li><a href="{$urls.pages.sitemap}">Mappa del sito</a></li>
          <li><a href="{$urls.pages.stores}">Negozi</a></li>
        </ul>
        <p>P.IVA 07904931214</p>
      </div>

      <div class="stz-footer__news">
        <h4>Newsletter</h4>
        <p>Iscriviti per conoscere in anticipo nuovi arrivi e collezioni.</p>
        <form action="{$urls.current_url}" method="post">
          <input type="email" name="email" placeholder="E-mail" required>
          <input type="hidden" name="action" value="0">
          <input type="hidden" name="submitNewsletter" value="1">
          <button type="submit">Iscriviti</button>
        </form>
      </div>
    </div>

    <div class="stz-footer__bottom">
      <div class="stz-copy">&copy; {'Y'|date} Stizzo Gioielleria</div>
      <div class="stz-payments">
        {foreach ['visa', 'master', 'maestro', 'american_express', 'paypal', 'apple_pay', 'google_pay'] as $pay}
          <img src="{$stzImg}/pay-{$pay}.svg" alt="{$pay}" loading="lazy">
        {/foreach}
      </div>
    </div>
  </div>
{/block}
