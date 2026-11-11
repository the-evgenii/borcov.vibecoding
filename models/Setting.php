<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property int $id
 * @property string $brands_json
 * @property string $forbidden_json
 * @property int $min_volume
 * @property string $language_urls_json
 * @property int $updated_at
 */
class Setting extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%settings}}';
    }

    /**
     * @return string[]
     */
    public function getBrands(): array
    {
        return $this->decodeList($this->brands_json);
    }

    /**
     * @param string[] $brands
     */
    public function setBrands(array $brands): void
    {
        $this->brands_json = $this->encode($brands);
    }

    /**
     * @return string[]
     */
    public function getForbidden(): array
    {
        return $this->decodeList($this->forbidden_json);
    }

    /**
     * @param string[] $forbidden
     */
    public function setForbidden(array $forbidden): void
    {
        $this->forbidden_json = $this->encode($forbidden);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageUrls(): array
    {
        try {
            $decoded = Json::decode($this->language_urls_json);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $urls = [];
        foreach ($decoded as $language => $url) {
            if (is_string($language) && is_string($url)) {
                $urls[$language] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param array<string, string> $urls
     */
    public function setLanguageUrls(array $urls): void
    {
        $this->language_urls_json = $this->encode($urls);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->updated_at = time();

        return true;
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

    private function encode(array $value): string
    {
        return Json::encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
