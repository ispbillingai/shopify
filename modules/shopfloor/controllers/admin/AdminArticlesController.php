<?php
/**
 * Warehouse articles — the gestionale inventory.
 *
 * Step one of goods management for the catering stock: find an article by code,
 * barcode or description, see where it sits and how many there are, and move it.
 *
 * Separate from the Magazzino screen next door on purpose. That one moves shop
 * stock through StockAvailable; this one moves gestionale stock held in
 * ps_shopfloor_article. They are different businesses' goods and mixing them in
 * one screen would invite loading jewellery against a spare-part code.
 *
 * Admin only. Nothing here reaches the storefront.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorAdminController.php';
require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorArticle.php';

class AdminArticlesController extends ShopFloorAdminController
{
    private const MAX_UNITS = 1000000;

    public function initPageHeaderToolbar(): void
    {
        $this->page_header_toolbar_title = ShopFloorLang::get('articles_title');

        parent::initPageHeaderToolbar();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addJS(_MODULE_DIR_ . 'shopfloor/views/js/articles.js?v=' . ShopFloor::ASSET_V);
    }

    public function renderView(): string
    {
        ShopFloorArticle::createTable();
        ShopFloorLedger::ensureArticleColumn();

        return $this->renderTemplate('articles.tpl', [
            'summary' => ShopFloorArticle::summary(),
            'movements' => $this->decorate(ShopFloorLedger::recentArticles(25)),
            'employee_name' => trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname),
            'value_display' => $this->money(ShopFloorArticle::summary()['value']),
        ]);
    }

    /**
     * Search the gestionale articles rather than the shop catalogue.
     */
    public function ajaxProcessSearch(): void
    {
        $rows = ShopFloorArticle::search((string) Tools::getValue('q', ''));

        $this->json([
            'ok' => true,
            'rows' => array_map(fn (array $row): array => $this->present($row), $rows),
        ]);
    }

    /**
     * Goods arriving: add to what is already there.
     */
    public function ajaxProcessIntake(): void
    {
        $quantity = $this->quantityInput();

        if ($quantity <= 0) {
            $this->fail(ShopFloorLang::get('err_enter_arrived'));
        }

        $this->applyAndRespond($quantity, ShopFloorLedger::TYPE_INTAKE);
    }

    /**
     * A stock take: declare what is really on the shelf. The difference is what
     * gets logged, not the number typed.
     */
    public function ajaxProcessCorrect(): void
    {
        $target = $this->quantityInput();

        if ($target < 0) {
            $this->fail(ShopFloorLang::get('err_enter_shelf'));
        }

        $article = $this->requireArticle();
        $current = (float) $article['quantity'];

        if (abs($current - $target) < 0.0005) {
            $this->fail(ShopFloorLang::get('err_same_quantity'));
        }

        $this->applyAndRespond($target - $current, ShopFloorLedger::TYPE_CORRECTION);
    }

    private function applyAndRespond(float $delta, string $type): void
    {
        $article = $this->requireArticle();

        $result = ShopFloorLedger::applyArticleChange(
            (int) $article['id_article'],
            $delta,
            $type,
            (string) Tools::getValue('note', '')
        );

        $updated = ShopFloorArticle::find((int) $article['id_article']);

        $this->json([
            'ok' => true,
            'quantity_before' => $result['quantity_before'],
            'quantity_after' => $result['quantity_after'],
            'delta' => $delta,
            'row' => $updated ? $this->present($updated) : null,
            'summary' => ShopFloorArticle::summary(),
            'value_display' => $this->money(ShopFloorArticle::summary()['value']),
            'movements' => $this->decorate(ShopFloorLedger::recentArticles(25)),
        ]);
    }

    /**
     * Fractional quantities are real here: the export carries goods sold by
     * weight and by length, so this is not an int.
     */
    private function quantityInput(): float
    {
        $raw = str_replace(',', '.', (string) Tools::getValue('quantity', ''));

        if ($raw === '' || !is_numeric($raw)) {
            return -1.0;
        }

        $quantity = (float) $raw;

        return $quantity > self::MAX_UNITS ? -1.0 : $quantity;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireArticle(): array
    {
        $article = ShopFloorArticle::find((int) Tools::getValue('id_article', 0));

        if ($article === null) {
            $this->fail(ShopFloorLang::get('err_pick_product'));
        }

        return $article;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return [
            'id_article' => (int) $row['id_article'],
            'code' => (string) $row['code'],
            'barcode' => (string) $row['barcode'],
            'description' => (string) $row['description'],
            'location' => (string) $row['location'],
            'class' => (string) $row['class'],
            'supplier' => (string) $row['supplier'],
            'quantity' => (float) $row['quantity'],
            'is_service' => (bool) $row['is_service'],
            'cost_display' => $this->money((float) $row['cost_price']),
            'list_display' => $this->money((float) $row['list_price']),
        ];
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
            $movement['type_display'] = ShopFloorLang::get('type_' . (string) $movement['type']);
            $movement['time_display'] = date('H:i', strtotime((string) $movement['date_add']));
            $movement['date_display'] = date('d/m/Y', strtotime((string) $movement['date_add']));

            return $movement;
        }, $movements);
    }
}
