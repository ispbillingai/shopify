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
        $this->page_header_toolbar_title = ShopFloorLang::get('warehouse_title');

        parent::initPageHeaderToolbar();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addJS(_MODULE_DIR_ . 'shopfloor/views/js/warehouse.js?v=' . ShopFloor::ASSET_V);
    }

    public function renderView(): string
    {
        return $this->renderTemplate('warehouse.tpl', [
            'today' => ShopFloorLedger::todaySummary(),
            'movements' => $this->decorate(ShopFloorLedger::recent(25)),
            'employee_name' => trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname),
            // Only offered when this employee may actually open it. The default
            // Logistician profile has products but not stock management, and a
            // link that answers 403 is worse than no link.
            'stock_link' => Access::isGranted('ROLE_MOD_TAB_ADMINSTOCKMANAGEMENT_READ', $this->context->employee->id_profile)
                ? $this->context->link->getAdminLink('AdminStockManagement')
                : '',
            // Seeing, changing and adding products is PrestaShop's own product
            // form. Rebuilding it here would mean a second, worse editor to keep
            // in step with combinations, prices, images and SEO.
            'catalogue_link' => $this->context->link->getAdminLink('AdminProducts'),
            'new_product_link' => $this->context->link->getAdminLink('AdminProducts', true, ['route' => 'admin_products_create']),
            'can_edit_products' => Access::isGranted('ROLE_MOD_TAB_ADMINPRODUCTS_UPDATE', $this->context->employee->id_profile),
            'product_edit_base' => $this->context->link->getAdminLink('AdminProducts'),
        ]);
    }

    /**
     * Goods arriving: add to what is already there.
     */
    public function ajaxProcessIntake(): void
    {
        $quantity = (int) Tools::getValue('quantity', 0);

        if ($quantity < 1 || $quantity > self::MAX_UNITS) {
            $this->fail(ShopFloorLang::get('err_enter_arrived'));
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
            $this->fail(ShopFloorLang::get('err_enter_shelf'));
        }

        [$idProduct, $idProductAttribute] = $this->requireProduct();

        $current = (int) StockAvailable::getQuantityAvailableByProduct(
            $idProduct,
            $idProductAttribute,
            (int) $this->context->shop->id
        );

        if ($current === $target) {
            $this->fail(ShopFloorLang::get('err_same_quantity'));
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
            $this->fail(ShopFloorLang::get('err_pick_product'));
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
            // 'intake' / 'correction' / 'sale' are storage values, not labels.
            $movement['type_display'] = ShopFloorLang::get('type_' . (string) $movement['type']);
            $movement['time_display'] = date('H:i', strtotime((string) $movement['date_add']));
            $movement['date_display'] = date('d/m/Y', strtotime((string) $movement['date_add']));

            return $movement;
        }, $movements);
    }
}
