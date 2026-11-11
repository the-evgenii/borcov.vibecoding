<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Import;
use app\services\DemoDataLoader;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class DemoDataLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        TestDb::settings();
    }

    public function testBundledDemoFilesLoadOnce(): void
    {
        $loader = new DemoDataLoader();

        $first = $loader->load(null);
        self::assertSame(5, $first['loaded']);
        self::assertSame(0, $first['skipped']);
        self::assertSame(89, $first['records']);
        self::assertSame([], $first['errors']);
        self::assertSame(5, (int) Import::find()->count());

        $second = $loader->load(null);
        self::assertSame(0, $second['loaded']);
        self::assertSame(5, $second['skipped']);
        self::assertSame(0, $second['records']);
        self::assertSame(5, (int) Import::find()->count());
    }
}
