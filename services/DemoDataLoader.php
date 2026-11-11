<?php

declare(strict_types=1);

namespace app\services;

use app\models\Import;
use app\models\KeywordSource;

final class DemoDataLoader
{
    private const FILES = [
        'google_ads_keywords.csv' => KeywordSource::GOOGLE_ADS,
        'search_console_queries.csv' => KeywordSource::SEARCH_CONSOLE,
        'ahrefs_organic_keywords.csv' => KeywordSource::AHREFS_ORGANIC,
        'ahrefs_paid_keywords.csv' => KeywordSource::AHREFS_PAID,
        'search_console_queries.json' => KeywordSource::SEARCH_CONSOLE,
    ];

    public function __construct(private readonly Importer $importer = new Importer())
    {
    }

    /**
     * Demo loading is idempotent: a bundled file is skipped after it has been
     * imported once, so clicking the dashboard button twice does not inflate
     * the raw dataset.
     *
     * @return array{loaded: int, skipped: int, records: int, errors: string[]}
     */
    public function load(?int $userId = null): array
    {
        $loaded = 0;
        $skipped = 0;
        $records = 0;
        $errors = [];

        foreach (self::FILES as $basename => $source) {
            $storedName = 'demo/' . $basename;
            $alreadyLoaded = Import::find()
                ->where(['original_filename' => $storedName])
                ->orWhere(['original_filename' => $basename])
                ->exists();
            if ($alreadyLoaded) {
                $skipped++;
                continue;
            }

            $path = dirname(__DIR__) . '/data/demo/' . $basename;
            $content = is_file($path) ? file_get_contents($path) : false;
            if ($content === false) {
                $errors[] = sprintf('Демо-файл отсутствует: %s', $basename);
                continue;
            }

            try {
                $import = $this->importer->import($source, $storedName, $content, $userId);
                $loaded++;
                $records += $import->rows_imported;
            } catch (ImportException $exception) {
                $errors[] = sprintf('%s: %s', $basename, $exception->getMessage());
            }
        }

        return [
            'loaded' => $loaded,
            'skipped' => $skipped,
            'records' => $records,
            'errors' => $errors,
        ];
    }
}
