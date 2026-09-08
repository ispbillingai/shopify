<?php
/**
 * 1.0.0 -> 1.1.0: warehouse articles.
 *
 * Adds the gestionale article table, the Articoli screen, and the id_article
 * column the movement ledger needs to record a movement against an article
 * rather than a shop product.
 *
 * Written as an upgrade rather than a one-off script so a rebuild from the repo
 * lands in the same state as the live server.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0(ShopFloor $module): bool
{
    require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorArticle.php';
    require_once _PS_MODULE_DIR_ . 'shopfloor/classes/ShopFloorLedger.php';

    ShopFloorArticle::createTable();
    ShopFloorLedger::ensureArticleColumn();

    if (Tab::getIdFromClassName('AdminArticles')) {
        return true;
    }

    $idParent = (int) Tab::getIdFromClassName('AdminShopFloor');

    if (!$idParent) {
        return false;
    }

    $idItalian = (int) Language::getIdByIso('it');
    $names = [];

    foreach (Language::getIDs(false) as $idLang) {
        $names[(int) $idLang] = ((int) $idLang === $idItalian) ? 'Articoli magazzino' : 'Warehouse articles';
    }

    $tab = new Tab();
    $tab->class_name = 'AdminArticles';
    $tab->module = $module->name;
    $tab->id_parent = $idParent;
    $tab->active = true;
    $tab->name = $names;

    if (!$tab->add()) {
        return false;
    }

    // Same reach as the Magazzino screen: whoever loads the goods.
    $idProfile = (int) Db::getInstance()->getValue(
        'SELECT id_profile FROM `' . _DB_PREFIX_ . 'profile_lang` WHERE name = "Logistician"'
    );

    if ($idProfile) {
        $access = new Access();

        foreach (['view', 'add', 'edit', 'delete'] as $action) {
            $access->updateLgcAccess($idProfile, (int) $tab->id, $action, true);
        }
    }

    return true;
}
