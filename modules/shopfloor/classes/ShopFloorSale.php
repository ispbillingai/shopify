<?php
/**
 * Turning a till ticket into a PrestaShop order.
 *
 * The counter deliberately does not invent its own notion of a sale. It builds a
 * normal cart and hands it to PaymentModule::validateOrder(), the same call every
 * payment module makes. That buys correct stock decrements, invoices, order
 * states and — the point of the exercise — counter takings that show up in the
 * same Orders list and the same statistics as the online shop.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopFloorSale
{
    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_CARD = 'card';

    /**
     * @param array<int, array{id_product: int, id_product_attribute: int, quantity: int}> $lines
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the ticket cannot become an order
     */
    public static function ring(ShopFloor $module, array $lines, string $payment, string $note = ''): array
    {
        if ($lines === []) {
            throw new RuntimeException('The ticket is empty.');
        }

        $context = Context::getContext();

        $customer = new Customer((int) Configuration::get(ShopFloor::CONF_CUSTOMER));
        $address = new Address((int) Configuration::get(ShopFloor::CONF_ADDRESS));
        $idCarrier = (int) Configuration::get(ShopFloor::CONF_CARRIER);

        if (!Validate::isLoadedObject($customer) || !Validate::isLoadedObject($address) || !$idCarrier) {
            throw new RuntimeException(
                'The counter is not set up. Reinstall the "Counter sales & warehouse" module.'
            );
        }

        $cart = self::buildCart($context, $customer, $address, $idCarrier);

        // Everything below prices against this cart, so the context has to point at
        // it rather than at whatever the employee's session was holding.
        $context->cart = $cart;
        $context->customer = $customer;
        $context->currency = new Currency((int) $cart->id_currency);
        $context->country = new Country((int) $address->id_country);

        $stockBefore = [];

        foreach ($lines as $line) {
            $idProduct = (int) $line['id_product'];
            $idProductAttribute = (int) $line['id_product_attribute'];
            $quantity = (int) $line['quantity'];

            if ($idProduct <= 0 || $quantity <= 0) {
                throw new RuntimeException('A ticket line is missing a product or a quantity.');
            }

            $key = $idProduct . '-' . $idProductAttribute;
            $stockBefore[$key] = (int) StockAvailable::getQuantityAvailableByProduct(
                $idProduct,
                $idProductAttribute,
                (int) $cart->id_shop
            );

            $added = $cart->updateQty($quantity, $idProduct, $idProductAttribute, false, 'up', 0, null, false);

            if ($added !== true) {
                throw new RuntimeException(self::describeRejection($idProduct, $idProductAttribute, $quantity, (int) $cart->id_shop));
            }
        }

        $cart->setDeliveryOption([(int) $address->id => $idCarrier . ',']);
        $cart->update();

        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        // "Delivered", not "Payment accepted": at a counter the goods are paid for
        // and in the customer's hands in the same instant. It is also the state
        // that makes PrestaShop write a native stock movement — it only records one
        // when the state is flagged shipped — so counter sales show up on the
        // Stock > Movements page next to everything else.
        $module->validateOrderQuietly(
            (int) $cart->id,
            (int) Configuration::get('PS_OS_DELIVERED'),
            $total,
            self::paymentLabel($payment),
            $note,
            (string) $customer->secure_key
        );

        $idOrder = (int) $module->currentOrder;

        if (!$idOrder) {
            throw new RuntimeException('PrestaShop did not return an order for this ticket.');
        }

        self::recordLines($lines, $stockBefore, (int) $cart->id_shop, $idOrder, $note);

        $order = new Order($idOrder);

        return [
            'id_order' => $idOrder,
            'reference' => (string) $order->reference,
            'total' => $total,
            'payment' => $payment,
            'currency' => (string) $context->currency->iso_code,
        ];
    }

    private static function buildCart(Context $context, Customer $customer, Address $address, int $idCarrier): Cart
    {
        $cart = new Cart();
        $cart->id_customer = (int) $customer->id;
        $cart->id_address_delivery = (int) $address->id;
        $cart->id_address_invoice = (int) $address->id;
        $cart->id_lang = (int) $context->language->id;
        $cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_carrier = $idCarrier;
        $cart->id_shop = (int) $context->shop->id;
        $cart->id_shop_group = (int) $context->shop->id_shop_group;
        $cart->recyclable = 0;
        $cart->gift = 0;
        $cart->secure_key = $customer->secure_key;

        if (!$cart->add()) {
            throw new RuntimeException('The till could not open a cart for this sale.');
        }

        return $cart;
    }

    /**
     * updateQty() answers false for several different reasons. Guessing which one
     * costs the person at the till a phone call, so work it out and say it.
     */
    private static function describeRejection(int $idProduct, int $idProductAttribute, int $quantity, int $idShop): string
    {
        $row = ShopFloorCatalog::find($idProduct, $idProductAttribute);
        $name = $row['label'] ?? ('product #' . $idProduct);
        $available = (int) StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute, $idShop);

        if ($available < $quantity) {
            return sprintf(
                '%s: only %d in stock, %d asked for. Load the goods in the warehouse first, or reduce the quantity.',
                $name,
                $available,
                $quantity
            );
        }

        $product = new Product($idProduct);

        if (!$product->available_for_order) {
            return sprintf('%s is flagged "not available for order" in the catalogue, so it cannot be sold.', $name);
        }

        return sprintf('%s could not be added to the ticket.', $name);
    }

    /**
     * validateOrder() has already moved the stock. These rows record who sold it
     * and against which order, so the warehouse log reads as one story.
     *
     * @param array<int, array{id_product: int, id_product_attribute: int, quantity: int}> $lines
     * @param array<string, int> $stockBefore
     */
    private static function recordLines(array $lines, array $stockBefore, int $idShop, int $idOrder, string $note): void
    {
        foreach ($lines as $line) {
            $idProduct = (int) $line['id_product'];
            $idProductAttribute = (int) $line['id_product_attribute'];
            $quantity = (int) $line['quantity'];
            $key = $idProduct . '-' . $idProductAttribute;

            $after = (int) StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute, $idShop);

            ShopFloorLedger::record(
                $idProduct,
                $idProductAttribute,
                -$quantity,
                $stockBefore[$key] ?? ($after + $quantity),
                $after,
                ShopFloorLedger::TYPE_SALE,
                $note,
                $idOrder
            );
        }
    }

    private static function paymentLabel(string $payment): string
    {
        return $payment === self::PAYMENT_CARD ? 'Counter sale — card' : 'Counter sale — cash';
    }
}
