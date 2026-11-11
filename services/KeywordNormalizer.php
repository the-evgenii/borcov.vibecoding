<?php

declare(strict_types=1);

namespace app\services;

final class KeywordNormalizer
{
    private const JUNK_KEYWORDS = [
        'asdf',
        'asdfasdf',
        'test',
        'testing',
        'dummy',
        'lorem',
        'lorem ipsum',
        'keyword',
        'keywords',
        'sample',
        'example',
        'foo',
        'bar',
        'xxx',
        'n a',
        '123',
        '1234',
        '0000',
        'qwerty',
        'йцукен',
        'фыва',
    ];

    public function cleanText(string $raw): string
    {
        $raw = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $raw);
        $raw = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw) ?? '';
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($raw, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $raw = $normalized;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    }

    public function normalize(string $raw): string
    {
        $text = $this->cleanText($raw);
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(["’", "‘", '`'], "'", $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    public function display(string $raw): string
    {
        return mb_substr($this->cleanText($raw), 0, 255);
    }

    public function titleCaseFirst(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    public function isJunk(string $raw, ?string $normalized = null): bool
    {
        $clean = $this->cleanText($raw);
        $norm = $normalized ?? $this->normalize($raw);

        if ($clean === '' || $norm === '') {
            return true;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $raw) === 1) {
            return true;
        }

        $trimmed = ltrim($raw, " \t\r\n");
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            // Formula-injection trigger. Kept out of the export instead of
            // silently prefixing keyword text with an apostrophe.
            return true;
        }

        if (mb_strlen($clean) > 80 || mb_strlen($norm) < 2) {
            return true;
        }

        if (preg_match('/\p{L}/u', $norm) !== 1) {
            return true;
        }

        $words = preg_split('/\s+/u', $norm) ?: [];
        if (count($words) > 10) {
            return true;
        }

        if (preg_match('~(https?://|www\.)~iu', $clean) === 1 || preg_match('/<[^>]*>/u', $clean) === 1) {
            return true;
        }

        if (in_array($norm, self::JUNK_KEYWORDS, true)) {
            return true;
        }

        return preg_match('/(.)\1{4,}/u', $norm) === 1;
    }

    /**
     * @param string[] $phrases
     */
    public function matchAny(string $normalized, array $phrases): ?string
    {
        $haystack = ' ' . $normalized . ' ';
        foreach ($phrases as $phrase) {
            $needle = $this->normalize($phrase);
            if ($needle === '') {
                continue;
            }
            if (str_contains($haystack, ' ' . $needle . ' ')) {
                return $phrase;
            }
        }

        return null;
    }

    public function shorten(string $text, int $maxLength): ?string
    {
        $text = $this->cleanText($text);
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace >= (int) floor($maxLength * 0.4)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        $cut = rtrim($cut, " \t.,;:!?—–-");
        if ($cut === '' || mb_strlen($cut) < 2) {
            return null;
        }

        return $cut;
    }
}
