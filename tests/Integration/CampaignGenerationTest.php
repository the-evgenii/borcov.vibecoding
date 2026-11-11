<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Campaign;
use app\models\CampaignExclusion;
use app\models\CampaignGroup;
use app\models\Keyword;
use app\models\KeywordSource;
use app\services\CampaignGenerator;
use app\tests\Support\Seed;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class CampaignGenerationTest extends TestCase
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

    public function testGenerationMergesLanguagesExcludesRulesAndBuildsRsaAssets(): void
    {
        Seed::csv(KeywordSource::GOOGLE_ADS, [
            ['used keyword', 100, 'en'],
        ]);
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['website builder alternative', 500, 'en'],
            ['low volume candidate', 10, 'en'],
            ['site.pro review', 100, 'en'],
            ['casino keyword', 100, 'en'],
        ]);
        Seed::csv(KeywordSource::AHREFS_PAID, [
            ['website builder alternative', 900, 'en'],
            ['ウェブサイト作成', 800, 'ja'],
        ]);
        Seed::csv(KeywordSource::AHREFS_ORGANIC, [
            ['website baukasten', 800, 'de'],
            ['svetainių kūrimas', 300, 'lt'],
            ['конструктор сайтов', 400, 'ru'],
        ]);

        $campaign = (new CampaignGenerator())->generate(null);
        $summary = $campaign->getSummary();

        self::assertSame(4, (int) $summary['groups']);
        self::assertSame(4, (int) $summary['ads']);
        self::assertSame(5, (int) $summary['excluded_keys']);

        $groups = CampaignGroup::find()
            ->where(['campaign_id' => $campaign->id])
            ->with('ads')
            ->all();
        self::assertCount(4, $groups);

        $byLanguage = [];
        foreach ($groups as $group) {
            $byLanguage[$group->language] = $group;
            self::assertSame('Site.pro | ' . match ($group->language) {
                'en' => 'English',
                'de' => 'German',
                'lt' => 'Lithuanian',
                'ru' => 'Russian',
                default => 'Unknown',
            } . ' | Search', $group->campaign_name);
            self::assertSame(1, count($group->ads));
            $ad = $group->ads[0];
            self::assertCount(5, $ad->getHeadlines());
            self::assertCount(3, $ad->getDescriptions());
            self::assertLessThanOrEqual(30, max(array_map('mb_strlen', $ad->getHeadlines())));
            self::assertLessThanOrEqual(90, max(array_map('mb_strlen', $ad->getDescriptions())));
            self::assertSame($group->target_url, $ad->final_url);
            self::assertMatchesRegularExpression('/^https:\/\//', $ad->final_url);
        }

        $english = $byLanguage['en'];
        self::assertSame('website builder alternative', $english->keyword);
        self::assertSame(900, (int) $english->volume);
        self::assertEqualsCanonicalizing(
            [KeywordSource::SEARCH_CONSOLE, KeywordSource::AHREFS_PAID],
            $english->getSources(),
        );
        self::assertSame('https://site.pro/Website-Builder/', $english->target_url);

        self::assertSame('https://site.pro/de/', $byLanguage['de']->target_url);
        self::assertSame('https://site.pro/lt/', $byLanguage['lt']->target_url);
        self::assertSame('https://site.pro/ru/', $byLanguage['ru']->target_url);

        $germanAd = $byLanguage['de']->ads[0];
        self::assertContains('Website schnell erstellen', $germanAd->getHeadlines());

        $reasons = CampaignExclusion::find()
            ->select('reason')
            ->where(['campaign_id' => $campaign->id])
            ->column();
        self::assertContains(CampaignExclusion::REASON_UNSUPPORTED_LANGUAGE, $reasons);
        self::assertContains(Keyword::REASON_ALREADY_USED, $reasons);
        self::assertContains(Keyword::REASON_BRAND, $reasons);
        self::assertContains(Keyword::REASON_FORBIDDEN, $reasons);
        self::assertContains(Keyword::REASON_LOW_VOLUME, $reasons);

        $unsupported = CampaignExclusion::find()
            ->where(['campaign_id' => $campaign->id, 'reason' => CampaignExclusion::REASON_UNSUPPORTED_LANGUAGE])
            ->one();
        self::assertNotNull($unsupported);
        self::assertStringContainsString('ja', $unsupported->detail);
    }

    public function testGenerationIsDeterministicAndMissingUrlIsExplicit(): void
    {
        Seed::csv(KeywordSource::AHREFS_ORGANIC, [
            ['website baukasten', 800, 'de'],
            ['svetainių kūrimas', 300, 'lt'],
        ]);

        $first = (new CampaignGenerator())->generate(null);
        $second = (new CampaignGenerator())->generate(null);

        $firstGroups = CampaignGroup::find()->where(['campaign_id' => $first->id])->orderBy(['language' => SORT_ASC])->all();
        $secondGroups = CampaignGroup::find()->where(['campaign_id' => $second->id])->orderBy(['language' => SORT_ASC])->all();
        self::assertSame(
            array_map(static fn (CampaignGroup $group): string => $group->language . '|' . $group->keyword, $firstGroups),
            array_map(static fn (CampaignGroup $group): string => $group->language . '|' . $group->keyword, $secondGroups),
        );
        self::assertSame(
            $firstGroups[0]->ads[0]->getHeadlines(),
            $secondGroups[0]->ads[0]->getHeadlines(),
        );

        TestDb::settings(
            ['site.pro'],
            ['casino'],
            50,
            ['en' => 'https://site.pro/', 'de' => 'https://site.pro/de/', 'lt' => ''],
        );
        $third = (new CampaignGenerator())->generate(null);
        $thirdSummary = $third->getSummary();
        self::assertSame(1, (int) $thirdSummary['groups']);
        $missing = CampaignExclusion::find()
            ->where(['campaign_id' => $third->id, 'reason' => CampaignExclusion::REASON_MISSING_TARGET_URL])
            ->one();
        self::assertNotNull($missing);
        self::assertSame('lt', $missing->language);
    }

    public function testEmptyPipelineStillProducesAnAuditableSnapshot(): void
    {
        $campaign = (new CampaignGenerator())->generate(null);
        $summary = $campaign->getSummary();

        self::assertSame(0, (int) $summary['groups']);
        self::assertSame(0, (int) $summary['ads']);
        self::assertSame(0, (int) $summary['excluded_keys']);
        self::assertSame(0, (int) CampaignGroup::find()->where(['campaign_id' => $campaign->id])->count());
    }
}
