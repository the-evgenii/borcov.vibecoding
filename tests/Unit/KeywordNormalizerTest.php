<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\services\KeywordNormalizer;
use app\services\LanguageCatalog;
use app\services\UrlGuard;
use PHPUnit\Framework\TestCase;

final class KeywordNormalizerTest extends TestCase
{
    public function testNormalizesCasePunctuationAndWhitespace(): void
    {
        $normalizer = new KeywordNormalizer();

        self::assertSame('site pro website builder', $normalizer->normalize('  Site.Pro   Website-Builder! '));
        self::assertSame('créateur de site web', $normalizer->normalize('Créateur de site web'));
        self::assertSame('интернет магазин', $normalizer->normalize('  Интернет-магазин  '));
    }

    public function testDisplayKeepsReadableCaseButCollapsesWhitespace(): void
    {
        $normalizer = new KeywordNormalizer();

        self::assertSame('Site.Pro Website Builder', $normalizer->display("  Site.Pro \n Website   Builder "));
    }

    public function testCleanTextTreatsLfAsWhitespace(): void
    {
        $normalizer = new KeywordNormalizer();

        self::assertSame('alpha beta', $normalizer->cleanText("alpha\nbeta"));
        self::assertSame('alpha beta', $normalizer->normalize("alpha\nbeta"));
    }

    public function testLanguageAliasesAndRegionalCodes(): void
    {
        self::assertSame('en', LanguageCatalog::normalize('en-US'));
        self::assertSame('en', LanguageCatalog::normalize('English'));
        self::assertSame('de', LanguageCatalog::normalize('de_DE'));
        self::assertSame('fr', LanguageCatalog::normalize('français'));
        self::assertSame('ru', LanguageCatalog::normalize('русский'));
        self::assertSame('ja', LanguageCatalog::normalize('ja'));
        self::assertSame('', LanguageCatalog::normalize('  '));
    }

    public function testJunkRules(): void
    {
        $normalizer = new KeywordNormalizer();

        self::assertTrue($normalizer->isJunk(''));
        self::assertTrue($normalizer->isJunk('!!!'));
        self::assertTrue($normalizer->isJunk('12345'));
        self::assertTrue($normalizer->isJunk('asdf'));
        self::assertTrue($normalizer->isJunk('http://example.com'));
        self::assertTrue($normalizer->isJunk('buy <b>cheap</b> traffic'));
        self::assertTrue($normalizer->isJunk('=SUM(1,2)'));
        self::assertTrue($normalizer->isJunk(str_repeat('a', 81)));
        self::assertTrue($normalizer->isJunk('one two three four five six seven eight nine ten eleven'));
        self::assertTrue($normalizer->isJunk('aaaaaaaa'));
        self::assertFalse($normalizer->isJunk('website builder'));
        self::assertFalse($normalizer->isJunk('интернет магазин'));
    }

    public function testPhraseMatchingUsesWholeNormalizedWords(): void
    {
        $normalizer = new KeywordNormalizer();

        self::assertSame('site.pro', $normalizer->matchAny('best site pro builder', ['site.pro']));
        self::assertNull($normalizer->matchAny('sites pro builder', ['site.pro']));
        self::assertNull($normalizer->matchAny('freedom website', ['free']));
    }

    public function testUrlGuardAcceptsOnlyHttpAndHttps(): void
    {
        self::assertTrue(UrlGuard::isHttpUrl('https://site.pro/'));
        self::assertTrue(UrlGuard::isHttpUrl('http://example.com/path?x=1'));
        self::assertFalse(UrlGuard::isHttpUrl('ftp://site.pro/'));
        self::assertFalse(UrlGuard::isHttpUrl('javascript:alert(1)'));
        self::assertFalse(UrlGuard::isHttpUrl('/relative/path'));
        self::assertFalse(UrlGuard::isHttpUrl('https://exa mple.com'));
    }
}
