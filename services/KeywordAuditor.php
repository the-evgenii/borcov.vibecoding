<?php

declare(strict_types=1);

namespace app\services;

use app\models\Keyword;
use app\models\KeywordSource;
use Yii;
use yii\helpers\Json;

final class KeywordAuditor
{
    public const REASON_ORDER = [
        Keyword::REASON_JUNK,
        Keyword::REASON_BRAND,
        Keyword::REASON_FORBIDDEN,
        Keyword::REASON_ALREADY_USED,
        Keyword::REASON_LOW_VOLUME,
        Keyword::REASON_DUPLICATE,
    ];

    public function __construct(
        private readonly SettingsService $settings = new SettingsService(),
        private readonly KeywordNormalizer $normalizer = new KeywordNormalizer(),
    ) {
    }

    /**
     * Recomputes statuses for every imported record and returns the merged
     * candidates and the key-level exclusions used by the campaign pipeline.
     *
     * @return array{
     *   summary: array<string, mixed>,
     *   candidates: array<string, array<string, mixed>>,
     *   excluded_keys: array<string, array<string, mixed>>
     * }
     */
    public function run(): array
    {
        $setting = $this->settings->get();
        $minimumVolume = max(0, (int) $setting->min_volume);
        $brands = $this->normalizePhrases($setting->getBrands());
        $forbidden = $this->normalizePhrases($setting->getForbidden());

        $usedKeys = [];
        $usedRows = Keyword::find()
            ->select(['keyword_norm', 'language'])
            ->where(['source' => KeywordSource::GOOGLE_ADS])
            ->asArray()
            ->all();
        foreach ($usedRows as $row) {
            $usedKeys[$this->key($row['keyword_norm'], LanguageCatalog::normalize((string) $row['language']))] = true;
        }

        $records = Keyword::find()->orderBy(['id' => SORT_ASC])->all();
        $keys = [];
        $seenUsableKeys = [];
        $byReason = array_fill_keys(self::REASON_ORDER, 0);
        $includedRecords = 0;
        $duplicateRecords = 0;
        $excludedRecords = 0;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($records as $record) {
                $language = LanguageCatalog::normalize($record->language);
                $key = $this->key($record->keyword_norm, $language);
                $baseReasons = $this->baseReasons(
                    $record,
                    $key,
                    $usedKeys,
                    $brands,
                    $forbidden,
                    $minimumVolume,
                );

                $duplicate = false;
                if ($baseReasons === []) {
                    if (isset($seenUsableKeys[$key])) {
                        $duplicate = true;
                    } else {
                        $seenUsableKeys[$key] = true;
                    }
                }

                $reasons = $baseReasons;
                if ($duplicate) {
                    $reasons[] = Keyword::REASON_DUPLICATE;
                }

                $status = Keyword::STATUS_INCLUDED;
                if ($baseReasons !== []) {
                    $status = Keyword::STATUS_EXCLUDED;
                    $excludedRecords++;
                } elseif ($duplicate) {
                    $status = Keyword::STATUS_DUPLICATE;
                    $duplicateRecords++;
                } else {
                    $includedRecords++;
                }

                $primaryReason = $this->primaryReason($reasons);
                $record->updateAttributes([
                    'status' => $status,
                    'reason' => $primaryReason,
                    'reasons_json' => Json::encode($reasons, JSON_UNESCAPED_UNICODE),
                ]);

                foreach ($reasons as $reason) {
                    $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
                }

                if (!isset($keys[$key])) {
                    $keys[$key] = [
                        'keyword' => $record->keyword,
                        'keyword_norm' => $record->keyword_norm,
                        'language' => $language,
                        'volume' => 0,
                        'usable_volume' => 0,
                        'sources' => [],
                        'has_usable' => false,
                        'reasons' => [],
                        'display_volume' => -1,
                    ];
                }

                $keys[$key]['volume'] = max($keys[$key]['volume'], (int) $record->volume);
                if (!in_array($record->source, $keys[$key]['sources'], true)) {
                    $keys[$key]['sources'][] = $record->source;
                }
                foreach ($baseReasons as $reason) {
                    $keys[$key]['reasons'][$reason] = true;
                }

                if ($baseReasons === []) {
                    $keys[$key]['has_usable'] = true;
                    $keys[$key]['usable_volume'] = max($keys[$key]['usable_volume'], (int) $record->volume);
                    if ((int) $record->volume > $keys[$key]['display_volume']) {
                        $keys[$key]['display_volume'] = (int) $record->volume;
                        $keys[$key]['keyword'] = $record->keyword;
                    }
                }
            }

            $transaction->commit();
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }

        $candidates = [];
        $excludedKeys = [];
        foreach ($keys as $key => $info) {
            if ($info['has_usable']) {
                $candidates[$key] = [
                    'keyword' => $info['keyword'],
                    'keyword_norm' => $info['keyword_norm'],
                    'language' => $info['language'],
                    'volume' => $info['usable_volume'],
                    'sources' => $info['sources'],
                ];
                continue;
            }

            $reasons = array_keys($info['reasons']);
            $reasons = $this->sortReasons($reasons);
            $excludedKeys[$key] = [
                'keyword' => $info['keyword'],
                'keyword_norm' => $info['keyword_norm'],
                'language' => $info['language'],
                'volume' => $info['volume'],
                'sources' => $info['sources'],
                'reasons' => $reasons,
                'primary_reason' => $this->primaryReason($reasons) ?? Keyword::REASON_JUNK,
                'detail' => implode(', ', array_map(
                    static fn (string $reason): string => Keyword::reasonLabels()[$reason] ?? $reason,
                    $reasons,
                )),
            ];
        }

        return [
            'summary' => [
                'records_total' => count($records),
                'included_records' => $includedRecords,
                'duplicate_records' => $duplicateRecords,
                'excluded_records' => $excludedRecords,
                'by_reason' => array_filter($byReason),
                'keys_total' => count($keys),
                'kept_keys' => count($candidates),
                'excluded_keys' => count($excludedKeys),
            ],
            'candidates' => $candidates,
            'excluded_keys' => $excludedKeys,
        ];
    }

    /**
     * Read-only summary of the persisted statuses for dashboards and keyword
     * filters. It intentionally does not write to the database.
     *
     * @return array<string, mixed>
     */
    public function statusSummary(): array
    {
        $rows = Keyword::find()
            ->select(['keyword_norm', 'language', 'status', 'reasons_json'])
            ->asArray()
            ->all();

        $byStatus = [
            Keyword::STATUS_INCLUDED => 0,
            Keyword::STATUS_DUPLICATE => 0,
            Keyword::STATUS_EXCLUDED => 0,
        ];
        $byReason = [];
        $keys = [];
        $keptKeys = [];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $key = $this->key((string) $row['keyword_norm'], LanguageCatalog::normalize((string) $row['language']));
            $keys[$key] = true;
            if ($status !== Keyword::STATUS_EXCLUDED) {
                $keptKeys[$key] = true;
            }

            $reasons = [];
            try {
                $decoded = Json::decode((string) $row['reasons_json']);
                $reasons = is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                $reasons = [];
            }

            foreach ($reasons as $reason) {
                if (is_string($reason)) {
                    $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
                }
            }
        }

        return [
            'records_total' => count($rows),
            'included_records' => $byStatus[Keyword::STATUS_INCLUDED],
            'duplicate_records' => $byStatus[Keyword::STATUS_DUPLICATE],
            'excluded_records' => $byStatus[Keyword::STATUS_EXCLUDED],
            'by_reason' => $byReason,
            'keys_total' => count($keys),
            'kept_keys' => count($keptKeys),
            'excluded_keys' => count($keys) - count($keptKeys),
        ];
    }

    /**
     * @return string[]
     */
    private function baseReasons(
        Keyword $record,
        string $key,
        array $usedKeys,
        array $brands,
        array $forbidden,
        int $minimumVolume,
    ): array {
        $reasons = [];
        if ($this->normalizer->isJunk($record->keyword, $record->keyword_norm)) {
            $reasons[] = Keyword::REASON_JUNK;
        }
        if ($this->matchesAnyNormalized($record->keyword_norm, $brands)) {
            $reasons[] = Keyword::REASON_BRAND;
        }
        if ($this->matchesAnyNormalized($record->keyword_norm, $forbidden)) {
            $reasons[] = Keyword::REASON_FORBIDDEN;
        }
        if ($record->source === KeywordSource::GOOGLE_ADS || isset($usedKeys[$key])) {
            $reasons[] = Keyword::REASON_ALREADY_USED;
        }
        if ((int) $record->volume < $minimumVolume) {
            $reasons[] = Keyword::REASON_LOW_VOLUME;
        }

        return $this->sortReasons(array_values(array_unique($reasons)));
    }

    /**
     * @return string[]
     */
    private function normalizePhrases(array $phrases): array
    {
        $result = [];
        foreach ($phrases as $phrase) {
            $normalized = $this->normalizer->normalize($phrase);
            if ($normalized !== '') {
                $result[$normalized] = true;
            }
        }

        return array_keys($result);
    }

    /**
     * @param string[] $normalizedPhrases
     */
    private function matchesAnyNormalized(string $normalizedKeyword, array $normalizedPhrases): bool
    {
        if ($normalizedPhrases === []) {
            return false;
        }

        $haystack = ' ' . $normalizedKeyword . ' ';
        foreach ($normalizedPhrases as $phrase) {
            if (str_contains($haystack, ' ' . $phrase . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $reasons
     * @return string[]
     */
    private function sortReasons(array $reasons): array
    {
        $order = array_flip(self::REASON_ORDER);
        usort($reasons, static fn (string $a, string $b): int => ($order[$a] ?? 999) <=> ($order[$b] ?? 999));

        return $reasons;
    }

    /**
     * @param string[] $reasons
     */
    private function primaryReason(array $reasons): ?string
    {
        return $this->sortReasons($reasons)[0] ?? null;
    }

    private function key(string $keywordNormalized, string $language): string
    {
        return LanguageCatalog::normalize($language) . '|' . $keywordNormalized;
    }
}
