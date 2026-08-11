<?php
/**
 * Retranslates the back-office menu (the admin sidebar) for one language.
 *
 *   php bin/retranslate_menu.php --iso=it
 *
 * The sidebar is the one part of the back office that is NOT read from the
 * translation catalogue when the page renders. Its labels are rows in
 * ps_tab_lang, written once by the tab translator as a step of installing a
 * language pack. A language added by hand through International > Languages
 * never runs that step, so the menu keeps the English names it was copied from
 * while every other string in the panel switches over.
 *
 * This runs that step on its own. Safe to re-run: it updates the existing rows
 * in place, and only writes a field when the translation actually differs.
 *
 * The Symfony catalogue for the language has to be installed already. Without
 * it every wording resolves back to English and the run is a no-op -- if the
 * rest of the back office is already translated, it is installed.
 *
 * The menu is cached, so clear the cache before checking the panel.
 */

declare(strict_types=1);

use PrestaShop\PrestaShop\Adapter\EntityTranslation\EntityTranslatorFactory;
use PrestaShopBundle\Translation\TranslatorInterface;

require_once __DIR__ . '/../config/config.inc.php';
require_once __DIR__ . '/../app/AdminKernel.php';

global $kernel;
$kernel = new AdminKernel('prod', false);
$kernel->boot();

// ---------------------------------------------------------------- arguments

$opts = getopt('', ['iso::']);
$iso = (string) ($opts['iso'] ?? 'it');

if (!Validate::isLanguageIsoCode($iso)) {
    fwrite(STDERR, "'$iso' is not a valid two-letter ISO code.\n");
    exit(1);
}

$langId = (int) Language::getIdByIso($iso, true);

if (!$langId) {
    fwrite(STDERR, "No language is installed with ISO code '$iso'.\n");
    exit(1);
}

$language = new Language($langId);

// ---------------------------------------------------------------- translate

// tab_lang carries no id_shop, so this is ignored for tabs -- but translate()
// takes it, and a sane value keeps it correct if that ever changes.
$shop = Context::getContext()->shop;
$shopId = ($shop && $shop->id) ? (int) $shop->id : (int) Configuration::get('PS_SHOP_DEFAULT');

$translator = $kernel->getContainer()->get(TranslatorInterface::class);

// The catalogue is cached per locale. Drop it, so wordings installed after this
// kernel last cached them are picked up instead of the stale set.
$translator->clearLanguage($language->locale);

(new EntityTranslatorFactory($translator))
    ->buildFromTableName('tab', $language->locale)
    ->translate($langId, $shopId);

// ------------------------------------------------------------------- report

// Read back what is in the table now, so the run can be checked rather than
// trusted. These are the top-level menu entries, which are the ones you see.
$rows = Db::getInstance()->executeS(
    'SELECT tl.name
     FROM ' . _DB_PREFIX_ . 'tab_lang tl
     INNER JOIN ' . _DB_PREFIX_ . 'tab t ON t.id_tab = tl.id_tab
     WHERE tl.id_lang = ' . $langId . ' AND t.id_parent IN (0, -1) AND t.active = 1
     ORDER BY t.position'
);

echo "Menu retranslated for {$language->name} ({$language->locale}).\n\n";
echo "Top-level entries now read:\n";

foreach ($rows as $row) {
    echo '  ' . $row['name'] . "\n";
}

echo "\nIf these are still English, the it-IT catalogue is not installed --\n";
echo "install the language pack from International > Translations first.\n";
echo "\nNow clear the cache:  php bin/console cache:clear --env=prod\n";
