<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Campaign;
use app\models\CampaignAd;
use app\models\CampaignExclusion;
use app\models\CampaignGroup;
use app\models\Import;
use app\models\Keyword;
use app\models\KeywordSource;
use app\models\Setting;
use app\models\User;
use app\services\CampaignGenerator;
use app\services\DataReset;
use app\services\SettingsService;
use app\tests\Support\Seed;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class DataResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        TestDb::settings(['site.pro'], ['casino'], 50);
        TestDb::admin('reset@example.com', 'Reset-password-123');
    }

    public function testResetRemovesImportsKeywordsAndCampaignsButKeepsSetup(): void
    {
        Seed::csv(KeywordSource::GOOGLE_ADS, [
            ['keyword builder', 200, 'en'],
            ['cheap casino', 200, 'en'],
        ]);
        Seed::csv(KeywordSource::AHREFS_ORGANIC, [
            ['site builder tool', 300, 'en'],
            ['site.pro casino reset exclusion', 300, 'en'],
        ]);
        (new CampaignGenerator())->generate(null);

        $before = [
            'imports' => (int) Import::find()->count(),
            'keywords' => (int) Keyword::find()->count(),
            'campaigns' => (int) Campaign::find()->count(),
            'groups' => (int) CampaignGroup::find()->count(),
            'ads' => (int) CampaignAd::find()->count(),
            'exclusions' => (int) CampaignExclusion::find()->count(),
        ];
        foreach ($before as $table => $count) {
            self::assertGreaterThan(0, $count, $table);
        }

        $removed = (new DataReset())->run();

        self::assertSame($before, [
            'imports' => $removed['imports'],
            'keywords' => $removed['keywords'],
            'campaigns' => $removed['campaigns'],
            'groups' => $removed['groups'],
            'ads' => $removed['ads'],
            'exclusions' => $removed['exclusions'],
        ]);
        self::assertSame(0, (int) Import::find()->count());
        self::assertSame(0, (int) Keyword::find()->count());
        self::assertSame(0, (int) Campaign::find()->count());
        self::assertSame(0, (int) CampaignGroup::find()->count());
        self::assertSame(0, (int) CampaignAd::find()->count());
        self::assertSame(0, (int) CampaignExclusion::find()->count());

        self::assertNotNull(User::findByEmail('reset@example.com'));

        $settings = (new SettingsService())->get();
        self::assertSame(['site.pro'], $settings->getBrands());
        self::assertSame(['casino'], $settings->getForbidden());
        self::assertSame(50, (int) $settings->min_volume);
        self::assertSame(1, (int) Setting::find()->count());
    }

    public function testResetIsIdempotentOnEmptyDatabase(): void
    {
        (new DataReset())->run();
        $removed = (new DataReset())->run();

        self::assertSame(0, $removed['imports']);
        self::assertSame(0, $removed['keywords']);
        self::assertSame(0, $removed['campaigns']);
    }
}
