<?php
/**
 * Diagnoses and repairs an admin sidebar that stays in English after the rest
 * of the back office has been translated.
 *
 *   php bin/retranslate_menu.php --iso=it            diagnose, then repair
 *   php bin/retranslate_menu.php --iso=it --check    diagnose only, write nothing
 *
 * The sidebar is the one part of the back office that is not read from the
 * translation catalogue when the page renders. Tab::getTabs() joins tab_lang on
 * the employee's language and takes the `name` column from there, so the menu
 * shows whatever was written into that table -- normally by the tab translator,
 * as a step of installing a language pack. A language added by hand through
 * International > Languages skips that step, so the rows keep the English text
 * they were copied from while every other string in the panel switches over.
 *
 * There are two different reasons the menu can stay English, and they need
 * opposite fixes, so this checks which one it is before touching anything:
 *
 *   1. The catalogue has the menu wordings, but tab_lang was never rewritten.
 *      Repairable here -- that is what the repair step below does.
 *   2. The Admin.Navigation.Menu domain is missing from the catalogue for this
 *      locale. Nothing to copy from, so this script cannot help; the language
 *      pack has to be installed from International > Translations first.
 *
 * Safe to re-run. The repair updates rows in place and only writes a field when
 * the translation actually differs from what is stored.
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

$opts = getopt('', ['iso::', 'check']);
$iso = (string) ($opts['iso'] ?? 'it');
$checkOnly = array_key_exists('check', $opts);

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
$db = Db::getInstance();

echo "Language: {$language->name}  id_lang={$langId}  locale={$language->locale}  active={$language->active}\n\n";

// ------------------------------------------------------- who sees this menu

// The back office reads the employee's language, not the shop default -- see
// config/config.inc.php, which copies employee->id_lang onto the cookie. If the
// employee is on another language, that is the row set the menu is reading and
// translating this one would change nothing they can see.
$employees = $db->executeS(
    'SELECT e.email, e.id_lang, l.iso_code
     FROM ' . _DB_PREFIX_ . 'employee e
     LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON l.id_lang = e.id_lang
     WHERE e.active = 1'
);

echo "Active employees and the language their panel reads:\n";
foreach ($employees as $employee) {
    $flag = ((int) $employee['id_lang'] === $langId) ? '' : '   <-- not ' . $iso;
    echo "  {$employee['email']}: {$employee['iso_code']}{$flag}\n";
}
echo "\n";

// --------------------------------------------- is the catalogue even there?

// The decisive check. The repair works by translating each menu wording through
// the Admin.Navigation.Menu domain, so if that domain is not loaded for this
// locale there is nothing to copy from and the repair is a no-op.
$translator = $kernel->getContainer()->get(TranslatorInterface::class);
$translator->clearLanguage($language->locale);

$probes = ['Orders', 'Catalog', 'Customers', 'Shipping', 'International'];
$translated = 0;

echo "Catalogue check (Admin.Navigation.Menu for {$language->locale}):\n";
foreach ($probes as $probe) {
    $result = $translator->trans($probe, [], 'Admin.Navigation.Menu', $language->locale);
    if ($result !== $probe) {
        ++$translated;
    }
    echo "  $probe -> $result\n";
}
echo "\n";

if (0 === $translated) {
    echo "The catalogue has no Italian for these wordings, so this is cause 2.\n";
    echo "Nothing here can fix that -- the menu has no translated text to copy from.\n\n";
    echo "Install the pack first: International > Translations > Add / update a language,\n";
    echo "pick the language, then run this script again.\n";
    exit(1);
}

// ------------------------------------------------------- what is stored now

$before = $db->executeS(
    'SELECT tl.id_tab, tl.name
     FROM ' . _DB_PREFIX_ . 'tab_lang tl
     INNER JOIN ' . _DB_PREFIX_ . 'tab t ON t.id_tab = tl.id_tab
     WHERE tl.id_lang = ' . $langId . ' AND t.id_parent IN (0, -1) AND t.active = 1
     ORDER BY t.position'
);

if (empty($before)) {
    echo "No tab_lang rows exist for this language at all, which is why the menu is\n";
    echo "falling back. Reinstall the language pack to create them.\n";
    exit(1);
}

echo "Top-level menu rows stored now:\n";
foreach ($before as $row) {
    echo "  {$row['name']}\n";
}
echo "\n";

if ($checkOnly) {
    echo "--check given, stopping without writing.\n";
    exit(0);
}

// ------------------------------------------------------------------ repair

// tab_lang carries no id_shop, so this is ignored for tabs -- but translate()
// takes it, and a sane value keeps it correct if that ever changes.
$shop = Context::getContext()->shop;
$shopId = ($shop && $shop->id) ? (int) $shop->id : (int) Configuration::get('PS_SHOP_DEFAULT');

(new EntityTranslatorFactory($translator))
    ->buildFromTableName('tab', $language->locale)
    ->translate($langId, $shopId);

// --------------------------------------------------------------- and after

$after = $db->executeS(
    'SELECT tl.id_tab, tl.name
     FROM ' . _DB_PREFIX_ . 'tab_lang tl
     INNER JOIN ' . _DB_PREFIX_ . 'tab t ON t.id_tab = tl.id_tab
     WHERE tl.id_lang = ' . $langId . ' AND t.id_parent IN (0, -1) AND t.active = 1
     ORDER BY t.position'
);

$previous = [];
foreach ($before as $row) {
    $previous[$row['id_tab']] = $row['name'];
}

$changed = 0;
echo "Top-level menu rows after the repair:\n";
foreach ($after as $row) {
    $was = $previous[$row['id_tab']] ?? '';
    if ($was !== $row['name']) {
        ++$changed;
        echo "  {$was}  ->  {$row['name']}\n";
    } else {
        echo "  {$row['name']}  (unchanged)\n";
    }
}

echo "\n$changed of " . count($after) . " top-level entries rewritten.\n";

if (0 === $changed) {
    echo "\nNothing changed even though the catalogue has translations. That means the\n";
    echo "stored names could not be matched back to a source wording -- check whether\n";
    echo "the tab table has its wording/wording_domain columns filled in.\n";
    exit(1);
}

echo "\nNow clear the cache, the menu is cached:\n";
echo "  php bin/console cache:clear --env=prod\n";
