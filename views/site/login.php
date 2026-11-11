<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\LoginForm $model */

use yii\helpers\Html;

$this->title = 'Вход';
?>
<div class="login-wrap">
    <div class="login-brand">
        <span class="login-wordmark">Vibe Ads Console</span>
        <span class="login-brand-note">Операции с ключевыми словами</span>
    </div>

    <section class="login-card">
        <h1>Вход</h1>
        <p class="login-sub">Продолжите работу с ключевыми словами.</p>

        <?= Html::beginForm(['/site/login'], 'post', ['class' => 'form-stack', 'autocomplete' => 'on']) ?>
            <div class="field">
                <?= Html::label('Эл. почта', 'login-email', ['class' => 'field-label']) ?>
                <?= Html::textInput('LoginForm[email]', $model->email, [
                    'id' => 'login-email',
                    'class' => 'input' . ($model->hasErrors('email') ? ' is-invalid' : ''),
                    'type' => 'email',
                    'autocomplete' => 'username',
                    'autofocus' => true,
                    'required' => true,
                ]) ?>
                <?= $this->render('//site/_field_errors', ['model' => $model, 'attribute' => 'email']) ?>
            </div>

            <div class="field">
                <?= Html::label('Пароль', 'login-password', ['class' => 'field-label']) ?>
                <?= Html::passwordInput('LoginForm[password]', $model->password, [
                    'id' => 'login-password',
                    'class' => 'input' . ($model->hasErrors('password') ? ' is-invalid' : ''),
                    'autocomplete' => 'current-password',
                    'required' => true,
                ]) ?>
                <?= $this->render('//site/_field_errors', ['model' => $model, 'attribute' => 'password']) ?>
            </div>

            <?= Html::submitButton('Войти', ['class' => 'btn btn-primary btn-block']) ?>
        <?= Html::endForm() ?>
    </section>
</div>
