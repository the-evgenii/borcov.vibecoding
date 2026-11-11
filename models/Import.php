<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $source
 * @property string $original_filename
 * @property string $format
 * @property int $rows_total
 * @property int $rows_imported
 * @property int $rows_rejected
 * @property string|null $errors_json
 * @property int $created_at
 */
class Import extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%imports}}';
    }

    public function getKeywords(): ActiveQuery
    {
        return $this->hasMany(Keyword::class, ['import_id' => 'id']);
    }

    public function getSourceLabel(): string
    {
        return KeywordSource::label($this->source);
    }

    public function getErrorItems(): array
    {
        if ($this->errors_json === null || $this->errors_json === '') {
            return [];
        }

        try {
            $decoded = Json::decode($this->errors_json);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $this->created_at = time();
        }

        return true;
    }
}
