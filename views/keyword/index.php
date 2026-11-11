<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Keyword[] $keywords */
/** @var yii\data\Pagination $pagination */
/** @var array<string, mixed> $summary */
/** @var string[] $languages */
/** @var array<string, mixed> $filters */

use app\models\Keyword;
use app\models\KeywordSource;
use app\services\LanguageCatalog;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Ключевые слова';
$this->params['pageTitle'] = 'Ключевые слова';

$badgeClass = static fn (string $status): string => match ($status) {
    Keyword::STATUS_INCLUDED => 'badge-success',
    Keyword::STATUS_DUPLICATE => 'badge-warning',
    default => 'badge-danger',
};

$shortReason = Keyword::shortReasonLabels();
?>
<div class="page-head">
    <div>
        <h1>Ключевые слова</h1>
        <p class="page-desc">Исходные строки источников, статус очистки и причины исключения. Фильтры применяются к импортированным записям.</p>
    </div>
    <div class="page-actions">
        <?= Html::a('Импортировать данные', ['/import/index'], ['class' => 'btn btn-secondary']) ?>
        <?= Html::beginForm(['/campaign/generate'], 'post', ['class' => 'inline-form']) ?>
            <button type="submit" class="btn btn-primary">Сгенерировать кампанию</button>
        <?= Html::endForm() ?>
    </div>
</div>

<section class="metrics metrics-compact" aria-label="Сводка ключевых слов">
    <div class="metric">
        <span class="metric-label">Всего записей</span>
        <span class="metric-value"><?= number_format((int) ($summary['records_total'] ?? 0)) ?></span>
    </div>
    <div class="metric">
        <span class="metric-label">Оставлено</span>
        <span class="metric-value"><?= number_format((int) ($summary['included_records'] ?? 0)) ?></span>
    </div>
    <div class="metric">
        <span class="metric-label">Дубликаты</span>
        <span class="metric-value"><?= number_format((int) ($summary['duplicate_records'] ?? 0)) ?></span>
    </div>
    <div class="metric">
        <span class="metric-label">Исключено</span>
        <span class="metric-value"><?= number_format((int) ($summary['excluded_records'] ?? 0)) ?></span>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Фильтры</h2>
        <span class="panel-meta">Совпадений: <?= number_format((int) $pagination->totalCount) ?></span>
    </div>
    <div class="panel-body">
        <?= Html::beginForm(['/keyword/index'], 'get', ['class' => 'filter-grid']) ?>
            <div class="field field-compact">
                <?= Html::label('Поиск', 'filter-q', ['class' => 'field-label']) ?>
                <?= Html::textInput('q', (string) $filters['q'], [
                    'id' => 'filter-q',
                    'class' => 'input',
                    'placeholder' => 'Содержит…',
                ]) ?>
            </div>
            <div class="field field-compact">
                <?= Html::label('Статус', 'filter-status', ['class' => 'field-label']) ?>
                <?= Html::dropDownList('status', (string) $filters['status'], Keyword::statusLabels(), [
                    'id' => 'filter-status',
                    'class' => 'input',
                    'prompt' => 'Все статусы',
                ]) ?>
            </div>
            <div class="field field-compact">
                <?= Html::label('Причина', 'filter-reason', ['class' => 'field-label']) ?>
                <?= Html::dropDownList('reason', (string) $filters['reason'], Keyword::reasonLabels(), [
                    'id' => 'filter-reason',
                    'class' => 'input',
                    'prompt' => 'Все причины',
                ]) ?>
            </div>
            <div class="field field-compact">
                <?= Html::label('Источник', 'filter-source', ['class' => 'field-label']) ?>
                <?= Html::dropDownList('source', (string) $filters['source'], KeywordSource::labels(), [
                    'id' => 'filter-source',
                    'class' => 'input',
                    'prompt' => 'Все источники',
                ]) ?>
            </div>
            <div class="field field-compact">
                <?= Html::label('Язык', 'filter-language', ['class' => 'field-label']) ?>
                <?= Html::dropDownList(
                    'language',
                    (string) $filters['language'],
                    array_combine($languages, array_map(
                        static fn (string $code): string => LanguageCatalog::isSupported($code)
                            ? LanguageCatalog::displayName($code) . ' (' . $code . ')'
                            : $code,
                        $languages,
                    )),
                    ['id' => 'filter-language', 'class' => 'input', 'prompt' => 'Все языки'],
                ) ?>
            </div>
            <div class="filter-actions">
                <?= Html::submitButton('Применить', ['class' => 'btn btn-primary btn-small']) ?>
                <?= Html::a('Сбросить', ['/keyword/index'], ['class' => 'btn btn-secondary btn-small']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Записи</h2>
        <span class="panel-meta">Страница <?= (int) $pagination->getPage() + 1 ?> из <?= max(1, (int) $pagination->getPageCount()) ?></span>
    </div>

    <?php if ($keywords === []): ?>
        <div class="panel-body">
            <div class="empty-inline">
                <p>Нет строк, соответствующих фильтрам.</p>
                <?= Html::a('Сбросить фильтры', ['/keyword/index'], ['class' => 'btn btn-secondary btn-small']) ?>
            </div>
        </div>
    <?php else: ?>
        <div class="panel-body panel-body-flush">
            <div class="table-wrap">
                <table class="data-table keyword-table" aria-label="Записи ключевых слов">
                    <thead>
                        <tr>
                            <th>Ключевое слово</th>
                            <th>Источник</th>
                            <th>Язык</th>
                            <th class="numeric">Объём</th>
                            <th>Статус</th>
                            <th>Причины</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($keywords as $keyword): ?>
                        <tr>
                            <td>
                                <span class="keyword-text"><?= Html::encode($keyword->keyword) ?></span>
                                <span class="keyword-normalized"><?= Html::encode($keyword->keyword_norm) ?></span>
                                <span class="record-meta">
                                    <?= Html::a('#' . (int) $keyword->import_id, Url::to(['/keyword/index', 'import_id' => $keyword->import_id]), ['class' => 'text-link']) ?>
                                    <span class="meta-sep">·</span>
                                    <?= Html::encode(Yii::$app->formatter->asDatetime($keyword->created_at, 'php:Y-m-d H:i')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-source">
                                    <span class="source-dot source-<?= Html::encode($keyword->source) ?>" aria-hidden="true"></span>
                                    <?= Html::encode($keyword->getSourceLabel()) ?>
                                </span>
                                <?php if ($keyword->url): ?>
                                    <a class="mono url-cell" href="<?= Html::encode($keyword->url) ?>" target="_blank" rel="noopener noreferrer" title="<?= Html::encode($keyword->url) ?>">
                                        <?= Html::encode(mb_strimwidth($keyword->url, 0, 38, '…')) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-neutral"><?= Html::encode(strtoupper($keyword->language)) ?></span></td>
                            <td class="numeric"><?= number_format((int) $keyword->volume) ?></td>
                            <td><span class="badge <?= $badgeClass($keyword->status) ?>"><?= Html::encode($keyword->getStatusLabel()) ?></span></td>
                            <td>
                                <?php $reasons = $keyword->getReasons(); ?>
                                <?php if ($reasons === []): ?>
                                    <span class="muted">—</span>
                                <?php else: ?>
                                    <div class="badge-list">
                                        <?php foreach ($reasons as $reason): ?>
                                            <span class="badge badge-<?= Html::encode($reason) ?>" title="<?= Html::encode(Keyword::reasonLabels()[$reason] ?? $reason) ?>">
                                                <?= Html::encode($shortReason[$reason] ?? $reason) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
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
