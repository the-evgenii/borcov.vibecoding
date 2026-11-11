<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Keyword;
use app\models\KeywordSource;
use app\tests\Support\Seed;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class AuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        TestDb::settings(
            ['site.pro', 'site pro'],
            ['casino', 'free'],
            50,
        );
    }

    public function testCleaningReasonsAndCrossSourceAlreadyUsedRule(): void
    {
        Seed::csv(KeywordSource::GOOGLE_ADS, [
            ['cross source used', 100, 'en'],
        ]);
        Seed::csv(KeywordSource::AHREFS_ORGANIC, [
            ['cross source used', 500, 'en'],
            ['very low volume keyword', 10, 'en'],
            ['asdf', 100, 'en'],
        ]);
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['brand new keyword site.pro', 100, 'en'],
            ['clean unique keyword', 100, 'en'],
        ]);
        Seed::csv(KeywordSource::AHREFS_PAID, [
            ['casino website builder', 100, 'en'],
            ['clean unique keyword', 300, 'en'],
            ['=SUM(1,2)', 100, 'en'],
        ]);

        $result = TestDb::audit();

        $used = Keyword::find()->where(['keyword' => 'cross source used'])->all();
        self::assertCount(2, $used);
        foreach ($used as $record) {
            self::assertSame(Keyword::STATUS_EXCLUDED, $record->status);
            self::assertContains(Keyword::REASON_ALREADY_USED, $record->getReasons());
        }

        $brand = Keyword::find()->where(['keyword' => 'brand new keyword site.pro'])->one();
        self::assertNotNull($brand);
        self::assertSame(Keyword::STATUS_EXCLUDED, $brand->status);
        self::assertContains(Keyword::REASON_BRAND, $brand->getReasons());

        $forbidden = Keyword::find()->where(['keyword' => 'casino website builder'])->one();
        self::assertNotNull($forbidden);
        self::assertSame(Keyword::STATUS_EXCLUDED, $forbidden->status);
        self::assertContains(Keyword::REASON_FORBIDDEN, $forbidden->getReasons());

        $low = Keyword::find()->where(['keyword' => 'very low volume keyword'])->one();
        self::assertNotNull($low);
        self::assertSame(Keyword::STATUS_EXCLUDED, $low->status);
        self::assertContains(Keyword::REASON_LOW_VOLUME, $low->getReasons());

        $junk = Keyword::find()->where(['keyword' => 'asdf'])->one();
        self::assertNotNull($junk);
        self::assertContains(Keyword::REASON_JUNK, $junk->getReasons());

        $formula = Keyword::find()->where(['keyword' => '=SUM(1,2)'])->one();
        self::assertNotNull($formula);
        self::assertContains(Keyword::REASON_JUNK, $formula->getReasons());

        self::assertSame(300, (int) $result['candidates']['en|clean unique keyword']['volume']);
        self::assertEqualsCanonicalizing(
            [KeywordSource::SEARCH_CONSOLE, KeywordSource::AHREFS_PAID],
            $result['candidates']['en|clean unique keyword']['sources'],
        );

        $clean = Keyword::find()->where(['keyword' => 'clean unique keyword'])->orderBy(['id' => SORT_ASC])->all();
        self::assertCount(2, $clean);
        self::assertSame(Keyword::STATUS_INCLUDED, $clean[0]->status);
        self::assertSame(Keyword::STATUS_DUPLICATE, $clean[1]->status);
        self::assertSame(1, (int) $result['summary']['duplicate_records']);
    }

    public function testSettingsChangeRecalculatesPersistedStatuses(): void
    {
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['middle volume keyword', 100, 'en'],
        ]);
        $record = Keyword::find()->where(['keyword' => 'middle volume keyword'])->one();
        self::assertNotNull($record);
        self::assertSame(Keyword::STATUS_INCLUDED, $record->status);

        $setting = TestDb::settings(['site.pro'], ['casino'], 150);
        self::assertSame(150, (int) $setting->min_volume);
        TestDb::audit();
        $record->refresh();
        self::assertSame(Keyword::STATUS_EXCLUDED, $record->status);
        self::assertContains(Keyword::REASON_LOW_VOLUME, $record->getReasons());
    }
}
