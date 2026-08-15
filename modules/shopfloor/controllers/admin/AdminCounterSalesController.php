<?php
/**
 * The till — vendita al banco.
 *
 * Search or scan, build a ticket, take cash or card. The finished ticket becomes
 * an ordinary PrestaShop order, so stock falls and the money appears in the same
 * Orders list as the online shop.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorAdminController.php';

class AdminCounterSalesController extends ShopFloorAdminController
{
    public function initPageHeaderToolbar(): void
    {
        $this->page_header_toolbar_title = $this->trans('Counter sales', [], 'Modules.Shopfloor.Admin');

        parent::initPageHeaderToolbar();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addJS(_MODULE_DIR_ . 'shopfloor/views/js/counter.js');
    }

    public function renderView(): string
    {
        return $this->renderTemplate('counter.tpl', [
            'today' => $this->todayAtTheTill(),
            'currency_sign' => $this->context->currency->sign,
            'employee_name' => trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname),
            'orders_link' => $this->context->link->getAdminLink('AdminOrders'),
        ]);
    }

    /**
     * Close a ticket.
     */
    public function ajaxProcessCheckout(): void
    {
        $lines = json_decode((string) Tools::getValue('lines', '[]'), true);

        if (!is_array($lines) || $lines === []) {
            $this->fail($this->trans('The ticket is empty.', [], 'Modules.Shopfloor.Admin'));
        }

        $payment = (string) Tools::getValue('payment', ShopFloorSale::PAYMENT_CASH);

        if (!in_array($payment, [ShopFloorSale::PAYMENT_CASH, ShopFloorSale::PAYMENT_CARD], true)) {
            $this->fail($this->trans('Unknown payment method.', [], 'Modules.Shopfloor.Admin'));
        }

        $clean = [];

        foreach ($lines as $line) {
            $clean[] = [
                'id_product' => (int) ($line['id_product'] ?? 0),
                'id_product_attribute' => (int) ($line['id_product_attribute'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
            ];
        }

        try {
            /** @var ShopFloor $module */
            $module = $this->module;
            $sale = ShopFloorSale::ring($module, $clean, $payment, (string) Tools::getValue('note', ''));
        } catch (Throwable $exception) {
            // The message is written for the person at the counter, not for a log
            // reader, so pass it through rather than replacing it with "an error".
            PrestaShopLogger::addLog(
                'Counter sale failed: ' . $exception->getMessage(),
                3,
                null,
                'ShopFloor',
                0,
                true
            );

            $this->fail($exception->getMessage());
        }

        $tendered = (float) str_replace(',', '.', (string) Tools::getValue('tendered', '0'));
        $change = ($payment === ShopFloorSale::PAYMENT_CASH && $tendered > $sale['total'])
            ? $tendered - $sale['total']
            : 0.0;

        $this->json([
            'ok' => true,
            'id_order' => $sale['id_order'],
            'reference' => $sale['reference'],
            'total' => $sale['total'],
            'total_display' => $this->money((float) $sale['total']),
            'change' => $change,
            'change_display' => $this->money($change),
            'today' => $this->todayAtTheTill(),
            'order_link' => $this->context->link->getAdminLink('AdminOrders', true, [], [
                'id_order' => (int) $sale['id_order'],
                'vieworder' => 1,
            ]),
        ]);
    }

    /**
     * @return array{count: int, total: float, total_display: string}
     */
    private function todayAtTheTill(): array
    {
        $row = Db::getInstance()->getRow(
            'SELECT COUNT(*) AS n, COALESCE(SUM(total_paid_tax_incl), 0) AS total
             FROM `' . _DB_PREFIX_ . 'orders`
             WHERE module = "shopfloor"
               AND id_shop = ' . (int) $this->context->shop->id . '
               AND DATE(date_add) = CURDATE()'
        );

        $total = (float) ($row['total'] ?? 0);

        return [
            'count' => (int) ($row['n'] ?? 0),
            'total' => $total,
            'total_display' => $this->money($total),
        ];
    }
}
