<?php

declare(strict_types=1);

namespace app\services;

use app\models\Campaign;
use app\models\CampaignAd;
use app\models\CampaignExclusion;
use app\models\CampaignGroup;
use app\models\Keyword;
use Yii;
use yii\helpers\Json;

final class CampaignGenerator
{
    public function __construct(
        private readonly SettingsService $settings = new SettingsService(),
        private readonly KeywordAuditor $auditor = new KeywordAuditor(),
        private readonly AdCopyGenerator $copy = new AdCopyGenerator(),
        private readonly KeywordNormalizer $normalizer = new KeywordNormalizer(),
    ) {
    }

    public function generate(?int $userId = null): Campaign
    {
        $setting = $this->settings->get();
        $targetUrls = $setting->getLanguageUrls();
        $audit = $this->auditor->run();
        $exclusions = $audit['excluded_keys'];
        $groups = [];

        foreach ($audit['candidates'] as $key => $candidate) {
            $language = $candidate['language'];

            if (!LanguageCatalog::isSupported($language)) {
                $exclusions[$key] = $this->generationExclusion(
                    $candidate,
                    CampaignExclusion::REASON_UNSUPPORTED_LANGUAGE,
                    sprintf('Для языка «%s» нет проверенного текста объявления.', $language),
                );
                continue;
            }

            $targetUrl = trim((string) ($targetUrls[$language] ?? ''));
            if ($targetUrl === '' || !UrlGuard::isHttpUrl($targetUrl)) {
                $exclusions[$key] = $this->generationExclusion(
                    $candidate,
                    CampaignExclusion::REASON_MISSING_TARGET_URL,
                    sprintf('Для языка «%s» не настроен корректный http(s)-целевой URL.', $language),
                );
                continue;
            }

            try {
                $adCopy = $this->copy->generate($language, $candidate['keyword']);
            } catch (AdCopyException $exception) {
                $exclusions[$key] = $this->generationExclusion(
                    $candidate,
                    CampaignExclusion::REASON_COPY_ERROR,
                    $exception->getMessage(),
                );
                continue;
            }

            $groups[] = [
                'language' => $language,
                'campaign_name' => sprintf('Site.pro | %s | Search', LanguageCatalog::name($language)),
                'ad_group_name' => mb_substr($candidate['keyword'], 0, 255),
                'keyword' => $candidate['keyword'],
                'keyword_norm' => $candidate['keyword_norm'],
                'volume' => (int) $candidate['volume'],
                'sources' => $candidate['sources'],
                'target_url' => $targetUrl,
                'ad' => $adCopy,
            ];
        }

        $languageOrder = array_flip(LanguageCatalog::codes());
        usort($groups, static function (array $a, array $b) use ($languageOrder): int {
            return [
                $languageOrder[$a['language']] ?? 999,
                mb_strtolower($a['keyword']),
            ] <=> [
                $languageOrder[$b['language']] ?? 999,
                mb_strtolower($b['keyword']),
            ];
        });

        uasort($exclusions, static function (array $a, array $b) use ($languageOrder): int {
            return [
                $languageOrder[$a['language']] ?? 999,
                mb_strtolower($a['keyword']),
            ] <=> [
                $languageOrder[$b['language']] ?? 999,
                mb_strtolower($b['keyword']),
            ];
        });

        $excludedByReason = array_fill_keys(array_keys(CampaignExclusion::reasonLabels()), 0);
        $languages = [];
        foreach ($exclusions as $exclusion) {
            $reason = (string) $exclusion['primary_reason'];
            $excludedByReason[$reason] = ($excludedByReason[$reason] ?? 0) + 1;
            $language = (string) $exclusion['language'];
            if (!isset($languages[$language])) {
                $languages[$language] = [
                    'name' => LanguageCatalog::isSupported($language)
                        ? LanguageCatalog::name($language)
                        : mb_strtoupper($language),
                    'groups' => 0,
                    'excluded' => 0,
                ];
            }
            $languages[$language]['excluded']++;
        }
        foreach ($groups as $group) {
            $language = $group['language'];
            if (!isset($languages[$language])) {
                $languages[$language] = [
                    'name' => LanguageCatalog::name($language),
                    'groups' => 0,
                    'excluded' => 0,
                ];
            }
            $languages[$language]['groups']++;
        }
        ksort($languages);

        $summary = [
            'records_total' => $audit['summary']['records_total'],
            'included_records' => $audit['summary']['included_records'],
            'duplicate_records' => $audit['summary']['duplicate_records'],
            'excluded_records' => $audit['summary']['excluded_records'],
            'by_reason' => $audit['summary']['by_reason'],
            'kept_keys' => $audit['summary']['kept_keys'],
            'groups' => count($groups),
            'ads' => count($groups),
            'excluded_keys' => count($exclusions),
            'excluded_by_reason' => array_filter($excludedByReason),
            'languages' => array_values($languages),
            'generated_at' => date(DATE_ATOM),
            'template_ads' => true,
            'settings' => [
                'min_volume' => (int) $setting->min_volume,
                'brands' => count($setting->getBrands()),
                'forbidden' => count($setting->getForbidden()),
            ],
        ];

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $campaign = new Campaign([
                'created_by' => $userId,
                'summary_json' => Json::encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $campaign->save(false);

            foreach ($groups as $group) {
                $groupModel = new CampaignGroup([
                    'campaign_id' => $campaign->id,
                    'language' => $group['language'],
                    'campaign_name' => $group['campaign_name'],
                    'ad_group_name' => $group['ad_group_name'],
                    'keyword' => $group['keyword'],
                    'keyword_norm' => $group['keyword_norm'],
                    'volume' => $group['volume'],
                    'sources_json' => Json::encode($group['sources'], JSON_UNESCAPED_UNICODE),
                    'target_url' => $group['target_url'],
                    'created_at' => time(),
                ]);
                $groupModel->save(false);

                $ad = $group['ad'];
                $adModel = new CampaignAd([
                    'campaign_group_id' => $groupModel->id,
                    'ad_name' => $ad['adName'],
                    'headlines_json' => Json::encode($ad['headlines'], JSON_UNESCAPED_UNICODE),
                    'descriptions_json' => Json::encode($ad['descriptions'], JSON_UNESCAPED_UNICODE),
                    'path1' => $ad['path1'],
                    'path2' => $ad['path2'],
                    'final_url' => $group['target_url'],
                    'created_at' => time(),
                ]);
                $adModel->save(false);
            }

            foreach ($exclusions as $exclusion) {
                $exclusionModel = new CampaignExclusion([
                    'campaign_id' => $campaign->id,
                    'keyword' => $exclusion['keyword'],
                    'keyword_norm' => $exclusion['keyword_norm'],
                    'language' => $exclusion['language'],
                    'volume' => (int) $exclusion['volume'],
                    'sources_json' => Json::encode($exclusion['sources'], JSON_UNESCAPED_UNICODE),
                    'reason' => $exclusion['primary_reason'],
                    'detail' => $this->normalizer->shorten($exclusion['detail'] ?? '', 255)
                        ?? Keyword::reasonLabels()[$exclusion['primary_reason']]
                        ?? $exclusion['primary_reason'],
                    'created_at' => time(),
                ]);
                $exclusionModel->save(false);
            }

            $transaction->commit();
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }

        return $campaign;
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function generationExclusion(array $candidate, string $reason, string $detail): array
    {
        $label = CampaignExclusion::reasonLabels()[$reason] ?? $reason;

        return [
            'keyword' => $candidate['keyword'],
            'keyword_norm' => $candidate['keyword_norm'],
            'language' => $candidate['language'],
            'volume' => (int) $candidate['volume'],
            'sources' => $candidate['sources'],
            'reasons' => [$reason],
            'primary_reason' => $reason,
            'detail' => $detail !== '' ? $detail : $label,
        ];
    }
}
