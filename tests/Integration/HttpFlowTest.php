<?php

declare(strict_types=1);

namespace app\tests\Integration;

use app\models\Campaign;
use app\models\CampaignAd;
use app\models\CampaignExclusion;
use app\models\CampaignGroup;
use app\models\Import;
use app\models\Keyword;
use app\models\KeywordSource;
use app\models\Setting;
use app\models\User;
use app\services\CampaignGenerator;
use app\services\SettingsService;
use app\tests\Support\HttpClient;
use app\tests\Support\HttpResponse;
use app\tests\Support\Seed;
use app\tests\Support\TestDb;
use PHPUnit\Framework\TestCase;

final class HttpFlowTest extends TestCase
{
    private string $_baseUrl = '';
    private string $_cookieJar = '';
    private string $_serverLog = '';

    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        TestDb::admin('e2e@example.com', 'E2e-password-123');
        $this->_cookieJar = TEST_ROOT . '/runtime/tests/e2e-cookies.txt';
        @unlink($this->_cookieJar);
        $this->_serverLog = TEST_ROOT . '/runtime/tests/e2e-server.log';
        @unlink($this->_serverLog);
    }

    public function testEndToEndUploadAdminGeneratePreviewAndExport(): void
    {
        [$process, $port] = $this->startServer();
        $this->_baseUrl = 'http://127.0.0.1:' . $port;

        try {
            $http = new HttpClient($this->_baseUrl, $this->_cookieJar);

            $unauthorized = $http->get('/keywords');
            self::assertSame(302, $unauthorized->status);
            self::assertStringContainsString('/login', (string) $unauthorized->location());

            $loginPage = $http->get('/login');
            self::assertSame(200, $loginPage->status);
            self::assertStringContainsString('Вход', $loginPage->body);
            self::assertStringContainsString('id="login-email"', $loginPage->body);
            self::assertStringContainsString('id="login-password"', $loginPage->body);
            self::assertStringContainsString('>Войти</button>', $loginPage->body);
            self::assertStringNotContainsString(
                'Данные входа настраиваются через переменные окружения',
                $loginPage->body,
            );
            self::assertStringNotContainsString(
                'Локальная среда · Без внешних сервисов',
                $loginPage->body,
            );
            $csrf = $http->csrf($loginPage->body);

            $emptyLogin = $http->post('/login', [
                '_csrf' => $csrf,
                'LoginForm[email]' => '',
                'LoginForm[password]' => '',
            ]);
            self::assertSame(422, $emptyLogin->status);
            self::assertStringContainsString('Необходимо заполнить', $emptyLogin->body);

            $loginPage = $http->get('/login');
            $csrf = $http->csrf($loginPage->body);

            $badLogin = $http->post('/login', [
                '_csrf' => $csrf,
                'LoginForm[email]' => 'e2e@example.com',
                'LoginForm[password]' => 'definitely-wrong',
            ]);
            self::assertSame(422, $badLogin->status);
            self::assertStringContainsString('Неверная эл. почта или пароль', $badLogin->body);

            $loginPage = $http->get('/login');
            $csrf = $http->csrf($loginPage->body);
            $login = $http->post('/login', [
                '_csrf' => $csrf,
                'LoginForm[email]' => 'e2e@example.com',
                'LoginForm[password]' => 'E2e-password-123',
            ]);
            self::assertSame(302, $login->status);

            $dashboard = $http->get('/');
            self::assertSame(200, $dashboard->status);
            self::assertStringContainsString('lang="ru"', $dashboard->body);
            self::assertStringContainsString('Импорт четырёх источников', $dashboard->body);
            self::assertStringContainsString('Загрузить демо-данные', $dashboard->body);
            self::assertStringNotContainsString('onsubmit=', $dashboard->body);
            self::assertStringContainsString('data-reset-confirm=', $dashboard->body);
            self::assertStringContainsString('reset-confirm.js', $dashboard->body);
            $csp = (string) $dashboard->header('content-security-policy');
            self::assertStringContainsString("default-src 'self'", $csp);
            self::assertStringNotContainsString('unsafe-inline', $csp);
            $confirmScript = $http->get('/js/reset-confirm.js');
            self::assertSame(200, $confirmScript->status);
            self::assertStringContainsString('addEventListener', $confirmScript->body);
            $csrf = $http->csrf($dashboard->body);

            $importsPage = $http->get('/imports');
            self::assertSame(200, $importsPage->status);
            self::assertStringContainsString('История импорта', $importsPage->body);
            self::assertStringContainsString('Примеры файлов', $importsPage->body);

            $guestSample = (new HttpClient($this->_baseUrl, $this->_cookieJar . '.guest'))->get('/import/sample?name=google-ads');
            self::assertSame(302, $guestSample->status);

            $sample = $http->get('/import/sample?name=google-ads');
            self::assertSame(200, $sample->status);
            self::assertStringContainsString('text/csv', (string) $sample->header('content-type'));
            self::assertStringContainsString('Keyword', $sample->body);
            self::assertSame(404, $http->get('/import/sample?name=unknown')->status);

            $csrf = $http->csrf($importsPage->body);
            $emptyImport = $http->post('/imports', ['_csrf' => $csrf]);
            self::assertSame(422, $emptyImport->status);
            self::assertStringContainsString('Необходимо заполнить', $emptyImport->body);

            $sourceFiles = [
                'google_ads_keywords.csv' => 'google_ads',
                'search_console_queries.csv' => 'search_console',
                'ahrefs_organic_keywords.csv' => 'ahrefs_organic',
                'ahrefs_paid_keywords.csv' => 'ahrefs_paid',
                'search_console_queries.json' => 'search_console',
            ];
            foreach ($sourceFiles as $filename => $source) {
                $path = TEST_ROOT . '/data/demo/' . $filename;
                $upload = $http->post('/imports', [
                    '_csrf' => $csrf,
                    'ImportForm[source]' => $source,
                ], [
                    'ImportForm[file]' => new \CURLFile(
                        $path,
                        str_ends_with($filename, '.json') ? 'application/json' : 'text/csv',
                        $filename,
                    ),
                ]);
                self::assertSame(302, $upload->status, $filename);
                self::assertStringContainsString('/imports', (string) $upload->location(), $filename);

                $importsPage = $http->get('/imports');
                self::assertSame(200, $importsPage->status, $filename);
                $csrf = $http->csrf($importsPage->body);
                self::assertStringContainsString('Импортировано', $importsPage->body, $filename);
                self::assertStringContainsString('История импорта', $importsPage->body, $filename);
            }

            $xssFile = TEST_ROOT . '/runtime/tests/xss.csv';
            file_put_contents(
                $xssFile,
                "keyword,volume,language,url\n\"<script>alert(1)</script>\",100,en,https://site.pro/\n",
            );
            $xssUpload = $http->post('/imports', [
                '_csrf' => $csrf,
                'ImportForm[source]' => 'search_console',
            ], [
                'ImportForm[file]' => new \CURLFile($xssFile, 'text/csv', 'xss.csv'),
            ]);
            self::assertSame(302, $xssUpload->status);

            $keywordsPage = $http->get('/keywords?q=script');
            self::assertSame(200, $keywordsPage->status);
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $keywordsPage->body);
            self::assertStringNotContainsString('<script>alert(1)</script>', $keywordsPage->body);
            $csrf = $http->csrf($keywordsPage->body);

            $invalidJson = TEST_ROOT . '/runtime/tests/invalid.json';
            file_put_contents($invalidJson, '{"items": [');
            $invalidUpload = $http->post('/imports', [
                '_csrf' => $csrf,
                'ImportForm[source]' => 'search_console',
            ], [
                'ImportForm[file]' => new \CURLFile($invalidJson, 'application/json', 'invalid.json'),
            ]);
            self::assertSame(422, $invalidUpload->status);
            self::assertStringContainsString('Некорректный JSON', $invalidUpload->body);

            $settingsPage = $http->get('/settings');
            self::assertSame(200, $settingsPage->status);
            $csrf = $http->csrf($settingsPage->body);

            $missingCsrf = $http->post('/settings', [
                'SettingsForm[minVolume]' => '50',
            ]);
            self::assertSame(400, $missingCsrf->status);

            $badSettings = $http->post('/settings', [
                '_csrf' => $csrf,
                'SettingsForm[brandsText][x]' => 'bad-shape',
                'SettingsForm[forbiddenText]' => 'casino',
                'SettingsForm[minVolume]' => '50',
                'SettingsForm[urls][en]' => 'https://site.pro/Website-Builder/',
            ]);
            self::assertSame(422, $badSettings->status);

            $settingsPage = $http->get('/settings');
            $csrf = $http->csrf($settingsPage->body);
            $savedSettings = $http->post('/settings', [
                '_csrf' => $csrf,
                'SettingsForm[brandsText]' => "site.pro\nsite pro\nsitepro",
                'SettingsForm[forbiddenText]' => "free\nkostenlos\nkostenloser\ngratuit\ngratis\ncasino",
                'SettingsForm[minVolume]' => '50',
                'SettingsForm[urls][en]' => 'https://site.pro/Website-Builder/',
                'SettingsForm[urls][de]' => 'https://site.pro/de/',
                'SettingsForm[urls][fr]' => 'https://site.pro/fr/',
                'SettingsForm[urls][es]' => 'https://site.pro/es/',
                'SettingsForm[urls][lt]' => 'https://site.pro/lt/',
                'SettingsForm[urls][ru]' => 'https://site.pro/ru/',
            ]);
            self::assertSame(302, $savedSettings->status);
            $settingsPage = $http->get('/settings');
            self::assertSame(200, $settingsPage->status);
            self::assertStringContainsString('Текущее: 50', $settingsPage->body);
            self::assertStringContainsString('Целевые URL по языкам', $settingsPage->body);
            $csrf = $http->csrf($settingsPage->body);

            $generate = $http->post('/campaigns/generate', ['_csrf' => $csrf]);
            self::assertSame(302, $generate->status);
            self::assertStringContainsString('/campaigns/preview', (string) $generate->location());

            $preview = $http->get('/campaigns/preview');
            self::assertSame(200, $preview->status);
            self::assertStringContainsString('Site.pro | English | Search', $preview->body);
            self::assertStringContainsString('Site.pro | German | Search', $preview->body);
            self::assertStringContainsString('Предпросмотр кампании', $preview->body);
            self::assertStringContainsString('Реклама', $preview->body);
            self::assertStringContainsString('Показать все RSA-ассеты', $preview->body);
            self::assertStringContainsString('Проверка структуры CSV пройдена', $preview->body);

            $export = $http->get('/campaigns/export');
            self::assertSame(200, $export->status);
            self::assertStringContainsString('text/csv', (string) $export->header('content-type'));
            self::assertStringContainsString('.csv', (string) $export->header('content-disposition'));
            self::assertTrue(mb_check_encoding($export->body, 'UTF-8'));
            self::assertStringStartsWith('Campaign,', $export->body);
            self::assertStringContainsString('Responsive search ad', $export->body);
            self::assertStringContainsString('https://site.pro/de/', $export->body);
            self::assertStringContainsString('https://site.pro/Website-Builder/', $export->body);
            self::assertStringNotContainsString('<script>', $export->body);

            $exportRows = $this->parseCsv($export->body);
            self::assertCount(26, $exportRows['header']);
            self::assertNotEmpty($exportRows['rows']);

            $previewCsrf = $http->csrf($preview->body);
            $logout = $http->post('/logout', ['_csrf' => $previewCsrf]);
            self::assertSame(302, $logout->status);
            self::assertStringContainsString('/login', (string) $logout->location());

            $afterLogout = $http->get('/keywords');
            self::assertSame(302, $afterLogout->status);
            self::assertStringContainsString('/login', (string) $afterLogout->location());
        } finally {
            $this->stopServer($process);
        }

        $log = is_file($this->_serverLog) ? (string) file_get_contents($this->_serverLog) : '';
        self::assertStringNotContainsString('PHP Fatal error', $log);
        self::assertStringNotContainsString('PHP Warning', $log);
    }

    public function testStaleCampaignCannotBeDownloadedUntilRegenerated(): void
    {
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [['website builder', 100, 'en']]);
        [$process, $port] = $this->startServer();
        $this->_baseUrl = 'http://127.0.0.1:' . $port;
        try {
            $http = new HttpClient($this->_baseUrl, $this->_cookieJar);
            $this->login($http);
            $csrf = $http->csrf($http->get('/settings')->body);
            self::assertSame(302, $http->post('/campaigns/generate', ['_csrf' => $csrf])->status);
            self::assertSame(200, $http->get('/campaigns/export')->status);

            $fields = $this->settingsFields($csrf);
            $fields['SettingsForm[minVolume]'] = '70';
            self::assertSame(302, $http->post('/settings', $fields)->status);
            $preview = $http->get('/campaigns/preview');
            self::assertSame(200, $preview->status);
            self::assertStringContainsString('Кампания устарела', $preview->body);
            self::assertStringNotContainsString('>Скачать CSV</a>', $preview->body);
            self::assertStringNotContainsString('>Скачать CSV для Editor</a>', $preview->body);
            self::assertSame(302, $http->get('/campaigns/export')->status);
            self::assertStringContainsString('Кампания устарела', $http->get('/campaigns/preview')->body);

            self::assertSame(302, $http->post('/campaigns/generate', ['_csrf' => $csrf])->status);
            self::assertSame(200, $http->get('/campaigns/export')->status);
            self::assertStringNotContainsString('Кампания устарела', $http->get('/campaigns/preview')->body);
        } finally {
            $this->stopServer($process);
        }
    }

    public function testSettingsRejectsIncompleteMalformedAndNestedInput(): void
    {
        TestDb::settings(['site.pro'], ['casino'], 50);

        [$process, $port] = $this->startServer();
        $this->_baseUrl = 'http://127.0.0.1:' . $port;

        try {
            $http = new HttpClient($this->_baseUrl, $this->_cookieJar);
            $this->login($http);

            $settingsPage = $http->get('/settings');
            self::assertSame(200, $settingsPage->status);
            $csrf = $http->csrf($settingsPage->body);

            $before = (new SettingsService())->get();
            $beforeBrands = $before->getBrands();
            $beforeForbidden = $before->getForbidden();
            $beforeMinVolume = (int) $before->min_volume;
            $beforeUrls = $before->getLanguageUrls();

            $csrfOnly = $http->post('/settings', ['_csrf' => $csrf]);
            self::assertSame(422, $csrfOnly->status);

            $partial = $http->post('/settings', [
                '_csrf' => $csrf,
                'SettingsForm[minVolume]' => '999',
            ]);
            self::assertSame(422, $partial->status);

            $nestedFields = $this->settingsFields($csrf);
            unset($nestedFields['SettingsForm[urls][en]']);
            $nestedFields['SettingsForm[urls][en][x]'] = 'nested';
            $nested = $http->post('/settings', $nestedFields);
            self::assertSame(422, $nested->status);
            self::assertStringContainsString('целевой URL', $nested->body);

            $after = (new SettingsService())->get();
            self::assertSame($beforeBrands, $after->getBrands());
            self::assertSame($beforeForbidden, $after->getForbidden());
            self::assertSame($beforeMinVolume, (int) $after->min_volume);
            self::assertSame($beforeUrls, $after->getLanguageUrls());

            $emptyFields = $this->settingsFields($csrf);
            $emptyFields['SettingsForm[brandsText]'] = '';
            $emptyFields['SettingsForm[forbiddenText]'] = '';
            foreach (['en', 'de', 'fr', 'es', 'lt', 'ru'] as $code) {
                $emptyFields['SettingsForm[urls][' . $code . ']'] = '';
            }
            $saved = $http->post('/settings', $emptyFields);
            self::assertSame(302, $saved->status);

            $afterSave = (new SettingsService())->get();
            self::assertSame([], $afterSave->getBrands());
            self::assertSame([], $afterSave->getForbidden());
            self::assertSame(50, (int) $afterSave->min_volume);
            self::assertSame([], $afterSave->getLanguageUrls());
        } finally {
            $this->stopServer($process);
        }
    }

    public function testResetRequiresAuthPostAndCsrfAndClearsAllGeneratedTables(): void
    {
        TestDb::settings(['site.pro'], ['casino'], 50);
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['reset clean keyword', 100, 'en'],
            ['site.pro casino reset exclusion', 100, 'en'],
        ]);
        (new CampaignGenerator())->generate(null);

        $before = [
            'imports' => (int) Import::find()->count(),
            'keywords' => (int) Keyword::find()->count(),
            'campaigns' => (int) Campaign::find()->count(),
            'groups' => (int) CampaignGroup::find()->count(),
            'ads' => (int) CampaignAd::find()->count(),
            'exclusions' => (int) CampaignExclusion::find()->count(),
        ];
        foreach ($before as $table => $count) {
            self::assertGreaterThan(0, $count, $table);
        }

        [$process, $port] = $this->startServer();
        $this->_baseUrl = 'http://127.0.0.1:' . $port;

        try {
            $guest = new HttpClient($this->_baseUrl, $this->_cookieJar . '.guest');
            self::assertSame(302, $guest->get('/reset-data')->status);

            $http = new HttpClient($this->_baseUrl, $this->_cookieJar);
            $this->login($http);

            self::assertSame(405, $http->get('/reset-data')->status);
            self::assertSame(400, $http->post('/reset-data', [])->status);

            self::assertSame($before['imports'], (int) Import::find()->count());
            self::assertSame($before['keywords'], (int) Keyword::find()->count());
            self::assertSame($before['campaigns'], (int) Campaign::find()->count());

            $dashboard = $http->get('/');
            $csrf = $http->csrf($dashboard->body);
            self::assertSame(302, $http->post('/reset-data', ['_csrf' => $csrf])->status);

            self::assertSame(0, (int) Import::find()->count());
            self::assertSame(0, (int) Keyword::find()->count());
            self::assertSame(0, (int) Campaign::find()->count());
            self::assertSame(0, (int) CampaignGroup::find()->count());
            self::assertSame(0, (int) CampaignAd::find()->count());
            self::assertSame(0, (int) CampaignExclusion::find()->count());
            self::assertSame(1, (int) User::find()->count());
            self::assertSame(1, (int) Setting::find()->count());
            self::assertSame(['site.pro'], (new SettingsService())->get()->getBrands());
        } finally {
            $this->stopServer($process);
        }
    }

    public function testKeywordReasonFiltersUseCompleteStoredReasons(): void
    {
        TestDb::settings(['site.pro'], ['casino'], 50);
        Seed::csv(KeywordSource::SEARCH_CONSOLE, [
            ['site.pro casino dual reason keyword', 100, 'en'],
            ['site.pro brand only keyword', 100, 'en'],
            ['casino forbidden only keyword', 100, 'en'],
            ['brand_extra exact token keyword', 100, 'en'],
        ]);
        TestDb::audit();

        $partial = Keyword::find()->where(['keyword' => 'brand_extra exact token keyword'])->one();
        self::assertNotNull($partial);
        $partial->updateAttributes([
            'status' => Keyword::STATUS_EXCLUDED,
            'reason' => 'brand_extra',
            'reasons_json' => '["brand_extra"]',
        ]);

        [$process, $port] = $this->startServer();
        $this->_baseUrl = 'http://127.0.0.1:' . $port;

        try {
            $http = new HttpClient($this->_baseUrl, $this->_cookieJar);
            $this->login($http);

            $brandPage = $http->get('/keywords?reason=brand');
            self::assertSame(200, $brandPage->status);
            self::assertStringContainsString('site.pro casino dual reason keyword', $brandPage->body);
            self::assertStringContainsString('site.pro brand only keyword', $brandPage->body);
            self::assertStringNotContainsString('brand_extra exact token keyword', $brandPage->body);

            $forbiddenPage = $http->get('/keywords?reason=forbidden');
            self::assertSame(200, $forbiddenPage->status);
            self::assertStringContainsString('site.pro casino dual reason keyword', $forbiddenPage->body);
            self::assertStringContainsString('casino forbidden only keyword', $forbiddenPage->body);
            self::assertStringNotContainsString('site.pro brand only keyword', $forbiddenPage->body);
        } finally {
            $this->stopServer($process);
        }

        $brandQuery = Keyword::find()->andWhere(Keyword::reasonCondition(Keyword::REASON_BRAND));
        self::assertSame(2, (int) (clone $brandQuery)->count());
        self::assertCount(1, (clone $brandQuery)->orderBy(['id' => SORT_ASC])->limit(1)->offset(1)->all());

        $forbiddenQuery = Keyword::find()->andWhere(Keyword::reasonCondition(Keyword::REASON_FORBIDDEN));
        self::assertSame(2, (int) (clone $forbiddenQuery)->count());
    }

    private function login(HttpClient $http): void
    {
        $loginPage = $http->get('/login');
        self::assertSame(200, $loginPage->status);
        $csrf = $http->csrf($loginPage->body);
        $login = $http->post('/login', [
            '_csrf' => $csrf,
            'LoginForm[email]' => 'e2e@example.com',
            'LoginForm[password]' => 'E2e-password-123',
        ]);
        self::assertSame(302, $login->status);
    }

    /**
     * @return array<string, string>
     */
    private function settingsFields(string $csrf): array
    {
        $fields = [
            '_csrf' => $csrf,
            'SettingsForm[brandsText]' => "site.pro\nsite pro\nsitepro",
            'SettingsForm[forbiddenText]' => "casino\nfree",
            'SettingsForm[minVolume]' => '50',
        ];
        foreach (['en', 'de', 'fr', 'es', 'lt', 'ru'] as $code) {
            $fields['SettingsForm[urls][' . $code . ']'] = $code === 'en'
                ? 'https://site.pro/Website-Builder/'
                : 'https://site.pro/' . $code . '/';
        }

        return $fields;
    }

    /**
     * @return array{0: resource, 1: int}
     */
    private function startServer(): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            self::fail('Unable to allocate a test port: ' . $error);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);

        $environment = getenv() ?: [];
        $environment = array_merge($environment, [
            'APP_ENV' => 'test',
            'APP_DEBUG' => '0',
            'APP_TIMEZONE' => 'UTC',
            'APP_COOKIE_VALIDATION_KEY' => 'e2e-cookie-validation-key-not-for-production',
            'APP_DB_DSN' => 'sqlite:' . TEST_DB_PATH,
        ]);

        $command = [
            PHP_BINARY,
            '-d',
            'upload_max_filesize=4M',
            '-d',
            'post_max_size=4M',
            '-S',
            '127.0.0.1:' . $port,
            '-t',
            'web',
            'web/router.php',
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $this->_serverLog, 'a'],
            2 => ['file', $this->_serverLog, 'a'],
        ];
        $process = proc_open($command, $descriptors, $pipes, TEST_ROOT, $environment);
        if (!is_resource($process)) {
            self::fail('Unable to start the PHP test server.');
        }

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
            if (is_resource($connection)) {
                fclose($connection);

                return [$process, $port];
            }
            usleep(100_000);
        }

        $this->stopServer($process);
        self::fail('The PHP test server did not start. Log: ' . @file_get_contents($this->_serverLog));
    }

    /**
     * @param resource $process
     */
    private function stopServer($process): void
    {
        if (!is_resource($process)) {
            return;
        }

        @proc_terminate($process);
        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(50_000);
        }
        @proc_close($process);
    }

    /**
     * @return array{header: string[], rows: array<int, array<string, string>>}
     */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, $csv);
        rewind($stream);

        $header = fgetcsv($stream, 0, ',', '"', '');
        self::assertIsArray($header);
        $rows = [];
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if ($values === [null]) {
                continue;
            }
            self::assertCount(count($header), $values);
            $rows[] = array_combine($header, $values);
        }
        fclose($stream);

        return ['header' => $header, 'rows' => $rows];
    }
}
