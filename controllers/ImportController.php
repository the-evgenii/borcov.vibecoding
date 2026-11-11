<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Import;
use app\models\ImportForm;
use app\services\ImportException;
use app\services\ImportValidationException;
use app\services\Importer;
use Yii;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ImportController extends BaseController
{
    /** Demo files offered on the import page; the key is the download route segment. */
    private const SAMPLE_FILES = [
        'google-ads' => ['google_ads_keywords.csv', 'Google Ads — CSV'],
        'search-console' => ['search_console_queries.csv', 'Search Console — CSV'],
        'search-console-json' => ['search_console_queries.json', 'Search Console — JSON'],
        'ahrefs-organic' => ['ahrefs_organic_keywords.csv', 'Ahrefs organic — CSV'],
        'ahrefs-paid' => ['ahrefs_paid_keywords.csv', 'Ahrefs paid — CSV'],
    ];

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
        $form = new ImportForm();
        $importErrors = [];

        if ($this->request->isPost) {
            $form->load($this->request->post());
            $form->file = UploadedFile::getInstance($form, 'file');

            if ($form->validate()) {
                try {
                    $content = file_get_contents($form->file->tempName);
                    if ($content === false) {
                        throw new ImportException('Не удалось прочитать загруженный файл.');
                    }

                    $import = (new Importer())->import(
                        $form->source,
                        $form->file->name,
                        $content,
                        (int) Yii::$app->user->id,
                    );

                    $message = sprintf(
                        'Импортировано %d из %d строк из %s.',
                        $import->rows_imported,
                        $import->rows_total,
                        $import->original_filename,
                    );
                    if ($import->rows_rejected > 0) {
                        $message .= sprintf(' Отклонено строк: %d.', $import->rows_rejected);
                        $details = [];
                        foreach (array_slice($import->getErrorItems(), 0, 3) as $error) {
                            $details[] = sprintf('строка %d: %s', $error['row'] ?? 0, $error['message'] ?? 'некорректная строка');
                        }
                        if ($details !== []) {
                            $message .= ' ' . implode('; ', $details);
                        }
                        Yii::$app->session->setFlash('warning', $message);
                    } else {
                        Yii::$app->session->setFlash('success', $message);
                    }

                    return $this->redirect(['/imports']);
                } catch (ImportValidationException $exception) {
                    $form->addError('file', $exception->getMessage());
                    $importErrors = $exception->getRowErrors();
                    Yii::$app->response->statusCode = 422;
                } catch (ImportException $exception) {
                    $form->addError('file', $exception->getMessage());
                    Yii::$app->response->statusCode = 422;
                } catch (\Throwable $exception) {
                    Yii::error($exception->getMessage(), __METHOD__);
                    $form->addError('file', 'Импорт завершился с ошибкой. Проверьте журнал приложения.');
                    Yii::$app->response->statusCode = 422;
                }
            } else {
                Yii::$app->response->statusCode = 422;
            }
        }

        $query = Import::find()->orderBy(['id' => SORT_DESC]);
        $pagination = new Pagination([
            'totalCount' => (clone $query)->count(),
            'pageSize' => 20,
        ]);
        $imports = $query
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        return $this->render('index', [
            'form' => $form,
            'imports' => $imports,
            'pagination' => $pagination,
            'importErrors' => $importErrors,
            'samples' => self::SAMPLE_FILES,
        ]);
    }

    public function actionSample(string $name): Response
    {
        $sample = self::SAMPLE_FILES[$name] ?? null;
        if ($sample === null) {
            throw new NotFoundHttpException('Пример файла не найден.');
        }

        [$filename] = $sample;
        $path = dirname(__DIR__) . '/data/demo/' . $filename;
        if (!is_file($path)) {
            throw new NotFoundHttpException('Пример файла недоступен.');
        }

        return $this->response->sendFile(
            $path,
            $filename,
            ['mimeType' => str_ends_with($filename, '.json') ? 'application/json' : 'text/csv; charset=UTF-8'],
        );
    }
}
