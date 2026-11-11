<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    public $email = '';
    public $password = '';

    public function rules(): array
    {
        return [
            [['email', 'password'], 'required'],
            ['email', 'trim'],
            ['email', 'email'],
            ['email', 'string', 'max' => 190],
            ['password', 'string', 'min' => 1, 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'email' => 'Эл. почта',
            'password' => 'Пароль',
        ];
    }

    public function beforeValidate(): bool
    {
        foreach (['email', 'password'] as $attribute) {
            if (!is_string($this->{$attribute})) {
                $this->{$attribute} = '';
                $this->addError($attribute, 'Поле должно быть простым текстом.');
            }
        }

        return parent::beforeValidate();
    }

    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $user = User::findByEmail((string) $this->email);
        if ($user === null || !$user->validatePassword((string) $this->password)) {
            $this->addError('password', 'Неверная эл. почта или пароль.');

            return false;
        }

        if (!Yii::$app->user->login($user, 0)) {
            $this->addError('password', 'Не удалось начать сессию.');

            return false;
        }

        Yii::$app->session->regenerateID(true);

        return true;
    }
}
