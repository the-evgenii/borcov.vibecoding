<?php

declare(strict_types=1);

namespace app\tests\Support;

use RuntimeException;

final class HttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $cookieJar,
    ) {
    }

    public function get(string $path): HttpResponse
    {
        return $this->request('GET', $path, []);
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, \CURLFile> $files
     */
    public function post(string $path, array $fields, array $files = []): HttpResponse
    {
        return $this->request('POST', $path, $fields, $files);
    }

    public function csrf(string $html): string
    {
        $patterns = [
            '/name="_csrf"\s+value="([^"]+)"/',
            '/value="([^"]+)"\s+name="_csrf"/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        throw new RuntimeException('CSRF token was not found in the HTML.');
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, \CURLFile> $files
     */
    private function request(string $method, string $path, array $fields, array $files = []): HttpResponse
    {
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialise cURL.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'VibeAdsConsole-e2e/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html, text/csv'],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $files === []
                ? http_build_query($fields, '', '&')
                : array_merge($fields, $files);
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('HTTP request failed: ' . curl_error($ch));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $body = substr((string) $raw, $headerSize);
        $headers = [];
        foreach (preg_split('/\r\n|\n|\r/', $rawHeaders) ?: [] as $line) {
            $separator = strpos($line, ':');
            if ($separator === false) {
                continue;
            }
            $name = mb_strtolower(trim(substr($line, 0, $separator)));
            $value = trim(substr($line, $separator + 1));
            if ($name !== '') {
                $headers[$name] = $value;
            }
        }

        return new HttpResponse($status, $headers, $body);
    }
}
