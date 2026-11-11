<?php

declare(strict_types=1);

require __DIR__ . '/constants.php';

$root = TEST_ROOT;
$testRuntime = dirname(TEST_DB_PATH);
if (!is_dir($testRuntime) && !mkdir($testRuntime, 0777, true) && !is_dir($testRuntime)) {
    fwrite(STDERR, "Unable to create {$testRuntime}\n");
    exit(1);
}

$databasePath = TEST_DB_PATH;
if (is_file($databasePath)) {
    unlink($databasePath);
}

putenv('APP_ENV=test');
putenv('APP_DEBUG=0');
putenv('APP_TIMEZONE=UTC');
putenv('APP_COOKIE_VALIDATION_KEY=test-only-cookie-validation-key-please-change');
putenv('APP_DB_DSN=sqlite:' . $databasePath);
putenv('APP_ADMIN_EMAIL=admin@example.com');
putenv('APP_ADMIN_PASSWORD=test-only-password');

require $root . '/config/env.php';
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

$config = require $root . '/config/console.php';
$application = new yii\console\Application($config);
$application->runAction('migrate/up', ['interactive' => 0]);
