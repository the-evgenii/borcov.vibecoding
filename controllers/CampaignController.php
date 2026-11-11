<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Campaign;
use app\models\CampaignExclusion;
use app\models\CampaignGroup;
use app\services\AdsExportBuilder;
use app\services\CampaignGenerator;
use app\services\ExportException;
use app\services\LanguageCatalog;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

class CampaignController extends BaseController
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
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'generate' => ['post'],
                    'preview' => ['get'],
                    'export' => ['get'],
                ],
            ],
        ];
    }

    public function actionGenerate(): Response
    {
        try {
            $campaign = (new CampaignGenerator())->generate((int) Yii::$app->user->id);
            $summary = $campaign->getSummary();
            Yii::$app->session->setFlash(
                'success',
                sprintf(
                    'Кампания сгенерирована: групп объявлений — %d, исключений — %d.',
                    (int) ($summary['groups'] ?? 0),
                    (int) ($summary['excluded_keys'] ?? 0),
                ),
            );
        } catch (\Throwable $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Не удалось сгенерировать кампанию. Проверьте журнал приложения.');
        }

        return $this->redirect(['/campaigns/preview']);
    }

    public function actionPreview(): string
    {
        $campaign = Campaign::latest();
        $groupsByLanguage = [];
        $exclusions = [];
        $exclusionCounts = [];
        $exclusionCount = 0;
        $validation = null;

        if ($campaign !== null) {
            $groups = CampaignGroup::find()
                ->where(['campaign_id' => $campaign->id])
                ->with('ads')
                ->orderBy(['language' => SORT_ASC, 'ad_group_name' => SORT_ASC])
                ->all();
            foreach ($groups as $group) {
                $groupsByLanguage[$group->language][] = $group;
            }

            $exclusionQuery = CampaignExclusion::find()
                ->where(['campaign_id' => $campaign->id])
                ->orderBy(['language' => SORT_ASC, 'keyword' => SORT_ASC]);
            $exclusionCount = (int) (clone $exclusionQuery)->count();
            $exclusions = $exclusionQuery->limit(300)->all();
            $counts = CampaignExclusion::find()
                ->select(['reason', 'COUNT(*) AS total'])
                ->where(['campaign_id' => $campaign->id])
                ->groupBy(['reason'])
                ->asArray()
                ->all();
            foreach ($counts as $row) {
                $exclusionCounts[$row['reason']] = (int) $row['total'];
            }

            try {
                $validation = (new AdsExportBuilder())->validate($campaign);
            } catch (ExportException $exception) {
                $validation = ['ok' => false, 'errors' => [$exception->getMessage()]];
            }
        }

        $languageOrder = [];
        foreach (LanguageCatalog::codes() as $index => $code) {
            $languageOrder[$code] = $index;
        }

        return $this->render('preview', [
            'campaign' => $campaign,
            'campaignSummary' => $campaign?->getSummary() ?? [],
            'groupsByLanguage' => $groupsByLanguage,
            'languageOrder' => $languageOrder,
            'exclusions' => $exclusions,
            'exclusionCount' => $exclusionCount,
            'exclusionCounts' => $exclusionCounts,
            'validation' => $validation,
        ]);
    }

    public function actionExport(): Response
    {
        $campaign = Campaign::latest();
        if ($campaign === null) {
            Yii::$app->session->setFlash('warning', 'Сначала сгенерируйте кампанию, затем скачивайте CSV.');

            return $this->redirect(['/campaigns/preview']);
        }

        try {
            $csv = (new AdsExportBuilder())->build($campaign);
        } catch (ExportException $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Проверка экспорта не пройдена: ' . $exception->getMessage());

            return $this->redirect(['/campaigns/preview']);
        }

        $filename = sprintf('google-ads-editor-%d-%s.csv', $campaign->id, date('Ymd-His'));

        return Yii::$app->response->sendContentAsFile($csv, $filename, [
            'mimeType' => 'text/csv; charset=UTF-8',
            'inline' => false,
        ]);
    }
}
