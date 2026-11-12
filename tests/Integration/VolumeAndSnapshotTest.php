<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Keyword;
use app\models\KeywordSource;
use app\services\AdsExportBuilder;
use app\services\CampaignGenerator;
use app\services\ExportException;
use app\services\ImportException;
use app\tests\Support\Seed;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class VolumeAndSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        TestDb::settings([], [], 50);
    }

    public function testDecimalVolumesRoundUpInCsvAndJson(): void
    {
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [['csv builder', '12,5', 'en']]);
        Seed::raw(KeywordSource::SEARCH_CONSOLE, 'input.json', '[{"keyword":"json builder","volume":49.1,"language":"en"}]');
        self::assertSame(13, (int) Keyword::findOne(['keyword' => 'csv builder'])->volume);
        self::assertSame(50, (int) Keyword::findOne(['keyword' => 'json builder'])->volume);
        self::assertSame(Keyword::STATUS_EXCLUDED, Keyword::findOne(['keyword' => 'csv builder'])->status);
        self::assertSame(Keyword::STATUS_INCLUDED, Keyword::findOne(['keyword' => 'json builder'])->status);
    }

    public function testImpressionsAndTrafficNeverOverrideSearchVolume(): void
    {
        Seed::raw(KeywordSource::SEARCH_CONSOLE, 'input.csv', "keyword,impressions,traffic,search volume,language\nwebsite builder,1000,900,10,en\n");
        $keyword = Keyword::findOne(['keyword' => 'website builder']);
        self::assertSame(10, (int) $keyword->volume);
        self::assertSame(Keyword::STATUS_EXCLUDED, $keyword->status);
        $this->expectException(ImportException::class);
        Seed::raw(KeywordSource::SEARCH_CONSOLE, 'input.json', '[{"keyword":"other builder","impressions":1000,"traffic":100,"language":"en"}]');
    }

    public function testVolumeBoundariesAndInvalidNumbers(): void
    {
        $import = Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['zero builder', '0', 'en'],
            ['whole builder', '50', 'en'],
            ['spaced builder', '1 200', 'en'],
            ['decimal builder', '50.001', 'en'],
            ['missing builder', '', 'en'],
            ['negative builder', '-1', 'en'],
            ['ambiguous builder', '1,200.5', 'en'],
            ['huge builder', '1000000000.1', 'en'],
        ]);
        self::assertSame(4, (int) $import->rows_imported);
        self::assertSame(4, (int) $import->rows_rejected);
        self::assertSame(0, (int) Keyword::findOne(['keyword' => 'zero builder'])->volume);
        self::assertSame(50, (int) Keyword::findOne(['keyword' => 'whole builder'])->volume);
        self::assertSame(1200, (int) Keyword::findOne(['keyword' => 'spaced builder'])->volume);
        self::assertSame(51, (int) Keyword::findOne(['keyword' => 'decimal builder'])->volume);
    }

    public function testSettingsChangesBlockExportUntilRegeneration(): void
    {
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [['website builder', 100, 'en']]);
        $campaign = (new CampaignGenerator())->generate();
        $builder = new AdsExportBuilder();
        self::assertStringContainsString('website builder', $builder->build($campaign));
        TestDb::settings([], ['another keyword'], 70);
        try {
            $builder->build($campaign);
            self::fail('Stale settings must block export.');
        } catch (ExportException $exception) {
            self::assertStringContainsString('устарела', $exception->getMessage());
        }
        self::assertStringContainsString('website builder', $builder->build((new CampaignGenerator())->generate()));
    }

    public function testNewImportsBlockOldSnapshot(): void
    {
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [['website builder', 100, 'en']]);
        $campaign = (new CampaignGenerator())->generate();
        Seed::csv(KeywordSource::GOOGLE_ADS, [['website builder', 100, 'en']]);
        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('устарела');
        (new AdsExportBuilder())->validate($campaign);
    }
}
