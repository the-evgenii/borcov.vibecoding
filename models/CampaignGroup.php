<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $language
 * @property string $campaign_name
 * @property string $ad_group_name
 * @property string $keyword
 * @property string $keyword_norm
 * @property int $volume
 * @property string $sources_json
 * @property string $target_url
 * @property int $created_at
 * @property CampaignAd[] $ads
 */
class CampaignGroup extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%campaign_groups}}';
    }

    public function getCampaign(): ActiveQuery
    {
        return $this->hasOne(Campaign::class, ['id' => 'campaign_id']);
    }

    public function getAds(): ActiveQuery
    {
        return $this->hasMany(CampaignAd::class, ['campaign_group_id' => 'id']);
    }

    /**
     * @return string[]
     */
    public function getSources(): array
    {
        try {
            $decoded = Json::decode($this->sources_json);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    public function getSourceLabels(): array
    {
        return array_map(static fn (string $source): string => KeywordSource::label($source), $this->getSources());
    }
}
