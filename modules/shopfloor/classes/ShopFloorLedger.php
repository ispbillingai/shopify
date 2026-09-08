<?php
/**
 * The shop floor ledger.
 *
 * PrestaShop already records stock movements, but it records the arithmetic: a
 * sign, a quantity, a reason id. It does not record why a human did it. This
 * table keeps the decision alongside the result — who, what they were looking at,
 * what the stock read before and after, which note they typed — so "why is this
 * count wrong?" is answerable months later without reconstructing it from orders.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopFloorLedger
{
    public const TYPE_INTAKE = 'intake';
    public const TYPE_CORRECTION = 'correction';
    public const TYPE_SALE = 'sale';

    /** PrestaShop's own movement reasons, so the native Movements page reads right. */
    private const REASON_EMPLOYEE_IN = 4;
    private const REASON_EMPLOYEE_OUT = 5;

    public static function tableName(): string
    {
        return _DB_PREFIX_ . 'shopfloor_movement';
    }

    public static function createTable(): bool
    {
        return (bool) Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . self::tableName() . '` (
                `id_shopfloor_movement` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
                `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
                `employee_name` VARCHAR(255) NOT NULL DEFAULT "",
                `id_product` INT UNSIGNED NOT NULL,
                `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
                `product_name` VARCHAR(255) NOT NULL DEFAULT "",
                `reference` VARCHAR(64) NOT NULL DEFAULT "",
                `type` VARCHAR(16) NOT NULL,
                `delta` INT NOT NULL,
                `quantity_before` INT NOT NULL,
                `quantity_after` INT NOT NULL,
                `id_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `note` VARCHAR(500) NOT NULL DEFAULT "",
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_shopfloor_movement`),
                KEY `idx_product` (`id_product`, `id_product_attribute`),
                KEY `idx_date_add` (`date_add`),
                KEY `idx_employee` (`id_employee`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Move stock and write both trails in one place, so no caller can move stock
     * without leaving a record of why.
     *
     * @param int $delta signed; negative takes stock out
     *
     * @return array{quantity_before: int, quantity_after: int}
     */
    public static function applyStockChange(
        int $idProduct,
        int $idProductAttribute,
        int $delta,
        string $type,
        string $note = '',
        ?int $idOrder = null
    ): array {
        $context = Context::getContext();
        $idShop = (int) $context->shop->id;

        $before = (int) StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute, $idShop);

        if ($delta !== 0) {
            StockAvailable::updateQuantity(
                $idProduct,
                $idProductAttribute,
                $delta,
                $idShop,
                true,
                [
                    'id_stock_mvt_reason' => $delta > 0 ? self::REASON_EMPLOYEE_IN : self::REASON_EMPLOYEE_OUT,
                    'id_order' => $idOrder,
                ]
            );
        }

        $after = (int) StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute, $idShop);

        self::record($idProduct, $idProductAttribute, $delta, $before, $after, $type, $note, $idOrder);

        return ['quantity_before' => $before, 'quantity_after' => $after];
    }

    /**
     * Write a ledger row without touching stock — for sales, where PrestaShop has
     * already decremented stock itself as part of validating the order.
     */
    public static function record(
        int $idProduct,
        int $idProductAttribute,
        int $delta,
        int $before,
        int $after,
        string $type,
        string $note = '',
        ?int $idOrder = null
    ): void {
        $context = Context::getContext();
        $employee = $context->employee;

        $product = new Product($idProduct, false, (int) $context->language->id);
        $reference = ShopFloorCatalog::referenceFor($idProduct, $idProductAttribute);

        Db::getInstance()->insert('shopfloor_movement', [
            'id_shop' => (int) $context->shop->id,
            'id_employee' => $employee ? (int) $employee->id : 0,
            'employee_name' => pSQL($employee ? trim($employee->firstname . ' ' . $employee->lastname) : ''),
            'id_product' => $idProduct,
            'id_product_attribute' => $idProductAttribute,
            'product_name' => pSQL(Tools::substr((string) $product->name, 0, 255)),
            'reference' => pSQL($reference),
            'type' => pSQL($type),
            'delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'id_order' => (int) $idOrder,
            'note' => pSQL(Tools::substr($note, 0, 500)),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 25, ?string $type = null): array
    {
        $where = $type !== null ? ' WHERE type = "' . pSQL($type) . '"' : '';

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . self::tableName() . '`' . $where . '
             ORDER BY id_shopfloor_movement DESC
             LIMIT ' . max(1, min($limit, 200))
        ) ?: [];
    }

    /**
     * Today's headline numbers for the warehouse screen.
     *
     * @return array{lines: int, units_in: int, units_out: int}
     */
    public static function todaySummary(): array
    {
        // Not "AS lines": LINES is reserved in MySQL 8, and the syntax error it
        // causes is swallowed by getRow(), which quietly reports a blank day.
        $row = Db::getInstance()->getRow(
            'SELECT
                COUNT(*) AS movement_count,
                COALESCE(SUM(GREATEST(delta, 0)), 0) AS units_in,
                COALESCE(SUM(GREATEST(-delta, 0)), 0) AS units_out
             FROM `' . self::tableName() . '`
             WHERE DATE(date_add) = CURDATE()'
        );

        return [
            'lines' => (int) ($row['movement_count'] ?? 0),
            'units_in' => (int) ($row['units_in'] ?? 0),
            'units_out' => (int) ($row['units_out'] ?? 0),
        ];
    }
}
