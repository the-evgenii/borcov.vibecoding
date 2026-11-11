<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use yii\web\Controller;

abstract class BaseController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $headers = Yii::$app->response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (!YII_DEBUG) {
            $headers->set(
                'Content-Security-Policy',
                "default-src 'self'; style-src 'self'; img-src 'self' data:; "
                . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'",
            );
        }

        return true;
    }
}
