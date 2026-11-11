<?php

declare(strict_types=1);

namespace app\services;

use app\models\Setting;

final class SettingsService
{
    private const ROW_ID = 1;

    public function get(): Setting
    {
        $setting = Setting::findOne(self::ROW_ID);
        if ($setting !== null) {
            return $setting;
        }

        $setting = new Setting(['id' => self::ROW_ID]);
        $setting->setBrands(self::defaultBrands());
        $setting->setForbidden(self::defaultForbidden());
        $setting->min_volume = 50;
        $setting->setLanguageUrls(LanguageCatalog::defaultUrls());
        $setting->save(false);

        return $setting;
    }

    /**
     * @return string[]
     */
    public function brands(): array
    {
        return $this->get()->getBrands();
    }

    /**
     * @return string[]
     */
    public function forbidden(): array
    {
        return $this->get()->getForbidden();
    }

    public function minVolume(): int
    {
        return max(0, (int) $this->get()->min_volume);
    }

    /**
     * @return array<string, string>
     */
    public function languageUrls(): array
    {
        return $this->get()->getLanguageUrls();
    }

    /**
     * @return string[]
     */
    public static function defaultBrands(): array
    {
        return ['site.pro', 'site pro', 'sitepro'];
    }

    /**
     * @return string[]
     */
    public static function defaultForbidden(): array
    {
        return [
            'free',
            'kostenlos',
            'kostenlose',
            'kostenloser',
            'gratuit',
            'gratuite',
            'gratis',
            'бесплатно',
            'бесплатный',
            'nemokamai',
            'nemokama',
            'nemokamas',
            'casino',
            'adult',
            'porn',
            'crack',
            'torrent',
            'scam',
            'viagra',
            'betting',
            'cheap',
        ];
    }
}
