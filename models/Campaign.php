<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property int $id
 * @property int|null $created_by
 * @property string $summary_json
 * @property int $created_at
 */
class Campaign extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%campaigns}}';
    }

    public function getGroups(): ActiveQuery
    {
        return $this->hasMany(CampaignGroup::class, ['campaign_id' => 'id']);
    }

    public function getExclusions(): ActiveQuery
    {
        return $this->hasMany(CampaignExclusion::class, ['campaign_id' => 'id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        try {
            $decoded = Json::decode($this->summary_json);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public static function latest(): ?self
    {
        return static::find()->orderBy(['id' => SORT_DESC])->one();
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
