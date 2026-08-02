<?php

namespace App\Support;

final class MessageUrlExtractor
{
    private const URL_PATTERN = '/https?:\/\/[^\s<]+|www\.[^\s<]+/iu';

    private const TRAILING_PUNCT = '/[.,;:!?)\]]+$/u';

    /**
     * @return list<string>
     */
    public static function extract(?string $body): array
    {
        if ($body === null || $body === '') {
            return [];
        }

        $urls = [];

        if (! preg_match_all(self::URL_PATTERN, $body, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $raw = $match[0];
            $url = preg_replace(self::TRAILING_PUNCT, '', $raw) ?? $raw;

            if ($url === '') {
                continue;
            }

            if (! preg_match('/^https?:\/\//iu', $url)) {
                $url = 'https://'.$url;
            }

            $urls[] = $url;
        }

        return $urls;
    }

    public static function singleUrl(?string $body): ?string
    {
        $urls = self::extract($body);

        return count($urls) === 1 ? $urls[0] : null;
    }
}
