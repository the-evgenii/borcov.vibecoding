<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array<string, mixed> $summary */
/** @var array<string, int> $bySource */
/** @var int $importCount */
/** @var app\models\Campaign|null $latestCampaign */
/** @var array<string, mixed> $campaignSummary */
/** @var int $urlCount */

use app\models\Keyword;
use app\models\KeywordSource;
use app\services\LanguageCatalog;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Обзор';
$this->params['pageTitle'] = 'Обзор';

$groups = (int) ($campaignSummary['groups'] ?? 0);
$excludedKeys = (int) ($campaignSummary['excluded_keys'] ?? 0);
$byReason = $summary['by_reason'] ?? [];
$reasonLabels = Keyword::reasonLabels();
$records = (int) ($summary['records_total'] ?? 0);
$flow = [
    [
        'title' => 'Загрузить источники данных',
        'text' => 'CSV или JSON из Google Ads, Search Console и Ahrefs.',
        'done' => $importCount > 0,
        'active' => $importCount === 0,
    ],
    [
        'title' => 'Очистить и объединить',
        'text' => 'Мусор, дубликаты, брендовые и запрещённые слова, порог объёма.',
        'done' => $records > 0,
        'active' => $records === 0,
    ],
    [
        'title' => 'Настроить правила',
        'text' => 'Брендовые слова, запрещённые слова и целевой URL для каждого языка.',
        'done' => $urlCount > 0,
        'active' => $records > 0 && $urlCount === 0,
    ],
    [
        'title' => 'Сгенерировать',
        'text' => 'Исключить используемые ключи, объединить дубликаты, сгруппировать по языкам.',
        'done' => $latestCampaign !== null,
        'active' => $records > 0 && $latestCampaign === null,
    ],
    [
        'title' => 'Проверить и экспортировать',
        'text' => 'Проверить группы, исключения и RSA-ассеты, затем скачать CSV.',
        'done' => $groups > 0,
        'active' => $latestCampaign !== null && $groups === 0,
    ],
];
?>
<div class="page-head">
    <div>
        <h1>Обзор</h1>
        <p class="page-desc">Импорт четырёх источников, очистка ключевых слов и подготовка строк для Google Ads Editor.</p>
    </div>
    <div class="page-actions">
        <?= Html::beginForm(['/site/demo-data'], 'post', ['class' => 'inline-form']) ?>
            <button type="submit" class="btn btn-secondary">Загрузить демо-данные</button>
        <?= Html::endForm() ?>
        <?= Html::beginForm(['/campaign/generate'], 'post', ['class' => 'inline-form']) ?>
            <button type="submit" class="btn btn-primary">Сгенерировать кампанию</button>
        <?= Html::endForm() ?>
        <?= Html::beginForm(['/site/reset-data'], 'post', [
            'class' => 'inline-form',
            'data-reset-confirm' => 'Удалить все импорты, ключевые слова и кампании? Настройки и вход останутся.',
        ]) ?>
            <button type="submit" class="btn btn-secondary">Очистить данные</button>
        <?= Html::endForm() ?>
    </div>
</div>

<section class="metrics" aria-label="Сводка пайплайна">
    <div class="metric">
        <span class="metric-label">Исходные записи</span>
        <span class="metric-value"><?= number_format($records) ?></span>
        <span class="metric-note">Пакетов импорта: <?= $importCount ?></span>
    </div>
    <div class="metric">
        <span class="metric-label">Очищено</span>
        <span class="metric-value"><?= number_format((int) ($summary['kept_keys'] ?? 0)) ?></span>
        <span class="metric-note">Уникальные ключевые слова после правил</span>
    </div>
    <div class="metric">
        <span class="metric-label">Исключено</span>
        <span class="metric-value"><?= number_format((int) ($summary['excluded_records'] ?? 0)) ?></span>
        <span class="metric-note">Объединено дубликатов: <?= number_format((int) ($summary['duplicate_records'] ?? 0)) ?></span>
    </div>
    <div class="metric">
        <span class="metric-label">Готово</span>
        <span class="metric-value"><?= number_format($groups) ?></span>
        <span class="metric-note">Исключено ключей: <?= number_format($excludedKeys) ?></span>
    </div>
</section>

<div class="workspace-grid">
    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Процесс</h2>
            <?= Html::a('Проверить ключевые слова', Url::to(['/keyword/index']), ['class' => 'text-link']) ?>
        </div>
        <ol class="checklist">
            <?php foreach ($flow as $index => $step): ?>
                <li class="checklist-item<?= $step['done'] ? ' is-done' : '' ?><?= $step['active'] ? ' is-current' : '' ?>">
                    <span class="check-indicator" aria-hidden="true"><?= $step['done'] ? '✓' : $index + 1 ?></span>
                    <span class="check-copy">
                        <span class="check-title"><?= Html::encode($step['title']) ?></span>
                        <span class="check-desc"><?= Html::encode($step['text']) ?></span>
                    </span>
                    <span class="check-state"><?= $step['done'] ? 'Готово' : ($step['active'] ? 'Далее' : 'Ожидает') ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Текущая кампания</h2>
            <?php if ($latestCampaign !== null): ?>
                <?= Html::a('Предпросмотр', Url::to(['/campaign/preview']), ['class' => 'text-link']) ?>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if ($latestCampaign === null): ?>
                <p class="empty-copy">Снимка кампании ещё нет. Загрузите данные или демо-файлы и запустите генерацию.</p>
                <?= Html::beginForm(['/campaign/generate'], 'post', ['class' => 'inline-form']) ?>
                    <button type="submit" class="btn btn-secondary btn-small">Сгенерировать кампанию</button>
                <?= Html::endForm() ?>
            <?php else: ?>
                <dl class="key-values">
                    <div class="kv"><dt>Сгенерировано</dt><dd><?= Html::encode(Yii::$app->formatter->asDatetime($latestCampaign->created_at, 'php:Y-m-d H:i')) ?> UTC</dd></div>
                    <div class="kv"><dt>Группы объявлений</dt><dd><?= number_format($groups) ?></dd></div>
                    <div class="kv"><dt>Адаптивные объявления</dt><dd><?= number_format((int) ($campaignSummary['ads'] ?? 0)) ?></dd></div>
                    <div class="kv"><dt>Исключённые ключи</dt><dd><?= number_format($excludedKeys) ?></dd></div>
                </dl>
                <div class="chip-row">
                    <?php foreach (($campaignSummary['languages'] ?? []) as $language): ?>
                        <?php if ((int) ($language['groups'] ?? 0) === 0) {
                            continue;
                        } ?>
                        <span class="chip">
                            <?= Html::encode(LanguageCatalog::displayNameForStoredName((string) ($language['name'] ?? ''))) ?>
                            <span><?= (int) ($language['groups'] ?? 0) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="button-row">
                    <?= Html::a('Открыть предпросмотр', Url::to(['/campaign/preview']), ['class' => 'btn btn-primary btn-small']) ?>
                    <?= Html::a('Скачать CSV', Url::to(['/campaign/export']), ['class' => 'btn btn-secondary btn-small']) ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="workspace-grid workspace-grid-lower">
    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Покрытие источников</h2>
            <?= Html::a('Управлять импортом', Url::to(['/import/index']), ['class' => 'text-link']) ?>
        </div>
        <div class="panel-body panel-body-flush">
            <div class="table-wrap">
                <table class="data-table compact" aria-label="Покрытие источников">
                    <thead>
                        <tr>
                            <th>Источник</th>
                            <th class="numeric">Строк</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (KeywordSource::labels() as $source => $label): ?>
                        <?php $count = (int) ($bySource[$source] ?? 0); ?>
                        <tr>
                            <td><span class="source-dot source-<?= Html::encode($source) ?>"></span><?= Html::encode($label) ?></td>
                            <td class="numeric"><?= number_format($count) ?></td>
                            <td><span class="badge <?= $count > 0 ? 'badge-success' : 'badge-neutral' ?>"><?= $count > 0 ? 'Импортирован' : 'Нет данных' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Отчёт об очистке</h2>
            <span class="panel-meta">Количество записей</span>
        </div>
        <div class="panel-body">
            <div class="reason-list">
                <?php foreach ($reasonLabels as $reason => $label): ?>
                    <div class="reason-row">
                        <span class="reason-name">
                            <span class="reason-dot reason-<?= Html::encode($reason) ?>" aria-hidden="true"></span>
                            <?= Html::encode($label) ?>
                        </span>
                        <span class="reason-count"><?= number_format((int) ($byReason[$reason] ?? 0)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="hint">У одной записи может быть несколько причин. Все строки источника Google Ads считаются уже используемыми, включая совпадения из других источников.</p>
        </div>
    </section>
</div>
