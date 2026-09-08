<?php
/**
 * Warehouse articles, imported from the gestionale.
 *
 * These are NOT PrestaShop products, and that is deliberate. They belong to a
 * different business from the jewellery the shop sells, they must never reach
 * the storefront, and they carry fields PrestaShop has no place for: a shelf
 * location, a merchandise class, an ERP supplier code. Putting them in
 * ps_product would risk them leaking into the shop and would fight the model on
 * every field.
 *
 * The gestionale stays the master. This table is a working copy the warehouse
 * can move stock against, and an import refreshes it.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopFloorArticle
{
    public static function tableName(): string
    {
        return _DB_PREFIX_ . 'shopfloor_article';
    }

    public static function createTable(): bool
    {
        return (bool) Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . self::tableName() . '` (
                `id_article` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                -- Codice: the gestionale key. Unique, and what a scanner or a
                -- human types. Not the export ID column, which is literally "+".
                `code` VARCHAR(64) NOT NULL,
                `barcode` VARCHAR(64) NOT NULL DEFAULT "",
                `description` VARCHAR(255) NOT NULL DEFAULT "",
                `class` VARCHAR(128) NOT NULL DEFAULT "",
                `subclass` VARCHAR(128) NOT NULL DEFAULT "",
                `location` VARCHAR(64) NOT NULL DEFAULT "",
                `supplier` VARCHAR(190) NOT NULL DEFAULT "",
                `supplier_code` VARCHAR(64) NOT NULL DEFAULT "",
                -- Decimal, not int: the export carries fractional quantities
                -- (1.2, 103.02) for goods sold by weight or length.
                `quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
                `available` DECIMAL(18,3) NOT NULL DEFAULT 0,
                `on_order` DECIMAL(18,3) NOT NULL DEFAULT 0,
                -- Wide enough to hold what the gestionale sends without
                -- truncating, including the one clearly corrupt price.
                `list_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `cost_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `sell_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `vat_rate` DECIMAL(6,3) NOT NULL DEFAULT 0,
                -- MANODOPERA, DIRITTO DI CHIAMATA and the like: billing lines,
                -- not stock. Imported so the list matches the gestionale, but
                -- flagged so they can be kept out of stock work.
                `is_service` TINYINT(1) NOT NULL DEFAULT 0,
                `last_movement` DATE NULL DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_article`),
                UNIQUE KEY `uniq_code` (`code`),
                KEY `idx_barcode` (`barcode`),
                KEY `idx_location` (`location`),
                KEY `idx_class` (`class`),
                KEY `idx_description` (`description`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Search by code, barcode or description, the three things a person at a
     * shelf actually has to hand.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query, int $limit = 50): array
    {
        $query = trim($query);

        if (Tools::strlen($query) < 2) {
            return [];
        }

        $escaped = pSQL($query);

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . self::tableName() . '`
             WHERE code LIKE "' . $escaped . '%"
                OR barcode = "' . $escaped . '"
                OR description LIKE "%' . $escaped . '%"
             ORDER BY (code = "' . $escaped . '" OR barcode = "' . $escaped . '") DESC,
                      quantity > 0 DESC,
                      description ASC
             LIMIT ' . max(1, min($limit, 200))
        ) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . self::tableName() . '` WHERE id_article = ' . $id
        );

        return $row ?: null;
    }

    public static function setQuantity(int $id, float $quantity): bool
    {
        return (bool) Db::getInstance()->execute(
            'UPDATE `' . self::tableName() . '`
             SET quantity = ' . (float) $quantity . ',
                 available = ' . (float) $quantity . ',
                 date_upd = NOW()
             WHERE id_article = ' . $id
        );
    }

    /**
     * Headline numbers for the screen.
     *
     * @return array{articles: int, in_stock: int, units: float, value: float, locations: int}
     */
    public static function summary(): array
    {
        $row = Db::getInstance()->getRow(
            'SELECT
                COUNT(*) AS articles,
                COALESCE(SUM(quantity > 0), 0) AS in_stock,
                COALESCE(SUM(GREATEST(quantity, 0)), 0) AS units,
                COALESCE(SUM(GREATEST(quantity, 0) * cost_price), 0) AS value,
                COUNT(DISTINCT NULLIF(location, "")) AS locations
             FROM `' . self::tableName() . '`
             WHERE is_service = 0'
        );

        return [
            'articles' => (int) ($row['articles'] ?? 0),
            'in_stock' => (int) ($row['in_stock'] ?? 0),
            'units' => (float) ($row['units'] ?? 0),
            'value' => (float) ($row['value'] ?? 0),
            'locations' => (int) ($row['locations'] ?? 0),
        ];
    }
}
