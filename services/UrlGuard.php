<?php

declare(strict_types=1);

namespace app\services;

final class UrlGuard
{
    public static function isHttpUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 2048) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $url) === 1 || str_contains($url, '\\')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = mb_strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return trim((string) $parts['host']) !== '';
    }
}
