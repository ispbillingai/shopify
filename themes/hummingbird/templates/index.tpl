{**
 * Stizzo Gioielleria custom homepage
 *}
{extends file=$layout}

{block name='breadcrumb'}{/block}

{block name='content_columns'}
  {block name='left_column'}{/block}

  {block name='content_wrapper'}
    {$stzImg = "{$urls.theme_assets}img/stizzo"}
    <div id="center-column" class="center-column page">
      <div id="content" class="page-content page-content--home stz-home">

        {* ---------- Stage ----------
         * Full-viewport slides, each a two-up split of portrait photography,
         * with the wordmark set large across the lower right and the campaign
         * line at top left. The hero-*.png files are deliberately not used here:
         * they are landscape and already carry the Stizzo wordmark inside the
         * artwork, which would collide with the one drawn over the top. They
         * still serve the category banners, where that baked-in mark is wanted.
         *}
        <section class="zr-stage" id="zr-stage">
          <div class="zr-stage__track">
            {$slides = [
              ['a' => 'tile-donna.webp',     'b' => 'swarovski-hero.png',
               'kicker' => 'La collezione',  'line' => 'Gioielli da donna',
               'sa' => 'Donna',              'sb' => 'Swarovski'],
              ['a' => 'tile-uomo.jpg',       'b' => 'review-ring.jpg',
               'kicker' => 'Il pezzo',       'line' => 'Anello filo della vita',
               'sa' => 'Uomo',               'sb' => 'Anello'],
              ['a' => 'tile-bimbo.jpg',      'b' => 'thankyou.jpg',
               'kicker' => 'Le novita',      'line' => 'Gioielli bambino',
               'sa' => 'Bambino',            'sb' => 'Novita']
            ]}
            {foreach $slides as $slide}
              <article class="zr-stage__slide">
                <a class="zr-stage__half" href="{$urls.pages.search}?s={$slide.sa|escape:'url'}">
                  <img src="{$stzImg}/{$slide.a}" alt="{$slide.sa}" {if !$slide@first}loading="lazy"{/if}>
                </a>
                <a class="zr-stage__half zr-stage__half--b" href="{$urls.pages.search}?s={$slide.sb|escape:'url'}">
                  <img src="{$stzImg}/{$slide.b}" alt="{$slide.sb}" loading="lazy">
                </a>

                <div class="zr-stage__editorial">
                  <p class="zr-stage__kicker">{$slide.kicker}</p>
                  <p class="zr-stage__line">{$slide.line}</p>
                </div>
              </article>
            {/foreach}
          </div>

          {* Drawn as text, not the 300px logo file, so it stays crisp at this size. *}
          <span class="zr-stage__mark" aria-hidden="true">STIZZO</span>

          <button class="zr-stage__arrow zr-stage__arrow--prev" aria-label="Precedente">&#8592;</button>
          <button class="zr-stage__arrow zr-stage__arrow--next" aria-label="Successiva">&#8594;</button>
        </section>

        {* ---------- Category tiles ---------- *}
        <section class="stz-tiles">
          {$tiles = [
            ['img' => 'tile-donna.webp', 'title' => 'Gioielli Da Donna', 'btn' => 'Scopri Il Gioielllo Che Fa Per Te!', 's' => 'Donna'],
            ['img' => 'tile-uomo.jpg',  'title' => 'Gioielli Da Uomo',  'btn' => 'Scopri Il Gioiello Che Fa Per Te!', 's' => 'Uomo'],
            ['img' => 'tile-bimbo.jpg', 'title' => 'Gioielli Bimbo',    'btn' => "Regala Un'Emozione!",               's' => 'Bambino']
          ]}
          {foreach $tiles as $tile}
            <div class="stz-tile">
              <img src="{$stzImg}/{$tile.img}" alt="{$tile.title}" loading="lazy">
              <h3>{$tile.title}</h3>
              <a class="stz-btn" href="{$urls.pages.search}?s={$tile.s|escape:'url'}">{$tile.btn}</a>
            </div>
          {/foreach}
        </section>

        {* ---------- Product carousel ---------- *}
        <section class="stz-collection">
          <div class="stz-collection__head">
            <h2>Anelli Chantecler</h2>
            <a href="{$urls.pages.search}?s=Chantecler">Visualizza tutto</a>
          </div>
          <div class="stz-products">
            {$prods = [
              ['img' => 'prod-44078.jpg',       'name' => 'Anello Et Voilà Campanelle Argento 43223'],
              ['img' => 'prod-44120.jpg',       'name' => 'Anello Et Voilà Campanelle Argento 43227'],
              ['img' => 'prod-campanelle.webp', 'name' => 'Anello Et Voilà Campanelle Argento Chantecler'],
              ['img' => 'prod-44067.jpg',       'name' => 'Anello Et Voilà Capriness Argento Chantecler'],
              ['img' => 'prod-44068.jpg',       'name' => 'Anello Et Voilà Capriness Argento Chantecler']
            ]}
            {foreach $prods as $p}
              <div class="stz-product">
                <a href="{$urls.pages.search}?s={$p.name|escape:'url'}">
                  <img src="{$stzImg}/{$p.img}" alt="{$p.name}" loading="lazy">
                  <p>{$p.name}</p>
                </a>
              </div>
            {/foreach}
          </div>
        </section>

        {* ---------- Thank you ---------- *}
        <section class="stz-thankyou" style="background-image:url('{$stzImg}/thankyou.jpg')">
          <h2>Thank you<br>for the trust</h2>
        </section>

        {* ---------- Brands ---------- *}
        <section class="stz-brands">
          <h2>I NOSTRI BRAND</h2>
          <div class="stz-brands__grid">
            {foreach ['brand-chantecler.png' => 'Chantecler', 'brand-leopizzo.webp' => 'LeoPizzo',
                      'brand-frederique.png' => 'Frederique Constant', 'brand-swarovski.png' => 'Swarovski',
                      'brand-chimento.png' => 'Chimento', 'brand-seiko.png' => 'Seiko',
                      'brand-zancan.png' => 'Zancan', 'brand-locman.png' => 'Locman',
                      'brand-giannotti.png' => 'Roberto Giannotti', 'brand-visconti.webp' => 'Giorgio Visconti'] as $file => $brand}
              <img src="{$stzImg}/{$file}" alt="{$brand}" loading="lazy">
            {/foreach}
          </div>
        </section>

        {* ---------- Swarovski overlay ---------- *}
        <section class="stz-overlay" style="background-image:url('{$stzImg}/swarovski-hero.png')">
          <div>
            <span class="stz-kicker">BRILLA CON</span>
            <h2>SWAROVSKI</h2>
            <a class="stz-btn stz-btn--light" href="{$urls.pages.search}?s=Swarovski">SCOPRI DI PIU'</a>
          </div>
        </section>

        {* ---------- Review ---------- *}
        <section class="stz-review">
          <figure>
            <div class="stz-review__stars">&#9733; &#9733; &#9733; &#9733; &#9733;</div>
            <blockquote>Ho fatto acquisti qui perdue anelli. Uno per me e uno per una mia amica, entrambe adoriamo la qualit&agrave; dei gioielli! Sicuramente continuer&ograve; ad acquistare su questo shop!</blockquote>
            <figcaption>&mdash; RECENSIONI DEI CLIENTI</figcaption>
          </figure>
          <div class="stz-review__img">
            <img src="{$stzImg}/review-ring.jpg" alt="Anello Filo della Vita con lettera" loading="lazy">
            <a href="{$urls.pages.search}?s=Anello+Filo+Della+Vita">ANELLO FILO DELLA VITA CON LETTERA</a>
          </div>
        </section>

        {* ---------- Chi siamo ---------- *}
        <section class="stz-chisiamo" style="background-image:url('{$stzImg}/chisiamo.jpg')">
          <div>
            <span class="stz-kicker">CONOSCIAMOCI MEGLIO..</span>
            <h2>Chi siamo?</h2>
          </div>
        </section>

        {* ---------- Quote ---------- *}
        <section class="stz-quote" style="background-image:url('{$stzImg}/quote-bg.jpg')">
          <p>La precisione nel lavoro, l'autenticita' del prodotto ed il rispetto per il cliente conquistano immediatamente l'attenzione di una clientela sempre piu' vasta ed esigente.</p>
        </section>

        {* ---------- Newsletter ---------- *}
        <section class="stz-newsletter">
          <h2>Iscriviti alla newsletter</h2>
          <p>Unisciti alla lista dei desideri. sarai il primo a conoscere i nuovi arrivi, gli sconti riservati ai clienti e riceverai il 10% di sconto sul tuo primo ordine STIZZO</p>
          <form action="{$urls.current_url}" method="post">
            <input type="email" name="email" placeholder="Inserisci l'indirizzo email" required>
            <input type="hidden" name="action" value="0">
            <input type="hidden" name="submitNewsletter" value="1">
            <button type="submit" class="stz-btn">Registrati</button>
          </form>
        </section>

        {* keep module hooks available but out of the custom flow *}
        <div class="d-none">{$HOOK_HOME nofilter}</div>
      </div>
    </div>

    <script>
    (function() {
      var hero = document.getElementById('zr-stage');
      if (!hero) return;
      var track = hero.querySelector('.zr-stage__track');
      var count = track.children.length, i = 0, timer;
      function go(n) {
        i = (n + count) % count;
        track.style.transform = 'translateX(-' + (i * 100) + '%)';
      }
      function auto() { timer = setInterval(function() { go(i + 1); }, 6000); }
      hero.querySelector('.zr-stage__arrow--prev').addEventListener('click', function() { clearInterval(timer); go(i - 1); auto(); });
      hero.querySelector('.zr-stage__arrow--next').addEventListener('click', function() { clearInterval(timer); go(i + 1); auto(); });
      auto();
    })();
    </script>
  {/block}

  {block name='right_column'}{/block}
{/block}
