<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Facade;

use ExcelleInsights\GoogleSuite\Auth\GoogleAuthManager;
use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use ExcelleInsights\GoogleSuite\Controller\OAuthController;
use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;
use ExcelleInsights\GoogleSuite\Services\CalendarService;
use ExcelleInsights\GoogleSuite\Services\DriveService;
use ExcelleInsights\GoogleSuite\Services\GmailService;
use PHPUnit\Framework\TestCase;

final class GoogleSuiteManagerTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        putenv('GOOGLE_CLIENT_ID=test-client-id.apps.googleusercontent.com');
        putenv('GOOGLE_CLIENT_SECRET=test-client-secret');
        putenv('GOOGLE_REDIRECT_URI=https://example.test/oauth/callback');
        putenv('GOOGLE_TABLE_PREFIX=google');

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE api_access_tokens (
                id INTEGER PRIMARY KEY,
                app TEXT,
                user_id TEXT,
                client_id TEXT,
                client_secret TEXT,
                access_token TEXT,
                expires_at INTEGER,
                created_at TEXT,
                updated_at TEXT
            )'
        );
    }

    public function testConstructsWithoutStoredToken(): void
    {
        $suite = new GoogleSuiteManager($this->pdo);

        $this->assertInstanceOf(GmailService::class, $suite->gmail());
        $this->assertInstanceOf(CalendarService::class, $suite->calendar());
        $this->assertInstanceOf(DriveService::class, $suite->drive());
        $this->assertInstanceOf(GoogleAuthManager::class, $suite->auth());
    }

    public function testGetAuthorizationUrlHasRequiredParams(): void
    {
        $suite = new GoogleSuiteManager($this->pdo);

        $url = $suite->getAuthorizationUrl('test-state');

        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('state=test-state', $url);
        $this->assertStringContainsString('userinfo.email', $url);
    }

    public function testAccessTokenThrowsWhenNoTokenStored(): void
    {
        $suite = new GoogleSuiteManager($this->pdo);

        $this->expectException(\ExcelleInsights\GoogleSuite\Exceptions\AuthenticationException::class);
        $suite->accessToken();
    }

    public function testStoredTokenIsReturnedAndNotRefreshedWhenFresh(): void
    {
        $suite = new GoogleSuiteManager($this->pdo);

        $this->pdo->prepare(
            'INSERT INTO api_access_tokens (app, user_id, access_token, expires_at) VALUES (?, ?, ?, ?)'
        )->execute([
            'google_suite',
            '1',
            json_encode([
                'access_token' => 'ya29.valid-token',
                'expires_in'   => 3600,
                'created'      => time(),
            ]),
            time() + 3600,
        ]);

        $this->assertSame('ya29.valid-token', $suite->accessToken());
        $this->assertTrue($suite->isAuthenticated());
    }

    public function testOAuthControllerHandleCallbackRejectsBadState(): void
    {
        $suite = new GoogleSuiteManager($this->pdo);
        $oauth = new OAuthController($suite);

        $result = $oauth->handleCallback(
            ['code' => 'abc', 'state' => 'wrong'],
            'expected-state',
        );

        $this->assertSame('error', $result['status']);
    }

    public function testOAuthControllerHandleCallbackRejectsMissingCode(): void
    {
        $suite = new GoogleSuiteManager($this->pdo);
        $oauth = new OAuthController($suite);

        $result = $oauth->handleCallback([], null);

        $this->assertSame('error', $result['status']);
    }
}