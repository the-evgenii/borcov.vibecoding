<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Keyword;
use app\models\KeywordSource;
use app\services\KeywordAuditor;
use app\services\LanguageCatalog;
use Yii;
use yii\data\Pagination;
use yii\filters\AccessControl;

class KeywordController extends BaseController
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

    public function actionIndex(): string
    {
        $q = trim($this->stringQuery('q'));
        $status = $this->stringQuery('status');
        if (!array_key_exists($status, Keyword::statusLabels())) {
            $status = '';
        }
        $reason = $this->stringQuery('reason');
        if (!array_key_exists($reason, Keyword::reasonLabels())) {
            $reason = '';
        }
        $source = $this->stringQuery('source');
        if (!KeywordSource::isValid($source)) {
            $source = '';
        }
        $language = LanguageCatalog::normalize($this->stringQuery('language'));
        if (mb_strlen($language) > 16) {
            $language = '';
        }
        $importIdRaw = Yii::$app->request->get('import_id', 0);
        $importId = is_scalar($importIdRaw) ? (int) $importIdRaw : 0;

        $query = Keyword::find()->with('importBatch');
        if ($q !== '') {
            $query->andFilterWhere(['like', 'keyword', $q]);
        }
        if ($status !== '') {
            $query->andWhere(['status' => $status]);
        }
        if ($reason !== '') {
            $query->andWhere(Keyword::reasonCondition($reason));
        }
        if ($source !== '') {
            $query->andWhere(['source' => $source]);
        }
        if ($language !== '') {
            $query->andWhere(['language' => $language]);
        }
        if ($importId > 0) {
            $query->andWhere(['import_id' => $importId]);
        }

        $pagination = new Pagination([
            'totalCount' => (clone $query)->count(),
            'pageSize' => 40,
        ]);
        $keywords = $query
            ->orderBy(['id' => SORT_DESC])
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        $languages = Keyword::find()
            ->select('language')
            ->distinct()
            ->orderBy(['language' => SORT_ASC])
            ->column();

        return $this->render('index', [
            'keywords' => $keywords,
            'pagination' => $pagination,
            'summary' => (new KeywordAuditor())->statusSummary(),
            'languages' => $languages,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'reason' => $reason,
                'source' => $source,
                'language' => $language,
                'import_id' => $importId,
            ],
        ]);
    }

    private function stringQuery(string $name): string
    {
        $value = Yii::$app->request->get($name);

        return is_string($value) ? $value : '';
    }
}
