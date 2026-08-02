<?php

namespace Tests\Unit;

use App\Support\MessageUrlExtractor;
use PHPUnit\Framework\TestCase;

class MessageUrlExtractorTest extends TestCase
{
    public function test_empty_body(): void
    {
        $this->assertSame([], MessageUrlExtractor::extract(null));
        $this->assertSame([], MessageUrlExtractor::extract(''));
        $this->assertNull(MessageUrlExtractor::singleUrl('hello'));
    }

    public function test_single_https_url(): void
    {
        $this->assertSame(
            ['https://example.com/path'],
            MessageUrlExtractor::extract('see https://example.com/path now'),
        );
        $this->assertSame(
            'https://example.com/path',
            MessageUrlExtractor::singleUrl('see https://example.com/path now'),
        );
    }

    public function test_www_normalized_to_https(): void
    {
        $this->assertSame(
            ['https://www.example.com'],
            MessageUrlExtractor::extract('go www.example.com please'),
        );
    }

    public function test_strips_trailing_punctuation(): void
    {
        $this->assertSame(
            ['https://example.com'],
            MessageUrlExtractor::extract('see https://example.com.'),
        );
    }

    public function test_multiple_urls_single_returns_null(): void
    {
        $body = 'a https://a.com b https://b.com';
        $this->assertCount(2, MessageUrlExtractor::extract($body));
        $this->assertNull(MessageUrlExtractor::singleUrl($body));
    }

    public function test_same_url_twice_is_not_single(): void
    {
        $body = 'https://example.com and https://example.com';
        $this->assertCount(2, MessageUrlExtractor::extract($body));
        $this->assertNull(MessageUrlExtractor::singleUrl($body));
    }
}
