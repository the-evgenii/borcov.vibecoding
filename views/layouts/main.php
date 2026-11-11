<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use yii\helpers\Html;
use yii\helpers\Url;

AppAsset::register($this);

$route = Yii::$app->controller->route;
$isGuest = Yii::$app->user->isGuest;
$pageTitle = $this->params['pageTitle'] ?? $this->title ?? Yii::$app->name;
$flashes = Yii::$app->session->getAllFlashes();

$navItems = [
    ['label' => 'Обзор', 'url' => ['/site/index'], 'active' => $route === 'site/index', 'icon' => 'overview'],
    ['label' => 'Импорт', 'url' => ['/import/index'], 'active' => str_starts_with($route, 'import/'), 'icon' => 'imports'],
    ['label' => 'Ключевые слова', 'url' => ['/keyword/index'], 'active' => str_starts_with($route, 'keyword/'), 'icon' => 'keywords'],
    ['label' => 'Кампании', 'url' => ['/campaign/preview'], 'active' => str_starts_with($route, 'campaign/'), 'icon' => 'campaigns'],
    ['label' => 'Настройки', 'url' => ['/settings/index'], 'active' => str_starts_with($route, 'settings/'), 'icon' => 'settings'],
];

$icons = [
    'overview' => '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="3" y="3" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="11.5" y="3" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="3" y="11.5" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>',
    'imports' => '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 3v9m0 0 3.25-3.25M10 12 6.75 8.75M4 14.5v1.5h12v-1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'keywords' => '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M7 5.5h9M7 10h9M7 14.5h9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="4" cy="5.5" r=".9" fill="currentColor"/><circle cx="4" cy="10" r=".9" fill="currentColor"/><circle cx="4" cy="14.5" r=".9" fill="currentColor"/></svg>',
    'campaigns' => '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2.75 10s2.4-4 7.25-4 7.25 4 7.25 4-2.4 4-7.25 4-7.25-4-7.25-4Z" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="1.9" stroke="currentColor" stroke-width="1.4"/></svg>',
    'settings' => '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 6h12M4 10h12M4 14h12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="7" cy="6" r="1.6" fill="#fff" stroke="currentColor" stroke-width="1.4"/><circle cx="13" cy="10" r="1.6" fill="#fff" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="14" r="1.6" fill="#fff" stroke="currentColor" stroke-width="1.4"/></svg>',
];

$flashMap = [
    'success' => 'success',
    'info' => 'info',
    'warning' => 'warning',
    'error' => 'error',
    'danger' => 'error',
];
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex,nofollow">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($pageTitle) ?> · <?= Html::encode(Yii::$app->name) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<a class="skip-link" href="#main">Перейти к содержимому</a>

<?php if ($isGuest): ?>
    <main class="auth-shell" id="main">
        <?= $content ?>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="<?= Url::to(['/site/index']) ?>">
                <span class="brand-name">Vibe Ads</span>
                <span class="brand-sub">Console</span>
            </a>

            <nav class="main-nav" aria-label="Основная навигация">
                <span class="nav-group-label">Рабочая область</span>
                <?php foreach ($navItems as $item): ?>
                    <a class="nav-link<?= $item['active'] ? ' is-active' : '' ?>"
                       href="<?= Url::to($item['url']) ?>"
                       <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                        <span class="nav-icon"><?= $icons[$item['icon']] ?></span>
                        <span class="nav-text"><?= Html::encode($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-account">
                    <span class="account-email" title="<?= Html::encode(Yii::$app->user->identity->email ?? '') ?>">
                        <?= Html::encode(Yii::$app->user->identity->email ?? '') ?>
                    </span>
                    <?= Html::beginForm(['/site/logout'], 'post', ['class' => 'inline-form']) ?>
                        <button type="submit" class="link-button">Выйти</button>
                    <?= Html::endForm() ?>
                </div>
            </div>
        </aside>

        <div class="main-wrap">
            <main class="content" id="main" tabindex="-1">
                <?php foreach ($flashes as $type => $messages): ?>
                    <?php
                    $flashType = $flashMap[$type] ?? 'info';
                    $messageList = is_array($messages) ? $messages : [$messages];
                    ?>
                    <div class="notice notice-<?= Html::encode($flashType) ?>" role="status">
                        <?php foreach ($messageList as $message): ?>
                            <p><?= Html::encode((string) $message) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?= $content ?>
            </main>
        </div>
    </div>
<?php endif; ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
