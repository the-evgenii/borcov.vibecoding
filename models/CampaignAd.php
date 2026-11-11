<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property int $id
 * @property int $campaign_group_id
 * @property string $ad_name
 * @property string $headlines_json
 * @property string $descriptions_json
 * @property string $path1
 * @property string $path2
 * @property string $final_url
 * @property int $created_at
 */
class CampaignAd extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%campaign_ads}}';
    }

    public function getGroup(): ActiveQuery
    {
        return $this->hasOne(CampaignGroup::class, ['id' => 'campaign_group_id']);
    }

    /**
     * @return string[]
     */
    public function getHeadlines(): array
    {
        return $this->decodeList($this->headlines_json);
    }

    /**
     * @return string[]
     */
    public function getDescriptions(): array
    {
        return $this->decodeList($this->descriptions_json);
    }

    /**
     * @return string[]
     */
    private function decodeList(string $json): array
    {
        try {
            $decoded = Json::decode($json);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
