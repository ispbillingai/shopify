<?php
/**
 * The goods-in desk — magazzino.
 *
 * The dedicated area for whoever loads the goods. Scan what arrived, type how
 * many, done. Corrections are a separate, deliberate action: adding 5 and
 * declaring "there are 5" are different claims, and conflating them is how stock
 * counts quietly drift.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorAdminController.php';

class AdminWarehouseController extends ShopFloorAdminController
{
    private const MAX_UNITS = 100000;

    public function initPageHeaderToolbar(): void
    {
        $this->page_header_toolbar_title = $this->trans('Warehouse', [], 'Modules.Shopfloor.Admin');

        parent::initPageHeaderToolbar();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addJS(_MODULE_DIR_ . 'shopfloor/views/js/warehouse.js?v=' . $this->module->version);
    }

    public function renderView(): string
    {
        return $this->renderTemplate('warehouse.tpl', [
            'today' => ShopFloorLedger::todaySummary(),
            'movements' => $this->decorate(ShopFloorLedger::recent(25)),
            'employee_name' => trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname),
            'stock_link' => $this->context->link->getAdminLink('AdminStockManagement'),
        ]);
    }

    /**
     * Goods arriving: add to what is already there.
     */
    public function ajaxProcessIntake(): void
    {
        $quantity = (int) Tools::getValue('quantity', 0);

        if ($quantity < 1 || $quantity > self::MAX_UNITS) {
            $this->fail($this->trans('Enter how many units arrived.', [], 'Modules.Shopfloor.Admin'));
        }

        $this->applyAndRespond($quantity, ShopFloorLedger::TYPE_INTAKE);
    }

    /**
     * A stock take: declare what is actually on the shelf, whatever the system
     * thought. The difference is what gets logged.
     */
    public function ajaxProcessCorrect(): void
    {
        $target = (int) Tools::getValue('quantity', -1);

        if ($target < 0 || $target > self::MAX_UNITS) {
            $this->fail($this->trans('Enter the quantity actually on the shelf.', [], 'Modules.Shopfloor.Admin'));
        }

        [$idProduct, $idProductAttribute] = $this->requireProduct();

        $current = (int) StockAvailable::getQuantityAvailableByProduct(
            $idProduct,
            $idProductAttribute,
            (int) $this->context->shop->id
        );

        if ($current === $target) {
            $this->fail($this->trans('That is already the recorded quantity.', [], 'Modules.Shopfloor.Admin'));
        }

        $this->applyAndRespond($target - $current, ShopFloorLedger::TYPE_CORRECTION);
    }

    private function applyAndRespond(int $delta, string $type): void
    {
        [$idProduct, $idProductAttribute] = $this->requireProduct();

        $note = (string) Tools::getValue('note', '');

        $result = ShopFloorLedger::applyStockChange($idProduct, $idProductAttribute, $delta, $type, $note);

        $row = ShopFloorCatalog::find($idProduct, $idProductAttribute);

        $this->json([
            'ok' => true,
            'quantity_before' => $result['quantity_before'],
            'quantity_after' => $result['quantity_after'],
            'delta' => $delta,
            'row' => $row,
            'today' => ShopFloorLedger::todaySummary(),
            'movements' => $this->decorate(ShopFloorLedger::recent(25)),
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function requireProduct(): array
    {
        $idProduct = (int) Tools::getValue('id_product', 0);
        $idProductAttribute = (int) Tools::getValue('id_product_attribute', 0);

        if ($idProduct <= 0 || ShopFloorCatalog::find($idProduct, $idProductAttribute) === null) {
            $this->fail($this->trans('Pick a product first.', [], 'Modules.Shopfloor.Admin'));
        }

        return [$idProduct, $idProductAttribute];
    }

    /**
     * @param array<int, array<string, mixed>> $movements
     *
     * @return array<int, array<string, mixed>>
     */
    private function decorate(array $movements): array
    {
        return array_map(static function (array $movement): array {
            $movement['delta_display'] = ((int) $movement['delta'] > 0 ? '+' : '') . (int) $movement['delta'];
            $movement['time_display'] = date('H:i', strtotime((string) $movement['date_add']));
            $movement['date_display'] = date('d/m/Y', strtotime((string) $movement['date_add']));

            return $movement;
        }, $movements);
    }
}
