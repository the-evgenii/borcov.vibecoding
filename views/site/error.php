<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */

use yii\helpers\Html;
use yii\helpers\Url;

$code = (int) Yii::$app->response->statusCode;
$titles = [
    400 => 'Некорректный запрос',
    403 => 'Доступ запрещён',
    404 => 'Страница не найдена',
    405 => 'Метод не поддерживается',
    422 => 'Некорректные данные',
    500 => 'Внутренняя ошибка сервера',
];
$messages = [
    400 => 'Запрос содержит некорректные данные.',
    403 => 'У вас нет доступа к этой странице.',
    404 => 'Запрошенная страница не существует.',
    405 => 'Этот метод запроса не поддерживается.',
    422 => 'Проверьте введённые данные и попробуйте снова.',
    500 => 'На сервере произошла ошибка. Попробуйте позже.',
];
$pageName = $titles[$code] ?? $name;
$pageMessage = $messages[$code] ?? $message;

$this->title = $pageName;
?>
<section class="error-state">
    <span class="error-code"><?= Html::encode((string) $code) ?></span>
    <h1><?= Html::encode($pageName) ?></h1>
    <p><?= Html::encode($pageMessage) ?></p>
    <div class="button-row">
        <?php if (Yii::$app->user->isGuest): ?>
            <?= Html::a('Перейти ко входу', Url::to(['/site/login']), ['class' => 'btn btn-primary']) ?>
        <?php else: ?>
            <?= Html::a('Вернуться к обзору', Url::to(['/site/index']), ['class' => 'btn btn-primary']) ?>
        <?php endif; ?>
    </div>
</section>
