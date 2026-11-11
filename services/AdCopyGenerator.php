<?php

declare(strict_types=1);

namespace app\services;

final class AdCopyGenerator
{
    private const HEADLINES = [
        'en' => [
            'Build a Website Fast',
            'No Code Required',
            'Ready-Made Templates',
            'See the Live Demo',
            'Online Store Builder',
            'Launch in Minutes',
        ],
        'de' => [
            'Website schnell erstellen',
            'Ohne Programmieren',
            'Fertige Vorlagen',
            'Demo jetzt ansehen',
            'Onlineshop erstellen',
            'In Minuten starten',
        ],
        'fr' => [
            'Créez un site rapidement',
            'Sans code',
            'Modèles prêts à l\'emploi',
            'Voir la démo',
            'Boutique en ligne',
            'Lancez en quelques minutes',
        ],
        'es' => [
            'Crea tu web rápido',
            'Sin programar',
            'Plantillas listas',
            'Ver la demo',
            'Tienda online',
            'Lanza en minutos',
        ],
        'lt' => [
            'Sukurkite svetainę greitai',
            'Be programavimo',
            'Paruošti šablonai',
            'Pažiūrėkite demo',
            'Internetinė parduotuvė',
            'Paleiskite per minutes',
        ],
        'ru' => [
            'Создайте сайт быстро',
            'Без программирования',
            'Готовые шаблоны',
            'Смотрите демо',
            'Интернет-магазин',
            'Запуск за минуты',
        ],
    ];

    private const DESCRIPTIONS = [
        'en' => [
            'Create your website with Site.pro. No code, ready templates and a fast launch.',
            'Launch a website, online store or landing page in minutes.',
            'Responsive design, built-in hosting and a simple editor.',
        ],
        'de' => [
            'Erstellen Sie Ihre Website mit Site.pro. Ohne Code, mit Vorlagen und schnellem Start.',
            'Website, Onlineshop oder Landingpage in wenigen Minuten starten.',
            'Responsives Design, integriertes Hosting und ein einfacher Editor.',
        ],
        'fr' => [
            'Créez votre site avec Site.pro. Sans code, modèles prêts et lancement rapide.',
            'Lancez un site, une boutique en ligne ou une page d\'atterrissage.',
            'Design responsive, hébergement intégré et éditeur simple.',
        ],
        'es' => [
            'Crea tu web con Site.pro. Sin código, plantillas listas y lanzamiento rápido.',
            'Lanza una web, tienda online o página de aterrizaje en minutos.',
            'Diseño adaptable, alojamiento incluido y editor sencillo.',
        ],
        'lt' => [
            'Sukurkite svetainę su Site.pro. Be kodo, su šablonais ir greitu paleidimu.',
            'Paleiskite svetainę, internetinę parduotuvę ar puslapį per minutes.',
            'Pritaikomas dizainas, integruotas hostingas ir paprastas redaktorius.',
        ],
        'ru' => [
            'Создайте сайт с Site.pro. Без кода, готовые шаблоны и быстрый запуск.',
            'Запустите сайт, интернет-магазин или лендинг за несколько минут.',
            'Адаптивный дизайн, встроенный хостинг и простой редактор.',
        ],
    ];

    private const PATHS = [
        'en' => ['website', 'demo'],
        'de' => ['webseite', 'demo'],
        'fr' => ['site', 'demo'],
        'es' => ['web', 'demo'],
        'lt' => ['svetaine', 'demo'],
        'ru' => ['site', 'demo'],
    ];

    public function __construct(private readonly KeywordNormalizer $normalizer = new KeywordNormalizer())
    {
    }

    /**
     * @return array{adName: string, headlines: string[], descriptions: string[], path1: string, path2: string}
     */
    public function generate(string $language, string $keyword): array
    {
        if (!LanguageCatalog::isSupported($language)) {
            throw new AdCopyException(
                sprintf('Для языка «%s» нет проверенного каталога объявлений. Добавьте проверенный текст перед запуском.', $language),
            );
        }

        $headlines = [];
        $keywordHeadline = $this->normalizer->shorten(
            $this->normalizer->titleCaseFirst($this->normalizer->cleanText($keyword)),
            30,
        );
        if ($keywordHeadline !== null) {
            $headlines[] = $keywordHeadline;
        }

        foreach (self::HEADLINES[$language] as $headline) {
            $headline = $this->normalizer->cleanText($headline);
            if ($headline === '' || mb_strlen($headline) > 30) {
                throw new AdCopyException(sprintf('В каталоге объявлений для языка «%s» некорректный заголовок.', $language));
            }
            $headlines[] = $headline;
        }

        $headlines = $this->unique($headlines);
        if (count($headlines) < 5) {
            throw new AdCopyException(sprintf('Недостаточно уникальных заголовков для языка «%s».', $language));
        }
        $headlines = array_slice($headlines, 0, 5);

        $descriptions = [];
        foreach (self::DESCRIPTIONS[$language] as $description) {
            $description = $this->normalizer->cleanText($description);
            if ($description === '' || mb_strlen($description) > 90) {
                throw new AdCopyException(sprintf('В каталоге объявлений для языка «%s» некорректное описание.', $language));
            }
            $descriptions[] = $description;
        }
        $descriptions = $this->unique($descriptions);
        if (count($descriptions) < 3) {
            throw new AdCopyException(sprintf('Недостаточно уникальных описаний для языка «%s».', $language));
        }

        [$path1, $path2] = self::PATHS[$language];
        $adName = $this->normalizer->shorten(
            sprintf('RSA | %s | %s', mb_strtoupper($language), $this->normalizer->display($keyword)),
            255,
        );

        if ($adName === null) {
            throw new AdCopyException('Не удалось сформировать название объявления.');
        }

        return [
            'adName' => $adName,
            'headlines' => $headlines,
            'descriptions' => $descriptions,
            'path1' => $path1,
            'path2' => $path2,
        ];
    }

    /**
     * @param string[] $values
     * @return string[]
     */
    private function unique(array $values): array
    {
        $seen = [];
        $result = [];
        foreach ($values as $value) {
            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $value;
        }

        return $result;
    }
}
