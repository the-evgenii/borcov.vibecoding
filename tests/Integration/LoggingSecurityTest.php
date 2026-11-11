<?php

declare(strict_types=1);

namespace app\tests\Integration;

use PHPUnit\Framework\TestCase;
use Yii;
use yii\log\FileTarget;
use yii\log\Logger;

final class LoggingSecurityTest extends TestCase
{
    public function testConfiguredLogTargetDoesNotLeakRequestSecrets(): void
    {
        $config = require TEST_ROOT . '/config/web.php';
        $targetConfig = $config['components']['log']['targets'][0];
        $logFile = TEST_ROOT . '/runtime/tests/logging-security.log';
        @unlink($logFile);
        $targetConfig['logFile'] = $logFile;
        $targetConfig['exportInterval'] = 0;

        $target = Yii::createObject($targetConfig);
        self::assertInstanceOf(FileTarget::class, $target);
        $target->init();

        $previousPost = $_POST;
        $previousCookie = $_COOKIE;
        $previousSession = $_SESSION ?? null;
        $previousServer = $_SERVER;

        $_POST = ['LoginForm' => ['password' => 'FAKE_LOGIN_PASSWORD_123']];
        $_COOKIE = ['vibeAdsSession' => 'FAKE_COOKIE_VALUE_456'];
        $_SESSION = ['auth' => 'FAKE_SESSION_VALUE_789'];
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer FAKE_HEADER_VALUE_ABC';

        try {
            $target->collect([[
                'diagnostic marker',
                Logger::LEVEL_ERROR,
                'security-test',
                time(),
                [],
                0,
            ]], true);

            $log = is_file($logFile) ? (string) file_get_contents($logFile) : '';
        } finally {
            $_POST = $previousPost;
            $_COOKIE = $previousCookie;
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            $_SERVER = $previousServer;
            @unlink($logFile);
        }

        self::assertStringContainsString('diagnostic marker', $log);
        self::assertStringContainsString('security-test', $log);
        self::assertSame([], $target->logVars);
        self::assertStringNotContainsString('FAKE_LOGIN_PASSWORD_123', $log);
        self::assertStringNotContainsString('FAKE_COOKIE_VALUE_456', $log);
        self::assertStringNotContainsString('FAKE_SESSION_VALUE_789', $log);
        self::assertStringNotContainsString('FAKE_HEADER_VALUE_ABC', $log);
    }
}
