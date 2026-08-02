<?php

namespace App\Support;

final class OgMetaParser
{
    /**
     * @return array{title: ?string, description: ?string, image_url: ?string}
     */
    public static function parse(string $html, string $pageUrl): array
    {
        $title = self::metaContent($html, ['og:title', 'twitter:title'])
            ?? self::documentTitle($html);
        $description = self::metaContent($html, ['og:description', 'twitter:description']);
        $image = self::metaContent($html, ['og:image', 'twitter:image']);

        if ($image !== null) {
            $image = self::absolutize($image, $pageUrl);
        }

        return [
            'title' => self::truncate(self::nullableTrim($title), 255),
            'description' => self::truncate(self::nullableTrim($description), 1000),
            'image_url' => self::nullableTrim($image),
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private static function metaContent(string $html, array $keys): ?string
    {
        foreach ($keys as $key) {
            $content = self::findMetaContent($html, $key);

            if ($content !== null) {
                return $content;
            }
        }

        return null;
    }

    private static function findMetaContent(string $html, string $key): ?string
    {
        $content = self::findMetaContentViaDom($html, $key);

        if ($content === null) {
            $content = self::findMetaContentViaRegex($html, $key);
        }

        return self::nullableTrim($content);
    }

    private static function findMetaContentViaDom(string $html, string $key): ?string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument();

            if (! @$dom->loadHTML('<?xml encoding="UTF-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return null;
            }

            $xpath = new \DOMXPath($dom);
            $lowerKey = strtolower($key);
            $nodes = $xpath->query('//meta[@property or @name]');

            if ($nodes === false) {
                return null;
            }

            foreach ($nodes as $node) {
                if (! $node instanceof \DOMElement) {
                    continue;
                }

                $property = strtolower($node->getAttribute('property'));
                $name = strtolower($node->getAttribute('name'));

                if ($property !== $lowerKey && $name !== $lowerKey) {
                    continue;
                }

                $content = $node->getAttribute('content');

                if ($content === '') {
                    continue;
                }

                return html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function findMetaContentViaRegex(string $html, string $key): ?string
    {
        $quotedKey = preg_quote($key, '/');
        $patterns = [
            '/<meta[^>]+(?:property|name)=["\']'.$quotedKey.'["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']'.$quotedKey.'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return null;
    }

    private static function documentTitle(string $html): ?string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument();

            if (@$dom->loadHTML('<?xml encoding="UTF-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
                $titles = $dom->getElementsByTagName('title');

                if ($titles->length > 0) {
                    $text = $titles->item(0)?->textContent ?? '';

                    if ($text !== '') {
                        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (preg_match('/<title[^>]*>([^<]*)<\/title>/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    private static function absolutize(string $url, string $pageUrl): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $pageParts = parse_url($pageUrl);

        if (! is_array($pageParts)) {
            return $url;
        }

        $scheme = $pageParts['scheme'] ?? 'https';
        $host = $pageParts['host'] ?? '';
        $port = isset($pageParts['port']) ? ':'.$pageParts['port'] : '';

        if (str_starts_with($url, '/')) {
            return $scheme.'://'.$host.$port.$url;
        }

        $path = $pageParts['path'] ?? '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';

        if (! str_ends_with($dir, '/')) {
            $dir .= '/';
        }

        $segments = explode('/', $dir.$url);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                if ($segment === '' && $resolved === []) {
                    $resolved[] = '';
                }

                continue;
            }

            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $segment;
        }

        $resolvedPath = implode('/', $resolved);

        if (! str_starts_with($resolvedPath, '/')) {
            $resolvedPath = '/'.$resolvedPath;
        }

        return $scheme.'://'.$host.$port.$resolvedPath;
    }

    private static function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
