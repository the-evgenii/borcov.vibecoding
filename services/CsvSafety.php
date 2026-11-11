<?php

declare(strict_types=1);

namespace app\services;

final class CsvSafety
{
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@'];

    public static function isDangerous(string $value): bool
    {
        $trimmed = ltrim($value, " \t\r\n");
        if ($trimmed === '') {
            return false;
        }

        return in_array($trimmed[0], self::FORMULA_TRIGGERS, true);
    }

    public static function assertTextSafe(string $value, string $field): void
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new ExportException(sprintf('Поле «%s» не является корректным UTF-8.', $field));
        }

        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new ExportException(sprintf('Поле «%s» не должно содержать переносы строк.', $field));
        }

        if (self::isDangerous($value)) {
            // Fail closed: prefixing an apostrophe would corrupt the exact
            // keyword/ad text that Google Ads Editor imports.
            throw new ExportException(sprintf(
                'Поле «%s» начинается с символа формулы и не может быть безопасно экспортировано.',
                $field,
            ));
        }
    }
}
