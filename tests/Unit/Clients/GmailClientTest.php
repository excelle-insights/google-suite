<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Clients;

use ExcelleInsights\GoogleSuite\Clients\GmailClient;
use PHPUnit\Framework\TestCase;

final class GmailClientTest extends TestCase
{
    public function testBase64UrlDecodeHandlesUnpaddedInput(): void
    {
        $encoded = rtrim(strtr(base64_encode('Hello, Gmail'), '+/', '-_'), '=');

        $this->assertSame('Hello, Gmail', GmailClient::base64UrlDecode($encoded));
    }

    public function testBase64UrlDecodePreservesZero(): void
    {
        $this->assertSame('0', GmailClient::base64UrlDecode('MA'));
    }

    public function testBase64UrlDecodeReturnsEmptyOnInvalidInput(): void
    {
        $this->assertSame('', GmailClient::base64UrlDecode('!!!!'));
    }
}
