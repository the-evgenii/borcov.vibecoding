<?php

declare(strict_types=1);

use yii\web\Request;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$cookieKey = (string) app_env('APP_COOKIE_VALIDATION_KEY', '');
if ($cookieKey === '') {
    if (YII_ENV === 'prod') {
        throw new RuntimeException('APP_COOKIE_VALIDATION_KEY must be set outside development.');
    }
    $cookieKey = 'dev-only-cookie-key-change-before-publishing';
}

$config = [
    'id' => 'vibe-ads-console',
    'name' => 'Vibe Ads Console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'language' => 'ru-RU',
    'timeZone' => app_env('APP_TIMEZONE', 'UTC'),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => $cookieKey,
            'enableCsrfValidation' => true,
            'csrfParam' => '_csrf',
            'csrfCookie' => [
                'httpOnly' => true,
                'secure' => app_bool('APP_COOKIE_SECURE', false),
                'sameSite' => 'Lax',
            ],
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => false,
            'loginUrl' => ['/site/login'],
            'returnUrlParam' => '__returnUrl',
        ],
        'session' => [
            'name' => 'vibeAdsSession',
            'cookieParams' => [
                'httpOnly' => true,
                'sameSite' => 'Lax',
                'secure' => app_bool('APP_COOKIE_SECURE', false),
            ],
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
            'cachePath' => '@runtime/cache',
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/app.log',
                    'maxFileSize' => 5 * 1024,
                    'maxLogFiles' => 5,
                    'logVars' => [],
                    'prefix' => static function (): string {
                        $request = Yii::$app->getRequest();
                        $ip = $request instanceof Request ? $request->getUserIP() : '-';
                        $user = Yii::$app->has('user') ? Yii::$app->get('user') : null;
                        $userId = $user !== null && ($identity = $user->getIdentity(false)) !== null
                            ? $identity->getId()
                            : '-';
                        $path = $request instanceof Request ? $request->getPathInfo() : '-';

                        return sprintf('[%s][%s][%s]', $ip, $userId, $path);
                    },
                ],
            ],
        ],
        'db' => $db,
        'assetManager' => [
            'basePath' => '@webroot/assets',
            'baseUrl' => '@web/assets',
            'appendTimestamp' => YII_DEBUG,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '' => 'site/index',
                'login' => 'site/login',
                'logout' => 'site/logout',
                'demo-data' => 'site/demo-data',
                'reset-data' => 'site/reset-data',
                'imports' => 'import/index',
                'keywords' => 'keyword/index',
                'settings' => 'settings/index',
                'campaigns/generate' => 'campaign/generate',
                'campaigns/preview' => 'campaign/preview',
                'campaigns/export' => 'campaign/export',
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
