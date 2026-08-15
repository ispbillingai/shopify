<?php
/**
 * Counter sales & warehouse.
 *
 * Two back office areas bolted onto the online shop:
 *
 *   - Counter sales (vendita al banco): a till. Search or scan, build a ticket,
 *     take cash or card. The sale becomes a real PrestaShop order, so stock drops
 *     and the takings land in the same lists and reports as the storefront.
 *   - Warehouse (magazzino): goods-in for the person who loads stock. Scan, type
 *     what arrived, done — plus stock corrections and a running log of who
 *     touched what.
 *
 * Both are ModuleAdminControllers, so PrestaShop's own login, CSRF tokens and
 * profile permissions guard them. This module adds no authentication of its own.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ShopFloorLedger.php';
require_once __DIR__ . '/classes/ShopFloorCatalog.php';
require_once __DIR__ . '/classes/ShopFloorSale.php';

class ShopFloor extends PaymentModule
{
    public const CONF_CUSTOMER = 'SHOPFLOOR_ID_CUSTOMER';
    public const CONF_ADDRESS = 'SHOPFLOOR_ID_ADDRESS';
    public const CONF_CARRIER = 'SHOPFLOOR_ID_CARRIER';

    /**
     * Raised for the duration of a counter sale so the order confirmation email
     * is not sent. A walk-in customer is standing at the till holding the goods;
     * mailing the shop's own placeholder address just slows the checkout down and
     * risks an SMTP timeout between one customer and the next.
     */
    public static $silenceMail = false;

    private const PARENT_TAB = 'AdminShopFloor';

    /**
     * class_name => [English name, Italian name, default profile granted access].
     * The profile is matched by name, so a renamed profile simply gets skipped
     * rather than granting the wrong people access.
     */
    private const TABS = [
        'AdminCounterSales' => ['Counter sales', 'Vendita al banco', 'Salesman'],
        'AdminWarehouse' => ['Warehouse', 'Magazzino', 'Logistician'],
    ];

    public function __construct()
    {
        $this->name = 'shopfloor';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'ispledger';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        // Required by PaymentModule::install(). The module never renders a payment
        // option on the storefront — it does not hook paymentOptions — it only
        // borrows validateOrder() to turn a till ticket into a real order.
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = 'Counter sales & warehouse';
        $this->description = 'Sell over the counter and load goods into the warehouse, '
            . 'against the same stock and orders as the online shop.';
        $this->confirmUninstall = 'Remove the counter and warehouse screens? '
            . 'Orders and stock history stay untouched.';
    }

    // ---------------------------------------------------------------- install

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('actionEmailSendBefore')
            && ShopFloorLedger::createTable()
            && $this->installTabs()
            && $this->provisionCounterIdentity();
    }

    public function uninstall(): bool
    {
        // The ledger table, the counter customer and past orders are deliberately
        // left in place: they are trading history, not module scaffolding.
        return $this->uninstallTabs() && parent::uninstall();
    }

    /**
     * One top level menu entry with the two screens under it.
     */
    private function installTabs(): bool
    {
        $idParent = (int) Tab::getIdFromClassName(self::PARENT_TAB);

        if (!$idParent) {
            $parent = new Tab();
            $parent->class_name = self::PARENT_TAB;
            // Deliberately left without a module, like every core parent tab: it has
            // no controller of its own, and PrestaShop opens its first child when the
            // menu entry is clicked.
            $parent->id_parent = $this->sellSectionId();
            $parent->active = true;
            $parent->icon = 'point_of_sale';
            $parent->name = $this->localised('Counter & Warehouse', 'Banco e Magazzino');

            if (!$parent->add()) {
                return false;
            }

            $idParent = (int) $parent->id;
        }

        foreach (self::TABS as $className => [$english, $italian, $profileName]) {
            $idTab = (int) Tab::getIdFromClassName($className);

            if (!$idTab) {
                $tab = new Tab();
                $tab->class_name = $className;
                $tab->module = $this->name;
                $tab->id_parent = $idParent;
                $tab->active = true;
                $tab->name = $this->localised($english, $italian);

                if (!$tab->add()) {
                    return false;
                }

                $idTab = (int) $tab->id;
            }

            // The warehouse hand should reach the warehouse and nothing else; the
            // counter staff likewise. Both also need the parent, or the menu entry
            // that contains their screen never renders.
            $this->grantProfile($profileName, $idTab);
            $this->grantProfile($profileName, $idParent);
        }

        return true;
    }

    private function uninstallTabs(): bool
    {
        $classNames = array_merge(array_keys(self::TABS), [self::PARENT_TAB]);

        foreach ($classNames as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);

            if ($idTab) {
                $tab = new Tab($idTab);

                if (Validate::isLoadedObject($tab) && !$tab->delete()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The "Sell" section of the sidebar, so the counter lands next to Orders and
     * Catalog rather than in a menu of its own. Falls back to the top level if a
     * future PrestaShop renames the section.
     */
    private function sellSectionId(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab`
             WHERE class_name = "SELL" AND id_parent = 0 LIMIT 1'
        );
    }

    /**
     * @return array<int, string> the same wording in every installed language,
     *                            Italian where the shop speaks it
     */
    private function localised(string $english, string $italian): array
    {
        $idItalian = (int) Language::getIdByIso('it');
        $names = [];

        foreach (Language::getIDs(false) as $idLang) {
            $names[(int) $idLang] = ((int) $idLang === $idItalian) ? $italian : $english;
        }

        return $names;
    }

    private function grantProfile(string $profileName, int $idTab): void
    {
        $idProfile = (int) Db::getInstance()->getValue(
            'SELECT id_profile FROM `' . _DB_PREFIX_ . 'profile_lang`
             WHERE name = "' . pSQL($profileName) . '" LIMIT 1'
        );

        if (!$idProfile) {
            return;
        }

        $access = new Access();

        foreach (['view', 'add', 'edit', 'delete'] as $action) {
            $access->updateLgcAccess($idProfile, $idTab, $action, true);
        }
    }

    // ------------------------------------------------------- counter identity

    /**
     * A till needs a customer, an address and a carrier before PrestaShop will
     * turn a cart into an order. None of it is ever shown to anybody — it exists
     * so a walk-in sale can travel the same code path as an online one.
     */
    private function provisionCounterIdentity(): bool
    {
        $route = $this->resolveCounterRoute();

        if ($route === null) {
            $this->_errors[] = $this->trans(
                'No free carrier is available for any active country, so counter sales '
                . 'could not be set up. Create a free "pick up in store" carrier, then install this module again.',
                [],
                'Modules.Shopfloor.Admin'
            );

            return false;
        }

        $customer = $this->ensureCounterCustomer();

        if ($customer === null) {
            return false;
        }

        $address = $this->ensureCounterAddress($customer, (int) $route['id_country']);

        if ($address === null) {
            return false;
        }

        Configuration::updateValue(self::CONF_CUSTOMER, (int) $customer->id);
        Configuration::updateValue(self::CONF_ADDRESS, (int) $address->id);
        Configuration::updateValue(self::CONF_CARRIER, (int) $route['id_carrier']);

        return true;
    }

    /**
     * Pick a country the shop can actually deliver to free of charge, preferring
     * the shop's own default. Without this the counter would build carts that
     * PrestaShop refuses to convert, because no carrier covers the address zone.
     *
     * @return array{id_country: int, id_carrier: int}|null
     */
    private function resolveCounterRoute(): ?array
    {
        $idDefault = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        $idGroup = (int) Configuration::get('PS_CUSTOMER_GROUP');

        $candidates = Db::getInstance()->executeS(
            'SELECT id_country FROM `' . _DB_PREFIX_ . 'country`
             WHERE active = 1
             ORDER BY (id_country = ' . $idDefault . ') DESC, id_country ASC'
        ) ?: [];

        foreach ($candidates as $candidate) {
            $idCountry = (int) $candidate['id_country'];
            $idZone = (int) Country::getIdZone($idCountry);

            if (!$idZone) {
                continue;
            }

            $idCarrier = (int) Db::getInstance()->getValue(
                'SELECT c.id_carrier
                 FROM `' . _DB_PREFIX_ . 'carrier` c
                 INNER JOIN `' . _DB_PREFIX_ . 'carrier_zone` cz
                    ON cz.id_carrier = c.id_carrier AND cz.id_zone = ' . $idZone . '
                 INNER JOIN `' . _DB_PREFIX_ . 'carrier_group` cg
                    ON cg.id_carrier = c.id_carrier AND cg.id_group = ' . $idGroup . '
                 WHERE c.active = 1 AND c.deleted = 0 AND c.is_free = 1
                 ORDER BY c.id_carrier ASC LIMIT 1'
            );

            if ($idCarrier) {
                return ['id_country' => $idCountry, 'id_carrier' => $idCarrier];
            }
        }

        return null;
    }

    private function ensureCounterCustomer(): ?Customer
    {
        $customer = new Customer((int) Configuration::get(self::CONF_CUSTOMER));

        if (Validate::isLoadedObject($customer)) {
            return $customer;
        }

        $email = 'counter@' . Configuration::get('PS_SHOP_DOMAIN');

        if (!Validate::isEmail($email)) {
            $email = 'counter@localhost.local';
        }

        $existing = (int) Db::getInstance()->getValue(
            'SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer`
             WHERE email = "' . pSQL($email) . '" AND deleted = 0 LIMIT 1'
        );

        if ($existing) {
            return new Customer($existing);
        }

        $customer = new Customer();
        $customer->firstname = 'Counter';
        $customer->lastname = 'Sale';
        $customer->email = $email;
        $customer->passwd = $this->randomPassword();
        // Kept in the ordinary customer group so it sees exactly the catalogue an
        // online shopper sees. A bespoke group would need category access rows of
        // its own, and the cart would start refusing products for no visible reason.
        $customer->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $customer->newsletter = false;
        $customer->optin = false;
        $customer->active = true;

        if (!$customer->add()) {
            $this->_errors[] = $this->trans('The counter customer could not be created.', [], 'Modules.Shopfloor.Admin');

            return null;
        }

        return $customer;
    }

    private function ensureCounterAddress(Customer $customer, int $idCountry): ?Address
    {
        $address = new Address((int) Configuration::get(self::CONF_ADDRESS));

        if (Validate::isLoadedObject($address)) {
            return $address;
        }

        $country = new Country($idCountry);

        $address = new Address();
        $address->id_customer = (int) $customer->id;
        $address->id_country = $idCountry;
        $address->alias = 'Counter';
        $address->firstname = 'Counter';
        $address->lastname = 'Sale';
        $address->address1 = Configuration::get('PS_SHOP_ADDR1') ?: 'In-store pickup';
        $address->city = Configuration::get('PS_SHOP_CITY') ?: 'Store';
        $address->postcode = $this->placeholderPostcode($country);

        if ($country->contains_states) {
            $address->id_state = (int) Db::getInstance()->getValue(
                'SELECT id_state FROM `' . _DB_PREFIX_ . 'state`
                 WHERE id_country = ' . $idCountry . ' AND active = 1 ORDER BY id_state ASC LIMIT 1'
            );
        }

        if (!$address->add()) {
            $this->_errors[] = $this->trans('The counter address could not be created.', [], 'Modules.Shopfloor.Admin');

            return null;
        }

        return $address;
    }

    /**
     * Build something that satisfies the country's postcode format. The address is
     * never posted anywhere, but PrestaShop still validates the shape.
     */
    private function placeholderPostcode(Country $country): string
    {
        $format = (string) $country->zip_code_format;

        if ($format === '') {
            return '00000';
        }

        $postcode = '';

        foreach (str_split($format) as $character) {
            if ($character === 'N') {
                $postcode .= '1';
            } elseif ($character === 'L') {
                $postcode .= 'A';
            } elseif ($character === 'C') {
                $postcode .= (string) $country->iso_code;
            } else {
                $postcode .= $character;
            }
        }

        return $postcode;
    }

    private function randomPassword(): string
    {
        $plaintext = Tools::passwdGen(32, 'RANDOM');

        try {
            /** @var \PrestaShop\PrestaShop\Core\Crypto\Hashing $crypto */
            $crypto = ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');

            return $crypto->hash($plaintext, _COOKIE_KEY_);
        } catch (Throwable $exception) {
            return md5(_COOKIE_KEY_ . $plaintext);
        }
    }

    // ------------------------------------------------------------------ sale

    /**
     * validateOrder() with the customer email held back for the duration.
     *
     * The flag is cleared in a finally block: if an order blows up half way, the
     * next email the shop tries to send — an online order confirmation, a password
     * reset — must not be swallowed because a counter sale failed earlier in the
     * same request.
     */
    public function validateOrderQuietly(
        int $idCart,
        int $idOrderState,
        float $amountPaid,
        string $paymentMethod,
        string $message,
        string $secureKey
    ): void {
        self::$silenceMail = true;

        try {
            $this->validateOrder(
                $idCart,
                $idOrderState,
                $amountPaid,
                $paymentMethod,
                $message !== '' ? $message : null,
                [],
                null,
                false,
                $secureKey
            );
        } finally {
            self::$silenceMail = false;
        }
    }

    // ------------------------------------------------------------------ hooks

    /**
     * Load the till stylesheet only on our own two screens, so the rest of the
     * back office is untouched.
     */
    public function hookActionAdminControllerSetMedia(): void
    {
        $controller = (string) Tools::getValue('controller');

        if (!array_key_exists($controller, self::TABS)) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/shopfloor.css', 'all', null, false);
    }

    /**
     * @return bool false cancels the send, which is what we want while a counter
     *              sale is being validated
     */
    public function hookActionEmailSendBefore(array $params): bool
    {
        return !self::$silenceMail;
    }
}
