<?php

declare(strict_types=1);

namespace app\services;

use app\models\Campaign;
use app\models\CampaignAd;
use app\models\CampaignGroup;
use Yii;

/**
 * Builds a Google Ads Editor CSV.
 *
 * The column names follow Google's documented Editor CSV headers. The file is
 * structurally validated before it is written: one entity per row, required
 * campaign/ad group/keyword/ad fields, RSA character limits, http(s) final
 * URLs, and formula-injection safety. Actual import into a live Google Ads
 * Editor is intentionally left as a manual verification step.
 */
final class AdsExportBuilder
{
    private const HEADER = [
        'Campaign',
        'Campaign type',
        'Campaign status',
        'Budget',
        'Bid strategy type',
        'Networks',
        'Language',
        'Ad group',
        'Ad group status',
        'Max CPC',
        'Keyword',
        'Type',
        'Status',
        'Ad type',
        'Ad name',
        'Headline 1',
        'Headline 2',
        'Headline 3',
        'Headline 4',
        'Headline 5',
        'Description 1',
        'Description 2',
        'Description 3',
        'Path 1',
        'Path 2',
        'Final URL',
    ];

    private const TEXT_FIELDS = [
        'Campaign',
        'Campaign type',
        'Campaign status',
        'Bid strategy type',
        'Networks',
        'Language',
        'Ad group',
        'Ad group status',
        'Keyword',
        'Type',
        'Status',
        'Ad type',
        'Ad name',
        'Headline 1',
        'Headline 2',
        'Headline 3',
        'Headline 4',
        'Headline 5',
        'Description 1',
        'Description 2',
        'Description 3',
        'Path 1',
        'Path 2',
    ];

    /**
     * @return array{ok: bool, columns: int, data_rows: int, campaigns: int, groups: int, ads: int}
     */
    public function validate(Campaign $campaign): array
    {
        [$rows, $stats] = $this->collect($campaign);
        $this->validateRows($rows);

        return [
            'ok' => true,
            'columns' => count(self::HEADER),
            'data_rows' => count($rows),
            'campaigns' => $stats['campaigns'],
            'groups' => $stats['groups'],
            'ads' => $stats['ads'],
        ];
    }

    public function build(Campaign $campaign): string
    {
        [$rows] = $this->collect($campaign);
        $this->validateRows($rows);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new ExportException('Не удалось создать поток CSV.');
        }

        fputcsv($stream, self::HEADER, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($csv === false || $csv === '') {
            throw new ExportException('Не удалось записать CSV-файл.');
        }

        return $csv;
    }

    /**
     * @return array{0: array<int, array<string, string>>, 1: array{campaigns: int, groups: int, ads: int}}
     */
    private function collect(Campaign $campaign): array
    {
        if ($campaign->isStale()) {
            throw new ExportException('Кампания устарела: изменились данные или настройки. Сгенерируйте кампанию заново перед экспортом.');
        }
        $groups = CampaignGroup::find()
            ->where(['campaign_id' => $campaign->id])
            ->with('ads')
            ->all();

        if ($groups === []) {
            throw new ExportException('В кампании нет групп объявлений для экспорта.');
        }

        $languageOrder = array_flip(LanguageCatalog::codes());
        usort($groups, static function (CampaignGroup $a, CampaignGroup $b) use ($languageOrder): int {
            return [
                $languageOrder[$a->language] ?? 999,
                mb_strtolower($a->ad_group_name),
            ] <=> [
                $languageOrder[$b->language] ?? 999,
                mb_strtolower($b->ad_group_name),
            ];
        });

        $gads = Yii::$app->params['gads'] ?? [];
        $budget = number_format((float) ($gads['dailyBudget'] ?? 10), 2, '.', '');
        $maxCpc = number_format((float) ($gads['maxCpc'] ?? 0.5), 2, '.', '');
        $matchType = (string) ($gads['matchType'] ?? 'Phrase');
        $networks = (string) ($gads['networks'] ?? 'Google Search');

        $rows = [];
        $campaignNames = [];
        $seenLanguage = [];
        $seenGroups = [];
        $stats = ['campaigns' => 0, 'groups' => 0, 'ads' => 0];

        foreach ($groups as $group) {
            $this->validateGroup($group);

            $language = $group->language;
            if (!isset($campaignNames[$language])) {
                $campaignNames[$language] = $group->campaign_name;
            } elseif ($campaignNames[$language] !== $group->campaign_name) {
                throw new ExportException('Для одного языка указано несколько названий кампаний.');
            }

            $groupKey = implode('|', [$group->campaign_name, $group->ad_group_name, $group->keyword, $matchType]);
            if (isset($seenGroups[$groupKey])) {
                throw new ExportException('Дублирующаяся комбинация группы объявлений и ключевого слова в экспорте.');
            }
            $seenGroups[$groupKey] = true;

            if (!isset($seenLanguage[$language])) {
                $seenLanguage[$language] = true;
                $stats['campaigns']++;
                $rows[] = $this->makeRow([
                    'Campaign' => $group->campaign_name,
                    'Campaign type' => 'Search',
                    'Campaign status' => 'Enabled',
                    'Budget' => $budget,
                    'Bid strategy type' => 'Manual CPC',
                    'Networks' => $networks,
                    'Language' => $language,
                ]);
            }

            $rows[] = $this->makeRow([
                'Campaign' => $group->campaign_name,
                'Ad group' => $group->ad_group_name,
                'Ad group status' => 'Enabled',
                'Max CPC' => $maxCpc,
            ]);

            $rows[] = $this->makeRow([
                'Campaign' => $group->campaign_name,
                'Ad group' => $group->ad_group_name,
                'Keyword' => $group->keyword,
                'Type' => $matchType,
                'Status' => 'Enabled',
            ]);
            $stats['groups']++;

            foreach ($group->ads as $ad) {
                $this->validateAd($ad, $group);
                $headlines = $ad->getHeadlines();
                $descriptions = $ad->getDescriptions();
                $values = [
                    'Campaign' => $group->campaign_name,
                    'Ad group' => $group->ad_group_name,
                    'Ad type' => 'Responsive search ad',
                    'Ad name' => $ad->ad_name,
                    'Path 1' => $ad->path1,
                    'Path 2' => $ad->path2,
                    'Final URL' => $ad->final_url,
                    'Status' => 'Enabled',
                ];
                foreach ($headlines as $index => $headline) {
                    $values['Headline ' . ($index + 1)] = $headline;
                }
                foreach ($descriptions as $index => $description) {
                    $values['Description ' . ($index + 1)] = $description;
                }
                $rows[] = $this->makeRow($values);
                $stats['ads']++;
            }
        }

        return [$rows, $stats];
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function makeRow(array $values): array
    {
        $row = array_fill_keys(self::HEADER, '');
        foreach ($values as $field => $value) {
            if (!array_key_exists($field, $row)) {
                throw new ExportException(sprintf('Неизвестная колонка Google Ads Editor: «%s».', $field));
            }
            $value = (string) $value;
            if (in_array($field, self::TEXT_FIELDS, true)) {
                CsvSafety::assertTextSafe($value, $field);
            }
            $row[$field] = $value;
        }

        return $row;
    }

    private function validateGroup(CampaignGroup $group): void
    {
        CsvSafety::assertTextSafe($group->campaign_name, 'Campaign');
        CsvSafety::assertTextSafe($group->ad_group_name, 'Ad group');
        CsvSafety::assertTextSafe($group->keyword, 'Keyword');
        CsvSafety::assertTextSafe($group->language, 'Language');

        if ($group->campaign_name === '' || mb_strlen($group->campaign_name) > 255) {
            throw new ExportException('Название кампании пустое или слишком длинное.');
        }
        if ($group->ad_group_name === '' || mb_strlen($group->ad_group_name) > 255) {
            throw new ExportException('Название группы объявлений пустое или слишком длинное.');
        }
        if (mb_strlen($group->keyword) > 80) {
            throw new ExportException('Ключевое слово длиннее 80 символов.');
        }
        if (count(preg_split('/\s+/u', $group->keyword) ?: []) > 10) {
            throw new ExportException('Ключевое слово содержит больше 10 слов.');
        }
        if (!LanguageCatalog::isSupported($group->language)) {
            throw new ExportException('Неподдерживаемый язык в экспорте: ' . $group->language);
        }
        if (!UrlGuard::isHttpUrl($group->target_url)) {
            throw new ExportException('Целевой URL должен быть http(s).');
        }
        if ((int) $group->volume < 0) {
            throw new ExportException('Объём ключевого слова не может быть отрицательным.');
        }
        if ($group->ads === []) {
            throw new ExportException('В каждой группе объявлений должно быть хотя бы одно адаптивное объявление.');
        }
    }

    private function validateAd(CampaignAd $ad, CampaignGroup $group): void
    {
        CsvSafety::assertTextSafe($ad->ad_name, 'Ad name');
        $headlines = $ad->getHeadlines();
        $descriptions = $ad->getDescriptions();

        if (count($headlines) < 3 || count($headlines) > 15) {
            throw new ExportException('Адаптивному объявлению нужно от 3 до 15 заголовков.');
        }
        if (count($descriptions) < 2 || count($descriptions) > 4) {
            throw new ExportException('Адаптивному объявлению нужно от 2 до 4 описаний.');
        }

        foreach ($headlines as $index => $headline) {
            CsvSafety::assertTextSafe($headline, 'Headline ' . ($index + 1));
            if ($headline === '' || mb_strlen($headline) > 30) {
                throw new ExportException('Заголовок пустой или длиннее 30 символов.');
            }
        }
        foreach ($descriptions as $index => $description) {
            CsvSafety::assertTextSafe($description, 'Description ' . ($index + 1));
            if ($description === '' || mb_strlen($description) > 90) {
                throw new ExportException('Описание пустое или длиннее 90 символов.');
            }
        }

        foreach ([$ad->path1, $ad->path2] as $path) {
            if ($path !== '' && mb_strlen($path) > 15) {
                throw new ExportException('Отображаемый путь длиннее 15 символов.');
            }
            if ($path !== '' && preg_match('/^[\p{L}\p{N}-]+$/u', $path) !== 1) {
                throw new ExportException('Отображаемые пути могут содержать только буквы, цифры и дефисы.');
            }
        }

        if (!UrlGuard::isHttpUrl($ad->final_url)) {
            throw new ExportException('Конечный URL должен быть http(s).');
        }
        if ($ad->ad_name === '' || mb_strlen($ad->ad_name) > 255) {
            throw new ExportException('Название объявления пустое или слишком длинное.');
        }
        if ($ad->final_url !== $group->target_url) {
            throw new ExportException('Конечный URL объявления должен совпадать с целевым URL языка.');
        }
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function validateRows(array $rows): void
    {
        if ($rows === []) {
            throw new ExportException('В экспорте нет строк.');
        }

        foreach ($rows as $row) {
            if (array_keys($row) !== self::HEADER) {
                throw new ExportException('Колонки CSV не соответствуют ожидаемой структуре Google Ads Editor.');
            }
            if (count($row) !== count(self::HEADER)) {
                throw new ExportException('В строке CSV неожиданное количество колонок.');
            }
        }
    }
}
