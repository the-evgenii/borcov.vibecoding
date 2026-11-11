<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\services\AdCopyException;
use app\services\AdCopyGenerator;
use app\services\LanguageCatalog;
use PHPUnit\Framework\TestCase;

final class AdCopyGeneratorTest extends TestCase
{
    private const KEYWORDS = [
        'en' => 'website builder',
        'de' => 'website baukasten',
        'fr' => 'créateur de site web',
        'es' => 'creador de páginas web',
        'lt' => 'svetainių kūrimas',
        'ru' => 'конструктор сайтов',
    ];

    public function testEverySupportedLanguageProducesValidRsaAssets(): void
    {
        $generator = new AdCopyGenerator();

        foreach (self::KEYWORDS as $language => $keyword) {
            $copy = $generator->generate($language, $keyword);

            self::assertCount(5, $copy['headlines'], $language);
            self::assertCount(3, $copy['descriptions'], $language);
            self::assertSame(5, count(array_unique(array_map('mb_strtolower', $copy['headlines']))), $language);
            self::assertLessThanOrEqual(30, max(array_map('mb_strlen', $copy['headlines'])), $language);
            self::assertLessThanOrEqual(90, max(array_map('mb_strlen', $copy['descriptions'])), $language);
            self::assertLessThanOrEqual(15, mb_strlen($copy['path1']));
            self::assertLessThanOrEqual(15, mb_strlen($copy['path2']));
            self::assertNotSame('', $copy['adName']);
            self::assertTrue(LanguageCatalog::isSupported($language));
        }
    }

    public function testKeywordsAppearInTheFirstHeadlineWhereTheyFit(): void
    {
        $generator = new AdCopyGenerator();
        $copy = $generator->generate('en', 'website builder');

        self::assertSame('Website builder', $copy['headlines'][0]);
    }

    public function testLongKeywordIsShortenedInsteadOfBreakingTheLimit(): void
    {
        $generator = new AdCopyGenerator();
        $copy = $generator->generate('en', 'the best website builder for small business owners');

        self::assertLessThanOrEqual(30, mb_strlen($copy['headlines'][0]));
        self::assertCount(5, $copy['headlines']);
    }

    public function testUnsupportedLanguageIsReportedInsteadOfInventingCopy(): void
    {
        $this->expectException(AdCopyException::class);
        (new AdCopyGenerator())->generate('ja', 'ウェブサイト作成');
    }
}
