<?php

declare(strict_types=1);

namespace app\services;

final class LanguageCatalog
{
    /**
     * Languages with hand-written, reviewed ad copy. Adding a language here is
     * deliberate: the generator never invents a translation for an unknown code.
     */
    private const LANGUAGES = [
        'en' => [
            'name' => 'English',
            'display_name' => 'Английский',
            'url' => 'https://site.pro/Website-Builder/',
        ],
        'de' => [
            'name' => 'German',
            'display_name' => 'Немецкий',
            'url' => 'https://site.pro/de/',
        ],
        'fr' => [
            'name' => 'French',
            'display_name' => 'Французский',
            'url' => 'https://site.pro/fr/',
        ],
        'es' => [
            'name' => 'Spanish',
            'display_name' => 'Испанский',
            'url' => 'https://site.pro/es/',
        ],
        'lt' => [
            'name' => 'Lithuanian',
            'display_name' => 'Литовский',
            'url' => 'https://site.pro/lt/',
        ],
        'ru' => [
            'name' => 'Russian',
            'display_name' => 'Русский',
            'url' => 'https://site.pro/ru/',
        ],
    ];

    private const NAME_ALIASES = [
        'english' => 'en',
        'eng' => 'en',
        'german' => 'de',
        'deutsch' => 'de',
        'ger' => 'de',
        'french' => 'fr',
        'français' => 'fr',
        'francais' => 'fr',
        'fre' => 'fr',
        'spanish' => 'es',
        'español' => 'es',
        'espanol' => 'es',
        'spa' => 'es',
        'lithuanian' => 'lt',
        'lietuvių' => 'lt',
        'lietuviu' => 'lt',
        'lit' => 'lt',
        'russian' => 'ru',
        'русский' => 'ru',
        'rus' => 'ru',
    ];

    public static function codes(): array
    {
        return array_keys(self::LANGUAGES);
    }

    public static function all(): array
    {
        return self::LANGUAGES;
    }

    public static function isSupported(string $code): bool
    {
        return isset(self::LANGUAGES[$code]);
    }

    public static function name(string $code): string
    {
        return self::LANGUAGES[$code]['name'] ?? mb_strtoupper($code);
    }

    /**
     * Russian display names for the UI. Stored campaign names keep the
     * canonical English names so generated data stays stable.
     */
    public static function displayName(string $code): string
    {
        return self::LANGUAGES[$code]['display_name'] ?? mb_strtoupper($code);
    }

    public static function displayNameForStoredName(string $name): string
    {
        foreach (self::LANGUAGES as $code => $language) {
            if ($language['name'] === $name) {
                return self::displayName($code);
            }
        }

        return $name;
    }

    /**
     * @return array<string, string>
     */
    public static function defaultUrls(): array
    {
        return array_map(static fn (array $language): string => $language['url'], self::LANGUAGES);
    }

    /**
     * Accepts ISO-like codes (en, en-US, en_US), common names and three-letter
     * codes. Unknown values are returned as-is so the UI can report them
     * explicitly instead of silently pretending they are English.
     */
    public static function normalize(string $raw): string
    {
        $value = trim(mb_strtolower($raw));
        $value = str_replace(['_', '–', '—'], ['-', '-', '-'], $value);
        if ($value === '') {
            return '';
        }

        if (isset(self::NAME_ALIASES[$value])) {
            return self::NAME_ALIASES[$value];
        }

        $parts = preg_split('/[\s().\/]+/u', $value) ?: [];
        $base = $parts[0] ?? $value;
        $base = preg_split('/[-]/u', $base)[0] ?? $base;

        if (isset(self::NAME_ALIASES[$base])) {
            return self::NAME_ALIASES[$base];
        }

        return preg_match('/^[a-z]{2,3}$/', $base) === 1 ? $base : mb_substr($value, 0, 16);
    }
}
