<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Import;
use app\models\Keyword;
use app\models\KeywordSource;
use app\services\ImportException;
use app\services\ImportValidationException;
use app\tests\Support\Seed;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class ImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        TestDb::settings();
    }

    public function testCsvImportsAllFourSourcesWithRealModels(): void
    {
        $keywords = [
            KeywordSource::GOOGLE_ADS => 'ads imported keyword',
            KeywordSource::SEARCH_CONSOLE => 'console imported keyword',
            KeywordSource::AHREFS_ORGANIC => 'organic imported keyword',
            KeywordSource::AHREFS_PAID => 'paid imported keyword',
        ];

        foreach ($keywords as $source => $keyword) {
            $import = Seed::csv($source, [[$keyword, 120, 'en-US', 'https://site.pro/']], $source . '.csv');

            self::assertSame(1, (int) $import->rows_total, $source);
            self::assertSame(1, (int) $import->rows_imported, $source);
            self::assertSame(0, (int) $import->rows_rejected, $source);
            self::assertSame($source, $import->source);

            $record = Keyword::find()->where(['keyword' => $keyword])->one();
            self::assertNotNull($record, $source);
            self::assertSame('en', $record->language);
            self::assertSame(120, (int) $record->volume);
            self::assertSame('https://site.pro/', $record->url);
            if ($source === KeywordSource::GOOGLE_ADS) {
                self::assertSame(Keyword::STATUS_EXCLUDED, $record->status);
                self::assertContains(Keyword::REASON_ALREADY_USED, $record->getReasons());
            } else {
                self::assertSame(Keyword::STATUS_INCLUDED, $record->status);
            }
        }
    }

    public function testJsonContainerAndFieldAliases(): void
    {
        $json = json_encode([
            'items' => [
                [
                    'query' => 'json alias keyword',
                    'impressions' => '1,200',
                    'language' => 'en_US',
                    'landing_page' => 'https://site.pro/',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $import = Seed::raw(KeywordSource::SEARCH_CONSOLE, 'queries.json', (string) $json);

        self::assertSame('json', $import->format);
        self::assertSame(1, (int) $import->rows_imported);
        $record = Keyword::find()->where(['keyword' => 'json alias keyword'])->one();
        self::assertNotNull($record);
        self::assertSame(1200, (int) $record->volume);
        self::assertSame('en', $record->language);
        self::assertSame('https://site.pro/', $record->url);
        self::assertSame(Keyword::STATUS_INCLUDED, $record->status);
    }

    public function testSearchConsoleCsvAliasesAreAccepted(): void
    {
        $csv = "Top queries,Impressions,Language,Landing page\n"
            . "console alias keyword,1234,de_DE,https://site.pro/de/\n";

        $import = Seed::raw(KeywordSource::SEARCH_CONSOLE, 'console.csv', $csv);

        self::assertSame(1, (int) $import->rows_imported);
        $record = Keyword::find()->where(['keyword' => 'console alias keyword'])->one();
        self::assertNotNull($record);
        self::assertSame(1234, (int) $record->volume);
        self::assertSame('de', $record->language);
    }

    public function testInvalidJsonIsRejectedWithoutWritingARecord(): void
    {
        try {
            Seed::raw(KeywordSource::SEARCH_CONSOLE, 'broken.json', '{"items": [');
            self::fail('Invalid JSON should throw.');
        } catch (ImportException $exception) {
            self::assertStringContainsString('Некорректный JSON', $exception->getMessage());
        }

        self::assertSame(0, (int) Import::find()->count());
        self::assertSame(0, (int) Keyword::find()->count());
    }

    public function testPartialCsvImportReportsRejectedRows(): void
    {
        $csv = "keyword,volume,language,url\n"
            . "valid partial keyword,100,en,https://site.pro/\n"
            . ",100,en,https://site.pro/\n"
            . "bad volume keyword,abc,en,https://site.pro/\n"
            . "bad url keyword,100,en,ftp://example.com/\n";

        $import = Seed::raw(KeywordSource::SEARCH_CONSOLE, 'partial.csv', $csv);

        self::assertSame(4, (int) $import->rows_total);
        self::assertSame(1, (int) $import->rows_imported);
        self::assertSame(3, (int) $import->rows_rejected);
        self::assertCount(3, $import->getErrorItems());
        self::assertNotNull(Keyword::find()->where(['keyword' => 'valid partial keyword'])->one());
        self::assertNull(Keyword::find()->where(['keyword' => 'bad url keyword'])->one());
    }

    public function testMissingRequiredColumnRejectsTheFile(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessageMatches('/volume/');
        Seed::raw(KeywordSource::SEARCH_CONSOLE, 'header.csv', "keyword,language\nno volume,en\n");
    }

    public function testEmptyFileIsRejected(): void
    {
        $this->expectException(ImportException::class);
        Seed::raw(KeywordSource::SEARCH_CONSOLE, 'empty.csv', '');
    }

    public function testOversizedFileIsRejectedBeforeParsing(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessageMatches('/лимита импорта/');
        Seed::raw(KeywordSource::SEARCH_CONSOLE, 'large.csv', str_repeat('a', 2 * 1024 * 1024 + 1));
    }

    public function testEmptyKeywordRowsCanBePartiallyImported(): void
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('В файле не найдено корректных строк.');
        Seed::raw(KeywordSource::SEARCH_CONSOLE, 'empty-rows.csv', "keyword,volume,language\n,100,en\n,200,en\n");
    }

    public function testDuplicateRowsWithinOneBatchAreExposed(): void
    {
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['duplicate fixture keyword', 100, 'en'],
            ['duplicate fixture keyword', 200, 'en'],
        ]);

        $records = Keyword::find()
            ->where(['keyword' => 'duplicate fixture keyword'])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        self::assertCount(2, $records);
        self::assertSame(Keyword::STATUS_INCLUDED, $records[0]->status);
        self::assertSame(Keyword::STATUS_DUPLICATE, $records[1]->status);
        self::assertContains(Keyword::REASON_DUPLICATE, $records[1]->getReasons());
    }

    public function testFormulaKeywordIsStoredButMarkedJunk(): void
    {
        Seed::csv(KeywordSource::AHREFS_ORGANIC, [
            ['=SUM(1,2)', 100, 'en', 'https://site.pro/'],
        ]);

        $record = Keyword::find()->where(['keyword' => '=SUM(1,2)'])->one();
        self::assertNotNull($record);
        self::assertSame(Keyword::STATUS_EXCLUDED, $record->status);
        self::assertContains(Keyword::REASON_JUNK, $record->getReasons());
    }

    public function testCsvRejectsKeywordLongerThan255BeforeTruncation(): void
    {
        $longKeyword = str_repeat('long keyword ', 30);
        $csv = "keyword,volume,language\n"
            . "valid long-row companion,100,en\n"
            . "{$longKeyword},100,en\n";

        $import = Seed::raw(KeywordSource::SEARCH_CONSOLE, 'long.csv', $csv);

        self::assertSame(2, (int) $import->rows_total);
        self::assertSame(1, (int) $import->rows_imported);
        self::assertSame(1, (int) $import->rows_rejected);
        self::assertNotNull(Keyword::find()->where(['keyword' => 'valid long-row companion'])->one());
        self::assertSame(0, (int) Keyword::find()->where(['like', 'keyword', 'long keyword'])->count());
        self::assertStringContainsString('255', $import->getErrorItems()[0]['message']);
    }

    public function testJsonRejectsKeywordLongerThan255BeforeTruncation(): void
    {
        $longKeyword = str_repeat('long keyword ', 30);
        $json = json_encode([
            ['keyword' => 'valid JSON long-row companion', 'volume' => 100, 'language' => 'en'],
            ['keyword' => $longKeyword, 'volume' => 100, 'language' => 'en'],
        ], JSON_UNESCAPED_UNICODE);

        $import = Seed::raw(KeywordSource::SEARCH_CONSOLE, 'long.json', (string) $json);

        self::assertSame(2, (int) $import->rows_total);
        self::assertSame(1, (int) $import->rows_imported);
        self::assertSame(1, (int) $import->rows_rejected);
        self::assertNotNull(Keyword::find()->where(['keyword' => 'valid JSON long-row companion'])->one());
        self::assertSame(0, (int) Keyword::find()->where(['like', 'keyword', 'long keyword'])->count());
        self::assertStringContainsString('255', $import->getErrorItems()[0]['message']);
    }

    public function testValidKeywordAt255CharactersIsPreserved(): void
    {
        $keyword = str_repeat('a', 255);
        $csv = "keyword,volume,language\n{$keyword},100,en\n";

        $import = Seed::raw(KeywordSource::SEARCH_CONSOLE, 'max.csv', $csv);

        self::assertSame(1, (int) $import->rows_imported);
        $record = Keyword::find()->one();
        self::assertNotNull($record);
        self::assertSame($keyword, $record->keyword);
    }
}
