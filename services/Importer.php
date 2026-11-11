<?php

declare(strict_types=1);

namespace app\services;

use app\models\Import;
use app\models\Keyword;
use app\models\KeywordSource;
use JsonException;
use Yii;
use yii\helpers\Json;

final class Importer
{
    private const ALIASES = [
        'keyword' => [
            'keyword',
            'keywords',
            'key word',
            'query',
            'search query',
            'term',
            'search term',
            'phrase',
            'top queries',
            'top query',
        ],
        'volume' => [
            'volume',
            'search volume',
            'avg monthly searches',
            'average monthly searches',
            'monthly searches',
            'monthly volume',
            'impressions',
            'searches',
            'traffic',
        ],
        'language' => [
            'language',
            'lang',
            'locale',
            'language code',
            'target language',
            'language targeting',
        ],
        'url' => [
            'url',
            'final url',
            'final urls',
            'landing page',
            'target url',
            'page',
            'link',
        ],
    ];

    private readonly KeywordNormalizer $normalizer;
    private readonly int $maxBytes;
    private readonly int $maxRows;

    public function __construct(
        private readonly KeywordAuditor $auditor = new KeywordAuditor(),
        ?int $maxBytes = null,
        ?int $maxRows = null,
    ) {
        $this->normalizer = new KeywordNormalizer();
        $this->maxBytes = $maxBytes ?? (int) (Yii::$app->params['import']['maxBytes'] ?? 2 * 1024 * 1024);
        $this->maxRows = $maxRows ?? (int) (Yii::$app->params['import']['maxRows'] ?? 5000);
    }

    public function import(string $source, string $filename, string $content, ?int $userId = null): Import
    {
        if (!KeywordSource::isValid($source)) {
            throw new ImportException('Неизвестный источник импорта.');
        }

        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '') {
            $filename = 'upload';
        }
        $filename = mb_substr($filename, 0, 255);

        $format = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new ImportException('Поддерживаются только файлы CSV и JSON.');
        }

        if (strlen($content) === 0) {
            throw new ImportException('Загруженный файл пуст.');
        }

        if (strlen($content) > $this->maxBytes) {
            throw new ImportException(sprintf(
                'Файл больше лимита импорта (%d МБ).',
                (int) floor($this->maxBytes / 1024 / 1024),
            ));
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new ImportException('Файл должен быть в кодировке UTF-8.');
        }

        $content = $this->stripBom($content);
        $parsed = $format === 'csv'
            ? $this->parseCsv($content)
            : $this->parseJson($content);

        if ($parsed['total'] === 0) {
            throw new ImportValidationException('Файл не содержит строк данных.');
        }

        if ($parsed['total'] > $this->maxRows) {
            throw new ImportException(sprintf(
                'Файл содержит больше строк, чем лимит импорта (%d).',
                $this->maxRows,
            ));
        }

        if ($parsed['rows'] === []) {
            throw new ImportValidationException('В файле не найдено корректных строк.', $parsed['errors']);
        }

        $errors = $this->limitErrors($parsed['errors']);
        $import = new Import([
            'user_id' => $userId,
            'source' => $source,
            'original_filename' => $filename,
            'format' => $format,
            'rows_total' => $parsed['total'],
            'rows_imported' => count($parsed['rows']),
            'rows_rejected' => $parsed['total'] - count($parsed['rows']),
            'errors_json' => $errors === [] ? null : Json::encode($errors, JSON_UNESCAPED_UNICODE),
        ]);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$import->save(false)) {
                throw new ImportException('Не удалось сохранить импорт.');
            }

            foreach ($parsed['rows'] as $row) {
                $keyword = new Keyword([
                    'import_id' => $import->id,
                    'source' => $source,
                    'keyword' => $row['keyword'],
                    'keyword_norm' => $row['keyword_norm'],
                    'volume' => $row['volume'],
                    'language' => $row['language'],
                    'url' => $row['url'],
                    'created_at' => time(),
                ]);
                if (!$keyword->save(false)) {
                    throw new ImportException('Не удалось сохранить импортированное ключевое слово.');
                }
            }

            $this->auditor->run();
            $transaction->commit();
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }

        return $import;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int, errors: array<int, array{row: int, message: string}>}
     */
    private function parseCsv(string $content): array
    {
        $firstLine = strtok($content, "\n");
        $delimiter = ',';
        if ($firstLine !== false) {
            $counts = [
                ',' => substr_count($firstLine, ','),
                ';' => substr_count($firstLine, ';'),
                "\t" => substr_count($firstLine, "\t"),
            ];
            arsort($counts);
            $candidate = array_key_first($counts);
            if ($counts[$candidate] > 0) {
                $delimiter = $candidate;
            }
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new ImportException('Не удалось прочитать CSV-файл.');
        }

        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        if ($header === false || $header === [null]) {
            fclose($stream);
            throw new ImportException('В CSV-файле нет строки заголовков.');
        }

        $map = $this->mapHeader($header);
        $this->assertRequiredColumns($map);

        $rows = [];
        $errors = [];
        $total = 0;
        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null] || $this->isEmptyRow($cells)) {
                continue;
            }

            $total++;
            $lineNumber = $total + 1;
            if ($total > $this->maxRows) {
                fclose($stream);
                throw new ImportException(sprintf(
                    'Файл содержит больше строк, чем лимит импорта (%d).',
                    $this->maxRows,
                ));
            }

            if (count($cells) > count($header)) {
                $extra = array_slice($cells, count($header));
                if (!$this->isEmptyRow($extra)) {
                    $errors[] = ['row' => $lineNumber, 'message' => 'Строка содержит больше столбцов, чем заголовок.'];
                    continue;
                }
            }

            $raw = [];
            foreach ($map as $canonical => $index) {
                $raw[$canonical] = $cells[$index] ?? '';
            }

            $row = $this->normalizeRow($raw, $lineNumber, $errors);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        fclose($stream);

        return ['rows' => $rows, 'total' => $total, 'errors' => $errors];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int, errors: array<int, array{row: int, message: string}>}
     */
    private function parseJson(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ImportException('Некорректный JSON: ' . $exception->getMessage());
        }

        if (!is_array($decoded)) {
            throw new ImportException('JSON должен содержать массив записей.');
        }

        if (!array_is_list($decoded)) {
            $records = null;
            foreach (['items', 'data', 'keywords', 'records'] as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key]) && array_is_list($decoded[$key])) {
                    $records = $decoded[$key];
                    break;
                }
            }
            if ($records === null) {
                throw new ImportException('JSON должен быть массивом или содержать массив items/data/keywords/records.');
            }
            $decoded = $records;
        }

        $total = count($decoded);
        if ($total > $this->maxRows) {
            throw new ImportException(sprintf(
                'Файл содержит больше строк, чем лимит импорта (%d).',
                $this->maxRows,
            ));
        }

        $rows = [];
        $errors = [];
        $index = 0;
        foreach ($decoded as $record) {
            $index++;
            if (!is_array($record) || array_is_list($record)) {
                $errors[] = ['row' => $index, 'message' => 'Запись должна быть JSON-объектом.'];
                continue;
            }

            $raw = [];
            foreach ($record as $key => $value) {
                $canonical = $this->canonicalHeader((string) $key);
                if ($canonical === null || array_key_exists($canonical, $raw)) {
                    continue;
                }
                if (is_array($value) || is_object($value)) {
                    $errors[] = [
                        'row' => $index,
                        'message' => sprintf('Поле «%s» должно быть скалярным значением.', $key),
                    ];
                    continue 2;
                }
                $raw[$canonical] = $value;
            }

            if (!array_key_exists('keyword', $raw) || !array_key_exists('volume', $raw) || !array_key_exists('language', $raw)) {
                $errors[] = [
                    'row' => $index,
                    'message' => 'В записи отсутствуют keyword, volume или language.',
                ];
                continue;
            }

            $row = $this->normalizeRow($raw, $index, $errors);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return ['rows' => $rows, 'total' => $total, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<int, array{row: int, message: string}> $errors
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $raw, int $rowNumber, array &$errors): ?array
    {
        $rawKeyword = is_scalar($raw['keyword'] ?? null) ? (string) $raw['keyword'] : '';
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $rawKeyword) === 1) {
            $errors[] = ['row' => $rowNumber, 'message' => 'Ключевое слово содержит управляющие символы.'];

            return null;
        }

        $keyword = $this->normalizer->cleanText($rawKeyword);
        if ($keyword === '') {
            $errors[] = ['row' => $rowNumber, 'message' => 'Ключевое слово обязательно.'];

            return null;
        }
        if (mb_strlen($keyword) > 255) {
            $errors[] = ['row' => $rowNumber, 'message' => 'Ключевое слово длиннее 255 символов.'];

            return null;
        }

        $language = LanguageCatalog::normalize(is_scalar($raw['language'] ?? null) ? (string) $raw['language'] : '');
        if ($language === '') {
            $errors[] = ['row' => $rowNumber, 'message' => 'Язык обязателен.'];

            return null;
        }
        if (mb_strlen($language) > 16) {
            $errors[] = ['row' => $rowNumber, 'message' => 'Код языка слишком длинный.'];

            return null;
        }

        $volumeRaw = is_scalar($raw['volume'] ?? null) ? (string) $raw['volume'] : '';
        $volumeRaw = trim(str_replace([',', ' ', "\u{00A0}"], '', $volumeRaw));
        if ($volumeRaw === '') {
            $volume = 0;
        } elseif (preg_match('/^\d+(?:\.\d+)?$/', $volumeRaw) !== 1) {
            $errors[] = ['row' => $rowNumber, 'message' => 'Объём должен быть неотрицательным числом.'];

            return null;
        } else {
            $volume = (int) $volumeRaw;
            if ($volume > 1_000_000_000) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Объём неразумно большой.'];

                return null;
            }
        }

        $url = trim(is_scalar($raw['url'] ?? null) ? (string) $raw['url'] : '');
        if ($url !== '' && !UrlGuard::isHttpUrl($url)) {
            $errors[] = ['row' => $rowNumber, 'message' => 'URL должен быть корректным http(s)-адресом.'];

            return null;
        }

        return [
            'keyword' => $keyword,
            'keyword_norm' => $this->normalizer->normalize($keyword),
            'volume' => $volume,
            'language' => $language,
            'url' => $url === '' ? null : $url,
        ];
    }

    /**
     * @param string[] $header
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $column) {
            $canonical = $this->canonicalHeader((string) $column);
            if ($canonical !== null && !array_key_exists($canonical, $map)) {
                $map[$canonical] = $index;
            }
        }

        return $map;
    }

    private function canonicalHeader(string $header): ?string
    {
        $normalized = mb_strtolower(trim($header));
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_C) ?: $normalized;
        }
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');

        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * @param array<string, int> $map
     */
    private function assertRequiredColumns(array $map): void
    {
        $missing = [];
        foreach (['keyword', 'volume', 'language'] as $required) {
            if (!array_key_exists($required, $map)) {
                $missing[] = $required;
            }
        }

        if ($missing !== []) {
            throw new ImportException(
                'Отсутствуют обязательные столбцы: ' . implode(', ', $missing) . '. '
                . 'Принимаются заголовки keyword, query, top queries. '
                . 'Алиасы объёма: volume, impressions, search volume. '
                . 'Алиасы языка: language, lang, locale.',
            );
        }
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array{row: int, message: string}> $errors
     * @return array<int, array{row: int, message: string}>
     */
    private function limitErrors(array $errors): array
    {
        if (count($errors) <= 50) {
            return $errors;
        }

        $limited = array_slice($errors, 0, 50);
        $limited[] = ['row' => 0, 'message' => sprintf('…и ещё ошибок строк: %d.', count($errors) - 50)];

        return $limited;
    }

    private function stripBom(string $content): string
    {
        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }
}
