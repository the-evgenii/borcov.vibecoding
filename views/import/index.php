<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\ImportForm $form */
/** @var app\models\Import[] $imports */
/** @var yii\data\Pagination $pagination */
/** @var array<int, array{row: int, message: string}> $importErrors */
/** @var array<string, array{0: string, 1: string}> $samples */

use app\models\ImportForm;
use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = 'Импорт';
$this->params['pageTitle'] = 'Импорт';
?>
<div class="page-head">
    <div>
        <h1>Импорт</h1>
        <p class="page-desc">Загружайте исходные файлы, проверяйте отклонённые строки и сохраняйте аудит исходных ключевых слов.</p>
    </div>
    <?= Html::a('Проверить ключевые слова', ['/keyword/index'], ['class' => 'btn btn-secondary']) ?>
</div>

<?php if ($importErrors !== []): ?>
    <div class="notice notice-error">
        <p><strong>Файл не был импортирован.</strong> Исправьте ошибки строк ниже и загрузите файл снова.</p>
        <ul class="error-list">
            <?php foreach (array_slice($importErrors, 0, 15) as $error): ?>
                <li>Строка <?= (int) ($error['row'] ?? 0) ?>: <?= Html::encode($error['message'] ?? 'некорректная строка') ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($importErrors) > 15): ?>
            <p class="hint">Скрыто ещё ошибок строк: <?= count($importErrors) - 15 ?>.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="split-grid">
    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Новый импорт</h2>
            <span class="panel-meta">CSV / JSON · 2 МБ · 5 000 строк</span>
        </div>
        <div class="panel-body">
            <?= Html::beginForm(['/import/index'], 'post', [
                'enctype' => 'multipart/form-data',
                'class' => 'form-stack',
                'novalidate' => true,
            ]) ?>
                <div class="field">
                    <?= Html::label('Источник данных', 'import-source', ['class' => 'field-label']) ?>
                    <?= Html::dropDownList('ImportForm[source]', $form->source, ImportForm::sourceOptions(), [
                        'id' => 'import-source',
                        'class' => 'input' . ($form->hasErrors('source') ? ' is-invalid' : ''),
                        'prompt' => 'Выберите источник…',
                        'required' => true,
                    ]) ?>
                    <?= $this->render('//site/_field_errors', ['model' => $form, 'attribute' => 'source']) ?>
                </div>

                <div class="field">
                    <?= Html::label('Файл', 'import-file', ['class' => 'field-label']) ?>
                    <?= Html::fileInput('ImportForm[file]', null, [
                        'id' => 'import-file',
                        'class' => 'input file-input' . ($form->hasErrors('file') ? ' is-invalid' : ''),
                        'accept' => '.csv,.json,text/csv,application/json',
                        'required' => true,
                    ]) ?>
                    <?= $this->render('//site/_field_errors', ['model' => $form, 'attribute' => 'file']) ?>
                </div>

                <div class="form-actions">
                    <?= Html::submitButton('Импортировать', ['class' => 'btn btn-primary']) ?>
                </div>
            <?= Html::endForm() ?>

            <div class="sample-links">
                <span class="sample-links-label">Примеры файлов:</span>
                <?php foreach ($samples as $key => [$filename, $label]): ?>
                    <?= Html::a(
                        Html::encode($label),
                        ['/import/sample', 'name' => $key],
                        ['class' => 'sample-link', 'title' => 'Скачать ' . $filename],
                    ) ?>
                <?php endforeach; ?>
            </div>

            <div class="form-note">
                <p><code>keyword</code>, <code>volume</code> и <code>language</code> обязательны. <code>url</code> можно не указывать; если указан — только <code>http</code> или <code>https</code>.</p>
                <p>Принимаются алиасы <code>query</code>, <code>top queries</code>, <code>search volume</code>, <code>final URL</code>, <code>lang</code> и <code>locale</code>. Заголовки сопоставляются без учёта регистра и пунктуации.</p>
                <p>Частотность — запросов в месяц, а не показы или трафик. Если выгрузка содержит только <code>impressions</code> или <code>traffic</code>, добавьте <code>volume</code> с частотностью из отдельного источника. В примерах частотность синтетическая.</p>
                <p>Дробная частотность округляется вверх: 12,5 → 13. Точка и запятая — десятичные разделители; тысячи записывайте без разделителя или с пробелом: 1200 или 1 200.</p>
                <p>JSON может быть массивом объектов или объектом с массивом <code>items</code>, <code>data</code>, <code>keywords</code> или <code>records</code>. Файл должен быть в UTF-8.</p>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Правила очистки</h2>
            <span class="panel-meta">Применяется автоматически</span>
        </div>
        <div class="panel-body">
            <ol class="policy-list">
                <li><strong>Мусор</strong><span>Пустые, только цифры, URL/HTML, длиннее 80 символов или 10 слов, триггеры формул.</span></li>
                <li><strong>Брендовые слова</strong><span>Настроенные фразы, совпадающие по нормализованным словам.</span></li>
                <li><strong>Запрещённые слова</strong><span>Настроенный многоязычный список запрещённых терминов.</span></li>
                <li><strong>Частотность</strong><span>Ключи ниже минимальной частотности исключаются.</span></li>
                <li><strong>Дубликаты</strong><span>Объединяются по нормализованному ключевому слову и языку при генерации.</span></li>
                <li><strong>Уже используется</strong><span>Все ключевые слова из Google Ads исключаются из новых кампаний.</span></li>
            </ol>
            <p class="hint">Неподдерживаемые языки остаются в админке и явно показываются в исключениях кампании. Перевод не подставляется.</p>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">История импорта</h2>
        <span class="panel-meta">Пакетов: <?= (int) $pagination->totalCount ?></span>
    </div>

    <?php if ($imports === []): ?>
        <div class="panel-body">
            <div class="empty-inline">
                <p>Импортов пока нет. Загрузите файл выше или используйте <strong>Загрузить демо-данные</strong> на странице обзора.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="panel-body panel-body-flush">
            <div class="table-wrap">
                <table class="data-table import-table" aria-label="История импорта">
                    <thead>
                        <tr>
                            <th>Пакет</th>
                            <th>Источник</th>
                            <th>Файл</th>
                            <th class="numeric">Импортировано</th>
                            <th class="numeric">Отклонено</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($imports as $import): ?>
                        <tr>
                            <td class="mono">#<?= (int) $import->id ?></td>
                            <td><span class="badge badge-source"><?= Html::encode($import->getSourceLabel()) ?></span></td>
                            <td>
                                <span class="file-cell">
                                    <span class="mono file-name" title="<?= Html::encode($import->original_filename) ?>"><?= Html::encode($import->original_filename) ?></span>
                                    <span class="badge badge-neutral"><?= Html::encode(mb_strtoupper($import->format)) ?></span>
                                </span>
                                <span class="record-meta">Создан <?= Html::encode(Yii::$app->formatter->asDatetime($import->created_at, 'php:Y-m-d H:i')) ?></span>
                                <?php $errorItems = $import->getErrorItems(); ?>
                                <?php if ($errorItems !== []): ?>
                                    <details class="inline-details">
                                        <summary>Ошибок строк: <?= count($errorItems) ?></summary>
                                        <ul class="error-list">
                                            <?php foreach (array_slice($errorItems, 0, 20) as $error): ?>
                                                <li>Строка <?= (int) ($error['row'] ?? 0) ?>: <?= Html::encode($error['message'] ?? 'некорректная строка') ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="numeric"><?= number_format((int) $import->rows_imported) ?> / <?= number_format((int) $import->rows_total) ?></td>
                            <td class="numeric"><?= number_format((int) $import->rows_rejected) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pager-wrap">
                <?= LinkPager::widget([
                    'pagination' => $pagination,
                    'options' => ['class' => 'pager'],
                    'linkOptions' => ['class' => 'pager-link'],
                    'prevPageLabel' => 'Назад',
                    'nextPageLabel' => 'Вперёд',
                ]) ?>
            </div>
        </div>
    <?php endif; ?>
</section>
