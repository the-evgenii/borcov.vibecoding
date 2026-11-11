<?php

declare(strict_types=1);

namespace app\models;

final class KeywordSource
{
    public const GOOGLE_ADS = 'google_ads';
    public const SEARCH_CONSOLE = 'search_console';
    public const AHREFS_ORGANIC = 'ahrefs_organic';
    public const AHREFS_PAID = 'ahrefs_paid';

    public static function all(): array
    {
        return [
            self::GOOGLE_ADS,
            self::SEARCH_CONSOLE,
            self::AHREFS_ORGANIC,
            self::AHREFS_PAID,
        ];
    }

    public static function labels(): array
    {
        return [
            self::GOOGLE_ADS => 'Google Ads',
            self::SEARCH_CONSOLE => 'Search Console',
            self::AHREFS_ORGANIC => 'Ahrefs organic',
            self::AHREFS_PAID => 'Ahrefs paid (competitors)',
        ];
    }

    public static function label(string $source): string
    {
        return self::labels()[$source] ?? $source;
    }

    public static function isValid(string $source): bool
    {
        return in_array($source, self::all(), true);
    }
}
