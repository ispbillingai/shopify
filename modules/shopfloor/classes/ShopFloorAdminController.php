<?php
/**
 * Shared plumbing for the counter and warehouse screens.
 *
 * Both are ModuleAdminControllers on purpose. That means PrestaShop's login,
 * session, CSRF token and per-profile permissions guard them exactly as they
 * guard Orders or Customers — this module writes no authentication of its own,
 * and a warehouse employee simply gets a profile that reaches one tab.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorLang.php';
require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorCatalog.php';
require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorLedger.php';
require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorSale.php';

abstract class ShopFloorAdminController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        // No ObjectModel behind these screens: they are a till and a goods-in desk,
        // not a CRUD list. 'view' routes initContent() straight to renderView().
        $this->display = 'view';
        $this->list_no_link = true;

        parent::__construct();
    }

    /**
     * The scanner is the primary input device, so both screens want the same
     * "type or scan, get sellable rows back" endpoint.
     */
    public function ajaxProcessSearch(): void
    {
        $rows = ShopFloorCatalog::search((string) Tools::getValue('q', ''));

        $this->json([
            'ok' => true,
            'rows' => array_map(
                fn (array $row): array => $row + ['price_display' => $this->money((float) $row['price'])],
                $rows
            ),
        ]);
    }

    /**
     * PrestaShop 9 dropped Tools::displayPrice(); the locale formats money now.
     */
    protected function money(float $amount): string
    {
        return $this->context->getCurrentLocale()->formatPrice(
            $amount,
            (string) $this->context->currency->iso_code
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($payload));

        exit;
    }

    protected function fail(string $message): void
    {
        $this->json(['ok' => false, 'error' => $message]);
    }

    /**
     * These pages own the full width; the standard "back" and "add" buttons point
     * at things that do not exist here.
     */
    public function initPageHeaderToolbar(): void
    {
        parent::initPageHeaderToolbar();

        $this->page_header_toolbar_btn = [];
    }

    protected function renderTemplate(string $template, array $variables): string
    {
        $this->context->smarty->assign($variables + [
            // L for the templates, the same map as JSON for the strings the
            // screens build in JavaScript.
            'L' => ShopFloorLang::all(),
            'shopfloor_lang_json' => ShopFloorLang::toJson(),
            'shopfloor_token' => $this->token,
            'shopfloor_link' => self::$currentIndex,
            'shopfloor_css' => _MODULE_DIR_ . 'shopfloor/views/css/shopfloor.css',
        ]);

        return $this->context->smarty->fetch($this->getTemplatePath() . $template);
    }
}
