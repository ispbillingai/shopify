{**
 * Stizzo Gioielleria custom footer
 *}
{$stzImg = "{$urls.theme_assets}img/stizzo"}

{block name='footer_main'}
  <section class="stz-services">
    <div><i class="material-icons">local_shipping</i><p>Free Shipping!</p></div>
    <div><i class="material-icons">place</i><p>Sede</p></div>
    <div><i class="material-icons">local_offer</i><p>Gift cards</p></div>
    <div><i class="material-icons">star_border</i><p>Valutazione 5.0 stelle</p></div>
  </section>

  <div class="footer stz-footer">
    <div class="stz-footer__grid">
      <div>
        <h4>STORE</h4>
        <p class="stz-store-text">Siamo ossessionati dal design e dal prodotto. Senza compromessi nello stile, nella qualit&agrave; e nelle prestazioni di ogni prodotto che creiamo.</p>
      </div>
      <div>
        <ul>
          <li><a href="{$urls.base_url}content/2-privacy-policy">Privacy policy</a></li>
          <li><a href="{$urls.base_url}content/1-spedizione-e-resi">Spedizione e resi</a></li>
        </ul>
      </div>
      <div class="stz-footer__news">
        <h4>Newsletter</h4>
        <p>Ottieni il 10% di sconto sul tuo primo acquisto! Inoltre, sii il primo a conoscere saldi, lanci di nuovi prodotti e offerte esclusive!</p>
        <form action="{$urls.current_url}" method="post">
          <input type="email" name="email" placeholder="Inserisci l'indirizzo email" required>
          <input type="hidden" name="action" value="0">
          <input type="hidden" name="submitNewsletter" value="1">
          <button type="submit">Registrati</button>
        </form>
      </div>
      <div>
        <h4>INFORMAZIONI AZIENDALI</h4>
        <p>P.IVA 07904931214</p>
      </div>
    </div>

    <div class="stz-footer__bottom">
      <div class="stz-copy">&copy; {'Y'|date} Stizzo Gioielleria. Powered by {$shop.name}</div>
      <div class="stz-payments">
        {foreach ['american_express', 'apple_pay', 'bancontact', 'blik', 'google_pay', 'spidealwero', 'maestro', 'master', 'mobilepay', 'paypal', 'shopify_pay', 'unionpay', 'visa'] as $pay}
          <img src="{$stzImg}/pay-{$pay}.svg" alt="{$pay}" loading="lazy">
        {/foreach}
      </div>
    </div>
  </div>
{/block}
