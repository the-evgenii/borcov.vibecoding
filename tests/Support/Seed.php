<?php

declare(strict_types=1);

namespace app\tests\Support;

use app\models\Import;
use app\services\Importer;

final class Seed
{
    /**
     * @param array<int, array{0: string, 1: int|string, 2?: string, 3?: string}> $rows
     */
    public static function csv(
        string $source,
        array $rows,
        string $filename = 'fixture.csv',
        string $header = 'keyword,volume,language,url',
    ): Import {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create the fixture CSV stream.');
        }

        fputcsv($stream, str_getcsv($header, ',', '"', ''), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (new Importer())->import($source, $filename, (string) $csv);
    }

    public static function raw(string $source, string $filename, string $content): Import
    {
        return (new Importer())->import($source, $filename, $content);
    }
}
