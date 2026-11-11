<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Campaign;
use app\models\Import;
use app\models\Keyword;
use app\models\LoginForm;
use app\services\DataReset;
use app\services\DemoDataLoader;
use app\services\KeywordAuditor;
use app\services\SettingsService;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'except' => ['login', 'error'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                    'demo-data' => ['post'],
                    'reset-data' => ['post'],
                ],
            ],
        ];
    }

    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    public function actionIndex(): string
    {
        $summary = (new KeywordAuditor())->statusSummary();
        $sourceCounts = Keyword::find()
            ->select(['source', 'COUNT(*) AS total'])
            ->groupBy(['source'])
            ->asArray()
            ->all();
        $bySource = [];
        foreach ($sourceCounts as $row) {
            $bySource[$row['source']] = (int) $row['total'];
        }

        $latestCampaign = Campaign::latest();
        $campaignSummary = $latestCampaign?->getSummary() ?? [];

        $urlCount = count(array_filter((new SettingsService())->languageUrls(), static fn (string $url): bool => $url !== ''));

        return $this->render('index', [
            'summary' => $summary,
            'bySource' => $bySource,
            'importCount' => (int) Import::find()->count(),
            'latestCampaign' => $latestCampaign,
            'campaignSummary' => $campaignSummary,
            'urlCount' => $urlCount,
        ]);
    }

    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($this->request->isPost && $model->load($this->request->post())) {
            if ($model->login()) {
                return $this->goBack(['/site/index']);
            }
            $model->password = '';
            Yii::$app->response->statusCode = 422;
        }

        return $this->render('login', ['model' => $model]);
    }

    public function actionLogout(): Response
    {
        Yii::$app->user->logout();
        Yii::$app->session->regenerateID(true);
        Yii::$app->session->setFlash('success', 'Вы вышли из системы.');

        return $this->redirect(['/site/login']);
    }

    public function actionDemoData(): Response
    {
        $result = (new DemoDataLoader())->load((int) Yii::$app->user->id);
        if ($result['errors'] !== []) {
            Yii::$app->session->setFlash(
                'error',
                'Демо-данные загружены частично: ' . implode(' ', $result['errors']),
            );
        } else {
            Yii::$app->session->setFlash(
                'success',
                sprintf(
                    'Демо-данные готовы: загружено файлов — %d, пропущено — %d, добавлено строк — %d.',
                    $result['loaded'],
                    $result['skipped'],
                    $result['records'],
                ),
            );
        }

        return $this->redirect(['/imports']);
    }

    public function actionResetData(): Response
    {
        $removed = (new DataReset())->run();

        Yii::$app->session->setFlash(
            'success',
            sprintf(
                'Данные очищены: импортов — %d, ключевых слов — %d, кампаний — %d. Настройки сохранены.',
                $removed['imports'],
                $removed['keywords'],
                $removed['campaigns'],
            ),
        );

        return $this->redirect(['/site/index']);
    }
}
