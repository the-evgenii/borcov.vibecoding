<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\services\LanguageCatalog;
use app\services\UrlGuard;
use PHPUnit\Framework\TestCase;

final class LanguageCatalogTest extends TestCase
{
    public function testEnglishDefaultUsesDedicatedEnglishLandingPage(): void
    {
        $urls = LanguageCatalog::defaultUrls();

        self::assertSame('https://site.pro/Website-Builder/', $urls['en']);
        self::assertSame('https://site.pro/de/', $urls['de']);
        self::assertSame('https://site.pro/fr/', $urls['fr']);
        self::assertSame('https://site.pro/es/', $urls['es']);
        self::assertSame('https://site.pro/lt/', $urls['lt']);
        self::assertSame('https://site.pro/ru/', $urls['ru']);

        foreach ($urls as $language => $url) {
            self::assertTrue(UrlGuard::isHttpUrl($url), $language);
        }
    }
}
