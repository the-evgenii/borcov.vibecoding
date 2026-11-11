<?php

declare(strict_types=1);

/** @var yii\base\Model $model */
/** @var string $attribute */
foreach ($model->getErrors($attribute) as $error): ?>
    <p class="field-error"><?= yii\helpers\Html::encode($error) ?></p>
<?php endforeach;
