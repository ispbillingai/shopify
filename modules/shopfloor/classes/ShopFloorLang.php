<?php
/**
 * Copy for the counter and warehouse screens.
 *
 * PrestaShop's own translation system keeps strings in the database and expects
 * somebody to type the Italian into the back office after installing. These two
 * screens are used by Italian staff every day, so the Italian ships with the
 * code instead — see lang/en.php and lang/it.php.
 *
 * The language is the signed-in employee's, not the shop's: the warehouse hand
 * and the person at the till each get their own, and neither is affected by
 * whatever language a customer is browsing the storefront in.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopFloorLang
{
    /** @var array<string, string>|null */
    private static $strings = null;

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$strings !== null) {
            return self::$strings;
        }

        $base = self::load('en');
        $iso = self::employeeIso();

        // Merged key by key, so a string missing from the Italian file falls back
        // to English rather than rendering as an empty label.
        self::$strings = ($iso === 'en') ? $base : array_merge($base, self::load($iso));

        return self::$strings;
    }

    /**
     * @param array<string, string|int> $replace placeholder => value, e.g. ['%p' => 'Ring']
     */
    public static function get(string $key, array $replace = []): string
    {
        $all = self::all();
        $text = $all[$key] ?? $key;

        foreach ($replace as $token => $value) {
            $text = str_replace($token, (string) $value, $text);
        }

        return $text;
    }

    /**
     * Everything the browser needs, for the strings the screens build in JS.
     */
    public static function toJson(): string
    {
        return (string) json_encode(self::all(), JSON_UNESCAPED_UNICODE);
    }

    private static function employeeIso(): string
    {
        $context = Context::getContext();

        $idLang = 0;

        if (isset($context->employee) && Validate::isLoadedObject($context->employee)) {
            $idLang = (int) $context->employee->id_lang;
        }

        if (!$idLang && isset($context->language) && $context->language->id) {
            $idLang = (int) $context->language->id;
        }

        if (!$idLang) {
            return 'en';
        }

        $language = new Language($idLang);

        if (!Validate::isLoadedObject($language)) {
            return 'en';
        }

        $iso = Tools::strtolower((string) $language->iso_code);

        // Guard the path: the iso code decides which file is required.
        return preg_match('/^[a-z]{2}$/', $iso) ? $iso : 'en';
    }

    /**
     * @return array<string, string>
     */
    private static function load(string $iso): array
    {
        $file = __DIR__ . '/../lang/' . $iso . '.php';

        if (!is_file($file)) {
            return [];
        }

        $strings = require $file;

        return is_array($strings) ? $strings : [];
    }
}
