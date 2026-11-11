<?php

declare(strict_types=1);

require __DIR__ . '/constants.php';

putenv('APP_ENV=test');
putenv('APP_DEBUG=0');
putenv('APP_TIMEZONE=UTC');
putenv('APP_COOKIE_VALIDATION_KEY=test-only-cookie-validation-key-please-change');
putenv('APP_DB_DSN=sqlite:' . TEST_DB_PATH);
putenv('APP_ADMIN_EMAIL=admin@example.com');
putenv('APP_ADMIN_PASSWORD=test-only-password');

require dirname(__DIR__) . '/config/env.php';
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
