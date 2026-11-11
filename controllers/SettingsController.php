<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\SettingsForm;
use app\services\KeywordAuditor;
use app\services\LanguageCatalog;
use app\services\SettingsService;
use Yii;
use yii\filters\AccessControl;
use yii\web\Response;

class SettingsController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(): Response|string
    {
        $service = new SettingsService();
        $setting = $service->get();
        $form = new SettingsForm();

        if ($this->request->isPost) {
            $post = $this->request->post();
            if (!$this->hasCompleteSettingsInput($post)) {
                $form->addError('urls', 'Форма настроек неполная. Обновите страницу и повторите отправку.');
                Yii::$app->response->statusCode = 422;
            } elseif (!$form->load($post)) {
                $form->addError('urls', 'Не удалось прочитать настройки.');
                Yii::$app->response->statusCode = 422;
            } elseif ($form->validate()) {
                $form->apply($setting);
                if ($setting->save(false) === false) {
                    $form->addError('urls', 'Не удалось сохранить настройки.');
                    Yii::$app->response->statusCode = 422;
                } else {
                    (new KeywordAuditor())->run();
                    Yii::$app->session->setFlash('success', 'Настройки сохранены, статусы ключевых слов пересчитаны.');

                    return $this->redirect(['/settings']);
                }
            } else {
                Yii::$app->response->statusCode = 422;
            }
        } else {
            $form->populate($setting);
        }

        return $this->render('index', [
            'form' => $form,
            'supportedLanguages' => LanguageCatalog::all(),
            'minVolume' => $setting->min_volume,
        ]);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function hasCompleteSettingsInput(array $post): bool
    {
        $data = $post['SettingsForm'] ?? null;
        if (!is_array($data)) {
            return false;
        }

        foreach (['brandsText', 'forbiddenText', 'minVolume', 'urls'] as $field) {
            if (!array_key_exists($field, $data)) {
                return false;
            }
        }

        if (!is_array($data['urls'])) {
            return false;
        }

        foreach (LanguageCatalog::codes() as $code) {
            if (!array_key_exists($code, $data['urls'])) {
                return false;
            }
        }

        return true;
    }
}
