# shopify.ispledger.com

House reference for this project: **where it lives, how you get to it, how we build
things here.** It documents the environment as it actually is — nothing in here has
been provisioned, migrated or deployed by writing this file.

- **Repo:** `https://github.com/ispbillingai/shopify.git` (public, branch `main`)
- **Local:** `F:\shopify`
- **Live:** `https://shopify.ispledger.com`
- **Stack:** PrestaShop 9.1.4 on PHP 8.4 + MySQL 8.0 + Apache 2.4 (Ubuntu 22.04).
  PrestaShop is Symfony 6.4 + Twig/Smarty, so **the house "no framework" skeleton
  in section 3 does not apply to this app** — it is kept below as the reference
  for the other apps on this box.

---

## 1. The server

Everything at `*.ispledger.com` lives on **one box**, shared with ~30 other apps
(`parking`, `order`, `betting`, `marketplace`, `whatsapp`, …).

| | |
|---|---|
| Host | `213.199.45.30` (hostname `vmi3290358`) |
| OS | Ubuntu 22.04.5 LTS |
| Web | Apache 2.4.52, **mod_php** (no FPM), OPcache with `validate_timestamps=1` |
| PHP | 8.4.8 — `pdo_mysql, curl, json, mbstring, intl, gd, openssl, sodium, zip, xsl, sockets, pcntl` (**no bcmath**) |
| MySQL | 8.0.46. **MySQL, not MariaDB** — never write MariaDB-only SQL |
| Composer | 2.2.6 at `/usr/bin/composer` |
| Web root | `/var/www/html/<project>` |
| Vhosts | `/etc/apache2/sites-available/<project>.conf` |
| Logs | `/var/log/apache2/<host>-error.log`, `-access.log` |
| TLS | Let's Encrypt **wildcard** `*.ispledger.com` at `/etc/letsencrypt/live/ispledger.com/` — already covers this host, so no new cert is ever issued for a new `*.ispledger.com` app |

> Apps on this box are independent of each other — separate repos, separate
> databases, separate vhosts. They only share the machine.

### Getting in

SSH as `root`. On Windows the client that works is PuTTY's `plink`:

```powershell
F:\PuttyGen\plink.exe -batch -pw "<root-password>" root@213.199.45.30 "<command>"
```

Host key fingerprints (pass with `-hostkey` on a machine that hasn't cached them):

```
ED25519  SHA256:+WSPLlgyzz08KnRYNk5WPjvtCj+SqBMt3umGLLfIOAI
RSA      SHA256:L9rV5VXFTnIB5U72hjX7p1HHWfZ90xtDrlXIYQc0BWE
```

Quoting gotchas:

- Nested quotes through `plink` are fragile. To run a non-trivial PHP/SQL snippet,
  base64 it locally and decode server-side:
  `echo <b64> | base64 -d > /tmp/x.php && php /tmp/x.php`
- **Don't** pipe a script into `plink`'s stdin — it opens an interactive shell and
  prepends a BOM.

### Database

MySQL root is reachable over the unix socket as `root` with **no password**:

```bash
mysql -e "SHOW DATABASES"
mysql shopify -e "SHOW TABLES"
```

House convention is one DB + one MySQL user per app, named after the app
(`parking`/`parking`, `order`/`order`, …). App code never uses root — it reads its
own credentials from `config/config.php`.

`phpMyAdmin` is aliased at `/phpmyadmin` inside each vhost.

### Apache vhost shape

Every app follows the same two-block pattern (this is `parking.conf`, the template):

```apache
<VirtualHost *:80>
    ServerName <app>.ispledger.com
    Redirect permanent / https://<app>.ispledger.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName <app>.ispledger.com
    DocumentRoot /var/www/html/<app>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/ispledger.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/ispledger.com/privkey.pem

    <Directory /var/www/html/<app>>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/<app>.ispledger.com-error.log
    CustomLog ${APACHE_LOG_DIR}/<app>.ispledger.com-access.log combined
</VirtualHost>
```

Enable with `a2ensite <app> && systemctl reload apache2`.

**Current state:** live. `shopify.ispledger.com` has its own vhost on the wildcard
cert, `/var/www/html/shopify` holds PrestaShop 9.1.4, and the `shopify` database is
populated (prefix `ps_`, app user `shopify`).

The vhost carries per-vhost PHP overrides, because PrestaShop needs more headroom
than the shared `php.ini` gives (`memory_limit` 128M, `upload_max_filesize` 2M).
They are scoped with `php_admin_value` so the other ~30 apps on the box are
untouched:

```apache
php_admin_value memory_limit 512M
php_admin_value upload_max_filesize 64M
php_admin_value post_max_size 64M
php_admin_value max_execution_time 600
php_admin_value max_input_vars 10000
```

It also carries **static asset caching**, added 2026-08-15. Nothing was cacheable
before — no `Cache-Control`, no `Expires`, only `Last-Modified` — so every back
office page revalidated the whole asset set, including the **3.2 MB Material
Symbols font** the admin theme ships. That is what made the back office feel slow;
the server itself answers in ~0.14 s.

```apache
<LocationMatch "\.(woff2?|ttf|otf|eot)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
</LocationMatch>
<LocationMatch "\.(css|js|png|jpe?g|gif|svg|webp|ico|avif)$">
    Header set Cache-Control "public, max-age=604800"
</LocationMatch>
```

`mod_headers` is already enabled box-wide, so this needed no new Apache module,
and it is scoped to this vhost — the other ~30 apps are unaffected. Fonts get a
year because their filenames carry webpack content hashes; a changed font is a
changed URL. PHP responses are untouched, so PrestaShop keeps sending its own
`no-store` on actual pages.

⚠️ **Because `.css` and `.js` are now cached for a week, our own module assets
must be versioned** or an edit will not reach anyone already logged in.
Both admin modules append a `?v=`: `shopifylook` uses its `$this->version`, and
`shopfloor` uses a dedicated `ShopFloor::ASSET_V` constant — bumping a module's
version makes PrestaShop hunt for an upgrade script, which that module has no
need of. The storefront skin is versioned by hand in `head.tpl`.
`Media::getMediaPath()` checks `file_exists` against the path only and re-appends
the query, so a version string is safe there.

---

## 2. Deploy

Deploy is a `git pull`. Nothing is built, bundled or compiled.

```bash
cd /var/www/html/shopify
git pull origin main
rm -rf var/cache/prod/*        # PrestaShop caches its Symfony container
chown -R www-data:www-data .
```

There is no `migrate.php` here — PrestaShop owns its own schema. Database changes
come from module install/upgrade scripts, not from `migrations/*.sql`.

- The GitHub repo is **public**, so `git pull` needs no credentials on the server.
- Apache is mod_php with `validate_timestamps=1`, so a pull is **live within ~2s**
  — no `systemctl reload apache2`, no cache flush.
- If git complains about ownership, the box registers each app once:
  `git config --global --add safe.directory /var/www/html/shopify`
- **Any PHP you run on the server as `root` — a one-off install or fix script —
  rebuilds `var/cache/` owned by `root`, and the site then returns 500 for
  everyone** (`Unable to create the cache directory …/var/cache/prod/admin/twig`).
  Always finish such a script with `rm -rf var/cache/* && chown -R www-data:www-data .`,
  and check the storefront afterwards. Booting PrestaShop from a standalone CLI
  script also needs the Symfony kernel, not just `config.inc.php`: set the global
  `$kernel` to a booted `AdminKernel`, or module installs die in
  `Language::updateMultilangTables()`.

- **`git pull` on the server intermittently fails with `could not read Username
  for 'https://github.com'`.** The repo is public and the box can reach GitHub —
  `GET /info/refs` returns 200, and `git ls-remote` works — but the follow-up
  `POST /git-upload-pack` comes back `401` with `www-authenticate: Basic realm="GitHub"`.
  It is anonymous rate limiting, and it clears on its own. When it will not, ship
  the commits over SSH instead of waiting:

  ```bash
  # locally, from the server's current HEAD
  git bundle create /tmp/up.bundle <server-sha>..HEAD
  scp /tmp/up.bundle root@213.199.45.30:/tmp/
  # on the server
  cd /var/www/html/shopify && git pull /tmp/up.bundle HEAD
  ```

  That keeps history identical to `main`, so the next successful `git pull`
  fast-forwards cleanly. `protocol.version = 0` is set on the server's clone,
  which made `ls-remote` reliable again.
- `rm -rf var/cache/*` occasionally reports `Directory not empty` when Apache is
  writing Smarty compiles at the same moment. Run it twice, or the `&&` chain
  after it silently stops.

**Work is not done until it is pulled, migrated and verified on the live server.**
Push straight to `main`; we don't branch for this.

---

## 3. How we build things here

The same skeleton every app on this box uses (`parking`, `order`, …). Copy it,
don't reinvent it.

```
config/
  config.sample.php    committed template
  config.php           real credentials — GITIGNORED, lives only on the server
db/
  schema.sql           full reference schema, kept current
migrations/
  001_init.sql         versioned, ascending, never edited once applied
  002_....sql
src/                   PSR-4, namespace Shop\  — outside the web root
  Bootstrap.php Config.php Db.php Settings.php Auth.php
views/                 page partials — outside the web root
public/                DocumentRoot
  index.php            health check
  ...
bin/                   cron entrypoints
migrate.php            migration runner
composer.json          psr-4 autoload map; vendor/ optional
```

### The five rules

1. **`Bootstrap::init()` is the first line of every entrypoint.** It registers the
   autoloader (composer's if `vendor/` exists, a 10-line PSR-4 fallback if not —
   so the app runs on a plain server where `composer install` was never run),
   loads config, overlays DB settings, sets the timezone. Endpoints stay thin.

2. **One PDO, via `Db::pdo()`.** A singleton reading its DSN from `Config`.
   Always `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`,
   `utf8mb4`. Prepared statements everywhere — never string-interpolate SQL.

3. **Config is a file, overlaid by the DB.** `config/config.php` returns a nested
   array (gitignored; only `db` is strictly required). `Settings` overlays
   admin-editable values from a `settings` table on top of it. Anything an admin
   should be able to change without a deploy goes in the overlay, not in code.
   The overlay is wrapped in a `try` so a missing DB never blocks boot.

4. **Schema changes are numbered `.sql` migrations.** `migrate.php` applies
   `migrations/*.sql` in ascending order and records each filename in a
   `migrations` table, so re-runs are no-ops. `php migrate.php --dry-run` lists
   pending. A migration that has been applied anywhere is **immutable** — fix
   forward with a new one.
   MySQL 8 has no `ADD COLUMN IF NOT EXISTS`; guard against
   `information_schema.COLUMNS` instead.

5. **Log the decision, not just the result.** Every outbound message to a
   `messages` table, every state change to an `events` / `activities` audit table.
   *"Why did this happen?"* must always be answerable from the DB.

### Conventions that keep biting people

- `declare(strict_types=1);` at the top of every PHP file.
- `src/` and `views/` sit **outside** `public/` and are never served.
- Secrets live in `config/config.php` on the server only — never committed.
- User-facing copy goes in `lang/en.php` / `lang/it.php`, not inline in code.
- Anything that sends, charges or emails is **off by default** until configured.
- Flat colours in the UI. No gradients.
- Test data created against a live database gets deleted afterwards, unasked.

---

## 4. What this app is

A single-storefront online shop — one store, one admin. **Not multi-tenant**: no
merchant signup, no per-merchant stores. PrestaShop 9.1.4 Classic with the
Hummingbird theme, installed with demo fixtures.

| Thing | Where it lives |
|---|---|
| Storefront | `https://shopify.ispledger.com` |
| Back office | `https://shopify.ispledger.com/admin/` |
| DB credentials | `app/config/parameters.php` — **gitignored**, server only |
| Our admin skin | `modules/shopifylook/` |
| Our storefront skin | `themes/hummingbird/assets/css/stizzo.css` |
| Counter & warehouse | `modules/shopfloor/` |
| Debranding | `docs/debrand.sql` |
| Catalogue importer | `bin/import_shopify.php` |
| Import source data | `/var/imports/shopify/` on the server — **outside the web root** |
| Vendor base | everything else — treat as third-party |

### The storefront look

The client's customer pointed at **zara.com** and asked for that design, so the
storefront follows an editorial-minimal system built from their screenshots of
the real category page. It lives in
`themes/hummingbird/assets/css/stizzo.css` + `assets/js/stizzo.js`, loaded by
`_partials/head.tpl`, with `header.tpl`, `footer.tpl`, `index.tpl`,
`catalog/listing/search.tpl` and `catalog/_partials/miniatures/product.tpl`
supplying the markup.

**There is no header bar.** Two rails are fixed to the viewport edges and the
page scrolls between them:

| | |
|---|---|
| Left rail | menu mark (top) · numbered categories + `FILTERS` (centred) · `VIEW 1 2 3` (bottom) |
| Right rail | `SEARCH` (top) · bag with count, account, help (centred) |

Content is inset by `--zr-rail`; full-bleed sections break back out with a
negative margin of the same variable. **Below 1100px the rails stop being
rails** — zeroing `--zr-rail` collapses the inset and every breakout in one move.

The rest of the system:

1. **Monochrome.** Black, white, grey. No accent colour.
2. **Nothing rounded, nothing shadowed.** Whitespace or a hairline.
3. **Two large columns, not four.** A dense grid reads as a catalogue; the
   reference leads with very few, very large images. The rail switch offers 1/2/3
   and remembers the choice in `localStorage`.
4. **Type small, light, widely tracked.** 11px uppercase labels at .12em,
   headings weight 300, price the same size as the name.

Four things to know before editing:

- **Product images are square** (250x250, 261x261, …) but the grid frame is 3:4,
  so the image is `object-fit: contain` on a wash, never `cover`. A cover crop
  takes a quarter off every photo and beheads the rings.
- **The card is a theme template override**, not CSS. The vendor markup puts the
  flags *inside* the image container, so no amount of reordering moves them
  under the photo — `miniatures/product.tpl` rebuilds the card instead. It keeps
  `data-ps-ref="add-to-cart"`, which is what `theme.js` binds the `+` to.
- **The rail categories link into search**, so `search.tpl` is the real category
  landing page. It opens on a campaign image chosen from the search term. The
  lookup is an **exact lowercase match, not a substring test** — PrestaShop's
  Smarty security policy does not expose `strpos()` to templates — so any new
  rail term needs a key adding to `$zrBanners`.
- **Bump the `?v=` in `head.tpl`** for both the CSS and the JS on every change
  (the vhost caches them for a week, section 1), and `rm -rf var/cache/*` after
  any template edit, because PrestaShop compiles Smarty.

The CSS targets the real Hummingbird DOM — `article.product-miniature`,
`.products` as the grid, `.columns-container.container` as the wrapper — so read
the rendered HTML, not the theme sources, before adding selectors.

#### The `@layer` trap

**The theme's stylesheet uses a CSS cascade layer, and that inverts `!important`.**
A layered `!important` beats an unlayered one *regardless of specificity*, so no
rule in `stizzo.css` can override one — a four-class selector with `!important`
still loses to the theme's three-class one.

This cost real time on the product flags, which the theme paints with
`.product-flags .badge:not(.discount){background-color:blue!important}`. The fix
is never to out-specify it; it is to **stop matching the selector**.
`product-flags.tpl` therefore emits `product-flag product-flag--<type>` and drops
Bootstrap's `badge` class entirely, after which the styling needs no `!important`
at all.

If an override refuses to apply no matter how specific it is, this is why. Check
for `@layer` in the theme CSS before assuming a specificity problem, and prefer
changing the markup over escalating the selector.

#### Verify by rendering, not by grepping

Chromium is on the server and headless screenshots caught six faults that markup
checks had passed clean — a control pinned to the wrong end of a rail, content
inset twice, a `+` stacked under the price because the theme set
`flex-direction:column`, a hero crop that cut the wordmark out of its own
artwork, white captions on cream, and a sort control that is built in JavaScript
and so appears in no served HTML at all.

```bash
# snap confinement blocks /tmp, so write somewhere else
chromium-browser --headless --disable-gpu --no-sandbox --hide-scrollbars   --window-size=390,1400 --virtual-time-budget=9000   --screenshot=/root/shots/m.png 'https://shopify.ispledger.com/search?s=Bambino'

# post-JavaScript DOM, for markup the server never sends
chromium-browser --headless --disable-gpu --no-sandbox   --virtual-time-budget=9000 --dump-dom '<url>' > dom.html
```

An isolated harness — a bare HTML file loading both stylesheets and a copy of the
markup — is the fastest way to settle whether a rule is losing on the cascade or
simply not matching.

### Selling off the shop floor

The shop is not only a website. `modules/shopfloor/` adds two back office areas
under **Sell → Counter & Warehouse** (*Banco e Magazzino*):

| Screen | Who | What it does |
|---|---|---|
| Counter sales / *Vendita al banco* | `AdminCounterSales` | Scan or search, build a ticket, take cash or card |
| Warehouse / *Magazzino* | `AdminWarehouse` | Goods-in, stock takes, and a log of who moved what |

Both are `ModuleAdminController`s, so **PrestaShop's own login, CSRF tokens and
profile permissions guard them** — the module adds no authentication of its own.
Install grants the `Salesman` profile the counter and the `Logistician` profile
the warehouse; each employee lands on their own screen at login and cannot reach
the other one, by menu or by URL.

A counter sale is a **real order**, not a parallel ledger. The module builds a
normal cart and calls `PaymentModule::validateOrder()`, so takings appear in the
same Orders list and the same statistics as the storefront.

Four things here that are load-bearing:

- **Counter orders settle as `Delivered`, not `Payment accepted`.** At a till the
  goods are paid for and handed over in one motion. It is also the only way
  PrestaShop records a stock movement — `OrderDetail` writes one only when the
  state is flagged `shipped` — so without it counter sales moved stock invisibly.
- **The till prices against the counter address, not the employee's context.**
  Tax follows the delivery address. Pricing against the shop's context showed one
  number and charged another on any product with a country-specific tax rule.
- **The counter's country is resolved at install, not assumed.** `PS_COUNTRY_DEFAULT`
  is KE, which sits in zone 4, and no carrier covers zone 4 — carts built against
  it would never convert. Install picks the first active country whose zone a free
  carrier actually serves (today: FR, via the free *Click and collect*).
- **Order confirmation emails are suppressed** for the duration of a counter sale,
  via `actionEmailSendBefore`. Nobody emails a walk-in customer, and an SMTP
  timeout between one customer and the next is a queue.

Every movement is also written to `ps_shopfloor_movement` — employee, note, and
the count before and after — alongside PrestaShop's native movement, because the
native one records the arithmetic but not the reason.

#### Copy and language

The two screens do **not** use PrestaShop's translation catalogues. Those live in
the database and expect somebody to type the Italian into the back office after
installing, so a fresh install speaks English until they do. The copy ships with
the code instead, in `modules/shopfloor/lang/en.php` and `lang/it.php`, which is
the house convention anyway.

`ShopFloorLang` picks the file matching the **signed-in employee's** language —
not the shop's, so the warehouse hand and the person at the till each get their
own — and merges over English key by key, so a missing string degrades to English
rather than to a blank label. The same map reaches the browser on the root
element's `data-lang`, so the strings the screens build in JavaScript come from
one file rather than a second place to translate.

Two things that follow from this:

- **An employee's `id_lang` decides the language.** All three accounts had to be
  switched to Italian; a new employee needs the same, or they get English.
- **Bump `ShopFloor::ASSET_V`, not `$this->version`, when the module's CSS or JS
  changes.** The vhost caches those for a week so the URL must change, but
  bumping the module version sends PrestaShop hunting for an upgrade script that
  does not exist.

#### The back office language, and the login page

Two separate mechanisms, which is why the shop could show an Italian menu and an
English login page at the same time:

- **Menu entries are database rows** (`ps_tab_lang`), so they were Italian all
  along, translated per language when each tab was created.
- **Everything the framework renders** — the login page, buttons, form labels —
  comes from Symfony catalogues in `translations/it-IT/`.

`UserLocaleSubscriber` picks the locale from the signed-in employee. **The login
page has no employee**, so it falls back to `PS_LANG_DEFAULT`, which was English.
That is now Italian (`id_lang` 3), which also makes the storefront serve Italian
by default — correct for this shop, and safe here because product names and
slugs are fully populated in both languages (0 products differ by slug, so no URL
changed). There is no language prefix on URLs and no `hreflang`, so nothing
needed redirecting.

⚠️ **`translations/*/` and `translations/*.zip` are gitignored**, so the
`it-IT` catalogue lives only on the server and a `git pull` will never restore
it. If the tree is ever rebuilt from the repo, reinstall the language pack —
either from **International → Translations** in the back office, or by
unzipping `translations/sf-it-IT.zip` into `translations/` and clearing
`var/cache`.

Two staff logins exist, deliberately as simple as the admin one (see the
security note in section 4): `magazzino@upgradesrls.com` and
`cassa@upgradesrls.com`, both with password `admin`. They are ordinary
employees, not module fixtures — rename, re-password or delete them in
**Advanced Parameters → Team**.

#### Staff logins are not shop logins

Two separate account systems, and confusing them has already cost time:

| | | |
|---|---|---|
| **Back office** | `https://shopify.ispledger.com/admin/` | employees — `ps_employee` |
| **Storefront** | `https://shopify.ispledger.com/login` | shoppers — `ps_customer` |

The three staff accounts are employees. They will **never** work on the
storefront's ACCEDI link, which signs in customers. If someone reports "the
login does not work", check which of the two they are on before anything else —
the access log tells you immediately, because the storefront login is
`GET /login?back=my-account` and the back office is `POST /admin/login`.

For testing the shop as a customer: **`cliente@upgradesrls.com` / `stizzo2026`**
(verified: signs in and reaches *Il mio account*). `pub@prestashop.com` is a
leftover demo fixture and is still active, but nobody here knows its password.
`counter@shopify.ispledger.com` is the till's walk-in customer — it has a random
hash and is never meant to sign in; see the counter section below.

#### Gotchas paid for once

- `Db::getValue()` and `getRow()` **append their own `LIMIT 1`**. A query that
  already ends in `LIMIT 1` becomes `LIMIT 1 LIMIT 1`, and the syntax error is
  swallowed and returned as `false`.
- `LINES` is reserved in MySQL 8, so `COUNT(*) AS lines` is a silent syntax error.
- `Tools::displayPrice()` is gone in PrestaShop 9 — use
  `$context->getCurrentLocale()->formatPrice($amount, $isoCode)`.
- `Access::updateLgcAccess()` cascades a grant to child tabs unless its fifth
  argument is `false`, which silently hands one employee another's screen.
- `ServiceLocator` is **not** in the global namespace; legacy core files import it.

### The catalogue

Imported from a Shopify export of the Stizzo Gioielleria store: **9,750 products**
across 17,484 variant rows, Italian jewellery and bags.

```bash
php bin/import_shopify.php --products=/var/imports/shopify/products.csv \
                           --inventory=/var/imports/shopify/inventory.csv
php bin/import_shopify.php --products=/var/imports/shopify/products.csv --images
```

Re-runnable — products already present are matched on `link_rewrite` and skipped,
so an interrupted run is restarted, not repaired. `--dry-run` and `--limit=N`
exist for trials.

Four things about this data that cost time to discover:

- **The row count lies.** `wc -l` reports ~112,000 because product descriptions
  contain HTML with embedded newlines. There are 17,484 actual CSV records. Parse
  with `fgetcsv`, never line by line.
- **SKUs carry a leading apostrophe** in the products export (`'1683800`) on 6,293
  rows — Excel's "treat as text" marker. The inventory export does *not* have it,
  so an unstripped SKU silently breaks the join and the product imports with zero
  stock. `sku()` in the importer strips it.
- **Prices are EUR**, so EUR is the shop's default currency at rate 1.0 and KES is
  deactivated. Leaving KES active would quote euro amounts as shillings.
  EUR arrived with an **empty `symbol`** in `ps_currency_lang`, so every price in
  the shop rendered bare (`27.72`, no `€`) — storefront included. Repaired on
  2026-08-15 with `Currency::refreshLocalizedCurrencyData()`, which pulls symbol
  and pattern from CLDR. If a currency ever displays without its symbol again,
  that is the call to make; note it takes language *rows*, not language ids.
- **Stock lives in a separate export** split across two warehouses (Stizzo Borse,
  Stizzo Gioielleria). They are summed, because PrestaShop keeps one stock figure
  per product unless Advanced Stock Management is switched on.

Products import with **no tax rules group**, so the displayed price equals the
exported price. Attach a group in the admin if VAT should be added on top.
`Published: false` in the export (7,634 of 9,750) becomes an inactive product —
present in the catalogue, absent from the storefront.

#### Known imperfections in the source data

- **14 SKUs are shared by 43 different products.** `ABX12311`, for example, is on
  four distinct Giorgio Visconti rings. Each of them gets that SKU's stock, so
  those 43 products *overstate availability* — 13 units read as 13 on each of
  four products. Fixing it means deciding which product owns the SKU; the
  importer cannot know.
- **The inventory export covers a wider catalogue than the product export.**
  4,726 units sit on 7,735 SKUs with no matching product, so the shop holds
  11,422 of the file's 16,148 units. Nothing was lost — those products simply
  are not in the product export.
- **Roughly 6,100 products have no image** because the export has none for them.
  Only 3,625 of 9,750 carry an `Image Src`.

#### What landed

| | |
|---|---|
| Products | 9,750 (2,116 active, matching `Published`) |
| Combinations | 3,099 |
| Brands / categories | 95 / 41 |
| Images | 8,995 — all 3,625 products that have images are complete, ~9 GB on disk |
| Stock | 4,396 products in stock |
| Prices | €0 – €19,070, average €163 |

### What is ours vs theirs

The repo tracks the **whole deployed tree**, vendor code included, so that a
`git pull` is the entire deploy. Two consequences worth knowing:

- The back office lives at plain `/admin`, deliberately. PrestaShop randomises
  this folder at install to hide it, and we gave that up on purpose: the repo is
  public, so the name was never going to stay secret. **The admin password is the
  only control on that door.** If we ever want defence in depth without
  complicating the login, the move is an IP allowlist or HTTP basic auth on the
  `<Directory>` block in the vhost.
- `install/` was deleted after setup and must never come back. PrestaShop refuses
  to boot while it exists, and it would let anyone re-run the installer.

### Customising

We edit whatever we need to, core included. Prefer a module hook, the theme, or
`override/` when one of those does the job — it costs nothing extra and survives
upgrades. `modules/shopifylook/` is the worked example: it restyles the whole
back office through `actionAdminControllerSetMedia` without touching core.

But some things have no hook, and then we edit core directly. **Every core file
we change is listed below, because a PrestaShop upgrade will silently overwrite
it and the vendor's version will come back.** Re-apply after any version bump.

| Core file | What we changed | Why not a hook |
|---|---|---|
| `src/PrestaShopBundle/Resources/views/Admin/Layout/login_layout.html.twig` | Vendor logo, copyright line and social links replaced with the shop name | `actionAdminControllerSetMedia` does not fire before authentication, so no module can reach the login page |
| `src/PrestaShopBundle/Controller/Admin/LoginController.php` | Removed the check in `checkRequiredActions()` that refuses login while the admin folder is named `admin` | Hard-coded guard in a Symfony controller; no hook or override intercepts it |

⚠️ **If an upgrade restores that second one, the back office locks you out** —
it refuses every login while the folder is `/admin`. Either re-apply the patch or
rename the folder to get back in.

The back office theme is compiled SCSS and rebuilding it needs npm, which this
box does not do. That is why the skin is plain CSS injected by a module — it
needs no build step.
