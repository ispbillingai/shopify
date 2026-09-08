<?php
/**
 * Import the gestionale article export into the warehouse.
 *
 *   php bin/import_artico.php --file=/var/imports/artico/latest.xlsx
 *   php bin/import_artico.php --file=... --dry-run
 *   php bin/import_artico.php --file=... --limit=50
 *
 * Reads the .xlsx straight, so the file the gestionale produces can be used as
 * it comes with no spreadsheet step in between. An .xlsx is a zip of XML, and
 * PHP has both built in.
 *
 * Re-runnable: articles are matched on Codice and updated in place, so running
 * it again refreshes quantities rather than duplicating rows. New codes are
 * added, and codes that have disappeared from the export are left alone,
 * because a shortened export must never silently wipe stock.
 *
 * Columns are found by their header text, not their position, so the gestionale
 * moving a column does not quietly shift every value one place.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

if (!defined('_PS_ADMIN_DIR_')) {
    define('_PS_ADMIN_DIR_', $root . '/admin');
}

require_once $root . '/config/config.inc.php';
require_once $root . '/modules/shopfloor/classes/ShopFloorArticle.php';

$options = getopt('', ['file:', 'dry-run', 'limit::', 'help']);

if (isset($options['help']) || !isset($options['file'])) {
    fwrite(STDOUT, "usage: php bin/import_artico.php --file=<export.xlsx> [--dry-run] [--limit=N]\n");
    exit(isset($options['help']) ? 0 : 1);
}

$file = (string) $options['file'];
$dryRun = array_key_exists('dry-run', $options);
$limit = isset($options['limit']) ? (int) $options['limit'] : 0;

if (!is_readable($file)) {
    fwrite(STDERR, 'cannot read ' . $file . "\n");
    exit(1);
}

ShopFloorArticle::createTable();

function columnIndex(string $ref): int
{
    preg_match('/([A-Z]+)/', $ref, $m);
    $n = 0;

    foreach (str_split($m[1] ?? 'A') as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }

    return $n - 1;
}

/**
 * An .xlsx cell holding text stores an index into sharedStrings.xml rather than
 * the text itself, so that table has to be read first.
 *
 * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
 */
function readSheet(string $path): array
{
    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        fwrite(STDERR, 'not a readable xlsx: ' . $path . "\n");
        exit(1);
    }

    $strings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedXml !== false) {
        $shared = new SimpleXMLElement($sharedXml);

        foreach ($shared->si as $si) {
            $text = '';

            foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                $text .= (string) $t;
            }

            $strings[] = $text;
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        fwrite(STDERR, 'no sheet1 in ' . $path . "\n");
        exit(1);
    }

    $sheet = new SimpleXMLElement($sheetXml);
    $rows = [];

    foreach ($sheet->sheetData->row as $row) {
        $cells = [];

        foreach ($row->c as $c) {
            $type = (string) $c['t'];

            if ($type === 'inlineStr') {
                $value = '';

                foreach ($c->xpath('.//*[local-name()="t"]') as $t) {
                    $value .= (string) $t;
                }
            } else {
                $raw = isset($c->v) ? (string) $c->v : '';
                $value = ($type === 's' && $raw !== '') ? ($strings[(int) $raw] ?? '') : $raw;
            }

            $cells[columnIndex((string) $c['r'])] = trim($value);
        }

        $rows[] = $cells;
    }

    $header = array_shift($rows) ?: [];

    return [$header, $rows];
}

/**
 * Excel keeps dates as a day count from 1899-12-30.
 */
function excelDate(string $serial): ?string
{
    if ($serial === '' || !is_numeric($serial)) {
        return null;
    }

    $days = (int) $serial;

    if ($days < 1 || $days > 60000) {
        return null;
    }

    return (new DateTime('1899-12-30'))->modify('+' . $days . ' days')->format('Y-m-d');
}

function decimal(string $value): float
{
    return (float) str_replace(',', '.', $value === '' ? '0' : $value);
}

/**
 * MANODOPERA, DIRITTO DI CHIAMATA, SPESE DI TRASPORTO: billing lines the
 * gestionale keeps beside real articles. Flagged, never dropped, so the list
 * still matches the gestionale and somebody can check the call.
 */
function looksLikeService(string $description): bool
{
    return (bool) preg_match('/(MANODOPER|DIRITTO|CHIAMAT|SPESE DI TRASPORT|NOLEGG|SMALTIMENT)/i', $description);
}

/**
 * @param array<int, string> $row
 * @param array<string, int> $byName
 */
function cell(array $row, array $byName, string $name): string
{
    $index = $byName[mb_strtolower($name)] ?? null;

    return $index === null ? '' : (string) ($row[$index] ?? '');
}

list($header, $rows) = readSheet($file);

$byName = [];

foreach ($header as $index => $name) {
    $byName[mb_strtolower(trim((string) $name))] = $index;
}

if (!isset($byName['codice'])) {
    fwrite(STDERR, 'the export has no Codice column; found: ' . implode(', ', $header) . "\n");
    exit(1);
}

$db = Db::getInstance();
$table = ShopFloorArticle::tableName();
$created = 0;
$updated = 0;
$services = 0;
$skipped = 0;
$negative = 0;
$processed = 0;

foreach ($rows as $row) {
    $code = cell($row, $byName, 'Codice');

    if ($code === '') {
        ++$skipped;
        continue;
    }

    if ($limit > 0 && $processed >= $limit) {
        break;
    }

    ++$processed;

    $description = cell($row, $byName, 'Descrizione');
    $quantity = decimal(cell($row, $byName, 'Esistenza'));
    $isService = looksLikeService($description) ? 1 : 0;

    if ($isService) {
        ++$services;
    }

    if ($quantity < 0) {
        ++$negative;
    }

    if ($dryRun) {
        continue;
    }

    $data = [
        'code' => pSQL($code),
        'barcode' => pSQL(cell($row, $byName, 'Barcode')),
        'description' => pSQL(Tools::substr($description, 0, 255)),
        'class' => pSQL(cell($row, $byName, 'Classe Merc.')),
        'subclass' => pSQL(cell($row, $byName, 'Sotto classe')),
        'location' => pSQL(cell($row, $byName, 'Ubicazione')),
        'supplier' => pSQL(Tools::substr(cell($row, $byName, 'Fornitore'), 0, 190)),
        'supplier_code' => pSQL(cell($row, $byName, 'Cod. Fornitore 1')),
        'quantity' => $quantity,
        'available' => decimal(cell($row, $byName, 'Disponibile')),
        'on_order' => decimal(cell($row, $byName, 'Ordinato')),
        'list_price' => decimal(cell($row, $byName, 'LISTINO')),
        'cost_price' => decimal(cell($row, $byName, 'Prezzo Acq.')),
        'sell_price' => decimal(cell($row, $byName, 'Prezzo Ven. 4')),
        'vat_rate' => decimal(cell($row, $byName, 'IVA')),
        'is_service' => $isService,
    ];

    $assignments = [];

    foreach ($data as $column => $value) {
        $assignments[] = '`' . $column . '` = ' . (is_string($value) ? '"' . $value . '"' : $value);
    }

    $lastMovement = excelDate(cell($row, $byName, 'Ult. Data Mov.'));
    $assignments[] = '`last_movement` = ' . ($lastMovement === null ? 'NULL' : '"' . pSQL($lastMovement) . '"');
    $assignments[] = '`date_upd` = NOW()';

    $existing = (int) $db->getValue('SELECT id_article FROM `' . $table . '` WHERE code = "' . $data['code'] . '"');

    if ($existing) {
        $db->execute('UPDATE `' . $table . '` SET ' . implode(', ', $assignments) . ' WHERE id_article = ' . $existing);
        ++$updated;
    } else {
        $assignments[] = '`date_add` = NOW()';
        $db->execute('INSERT INTO `' . $table . '` SET ' . implode(', ', $assignments));
        ++$created;
    }
}

$total = (int) $db->getValue('SELECT COUNT(*) FROM `' . $table . '`');

fwrite(STDOUT, sprintf(
    "%s\n  rows read      %d\n  created        %d\n  updated        %d\n  skipped        %d (no Codice)\n  service lines  %d (flagged, not stock)\n  negative stock %d (imported as-is)\n  in table now   %d\n",
    $dryRun ? 'DRY RUN, nothing written' : 'Import complete',
    count($rows),
    $created,
    $updated,
    $skipped,
    $services,
    $negative,
    $total
));
