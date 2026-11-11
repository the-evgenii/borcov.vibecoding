<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\Json;

/**
 * @property int $id
 * @property int $import_id
 * @property string $source
 * @property string $keyword
 * @property string $keyword_norm
 * @property int $volume
 * @property string $language
 * @property string|null $url
 * @property string $status
 * @property string|null $reason
 * @property string|null $reasons_json
 * @property int $created_at
 */
class Keyword extends ActiveRecord
{
    public const STATUS_INCLUDED = 'included';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_EXCLUDED = 'excluded';

    public const REASON_JUNK = 'junk';
    public const REASON_BRAND = 'brand';
    public const REASON_FORBIDDEN = 'forbidden';
    public const REASON_ALREADY_USED = 'already_used';
    public const REASON_LOW_VOLUME = 'low_volume';
    public const REASON_DUPLICATE = 'duplicate';

    public static function tableName(): string
    {
        return '{{%keywords}}';
    }

    public static function reasonCondition(string $reason): Expression
    {
        $db = static::getDb();
        if ($db->getDriverName() === 'sqlite') {
            return new Expression(
                'EXISTS (SELECT 1 FROM json_each(reasons_json) WHERE json_each.value = :reason)',
                [':reason' => $reason],
            );
        }

        return new Expression('JSON_CONTAINS(reasons_json, :reason)', [':reason' => Json::encode($reason)]);
    }

    public function getImportBatch(): ActiveQuery
    {
        return $this->hasOne(Import::class, ['id' => 'import_id']);
    }

    public function getSourceLabel(): string
    {
        return KeywordSource::label($this->source);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_INCLUDED => 'Оставлено',
            self::STATUS_DUPLICATE => 'Дубликат',
            self::STATUS_EXCLUDED => 'Исключено',
        ];
    }

    public static function reasonLabels(): array
    {
        return [
            self::REASON_JUNK => 'Мусор',
            self::REASON_BRAND => 'Брендовое слово',
            self::REASON_FORBIDDEN => 'Запрещённое слово',
            self::REASON_ALREADY_USED => 'Уже используется в Google Ads',
            self::REASON_LOW_VOLUME => 'Ниже порога объёма',
            self::REASON_DUPLICATE => 'Дубликат',
        ];
    }

    /**
     * Short labels for dense table badges. Full labels remain in reasonLabels().
     */
    public static function shortReasonLabels(): array
    {
        return [
            self::REASON_JUNK => 'Мусор',
            self::REASON_BRAND => 'Бренд',
            self::REASON_FORBIDDEN => 'Запрещено',
            self::REASON_ALREADY_USED => 'Уже используется',
            self::REASON_LOW_VOLUME => 'Низкий объём',
            self::REASON_DUPLICATE => 'Дубликат',
        ];
    }

    public function getStatusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function getReasonLabel(): string
    {
        if ($this->reason === null) {
            return '';
        }

        return self::reasonLabels()[$this->reason] ?? $this->reason;
    }

    /**
     * @return string[]
     */
    public function getReasons(): array
    {
        if ($this->reasons_json === null || $this->reasons_json === '') {
            return [];
        }

        try {
            $decoded = Json::decode($this->reasons_json);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    public function getReasonLabels(): array
    {
        $labels = self::reasonLabels();

        return array_map(static fn (string $reason): string => $labels[$reason] ?? $reason, $this->getReasons());
    }
}
