<?php
/**
 * Finding a product fast enough to keep a queue moving.
 *
 * The catalogue here is ~9,750 products over ~3,100 combinations, and much of it
 * is unpublished — present in the warehouse, absent from the storefront. Both
 * screens therefore search the whole catalogue, not just what is online, and both
 * return combinations as their own sellable rows.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopFloorCatalog
{
    private const MAX_PRODUCTS = 20;
    private const MAX_ROWS = 60;

    /**
     * Search by SKU, barcode or name.
     *
     * A scanner types the whole code and hits enter, so exact reference and EAN
     * matches are ranked first; a human typing "brac" wants the name match.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query): array
    {
        $query = trim($query);

        if (Tools::strlen($query) < 2) {
            return [];
        }

        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $escaped = pSQL($query);
        $like = '%' . $escaped . '%';
        $prefix = $escaped . '%';

        // Products whose own reference, barcode or name matches.
        $products = Db::getInstance()->executeS(
            'SELECT p.id_product,
                    (p.reference = "' . $escaped . '" OR p.ean13 = "' . $escaped . '" OR p.upc = "' . $escaped . '") AS exact
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
             WHERE p.reference LIKE "' . $prefix . '"
                OR p.ean13 = "' . $escaped . '"
                OR p.upc = "' . $escaped . '"
                OR pl.name LIKE "' . $like . '"
             ORDER BY exact DESC, pl.name ASC
             LIMIT ' . self::MAX_PRODUCTS
        ) ?: [];

        $idProducts = array_map(static fn (array $row): int => (int) $row['id_product'], $products);

        // A combination often carries the SKU that is actually printed on the tag,
        // so a scan can match a combination whose parent product does not match.
        $fromCombination = Db::getInstance()->executeS(
            'SELECT DISTINCT pa.id_product
             FROM `' . _DB_PREFIX_ . 'product_attribute` pa
             WHERE pa.reference LIKE "' . $prefix . '"
                OR pa.ean13 = "' . $escaped . '"
                OR pa.upc = "' . $escaped . '"
             LIMIT ' . self::MAX_PRODUCTS
        ) ?: [];

        foreach ($fromCombination as $row) {
            $idProducts[] = (int) $row['id_product'];
        }

        $idProducts = array_values(array_unique($idProducts));

        if ($idProducts === []) {
            return [];
        }

        return self::expand($idProducts, $idLang, $idShop);
    }

    /**
     * Turn matched products into sellable rows: one per combination, or a single
     * row for a product without combinations.
     *
     * @param array<int, int> $idProducts
     *
     * @return array<int, array<string, mixed>>
     */
    private static function expand(array $idProducts, int $idLang, int $idShop): array
    {
        $idList = implode(',', array_map('intval', $idProducts));

        $products = Db::getInstance()->executeS(
            'SELECT p.id_product, pl.name, p.reference, p.ean13, ps.active
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
             WHERE p.id_product IN (' . $idList . ')
             ORDER BY pl.name ASC'
        ) ?: [];

        $combinations = Db::getInstance()->executeS(
            'SELECT pa.id_product, pa.id_product_attribute, pa.reference, pa.ean13
             FROM `' . _DB_PREFIX_ . 'product_attribute` pa
             INNER JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` pas
                ON pas.id_product_attribute = pa.id_product_attribute AND pas.id_shop = ' . $idShop . '
             WHERE pa.id_product IN (' . $idList . ')
             ORDER BY pa.id_product_attribute ASC'
        ) ?: [];

        $byProduct = [];

        foreach ($combinations as $combination) {
            $byProduct[(int) $combination['id_product']][] = $combination;
        }

        $labels = self::combinationLabels(
            array_map(static fn (array $row): int => (int) $row['id_product_attribute'], $combinations),
            $idLang
        );

        $rows = [];

        foreach ($products as $product) {
            $idProduct = (int) $product['id_product'];

            if (!isset($byProduct[$idProduct])) {
                $rows[] = self::row($idProduct, 0, (string) $product['name'], '', (string) $product['reference'], (string) $product['ean13'], (bool) $product['active'], $idShop);

                continue;
            }

            foreach ($byProduct[$idProduct] as $combination) {
                $idProductAttribute = (int) $combination['id_product_attribute'];

                $rows[] = self::row(
                    $idProduct,
                    $idProductAttribute,
                    (string) $product['name'],
                    $labels[$idProductAttribute] ?? '',
                    (string) ($combination['reference'] ?: $product['reference']),
                    (string) ($combination['ean13'] ?: $product['ean13']),
                    (bool) $product['active'],
                    $idShop
                );
            }
        }

        // In stock first: the person at the till wants what they can actually hand over.
        usort($rows, static function (array $a, array $b): int {
            $stockA = $a['quantity'] > 0 ? 0 : 1;
            $stockB = $b['quantity'] > 0 ? 0 : 1;

            return [$stockA, $a['label']] <=> [$stockB, $b['label']];
        });

        return array_slice($rows, 0, self::MAX_ROWS);
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        int $idProduct,
        int $idProductAttribute,
        string $name,
        string $variant,
        string $reference,
        string $ean13,
        bool $active,
        int $idShop
    ): array {
        return [
            'id_product' => $idProduct,
            'id_product_attribute' => $idProductAttribute,
            'label' => $name,
            'variant' => $variant,
            'reference' => $reference,
            'ean13' => $ean13,
            'active' => $active,
            'quantity' => (int) StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute, $idShop),
            'price' => (float) Product::getPriceStatic($idProduct, true, $idProductAttribute ?: null, 2),
        ];
    }

    /**
     * "Size: M, Colour: Gold" for each combination, in one query.
     *
     * @param array<int, int> $idProductAttributes
     *
     * @return array<int, string>
     */
    private static function combinationLabels(array $idProductAttributes, int $idLang): array
    {
        $idProductAttributes = array_values(array_unique(array_filter($idProductAttributes)));

        if ($idProductAttributes === []) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT pac.id_product_attribute,
                    GROUP_CONCAT(DISTINCT CONCAT(agl.name, ": ", al.name) ORDER BY agl.name SEPARATOR ", ") AS label
             FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
             INNER JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.id_attribute = pac.id_attribute
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_lang` al
                ON al.id_attribute = a.id_attribute AND al.id_lang = ' . $idLang . '
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_group` ag
                ON ag.id_attribute_group = a.id_attribute_group
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                ON agl.id_attribute_group = ag.id_attribute_group AND agl.id_lang = ' . $idLang . '
             WHERE pac.id_product_attribute IN (' . implode(',', array_map('intval', $idProductAttributes)) . ')
             GROUP BY pac.id_product_attribute'
        ) ?: [];

        $labels = [];

        foreach ($rows as $row) {
            $labels[(int) $row['id_product_attribute']] = (string) $row['label'];
        }

        return $labels;
    }

    /**
     * The SKU a human would read off the tag: the combination's own, falling back
     * to the product's.
     */
    public static function referenceFor(int $idProduct, int $idProductAttribute): string
    {
        if ($idProductAttribute) {
            $reference = (string) Db::getInstance()->getValue(
                'SELECT reference FROM `' . _DB_PREFIX_ . 'product_attribute`
                 WHERE id_product_attribute = ' . $idProductAttribute
            );

            if ($reference !== '') {
                return $reference;
            }
        }

        return (string) Db::getInstance()->getValue(
            'SELECT reference FROM `' . _DB_PREFIX_ . 'product` WHERE id_product = ' . $idProduct
        );
    }

    /**
     * One row by its ids, for screens that already know what they are looking at.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $idProduct, int $idProductAttribute): ?array
    {
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $product = Db::getInstance()->getRow(
            'SELECT p.id_product, pl.name, p.reference, p.ean13, ps.active
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
             WHERE p.id_product = ' . $idProduct
        );

        if (!$product) {
            return null;
        }

        $variant = '';
        $reference = (string) $product['reference'];

        if ($idProductAttribute) {
            $labels = self::combinationLabels([$idProductAttribute], $idLang);
            $variant = $labels[$idProductAttribute] ?? '';
            $reference = self::referenceFor($idProduct, $idProductAttribute);
        }

        return self::row(
            $idProduct,
            $idProductAttribute,
            (string) $product['name'],
            $variant,
            $reference,
            (string) $product['ean13'],
            (bool) $product['active'],
            $idShop
        );
    }
}
