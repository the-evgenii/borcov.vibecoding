<?php

declare(strict_types=1);

namespace app\tests\Support;

use app\models\Setting;
use app\models\User;
use app\services\KeywordAuditor;
use app\services\SettingsService;
use Yii;

final class TestDb
{
    public static function reset(): void
    {
        $db = Yii::$app->db;
        $tables = [
            '{{%campaign_ads}}',
            '{{%campaign_exclusions}}',
            '{{%campaign_groups}}',
            '{{%campaigns}}',
            '{{%keywords}}',
            '{{%imports}}',
            '{{%admin_user}}',
            '{{%settings}}',
        ];
        foreach ($tables as $table) {
            $db->createCommand()->delete($table)->execute();
        }
    }

    public static function admin(string $email = 'e2e@example.com', string $password = 'E2e-password-123'): User
    {
        $user = new User(['email' => $email]);
        $user->setPassword($password);
        $user->save(false);

        return $user;
    }

    /**
     * @param string[] $brands
     * @param string[] $forbidden
     * @param array<string, string>|null $urls
     */
    public static function settings(
        array $brands = ['site.pro', 'site pro', 'sitepro'],
        array $forbidden = ['free', 'kostenlos', 'kostenloser', 'gratis', 'casino'],
        int $minimumVolume = 50,
        ?array $urls = null,
    ): Setting {
        $setting = (new SettingsService())->get();
        $setting->setBrands($brands);
        $setting->setForbidden($forbidden);
        $setting->min_volume = $minimumVolume;
        $setting->setLanguageUrls($urls ?? [
            'en' => 'https://site.pro/Website-Builder/',
            'de' => 'https://site.pro/de/',
            'fr' => 'https://site.pro/fr/',
            'es' => 'https://site.pro/es/',
            'lt' => 'https://site.pro/lt/',
            'ru' => 'https://site.pro/ru/',
        ]);
        $setting->save(false);

        return $setting;
    }

    public static function audit(): array
    {
        return (new KeywordAuditor())->run();
    }
}
