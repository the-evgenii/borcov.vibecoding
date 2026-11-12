<?php

declare(strict_types=1);

namespace app\models;

use app\services\SettingsService;
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

    public static function sourceFingerprint(): string
    {
        $settings = (new SettingsService())->get();

        return hash('sha256', Json::encode([
            'imports' => Import::find()->select('id')->orderBy(['id' => SORT_ASC])->column(),
            'brands' => $settings->getBrands(),
            'forbidden' => $settings->getForbidden(),
            'min_volume' => (int) $settings->min_volume,
            'urls' => $settings->getLanguageUrls(),
        ]));
    }

    public function isStale(): bool
    {
        return ($this->getSummary()['source_fingerprint'] ?? null) !== self::sourceFingerprint();
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
