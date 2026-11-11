<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\services\CsvSafety;
use app\services\ExportException;
use PHPUnit\Framework\TestCase;

final class CsvSafetyTest extends TestCase
{
    public function testDetectsFormulaTriggers(): void
    {
        foreach (['=1+1', '+cmd', '-cmd', '@cmd', " \t=cmd", "\r=cmd"] as $value) {
            self::assertTrue(CsvSafety::isDangerous($value), $value);
        }

        self::assertFalse(CsvSafety::isDangerous('website builder'));
        self::assertFalse(CsvSafety::isDangerous('x=y'));
        self::assertFalse(CsvSafety::isDangerous(''));
    }

    public function testFailsClosedInsteadOfMutatingGoogleAdsText(): void
    {
        $this->expectException(ExportException::class);
        CsvSafety::assertTextSafe('=HYPERLINK("https://evil.example")', 'Keyword');
    }

    public function testRejectsLineBreaksInExportedText(): void
    {
        $this->expectException(ExportException::class);
        CsvSafety::assertTextSafe("website\nbuilder", 'Keyword');
    }
}
