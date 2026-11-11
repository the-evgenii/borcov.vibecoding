<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\ImportForm;
use app\models\LoginForm;
use app\models\SettingsForm;
use app\models\User;
use app\services\SettingsService;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class SettingsAndSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
    }

    public function testInvalidTargetUrlIsRejected(): void
    {
        $form = new SettingsForm();
        $form->load([
            'SettingsForm' => [
                'brandsText' => 'site.pro',
                'forbiddenText' => 'casino',
                'minVolume' => '50',
                'urls' => ['en' => 'javascript:alert(1)'],
            ],
        ]);

        self::assertFalse($form->validate());
        self::assertNotEmpty($form->getErrors('urls'));
        self::assertStringContainsString('целевой URL', (string) $form->getFirstError('urls'));
    }

    public function testValidSettingsCanBeApplied(): void
    {
        $setting = (new SettingsService())->get();
        $form = new SettingsForm();
        $form->load([
            'SettingsForm' => [
                'brandsText' => "site.pro\nsite pro",
                'forbiddenText' => "casino\nfree",
                'minVolume' => '75',
                'urls' => ['en' => 'https://site.pro/Website-Builder/', 'de' => 'https://site.pro/de/'],
            ],
        ]);
        self::assertTrue($form->validate(), implode(' ', array_merge(...array_values($form->getErrors()))));
        $form->apply($setting);
        $setting->save(false);

        $reloaded = (new SettingsService())->get();
        self::assertSame(75, (int) $reloaded->min_volume);
        self::assertSame(['site.pro', 'site pro'], $reloaded->getBrands());
        self::assertSame('https://site.pro/de/', $reloaded->getLanguageUrls()['de']);
    }

    public function testMalformedFormArraysDoNotThrowTypeErrors(): void
    {
        $login = new LoginForm();
        $login->load(['LoginForm' => ['email' => ['not-an-email'], 'password' => ['x']]]);
        self::assertFalse($login->validate());

        $settings = new SettingsForm();
        $settings->load([
            'SettingsForm' => [
                'brandsText' => ['bad'],
                'forbiddenText' => ['bad'],
                'minVolume' => [],
                'urls' => 'not-an-array',
            ],
        ]);
        self::assertFalse($settings->validate());

        $import = new ImportForm();
        $import->load(['ImportForm' => ['source' => ['google_ads']]]);
        self::assertFalse($import->validate());
    }

    public function testPasswordHashingAndNonDevDebugConfiguration(): void
    {
        $password = 'Strong-test-password-123';
        $user = TestDb::admin('hash@example.com', $password);

        self::assertNotSame($password, $user->password_hash);
        self::assertGreaterThan(50, strlen($user->password_hash));
        self::assertTrue($user->validatePassword($password));
        self::assertFalse($user->validatePassword('wrong-password'));
        self::assertNotSame('unknown', password_get_info($user->password_hash)['algoName']);

        self::assertFalse(YII_DEBUG);
        self::assertSame('test', YII_ENV);
    }
}
