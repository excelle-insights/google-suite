<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Config;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use ExcelleInsights\GoogleSuite\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;

final class GoogleSuiteConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_TABLE_PREFIX'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        putenv('GOOGLE_TABLE_PREFIX');
    }

    private function env(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    public function testEnsureCredentialsThrowsWhenMissing(): void
    {
        foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        $this->assertSame('', GoogleSuiteConfig::clientId());

        $this->expectException(AuthenticationException::class);
        GoogleSuiteConfig::ensureCredentials();
    }

    public function testTablePrefixIsApplied(): void
    {
        $this->env('GOOGLE_TABLE_PREFIX', 'gs');

        $this->assertSame('gs_messages', GoogleSuiteConfig::table('messages'));
    }

    public function testTablePrefixDefaultsToGoogle(): void
    {
        $this->assertSame('google_messages', GoogleSuiteConfig::table('messages'));
    }

    public function testRedirectUriSettable(): void
    {
        GoogleSuiteConfig::setRedirectUri('https://example.test/new-callback');

        $this->assertSame('https://example.test/new-callback', GoogleSuiteConfig::redirectUri());
    }

    public function testAuthAndTokenUrls(): void
    {
        $this->assertStringContainsString('oauth2', GoogleSuiteConfig::authBaseUrl());
        $this->assertStringContainsString('token', GoogleSuiteConfig::tokenUrl());
    }

    public function testAttachmentRootDefaultsToSystemTemp(): void
    {
        $this->assertStringContainsString('google-suite-attachments', GoogleSuiteConfig::attachmentRoot());
    }
}