<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Campaign;
use app\models\KeywordSource;
use app\services\AdsExportBuilder;
use app\services\CampaignGenerator;
use app\services\CsvSafety;
use app\services\ExportException;
use app\tests\Support\Seed;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class ExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        TestDb::settings(['site.pro'], ['casino', 'free'], 50);
    }

    public function testCsvHasValidEditorStructureAndRsaLimits(): void
    {
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['website builder alternative', 500, 'en', 'https://site.pro/'],
            ['website baukasten', 800, 'de', 'https://site.pro/de/'],
            ['=SUM(1,2)', 100, 'en', 'https://site.pro/'],
        ]);
        $campaign = (new CampaignGenerator())->generate(null);

        $builder = new AdsExportBuilder();
        $validation = $builder->validate($campaign);
        self::assertTrue($validation['ok']);
        self::assertSame(26, $validation['columns']);
        self::assertSame(2, $validation['campaigns']);
        self::assertSame(2, $validation['groups']);
        self::assertSame(2, $validation['ads']);

        $csv = $builder->build($campaign);
        self::assertTrue(mb_check_encoding($csv, 'UTF-8'));
        self::assertNotSame("\xEF\xBB\xBF", substr($csv, 0, 3));
        self::assertStringNotContainsString('=SUM', $csv);

        $parsed = $this->parseCsv($csv);
        self::assertCount(26, $parsed['header']);
        self::assertSame(
            ['Campaign', 'Campaign type', 'Campaign status', 'Budget', 'Bid strategy type', 'Networks', 'Language',
                'Ad group', 'Ad group status', 'Max CPC', 'Keyword', 'Type', 'Status', 'Ad type', 'Ad name',
                'Headline 1', 'Headline 2', 'Headline 3', 'Headline 4', 'Headline 5',
                'Description 1', 'Description 2', 'Description 3', 'Path 1', 'Path 2', 'Final URL'],
            $parsed['header'],
        );

        $campaigns = 0;
        $groups = 0;
        $keywords = 0;
        $ads = 0;
        foreach ($parsed['rows'] as $row) {
            self::assertCount(26, $row);
            if ($row['Campaign type'] !== '') {
                $campaigns++;
                self::assertSame('Search', $row['Campaign type']);
                self::assertSame('Enabled', $row['Campaign status']);
                self::assertSame('10.00', $row['Budget']);
                self::assertStringStartsWith('Site.pro | ', $row['Campaign']);
                self::assertContains($row['Language'], ['en', 'de']);
                continue;
            }
            if ($row['Ad type'] !== '') {
                $ads++;
                self::assertSame('Responsive search ad', $row['Ad type']);
                self::assertSame('Enabled', $row['Status']);
                self::assertStringStartsWith('https://site.pro/', $row['Final URL']);
                $headlines = $this->nonEmpty($row, 'Headline ');
                $descriptions = $this->nonEmpty($row, 'Description ');
                self::assertCount(5, $headlines);
                self::assertCount(3, $descriptions);
                self::assertLessThanOrEqual(30, max(array_map('mb_strlen', $headlines)));
                self::assertLessThanOrEqual(90, max(array_map('mb_strlen', $descriptions)));
                self::assertLessThanOrEqual(15, mb_strlen($row['Path 1']));
                self::assertLessThanOrEqual(15, mb_strlen($row['Path 2']));
                foreach (array_merge($headlines, $descriptions, [$row['Ad name'], $row['Ad group']]) as $text) {
                    self::assertFalse(CsvSafety::isDangerous($text));
                }
                self::assertStringStartsWith('https://', $row['Final URL']);
                continue;
            }
            if ($row['Keyword'] === '' && $row['Ad group'] !== '') {
                $groups++;
                self::assertSame('0.50', $row['Max CPC']);
                self::assertSame('Enabled', $row['Ad group status']);
                continue;
            }
            if ($row['Keyword'] !== '') {
                $keywords++;
                self::assertSame('Phrase', $row['Type']);
                self::assertSame('Enabled', $row['Status']);
                continue;
            }
        }

        self::assertSame(2, $campaigns);
        self::assertSame(2, $groups);
        self::assertSame(2, $keywords);
        self::assertSame(2, $ads);
        self::assertCount(2 + (2 * 2) + 2, $parsed['rows']);
    }

    public function testNoGroupsCannotBeExported(): void
    {
        $this->expectException(ExportException::class);
        $campaign = new Campaign(['summary_json' => '{}']);
        $campaign->save(false);
        (new AdsExportBuilder())->build($campaign);
    }

    /**
     * @return array{header: string[], rows: array<int, array<string, string>>}
     */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, $csv);
        rewind($stream);

        $header = fgetcsv($stream, 0, ',', '"', '');
        self::assertIsArray($header);
        $rows = [];
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if ($values === [null]) {
                continue;
            }
            self::assertCount(count($header), $values);
            $rows[] = array_combine($header, $values);
        }
        fclose($stream);

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * @param array<string, string> $row
     * @return string[]
     */
    private function nonEmpty(array $row, string $prefix): array
    {
        $values = [];
        foreach ($row as $key => $value) {
            if (str_starts_with($key, $prefix) && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
