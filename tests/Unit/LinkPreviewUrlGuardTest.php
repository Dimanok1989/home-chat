<?php

namespace Tests\Unit;

use App\Support\LinkPreviewUrlGuard;
use PHPUnit\Framework\TestCase;

class LinkPreviewUrlGuardTest extends TestCase
{
    public function test_allows_public_https(): void
    {
        $this->assertTrue(LinkPreviewUrlGuard::isAllowed('https://example.com/page'));
    }

    public function test_rejects_javascript_scheme(): void
    {
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('javascript:alert(1)'));
    }

    public function test_rejects_localhost(): void
    {
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://localhost/x'));
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://127.0.0.1/x'));
    }

    public function test_rejects_private_ipv4(): void
    {
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://192.168.1.10/'));
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://10.0.0.5/'));
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://172.16.5.1/'));
    }

    public function test_rejects_private_ipv6_literals(): void
    {
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://[::1]/'));
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://[fc00::1]/'));
        $this->assertFalse(LinkPreviewUrlGuard::isAllowed('http://[fe80::1]/'));
    }
}
