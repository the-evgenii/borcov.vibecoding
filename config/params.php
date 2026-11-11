<?php

declare(strict_types=1);

return [
    'appName' => 'Vibe Ads Console',
    'adminEmail' => app_env('APP_ADMIN_EMAIL', ''),
    'gads' => [
        'dailyBudget' => 10.00,
        'maxCpc' => 0.50,
        'matchType' => 'Phrase',
        'networks' => 'Google Search',
    ],
    'import' => [
        'maxBytes' => 2 * 1024 * 1024,
        'maxRows' => 5000,
        'allowedExtensions' => ['csv', 'json'],
    ],
];
