<?php
/**
 * Imports a Shopify CSV export into the catalogue.
 *
 *   php bin/import_shopify.php --products=/root/import/products.csv \
 *                              --inventory=/root/import/inventory.csv
 *   php bin/import_shopify.php --products=... --images      (second pass)
 *
 * Options:
 *   --limit=N     stop after N products (for trial runs)
 *   --dry-run     report what would happen, write nothing
 *   --images      download images instead of importing products
 *
 * Shape of a Shopify export: one row per variant, and the product-level
 * columns (Title, Body, Vendor, Type) are filled in on the FIRST row of each
 * handle only. Later rows carry variant data, or nothing but an image. So rows
 * are grouped by Handle and the group is treated as one product.
 *
 * Safe to re-run. Products already present (matched on link_rewrite, which we
 * derive from the Shopify handle) are skipped, so an interrupted run can simply
 * be started again.
 *
 * Prices are imported with NO tax rule attached, so the price a customer sees
 * equals the price in the export. Attach a tax rules group in the admin if VAT
 * needs to be added on top.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.inc.php';
require_once __DIR__ . '/../app/AdminKernel.php';

global $kernel;
$kernel = new AdminKernel('prod', false);
$kernel->boot();

// ---------------------------------------------------------------- arguments

$opts = getopt('', ['products:', 'inventory::', 'limit::', 'dry-run', 'images']);

if (empty($opts['products'])) {
    fwrite(STDERR, "--products=/path/to/products.csv is required\n");
    exit(1);
}

$productsCsv = (string) $opts['products'];
$inventoryCsv = isset($opts['inventory']) ? (string) $opts['inventory'] : '';
$limit = isset($opts['limit']) ? (int) $opts['limit'] : 0;
$dryRun = array_key_exists('dry-run', $opts);
$imageMode = array_key_exists('images', $opts);

if (!is_readable($productsCsv)) {
    fwrite(STDERR, "cannot read {$productsCsv}\n");
    exit(1);
}

$langs = array_map(static fn (array $l): int => (int) $l['id_lang'], Language::getLanguages(false));
$idShop = (int) Configuration::get('PS_SHOP_DEFAULT') ?: 1;
$homeCategory = (int) Configuration::get('PS_HOME_CATEGORY') ?: 2;

// ---------------------------------------------------------------- helpers

function readCsv(string $path): Generator
{
    $fh = fopen($path, 'r');
    if ($fh === false) {
        throw new RuntimeException("cannot open {$path}");
    }

    $header = fgetcsv($fh, 0, ',', '"', '');
    if ($header === false) {
        throw new RuntimeException("empty csv {$path}");
    }
    // Strip a UTF-8 BOM off the first column name if present.
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

    while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if (count($row) === 1 && ($row[0] === null || $row[0] === '')) {
            continue;
        }
        // Pad/trim so array_combine never blows up on a ragged row.
        $row = array_pad(array_slice($row, 0, count($header)), count($header), '');
        yield array_combine($header, $row);
    }

    fclose($fh);
}

function col(array $row, string $name): string
{
    return isset($row[$name]) ? trim((string) $row[$name]) : '';
}

/** Shopify writes "true"/"false" strings. */
function isTrue(string $v): bool
{
    return strtolower($v) === 'true';
}

/**
 * 6,293 rows of the products export carry a leading apostrophe on the SKU and
 * barcode ("'1683800") — Excel's "treat as text" marker, saved into the file.
 * The inventory export does not have it. Left alone it breaks the SKU join, so
 * every one of those products imports with no stock and an ugly reference.
 */
function sku(array $row, string $name): string
{
    return ltrim(col($row, $name), "'");
}

/**
 * Fetches one image, with retries.
 *
 * A bare file_get_contents() ran clean for 2,700 products and then started
 * failing in bursts — the CDN throttles a client that hammers it, and PHP's
 * default user agent does not help. So: identify ourselves like a browser, cap
 * the wait, and back off before retrying rather than discarding the image on
 * the first refusal.
 *
 * @return array{0: ?string, 1: string} bytes (null on failure), and why
 */
function fetchImage(string $url, int $attempts = 3): array
{
    $lastWhy = 'no attempt made';

    for ($try = 1; $try <= $attempts; ++$try) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'follow_location' => 1,
                'user_agent' => 'Mozilla/5.0 (compatible; upgradesrls-import/1.0)',
                'header' => "Accept: image/avif,image/webp,image/*,*/*;q=0.8\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $bytes = @file_get_contents($url, false, $context);

        // $http_response_header is populated by the http wrapper.
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($bytes !== false && strlen($bytes) >= 100) {
            return [$bytes, ''];
        }

        $lastWhy = $bytes === false
            ? 'http ' . ($status ?: '?') . ' — ' . (error_get_last()['message'] ?? 'no response')
            : 'response too small (' . strlen($bytes) . ' bytes)';

        if ($try < $attempts) {
            sleep($try * 2);   // 2s, then 4s
        }
    }

    return [null, $lastWhy . " [after {$attempts} attempts]"];
}

/**
 * Failure reporting for the image pass.
 *
 * A flat "only log the first 5" cap hid a burst of 170 failures completely —
 * the counter jumped and there was nothing in the log to explain it. Log the
 * first few in full, then keep a heartbeat so a burst is always visible.
 */
function logFailure(int $failedCount, string $why): void
{
    if ($failedCount <= 10 || $failedCount % 25 === 0) {
        logLine("IMAGE FAIL #{$failedCount}: {$why}");
    }
}

function logLine(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * PrestaShop rejects a link_rewrite that is not slug-shaped, and it must be
 * unique or the product URL collides.
 */
function uniqueLinkRewrite(string $handle, array &$taken): string
{
    $base = Tools::str2url($handle);
    if ($base === '') {
        $base = 'product';
    }
    $slug = $base;
    $n = 2;
    while (isset($taken[$slug])) {
        $slug = $base . '-' . $n;
        ++$n;
    }
    $taken[$slug] = true;

    return $slug;
}

// ---------------------------------------------------------------- group rows

logLine('reading ' . basename($productsCsv));

$groups = [];
$order = [];
foreach (readCsv($productsCsv) as $row) {
    $handle = col($row, 'Handle');
    if ($handle === '') {
        continue;
    }
    if (!isset($groups[$handle])) {
        $groups[$handle] = [];
        $order[] = $handle;
    }
    $groups[$handle][] = $row;
}

logLine(sprintf('%d products across %d rows', count($groups), array_sum(array_map('count', $groups))));

// ---------------------------------------------------------------- inventory

$stock = [];
if ($inventoryCsv !== '' && is_readable($inventoryCsv)) {
    logLine('reading ' . basename($inventoryCsv));
    foreach (readCsv($inventoryCsv) as $row) {
        $sku = sku($row, 'SKU');
        if ($sku === '') {
            continue;
        }
        // Two warehouses (Stizzo Borse / Stizzo Gioielleria). PrestaShop keeps a
        // single figure per product unless Advanced Stock Management is on, so
        // the locations are summed.
        $onHand = (int) col($row, 'On hand (current)');
        $stock[$sku] = ($stock[$sku] ?? 0) + max(0, $onHand);
    }
    logLine(sprintf('stock for %d SKUs, %d units total', count($stock), array_sum($stock)));
}

// ---------------------------------------------------------------- image pass

if ($imageMode) {
    importImages($groups, $dryRun, $limit);
    exit(0);
}

// ---------------------------------------------------------------- lookups

logLine('loading existing catalogue');

$existing = [];
foreach (Db::getInstance()->executeS(
    'SELECT DISTINCT link_rewrite FROM ' . _DB_PREFIX_ . 'product_lang'
) as $r) {
    $existing[$r['link_rewrite']] = true;
}
logLine(sprintf('%d link_rewrites already in use', count($existing)));

$manufacturers = [];
foreach (Db::getInstance()->executeS('SELECT id_manufacturer, name FROM ' . _DB_PREFIX_ . 'manufacturer') as $r) {
    $manufacturers[mb_strtolower($r['name'])] = (int) $r['id_manufacturer'];
}

$categories = [];
foreach (Db::getInstance()->executeS(
    'SELECT c.id_category, cl.name FROM ' . _DB_PREFIX_ . 'category c
     JOIN ' . _DB_PREFIX_ . 'category_lang cl ON cl.id_category = c.id_category
     WHERE cl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT')
) as $r) {
    $categories[mb_strtolower($r['name'])] = (int) $r['id_category'];
}

// Indexing every product as it is written turns a 10 minute job into an hour.
$searchWasOn = (bool) Configuration::get('PS_SEARCH_INDEXATION');
if (!$dryRun && $searchWasOn) {
    Configuration::updateValue('PS_SEARCH_INDEXATION', 0);
    logLine('search indexation paused for the run');
}

// ---------------------------------------------------------------- import

$stats = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'combinations' => 0, 'brands' => 0, 'categories' => 0];
$done = 0;
$started = time();

foreach ($order as $handle) {
    if ($limit > 0 && $done >= $limit) {
        break;
    }
    ++$done;

    $rows = $groups[$handle];

    // The head row is the one carrying the product-level fields.
    $head = null;
    foreach ($rows as $r) {
        if (col($r, 'Title') !== '') {
            $head = $r;
            break;
        }
    }
    if ($head === null) {
        ++$stats['skipped'];
        continue;
    }

    $slug = Tools::str2url($handle);
    if ($slug !== '' && isset($existing[$slug])) {
        ++$stats['skipped'];
        continue;
    }

    $title = col($head, 'Title');
    if ($title === '') {
        ++$stats['skipped'];
        continue;
    }

    if ($dryRun) {
        ++$stats['created'];
        if ($done <= 5) {
            logLine(sprintf(
                'would create "%s" price=%s vendor=%s type=%s variants=%d images=%d',
                mb_substr($title, 0, 50),
                col($head, 'Variant Price'),
                col($head, 'Vendor'),
                col($head, 'Type'),
                count(array_filter($rows, static fn ($r) => sku($r, 'Variant SKU') !== '')),
                count(array_filter($rows, static fn ($r) => col($r, 'Image Src') !== ''))
            ));
        }
        continue;
    }

    try {
        // ---- brand
        $idManufacturer = 0;
        $vendor = col($head, 'Vendor');
        if ($vendor !== '') {
            $key = mb_strtolower($vendor);
            if (!isset($manufacturers[$key])) {
                $m = new Manufacturer();
                $m->name = mb_substr($vendor, 0, 64);
                $m->active = true;
                if ($m->add()) {
                    $manufacturers[$key] = (int) $m->id;
                    ++$stats['brands'];
                }
            }
            $idManufacturer = $manufacturers[$key] ?? 0;
        }

        // ---- category from Shopify "Type"
        $idCategory = $homeCategory;
        $type = col($head, 'Type');
        if ($type !== '' && strtolower($type) !== 'non categorizzato') {
            $key = mb_strtolower($type);
            if (!isset($categories[$key])) {
                $c = new Category();
                $c->id_parent = $homeCategory;
                $c->active = true;
                $catSlug = Tools::str2url($type);
                foreach ($langs as $idLang) {
                    $c->name[$idLang] = mb_substr($type, 0, 128);
                    $c->link_rewrite[$idLang] = $catSlug !== '' ? $catSlug : 'categoria-' . count($categories);
                }
                if ($c->add()) {
                    $categories[$key] = (int) $c->id;
                    ++$stats['categories'];
                }
            }
            $idCategory = $categories[$key] ?? $homeCategory;
        }

        // ---- product
        $product = new Product();

        $linkRewrite = uniqueLinkRewrite($handle, $existing);
        $body = col($head, 'Body (HTML)');
        // Strip anything script-shaped; PrestaShop's validator rejects it and
        // it has no business in a product description anyway.
        $body = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body) ?? '';
        $shortDesc = col($head, 'Breve Descrizione (product.metafields.custom.breve_descrizione)');
        if ($shortDesc === '') {
            $shortDesc = mb_substr(trim(strip_tags($body)), 0, 400);
        }

        foreach ($langs as $idLang) {
            // The catalogue is Italian. Both languages get the same text so the
            // English storefront is not blank.
            $product->name[$idLang] = mb_substr($title, 0, 128);
            $product->description[$idLang] = $body;
            $product->description_short[$idLang] = mb_substr($shortDesc, 0, 800);
            $product->link_rewrite[$idLang] = $linkRewrite;

            $seoTitle = col($head, 'SEO Title');
            $seoDesc = col($head, 'SEO Description');
            if ($seoTitle !== '') {
                $product->meta_title[$idLang] = mb_substr($seoTitle, 0, 128);
            }
            if ($seoDesc !== '') {
                $product->meta_description[$idLang] = mb_substr($seoDesc, 0, 512);
            }
        }

        $price = (float) str_replace(',', '.', col($head, 'Variant Price'));
        $cost = (float) str_replace(',', '.', col($head, 'Cost per item'));
        $grams = (float) str_replace(',', '.', col($head, 'Variant Grams'));

        $product->price = $price;
        $product->wholesale_price = $cost;
        $product->weight = $grams > 0 ? $grams / 1000 : 0;
        $product->reference = mb_substr(sku($head, 'Variant SKU'), 0, 64);
        $product->id_manufacturer = $idManufacturer;
        $product->id_category_default = $idCategory;
        $product->active = isTrue(col($head, 'Published'));
        $product->visibility = 'both';
        $product->minimal_quantity = 1;
        $product->state = 1;
        // No tax group: the shown price then equals the exported price exactly.
        $product->id_tax_rules_group = 0;

        $barcode = preg_replace('/\D/', '', sku($head, 'Variant Barcode')) ?? '';
        if (strlen($barcode) === 13) {
            $product->ean13 = $barcode;
        }

        if (!$product->add()) {
            ++$stats['failed'];
            logLine("FAILED to add: {$handle}");
            continue;
        }

        $product->addToCategories(array_unique([$homeCategory, $idCategory]));

        // ---- tags
        $tags = col($head, 'Tags');
        if ($tags !== '') {
            $list = array_filter(array_map('trim', explode(',', $tags)));
            if ($list) {
                foreach ($langs as $idLang) {
                    Tag::addTags($idLang, (int) $product->id, $list);
                }
            }
        }

        // ---- combinations
        $optionName = col($head, 'Option1 Name');
        $hasRealOptions = $optionName !== '' && strtolower($optionName) !== 'title';
        $totalQty = 0;

        if ($hasRealOptions) {
            $idAttributeGroup = getAttributeGroup($optionName, $langs);

            foreach ($rows as $r) {
                $value = col($r, 'Option1 Value');
                $sku = sku($r, 'Variant SKU');
                if ($value === '' || strtolower($value) === 'default title') {
                    continue;
                }

                $idAttribute = getAttribute($idAttributeGroup, $value, $langs);
                if (!$idAttribute) {
                    continue;
                }

                $vPrice = (float) str_replace(',', '.', col($r, 'Variant Price'));
                $qty = $sku !== '' ? ($stock[$sku] ?? 0) : 0;
                $totalQty += $qty;

                // Argument order matters here and is easy to get wrong:
                // wholesale_price, price, weight, unit_impact, ecotax, quantity,
                // id_images, reference, id_supplier, ean13, default, location,
                // upc, minimal_quantity, id_shop_list, available_date
                $idAttr = $product->addCombinationEntity(
                    0.0,
                    $vPrice - $price,   // impact relative to the base price
                    (float) str_replace(',', '.', col($r, 'Variant Grams')) / 1000,
                    0.0,
                    0.0,
                    $qty,
                    [],
                    mb_substr($sku, 0, 64),
                    0,
                    '',
                    0,
                    null,
                    null,
                    1,
                    [],
                    null
                );

                if ($idAttr) {
                    $combination = new Combination((int) $idAttr);
                    $combination->setAttributes([$idAttribute]);
                    StockAvailable::setQuantity((int) $product->id, (int) $idAttr, $qty);
                    ++$stats['combinations'];
                }
            }
        } else {
            $sku = $product->reference;
            $totalQty = $sku !== '' ? ($stock[$sku] ?? 0) : 0;
        }

        StockAvailable::setQuantity((int) $product->id, 0, $totalQty);

        ++$stats['created'];
    } catch (Throwable $e) {
        ++$stats['failed'];
        logLine("ERROR {$handle}: " . $e->getMessage());
    }

    if ($done % 250 === 0) {
        $rate = $done / max(1, time() - $started);
        logLine(sprintf(
            '%d/%d  created=%d skipped=%d failed=%d  %.1f/s',
            $done,
            count($order),
            $stats['created'],
            $stats['skipped'],
            $stats['failed'],
            $rate
        ));
    }
}

if (!$dryRun && $searchWasOn) {
    Configuration::updateValue('PS_SEARCH_INDEXATION', 1);
    logLine('search indexation re-enabled (run the reindex from the admin, or bin/console)');
}

logLine(sprintf(
    'done in %ds — created=%d skipped=%d failed=%d combinations=%d brands=%d categories=%d',
    time() - $started,
    $stats['created'],
    $stats['skipped'],
    $stats['failed'],
    $stats['combinations'],
    $stats['brands'],
    $stats['categories']
));

// ---------------------------------------------------------------- attributes

function getAttributeGroup(string $name, array $langs): int
{
    static $cache = [];
    $key = mb_strtolower($name);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $id = (int) Db::getInstance()->getValue(
        'SELECT agl.id_attribute_group FROM ' . _DB_PREFIX_ . 'attribute_group_lang agl
         WHERE agl.name = "' . pSQL($name) . '" LIMIT 1'
    );

    if (!$id) {
        $group = new AttributeGroup();
        $group->group_type = 'select';
        $group->is_color_group = false;
        foreach ($langs as $idLang) {
            $group->name[$idLang] = mb_substr($name, 0, 128);
            $group->public_name[$idLang] = mb_substr($name, 0, 64);
        }
        if ($group->add()) {
            $id = (int) $group->id;
        }
    }

    return $cache[$key] = $id;
}

function getAttribute(int $idGroup, string $value, array $langs): int
{
    static $cache = [];
    $key = $idGroup . '|' . mb_strtolower($value);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $id = (int) Db::getInstance()->getValue(
        'SELECT a.id_attribute FROM ' . _DB_PREFIX_ . 'attribute a
         JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON al.id_attribute = a.id_attribute
         WHERE a.id_attribute_group = ' . (int) $idGroup . '
           AND al.name = "' . pSQL($value) . '" LIMIT 1'
    );

    if (!$id) {
        $attribute = new ProductAttribute();
        $attribute->id_attribute_group = $idGroup;
        foreach ($langs as $idLang) {
            $attribute->name[$idLang] = mb_substr($value, 0, 128);
        }
        if ($attribute->add()) {
            $id = (int) $attribute->id;
        }
    }

    return $cache[$key] = $id;
}

// ---------------------------------------------------------------- images

function importImages(array $groups, bool $dryRun, int $limit): void
{
    $done = 0;
    $downloaded = 0;
    $failed = 0;
    $skipped = 0;
    $started = time();

    // Map Shopify handle -> product we created, via link_rewrite.
    $byRewrite = [];
    foreach (Db::getInstance()->executeS(
        'SELECT id_product, link_rewrite FROM ' . _DB_PREFIX_ . 'product_lang
         WHERE id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT')
    ) as $r) {
        $byRewrite[$r['link_rewrite']] = (int) $r['id_product'];
    }

    logLine(sprintf('%d products in catalogue to match against', count($byRewrite)));

    foreach ($groups as $handle => $rows) {
        if ($limit > 0 && $done >= $limit) {
            break;
        }

        $urls = [];
        foreach ($rows as $r) {
            $src = col($r, 'Image Src');
            if ($src !== '' && !in_array($src, $urls, true)) {
                $urls[] = $src;
            }
        }
        if (!$urls) {
            continue;
        }

        ++$done;

        $slug = Tools::str2url($handle);
        $idProduct = $byRewrite[$slug] ?? 0;
        if (!$idProduct) {
            ++$skipped;
            continue;
        }

        // Compare against what the export expects, not merely "has any image".
        //
        // A plain "has > 0 ? skip" left products stranded half-finished: when a
        // download burst failed partway, the product kept image 1 and could
        // never regain images 2 and 3, because it looked done. 47 products sat
        // in exactly that state, 97 images short.
        $has = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'image WHERE id_product = ' . (int) $idProduct
        );

        if ($has >= count($urls)) {
            ++$skipped;
            continue;
        }

        if ($dryRun) {
            continue;
        }

        // Short of a full set: clear what is there and fetch the lot. Simpler
        // and more reliable than diffing which URL maps to which stored image.
        if ($has > 0) {
            foreach (Db::getInstance()->executeS(
                'SELECT id_image FROM ' . _DB_PREFIX_ . 'image WHERE id_product = ' . (int) $idProduct
            ) ?: [] as $row) {
                try {
                    (new Image((int) $row['id_image']))->delete();
                } catch (Throwable) {
                    // if it will not delete, the re-add below still supersedes it
                }
            }
            logLine("repairing {$slug}: had {$has} of " . count($urls) . ' images');
        }

        $position = 1;
        foreach ($urls as $url) {
            $image = null;
            try {
                $image = new Image();
                $image->id_product = $idProduct;
                $image->position = $position;
                $image->cover = ($position === 1);

                if (!$image->add()) {
                    ++$failed;
                    logFailure($failed, "Image::add() refused for product {$idProduct}");
                    continue;
                }

                $image->associateTo([1]);
                $target = _PS_PRODUCT_IMG_DIR_ . $image->getImgPath() . '.jpg';
                @mkdir(dirname($target), 0755, true);

                [$bytes, $why] = fetchImage($url);
                if ($bytes === null) {
                    $image->delete();
                    ++$failed;
                    logFailure($failed, 'download failed: ' . $why);
                    continue;
                }

                file_put_contents($target, $bytes);

                foreach (ImageType::getImagesTypes('products') as $type) {
                    ImageManager::resize(
                        $target,
                        _PS_PRODUCT_IMG_DIR_ . $image->getImgPath() . '-' . $type['name'] . '.jpg',
                        (int) $type['width'],
                        (int) $type['height']
                    );
                }

                ++$downloaded;
                ++$position;
            } catch (Throwable $e) {
                ++$failed;
                // Drop the half-written row. Without this a failure leaves an
                // image record with no file behind it, and because this pass
                // skips products that "already have images", that product can
                // never be retried.
                if ($image !== null && (int) $image->id > 0) {
                    try {
                        $image->delete();
                    } catch (Throwable) {
                        // nothing useful to do here
                    }
                }

                logFailure($failed, get_class($e) . ': ' . $e->getMessage()
                    . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            }
        }

        if ($done % 100 === 0) {
            logLine(sprintf(
                '%d products  images=%d failed=%d skipped=%d  %.1f prod/s',
                $done,
                $downloaded,
                $failed,
                $skipped,
                $done / max(1, time() - $started)
            ));
        }
    }

    logLine(sprintf(
        'images done in %ds — downloaded=%d failed=%d skipped=%d',
        time() - $started,
        $downloaded,
        $failed,
        $skipped
    ));
}
