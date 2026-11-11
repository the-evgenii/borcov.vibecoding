<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\SettingsForm $form */
/** @var array<string, array{name: string, display_name: string, url: string}> $supportedLanguages */
/** @var int $minVolume */

use app\services\LanguageCatalog;
use yii\helpers\Html;

$this->title = 'Настройки';
$this->params['pageTitle'] = 'Настройки';

$urlErrors = $form->getErrors('urls');
?>
<div class="page-head">
    <div>
        <h1>Настройки</h1>
        <p class="page-desc">Настройте брендовые правила, запрещённые слова, порог объёма и целевые URL по языкам.</p>
    </div>
    <?= Html::a('Предпросмотр кампании', ['/campaign/preview'], ['class' => 'btn btn-secondary']) ?>
</div>

<?= Html::beginForm(['/settings/index'], 'post', ['class' => 'settings-form', 'novalidate' => true]) ?>
    <div class="settings-grid">
        <section class="form-section">
            <div class="form-section-head">
                <div>
                    <h2>Брендовые слова</h2>
                    <p>По одному слову в строке. Совпадение по целым нормализованным словам: пунктуация заменяется пробелом, поэтому site.pro и site pro — одно правило.</p>
                </div>
                <span class="panel-meta">Нормализовано: <?= (int) $form->normalizedTermCount($form->brandsText) ?></span>
            </div>
            <?= Html::label('Брендовые слова', 'settings-brands', ['class' => 'field-label']) ?>
            <?= Html::textarea('SettingsForm[brandsText]', $form->brandsText, [
                'id' => 'settings-brands',
                'class' => 'input textarea' . ($form->hasErrors('brandsText') ? ' is-invalid' : ''),
                'rows' => 7,
                'placeholder' => "site.pro\nsite pro\nsitepro",
            ]) ?>
            <?= $this->render('//site/_field_errors', ['model' => $form, 'attribute' => 'brandsText']) ?>
            <p class="hint">Список по умолчанию защищает собственный бренд. Добавляйте бренды конкурентов только если это соответствует вашей политике.</p>
        </section>

        <section class="form-section">
            <div class="form-section-head">
                <div>
                    <h2>Запрещённые слова</h2>
                    <p>По одному слову в строке. Любая совпавшая фраза исключает ключевое слово. Пунктуация заменяется пробелом, поэтому site.pro и site pro — одно правило.</p>
                </div>
                <span class="panel-meta">Нормализовано: <?= (int) $form->normalizedTermCount($form->forbiddenText) ?></span>
            </div>
            <?= Html::label('Запрещённые слова', 'settings-forbidden', ['class' => 'field-label']) ?>
            <?= Html::textarea('SettingsForm[forbiddenText]', $form->forbiddenText, [
                'id' => 'settings-forbidden',
                'class' => 'input textarea' . ($form->hasErrors('forbiddenText') ? ' is-invalid' : ''),
                'rows' => 7,
                'placeholder' => "casino\nadult\nfree",
            ]) ?>
            <?= $this->render('//site/_field_errors', ['model' => $form, 'attribute' => 'forbiddenText']) ?>
            <p class="hint">Демо-список многоязычный: «free», «kostenlos», «gratuit», «gratis», «бесплатно», «nemokamai». Удалите термины, которые нужны вашему предложению.</p>
        </section>
    </div>

    <section class="form-section form-section-inline">
        <div class="form-section-head">
            <div>
                <h2>Минимальная частотность</h2>
                <p>Ключи с частотностью ниже этого значения исключаются из генерации кампании.</p>
            </div>
            <span class="panel-meta">Текущее: <?= number_format((int) $minVolume) ?>/мес.</span>
        </div>
        <div class="field field-inline">
            <?= Html::label('Частотность, запросов в месяц', 'settings-volume', ['class' => 'field-label']) ?>
            <?= Html::input('number', 'SettingsForm[minVolume]', (string) $form->minVolume, [
                'id' => 'settings-volume',
                'class' => 'input input-narrow' . ($form->hasErrors('minVolume') ? ' is-invalid' : ''),
                'min' => 0,
                'max' => 1000000,
                'step' => 1,
                'required' => true,
            ]) ?>
        </div>
        <?= $this->render('//site/_field_errors', ['model' => $form, 'attribute' => 'minVolume']) ?>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <div>
                <h2>Целевые URL по языкам</h2>
                <p>Каждая языковая кампания использует этот конечный URL. Пустые или некорректные URL исключаются с диагностикой.</p>
            </div>
            <span class="panel-meta">Только http(s)</span>
        </div>

        <?php if ($urlErrors !== []): ?>
            <div class="notice notice-error">
                <?php foreach ($urlErrors as $error): ?>
                    <p><?= Html::encode($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="data-table url-table" aria-label="Целевые URL по языкам">
                <thead>
                    <tr>
                        <th>Язык</th>
                        <th>Код</th>
                        <th>Целевой URL</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($supportedLanguages as $code => $language): ?>
                    <?php
                    $urlValue = $form->urls[$code] ?? '';
                    $urlValue = is_string($urlValue) ? $urlValue : '';
                    ?>
                    <tr>
                        <td><?= Html::encode(LanguageCatalog::displayName($code)) ?></td>
                        <td><code><?= Html::encode($code) ?></code></td>
                        <td>
                            <?= Html::textInput('SettingsForm[urls][' . $code . ']', $urlValue, [
                                'id' => 'settings-url-' . $code,
                                'class' => 'input' . ($urlErrors !== [] ? ' is-invalid' : ''),
                                'type' => 'url',
                                'placeholder' => $language['url'],
                                'inputmode' => 'url',
                                'aria-label' => LanguageCatalog::displayName($code) . ': целевой URL',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="hint">Поддерживаемые каталоги объявлений: <?= Html::encode(implode(', ', array_keys($supportedLanguages))) ?>. Неподдерживаемые языки не переводятся автоматически.</p>
    </section>

    <div class="form-actions form-actions-bottom">
        <?= Html::submitButton('Сохранить настройки', ['class' => 'btn btn-primary']) ?>
        <span class="hint">Сохранение сразу пересчитывает статусы ключевых слов; следующая генерация использует новые правила.</span>
    </div>
<?= Html::endForm() ?>
