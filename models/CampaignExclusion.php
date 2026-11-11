<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $keyword
 * @property string $keyword_norm
 * @property string $language
 * @property int $volume
 * @property string $sources_json
 * @property string $reason
 * @property string $detail
 * @property int $created_at
 */
class CampaignExclusion extends ActiveRecord
{
    public const REASON_UNSUPPORTED_LANGUAGE = 'unsupported_language';
    public const REASON_MISSING_TARGET_URL = 'missing_target_url';
    public const REASON_COPY_ERROR = 'copy_error';

    public static function tableName(): string
    {
        return '{{%campaign_exclusions}}';
    }

    public function getCampaign(): ActiveQuery
    {
        return $this->hasOne(Campaign::class, ['id' => 'campaign_id']);
    }

    public static function reasonLabels(): array
    {
        return Keyword::reasonLabels() + [
            self::REASON_UNSUPPORTED_LANGUAGE => 'Неподдерживаемый язык',
            self::REASON_MISSING_TARGET_URL => 'Не задан целевой URL',
            self::REASON_COPY_ERROR => 'Ошибка генерации объявления',
        ];
    }

    public function getReasonLabel(): string
    {
        return self::reasonLabels()[$this->reason] ?? $this->reason;
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
