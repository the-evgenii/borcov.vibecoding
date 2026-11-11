<?php

declare(strict_types=1);

$dsn = app_env('APP_DB_DSN', 'sqlite:@app/runtime/db.sqlite');
$dsn = str_replace(
    ['@app', '@webroot'],
    [dirname(__DIR__), dirname(__DIR__) . '/web'],
    (string) $dsn,
);

$config = [
    'class' => \yii\db\Connection::class,
    'dsn' => $dsn,
];

if (!str_starts_with($dsn, 'sqlite:')) {
    $config['username'] = app_env('APP_DB_USER', '');
    $config['password'] = app_env('APP_DB_PASSWORD', '');
    $config['charset'] = 'utf8mb4';
}

return $config;
