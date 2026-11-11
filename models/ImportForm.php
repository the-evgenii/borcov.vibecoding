<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

class ImportForm extends Model
{
    public $source = '';
    public mixed $file = null;

    public function rules(): array
    {
        $maxBytes = (int) (Yii::$app->params['import']['maxBytes'] ?? 2 * 1024 * 1024);

        return [
            [['source', 'file'], 'required'],
            ['source', 'in', 'range' => KeywordSource::all()],
            [
                'file',
                'file',
                'extensions' => ['csv', 'json'],
                'maxSize' => $maxBytes,
                'checkExtensionByMimeType' => false,
                'skipOnEmpty' => false,
            ],
            ['file', 'validateUpload'],
        ];
    }

    public function beforeValidate(): bool
    {
        if (!is_string($this->source)) {
            $this->source = '';
            $this->addError('source', 'Выберите корректный источник данных.');
        }

        return parent::beforeValidate();
    }

    public function validateUpload(string $attribute): void
    {
        if ($this->file === null) {
            return;
        }

        if (!$this->file instanceof UploadedFile) {
            $this->addError($attribute, 'Загрузите файл CSV или JSON.');

            return;
        }

        if ($this->file->error !== UPLOAD_ERR_OK) {
            $message = match ($this->file->error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой.',
                UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично.',
                UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
                default => 'Не удалось загрузить файл. Попробуйте ещё раз.',
            };
            $this->addError($attribute, $message);
        }
    }

    public function attributeLabels(): array
    {
        return [
            'source' => 'Источник данных',
            'file' => 'Файл CSV или JSON',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return KeywordSource::labels();
    }
}
