<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Campaign|null $campaign */
/** @var array<string, mixed> $campaignSummary */
/** @var array<string, app\models\CampaignGroup[]> $groupsByLanguage */
/** @var array<string, int> $languageOrder */
/** @var app\models\CampaignExclusion[] $exclusions */
/** @var int $exclusionCount */
/** @var array<string, int> $exclusionCounts */
/** @var array<string, mixed>|null $validation */

use app\models\CampaignExclusion;
use app\models\Keyword;
use app\services\LanguageCatalog;
use yii\helpers\Html;

$this->title = 'Предпросмотр кампании';
$this->params['pageTitle'] = 'Предпросмотр кампании';

$shortExclusionReason = Keyword::shortReasonLabels() + [
    CampaignExclusion::REASON_UNSUPPORTED_LANGUAGE => 'Неподдерживаемый язык',
    CampaignExclusion::REASON_MISSING_TARGET_URL => 'Нет URL',
    CampaignExclusion::REASON_COPY_ERROR => 'Ошибка текста',
];

$orderedLanguages = [];
foreach (array_keys($languageOrder) as $code) {
    if (isset($groupsByLanguage[$code])) {
        $orderedLanguages[$code] = $groupsByLanguage[$code];
    }
}
foreach ($groupsByLanguage as $code => $groups) {
    if (!isset($orderedLanguages[$code])) {
        $orderedLanguages[$code] = $groups;
    }
}

$formatUrl = static function (string $url): string {
    $host = parse_url($url, PHP_URL_HOST);
    $path = parse_url($url, PHP_URL_PATH);
    $display = (string) ($host ?: $url);
    if (is_string($path) && $path !== '/' && $path !== '') {
        $display .= ' › ' . trim($path, '/');
    }

    return $display;
};
?>
<div class="page-head">
    <div>
        <h1>Предпросмотр кампании</h1>
        <p class="page-desc">Языковые кампании, объединённые ключевые слова, исключения и сгенерированные адаптивные объявления.</p>
    </div>
    <div class="page-actions">
        <?= Html::a('Настройки', ['/settings/index'], ['class' => 'btn btn-secondary']) ?>
        <?= Html::beginForm(['/campaign/generate'], 'post', ['class' => 'inline-form']) ?>
            <button type="submit" class="btn btn-secondary">Сгенерировать заново</button>
        <?= Html::endForm() ?>
        <?php if ($campaign !== null): ?>
            <?= Html::a('Скачать CSV', ['/campaign/export'], ['class' => 'btn btn-primary']) ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($campaign === null): ?>
    <section class="panel empty-state">
        <span class="empty-code">Нет запуска</span>
        <h2>Снимка кампании ещё нет</h2>
        <p>Импортируйте исходные файлы или загрузите демо-данные, затем сгенерируйте кампанию. Здесь появятся объединённые ключевые слова, исключения и RSA-ассеты.</p>
        <div class="button-row">
            <?= Html::beginForm(['/campaign/generate'], 'post', ['class' => 'inline-form']) ?>
                <button type="submit" class="btn btn-primary">Сгенерировать кампанию</button>
            <?= Html::endForm() ?>
            <?= Html::a('Открыть импорт', ['/import/index'], ['class' => 'btn btn-secondary']) ?>
        </div>
    </section>
<?php else: ?>
    <section class="metrics metrics-compact" aria-label="Сводка кампании">
        <div class="metric">
            <span class="metric-label">Группы объявлений</span>
            <span class="metric-value"><?= number_format((int) ($campaignSummary['groups'] ?? 0)) ?></span>
        </div>
        <div class="metric">
            <span class="metric-label">Адаптивные объявления</span>
            <span class="metric-value"><?= number_format((int) ($campaignSummary['ads'] ?? 0)) ?></span>
        </div>
        <div class="metric">
            <span class="metric-label">Исключённые ключи</span>
            <span class="metric-value"><?= number_format((int) ($campaignSummary['excluded_keys'] ?? 0)) ?></span>
        </div>
        <div class="metric">
            <span class="metric-label">Языки</span>
            <span class="metric-value"><?= number_format(count($orderedLanguages)) ?></span>
        </div>
    </section>

    <?php if ($validation !== null): ?>
        <div class="notice notice-<?= !empty($validation['ok']) ? 'success' : 'error' ?>">
            <?php if (!empty($validation['ok'])): ?>
                <p><strong>Проверка структуры CSV пройдена.</strong>
                    Строк данных: <?= (int) ($validation['data_rows'] ?? 0) ?>,
                    кампаний: <?= (int) ($validation['campaigns'] ?? 0) ?>,
                    групп объявлений: <?= (int) ($validation['groups'] ?? 0) ?>,
                    объявлений: <?= (int) ($validation['ads'] ?? 0) ?>.</p>
                <p class="hint">Структура, лимиты и http(s)-URL проверены локально. Перед публикацией просмотрите изменения в Google Ads Editor.</p>
            <?php else: ?>
                <p><strong>Проверка структуры экспорта не пройдена.</strong></p>
                <ul class="error-list">
                    <?php foreach (($validation['errors'] ?? []) as $error): ?>
                        <li><?= Html::encode((string) $error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($orderedLanguages === []): ?>
        <section class="panel empty-state">
            <span class="empty-code">0 групп</span>
            <h2>После этого запуска не осталось групп объявлений</h2>
            <p>Проверьте исключения, минимальную частотность, брендовые и запрещённые слова, а также целевые URL языков.</p>
            <div class="button-row">
                <?= Html::a('Изменить настройки', ['/settings/index'], ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Проверить ключевые слова', ['/keyword/index'], ['class' => 'btn btn-secondary']) ?>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($orderedLanguages as $language => $groups): ?>
        <?php
        $languageName = LanguageCatalog::displayName($language);
        $campaignName = $groups[0]->campaign_name ?? '';
        $targetUrl = $groups[0]->target_url ?? '';
        ?>
        <section class="campaign-section">
            <header class="campaign-header">
                <div>
                    <h2 class="campaign-title"><?= Html::encode($campaignName) ?></h2>
                    <p class="campaign-meta">
                        <span><?= Html::encode($languageName) ?></span>
                        <span class="meta-sep">·</span>
                        <span>Групп объявлений: <?= count($groups) ?></span>
                        <span class="meta-sep">·</span>
                        <a class="url-text" href="<?= Html::encode($targetUrl) ?>" target="_blank" rel="noopener noreferrer"><?= Html::encode($targetUrl) ?></a>
                    </p>
                </div>
                <span class="lang-badge"><?= Html::encode(strtoupper($language)) ?></span>
            </header>

            <div class="adgroup-list">
                <?php foreach ($groups as $group): ?>
                    <?php
                    $ad = $group->ads[0] ?? null;
                    $headlines = $ad?->getHeadlines() ?? [];
                    $descriptions = $ad?->getDescriptions() ?? [];
                    ?>
                    <article class="adgroup-row">
                        <div class="adgroup-main">
                            <h3 class="adgroup-title"><?= Html::encode($group->ad_group_name) ?></h3>
                            <p class="adgroup-sub"><?= Html::encode($group->keyword_norm) ?></p>
                            <p class="meta-line">
                                Объём <?= number_format((int) $group->volume) ?>
                                <span class="meta-sep">·</span>
                                <?= Html::encode(implode(' + ', $group->getSourceLabels())) ?>
                            </p>
                            <a class="url-text" href="<?= Html::encode($group->target_url) ?>" target="_blank" rel="noopener noreferrer"><?= Html::encode($group->target_url) ?></a>
                        </div>

                        <div class="adgroup-creative">
                            <div class="serp-preview">
                                <span class="serp-sponsored">Реклама</span>
                                <span class="serp-url"><?= Html::encode($formatUrl($group->target_url)) ?><?= $ad !== null && $ad->path1 !== '' ? ' › ' . Html::encode($ad->path1) : '' ?></span>
                                <span class="serp-headline"><?= Html::encode(implode(' · ', array_slice($headlines, 0, 3))) ?></span>
                                <span class="serp-description"><?= Html::encode($descriptions[0] ?? '') ?></span>
                            </div>

                            <?php if ($ad !== null): ?>
                                <details class="assets">
                                    <summary>Показать все RSA-ассеты (заголовков: <?= count($headlines) ?>, описаний: <?= count($descriptions) ?>)</summary>
                                    <div class="asset-grid">
                                        <div>
                                            <span class="meta-label">Заголовки · макс. 30</span>
                                            <ul class="asset-list">
                                                <?php foreach ($headlines as $headline): ?>
                                                    <li><span><?= Html::encode($headline) ?></span><b><?= mb_strlen($headline) ?></b></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div>
                                            <span class="meta-label">Описания · макс. 90</span>
                                            <ul class="asset-list">
                                                <?php foreach ($descriptions as $description): ?>
                                                    <li><span><?= Html::encode($description) ?></span><b><?= mb_strlen($description) ?></b></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <p class="hint">Пути: <span class="mono"><?= Html::encode($ad->path1) ?> / <?= Html::encode($ad->path2) ?></span> · Название объявления: <span class="mono"><?= Html::encode($ad->ad_name) ?></span></p>
                                </details>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (($campaignSummary['excluded_keys'] ?? 0) > 0): ?>
        <details class="exclusions-disclosure" open>
            <summary>
                <span class="disclosure-title">Исключения</span>
                <span class="panel-meta">Уникальных ключей: <?= number_format((int) $exclusionCount) ?></span>
            </summary>
            <div class="exclusions-body">
                <div class="chip-row">
                    <?php foreach (CampaignExclusion::reasonLabels() as $reason => $label): ?>
                        <?php if (!empty($exclusionCounts[$reason])): ?>
                            <span class="chip chip-danger">
                                <?= Html::encode($label) ?>
                                <span><?= number_format((int) $exclusionCounts[$reason]) ?></span>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="table-wrap">
                    <table class="data-table exclusion-table" aria-label="Исключённые ключевые слова">
                        <thead>
                            <tr>
                                <th>Ключевое слово</th>
                                <th>Язык</th>
                                <th class="numeric">Объём</th>
                                <th>Причина</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($exclusions as $exclusion): ?>
                            <tr>
                                <td>
                                    <span class="keyword-text"><?= Html::encode($exclusion->keyword) ?></span>
                                    <span class="record-meta"><?= Html::encode(implode(', ', $exclusion->getSourceLabels())) ?></span>
                                </td>
                                <td><span class="badge badge-neutral"><?= Html::encode(strtoupper($exclusion->language)) ?></span></td>
                                <td class="numeric"><?= number_format((int) $exclusion->volume) ?></td>
                                <td>
                                    <span class="badge badge-danger" title="<?= Html::encode($exclusion->getReasonLabel()) ?>">
                                        <?= Html::encode($shortExclusionReason[$exclusion->reason] ?? $exclusion->reason) ?>
                                    </span>
                                    <?php if ($exclusion->detail !== $exclusion->getReasonLabel()): ?>
                                        <span class="exclusion-detail"><?= Html::encode($exclusion->detail) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ((int) $exclusionCount > count($exclusions)): ?>
                    <p class="hint">Показаны первые <?= count($exclusions) ?> из <?= number_format((int) $exclusionCount) ?> исключений.</p>
                <?php endif; ?>
            </div>
        </details>
    <?php endif; ?>

    <section class="before-post">
        <h2>Перед публикацией</h2>
        <p>Этот проект не создаёт кампании в Google Ads. Скачайте UTF-8 CSV, откройте Google Ads Editor, выберите «Аккаунт → Импорт → Из файла» (в англоязычном Editor — Account → Import → From file), проверьте предложенные изменения и опубликуйте их. Тексты объявлений шаблонные и требуют проверки человеком.</p>
        <div class="button-row">
            <?= Html::a('Скачать CSV для Editor', ['/campaign/export'], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Посмотреть исходные ключевые слова', ['/keyword/index'], ['class' => 'btn btn-secondary']) ?>
        </div>
    </section>
<?php endif; ?>
