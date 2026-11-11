<?php

declare(strict_types=1);

namespace app\models;

use app\services\KeywordNormalizer;
use app\services\LanguageCatalog;
use app\services\UrlGuard;
use yii\base\Model;

class SettingsForm extends Model
{
    public $brandsText = '';
    public $forbiddenText = '';
    public $minVolume = 50;
    public $urls = [];

    private readonly KeywordNormalizer $_normalizer;

    public function __construct($config = [])
    {
        $this->_normalizer = new KeywordNormalizer();
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['brandsText', 'forbiddenText'], 'string', 'max' => 20000],
            ['minVolume', 'required'],
            ['minVolume', 'integer', 'min' => 0, 'max' => 1000000],
            ['brandsText', 'validateTermList'],
            ['forbiddenText', 'validateTermList'],
            ['urls', 'validateUrls'],
        ];
    }

    public function beforeValidate(): bool
    {
        foreach (['brandsText', 'forbiddenText'] as $attribute) {
            if (!is_string($this->{$attribute})) {
                $this->{$attribute} = '';
                $this->addError($attribute, 'Поле должно быть простым текстом.');
            }
        }

        if (!is_scalar($this->minVolume)) {
            $this->minVolume = '';
            $this->addError('minVolume', 'Минимальный объём должен быть числом.');
        }

        if (!is_array($this->urls)) {
            $this->urls = [];
            $this->addError('urls', 'Сопоставление URL должно быть передано массивом.');
        }

        foreach ($this->urls as $code => $url) {
            if (is_string($url)) {
                $this->urls[$code] = trim($url);
            }
        }

        return parent::beforeValidate();
    }

    public function validateTermList(string $attribute): void
    {
        if (!is_string($this->{$attribute})) {
            $this->addError($attribute, 'Поле должно быть простым текстом.');

            return;
        }

        if (!mb_check_encoding($this->{$attribute}, 'UTF-8')) {
            $this->addError($attribute, 'Поле должно быть корректным UTF-8.');

            return;
        }

        $terms = $this->parseLines($this->{$attribute});
        if (count($terms) > 100) {
            $this->addError($attribute, 'Используйте не более 100 терминов.');

            return;
        }

        foreach ($terms as $term) {
            if (mb_strlen($term) > 80) {
                $this->addError($attribute, 'Каждый термин должен быть не длиннее 80 символов.');
                break;
            }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $term) === 1) {
                $this->addError($attribute, 'Термины не могут содержать управляющие символы.');
                break;
            }
        }
    }

    public function validateUrls(): void
    {
        if (!is_array($this->urls)) {
            $this->addError('urls', 'Сопоставление URL должно быть массивом.');

            return;
        }

        $supported = LanguageCatalog::all();
        foreach ($this->urls as $code => $url) {
            if (!is_string($code) || !isset($supported[$code])) {
                $this->addError('urls', sprintf('Неподдерживаемый код языка «%s» в сопоставлении URL.', (string) $code));
                continue;
            }
            if ($url === '') {
                continue;
            }
            if (!is_string($url) || !UrlGuard::isHttpUrl($url)) {
                $this->addError('urls', sprintf(
                    '%s: целевой URL должен быть корректным http(s)-адресом.',
                    LanguageCatalog::displayName($code),
                ));
            }
        }
    }

    public function attributeLabels(): array
    {
        return [
            'brandsText' => 'Брендовые слова (по одному в строке)',
            'forbiddenText' => 'Запрещённые слова (по одному в строке)',
            'minVolume' => 'Минимальный месячный объём',
            'urls' => 'Целевые URL по языкам',
        ];
    }

    public function populate(Setting $setting): void
    {
        $this->brandsText = implode("\n", $setting->getBrands());
        $this->forbiddenText = implode("\n", $setting->getForbidden());
        $this->minVolume = (int) $setting->min_volume;
        $existing = $setting->getLanguageUrls();
        $this->urls = [];
        foreach (LanguageCatalog::codes() as $code) {
            $this->urls[$code] = (string) ($existing[$code] ?? '');
        }
    }

    public function apply(Setting $setting): void
    {
        $setting->setBrands($this->parseLines($this->brandsText));
        $setting->setForbidden($this->parseLines($this->forbiddenText));
        $setting->min_volume = (int) $this->minVolume;

        $urls = [];
        foreach (LanguageCatalog::codes() as $code) {
            $url = trim((string) ($this->urls[$code] ?? ''));
            if ($url !== '') {
                $urls[$code] = $url;
            }
        }
        $setting->setLanguageUrls($urls);
    }

    /**
     * @return string[]
     */
    public function parseLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/u', $text) ?: [];
        $result = [];
        $seen = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $key = mb_strtolower($line);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $line;
        }

        return $result;
    }

    public function normalizedTermCount(mixed $text): int
    {
        if (!is_string($text)) {
            return 0;
        }

        $terms = [];
        foreach ($this->parseLines($text) as $term) {
            $normalized = $this->_normalizer->normalize($term);
            if ($normalized !== '') {
                $terms[$normalized] = true;
            }
        }

        return count($terms);
    }
}
