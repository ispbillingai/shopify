<?php
/**
 * Shopify-style back office skin.
 *
 * Injects a stylesheet into every admin page. Nothing in PrestaShop core is
 * touched, so upgrades will not overwrite this and it can be switched off by
 * disabling the module.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopifyLook extends Module
{
    public function __construct()
    {
        $this->name = 'shopifylook';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'ispledger';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'Shopify-style admin skin';
        $this->description = 'Restyles the back office: light canvas, white cards, dark top bar, pill navigation.';
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('actionAdminControllerSetMedia');
    }

    public function uninstall(): bool
    {
        return parent::uninstall();
    }

    /**
     * Loaded on every back office page, after the theme's own stylesheets.
     */
    public function hookActionAdminControllerSetMedia(): void
    {
        // The ?v= is load-bearing: the vhost caches .css and .js for a week, so
        // without it an edit to this skin would not reach anyone already using
        // the back office until their cache expired.
        $this->context->controller->addCSS(
            $this->_path . 'views/css/admin.css?v=' . $this->version,
            'all',
            null,
            false
        );

        // Clears the icon-font holding pattern in admin.css as soon as the 3.2 MB
        // Material Symbols file resolves.
        $this->context->controller->addJS($this->_path . 'views/js/shopifylook.js?v=' . $this->version, false);
    }
}
