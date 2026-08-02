<?php

namespace Tests\Unit;

use App\Support\OgMetaParser;
use PHPUnit\Framework\TestCase;

class OgMetaParserTest extends TestCase
{
    public function test_parses_og_tags(): void
    {
        $html = <<<'HTML'
        <html><head>
          <meta property="og:title" content="Hello &amp; Co" />
          <meta property="og:description" content="Desc" />
          <meta property="og:image" content="https://cdn.example.com/a.jpg" />
          <title>Fallback</title>
        </head></html>
        HTML;

        $meta = OgMetaParser::parse($html, 'https://example.com/page');

        $this->assertSame('Hello & Co', $meta['title']);
        $this->assertSame('Desc', $meta['description']);
        $this->assertSame('https://cdn.example.com/a.jpg', $meta['image_url']);
    }

    public function test_falls_back_to_title_and_resolves_relative_image(): void
    {
        $html = <<<'HTML'
        <html><head>
          <meta property="og:image" content="/img/a.jpg" />
          <title> Page Title </title>
        </head></html>
        HTML;

        $meta = OgMetaParser::parse($html, 'https://example.com/dir/page');

        $this->assertSame('Page Title', $meta['title']);
        $this->assertSame('https://example.com/img/a.jpg', $meta['image_url']);
        $this->assertNull($meta['description']);
    }
}
