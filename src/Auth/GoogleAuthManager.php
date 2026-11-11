<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Auth;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use ExcelleInsights\GoogleSuite\Exceptions\AuthenticationException;
use ExcelleInsights\GoogleSuite\Repositories\AccessTokenRepository;
use Google\Client;
use Google\Service\Oauth2;
use PDO;

/**
 * OAuth2 token lifecycle for Google APIs.
 *
 * Responsibilities:
 *  - Build a configured Google\Client for the OAuth redirect.
 *  - Exchange an authorization code for access + refresh tokens.
 *  - Persist tokens in api_access_tokens (JSON payload).
 *  - Return a valid access token, refreshing automatically when expired.
 */
class GoogleAuthManager
{
    private readonly AccessTokenRepository $tokenRepo;

    /**
     * @param array<int, string>|null $scopes Scopes to request; defaults to GoogleScopes::all().
     */
    public function __construct(
        private readonly PDO $pdo,
        ?AccessTokenRepository $tokenRepo = null,
        private ?array $scopes = null,
        private ?string $app = null,
        private ?string $clientSecretPath = null,
    ) {
        $this->tokenRepo = $tokenRepo ?? new AccessTokenRepository($pdo);
        $this->scopes    = $scopes ?? GoogleScopes::all();
        $this->app       = $app ?? AccessTokenRepository::APP;
    }

    public function app(): string
    {
        return $this->app;
    }

    /**
     * Build a Google\Client wired to this package's credentials.
     */
    public function createClient(): Client
    {
        GoogleSuiteConfig::ensureCredentials();

        $client = new Client();
        $client->setApplicationName(GoogleSuiteConfig::appName());

        if ($this->clientSecretPath !== null && is_file($this->clientSecretPath)) {
            $client->setAuthConfig($this->clientSecretPath);
        } else {
            $client->setClientId(GoogleSuiteConfig::clientId());
            $client->setClientSecret(GoogleSuiteConfig::clientSecret());
        }

        $client->setScopes($this->scopes);
        $client->setRedirectUri(GoogleSuiteConfig::redirectUri());
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt('consent');

        return $client;
    }

    /**
     * Generate the authorization URL the user should visit.
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $client = $this->createClient();
        if ($state !== null && $state !== '') {
            $client->setState($state);
        }
        return $client->createAuthUrl();
    }

    /**
     * Validate a state token against the one issued during redirect.
     */
    public function verifyState(?string $expected, ?string $actual): bool
    {
        return $expected !== null
            && $actual !== null
            && hash_equals($expected, $actual);
    }

    /**
     * Exchange an authorization code for tokens and persist them.
     *
     * @return array<string, mixed> Full token payload (access_token, refresh_token, expires_in, scope, ...).
     */
    public function exchangeCodeForTokens(string $authCode, string|int $userId = 1): array
    {
        $client = $this->createClient();
        $token  = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($token['error'])) {
            throw new AuthenticationException(
                'Failed to exchange authorization code: ' . ($token['error_description'] ?? $token['error'])
            );
        }

        $this->persistToken($userId, $token);
        return $token;
    }

    /**
     * Return a valid access token, refreshing and persisting when needed.
     *
     * @throws AuthenticationException when no token exists or refresh fails.
     */
    public function getValidAccessToken(string|int $userId = 1): string
    {
        $record = $this->tokenRepo->findToken($this->app, $userId);
        if ($record === null || $record['access_token'] === null || $record['access_token'] === '') {
            throw new AuthenticationException(
                'No stored token for user ' . $userId . '. Re-authenticate first.'
            );
        }

        $payload = json_decode($record['access_token'], true);
        if (!is_array($payload) || empty($payload['access_token'])) {
            throw new AuthenticationException('Stored token payload is malformed. Re-authenticate.');
        }

        if ($this->tokenNeedsRefresh($payload, $record)) {
            $payload = $this->refreshStoredToken($userId, $payload);
        }

        return (string) $payload['access_token'];
    }

    /**
     * Refresh the stored token and persist the result.
     *
     * @return array<string, mixed> The refreshed token payload.
     */
    public function refreshAccessToken(string|int $userId = 1): array
    {
        $record = $this->tokenRepo->findToken($this->app, $userId);
        if ($record === null) {
            throw new AuthenticationException("No token to refresh for user $userId.");
        }

        $payload = json_decode($record['access_token'], true);
        if (!is_array($payload)) {
            throw new AuthenticationException('Stored token payload is malformed.');
        }

        return $this->refreshStoredToken($userId, $payload);
    }

    /**
     * Whether the stored token is expired (or close to expiry).
     */
    public function isTokenExpired(string|int $userId = 1): bool
    {
        $record = $this->tokenRepo->findToken($this->app, $userId);
        if ($record === null) {
            return true;
        }
        $payload = json_decode($record['access_token'], true);
        if (!is_array($payload) || empty($payload['access_token'])) {
            return true;
        }
        return $this->tokenNeedsRefresh($payload, $record);
    }

    /**
     * Revoke the stored token on Google and delete the local row.
     */
    public function revokeToken(string|int $userId = 1): void
    {
        try {
            $payload = $this->readPayload($userId);
            $token   = $payload['refresh_token'] ?? $payload['access_token'] ?? null;
            $client  = $this->createClient();
            if (is_string($token) && $token !== '') {
                // Pass the refresh token explicitly so Google revokes the
                // grant, not just the short-lived access token.
                $client->revokeToken($token);
            }
        } catch (\Throwable $e) {
            // Log and continue so local cleanup always happens.
            error_log('GoogleSuite revokeToken: ' . $e->getMessage());
        } finally {
            $this->tokenRepo->deleteToken($this->app, $userId);
        }
    }

    /**
     * Identify the connected Google account (email).
     */
    public function fetchAccountEmail(string|int $userId = 1): string
    {
        $accessToken = $this->getValidAccessToken($userId);
        $client      = $this->createClient();
        $client->setAccessToken($accessToken);
        $oauth = new Oauth2($client);
        return (string) $oauth->userinfo->get()->email;
    }

    private function refreshStoredToken(string|int $userId, array $payload): array
    {
        if (empty($payload['refresh_token'])) {
            throw new AuthenticationException(
                'Access token expired and no refresh_token is stored. Re-authenticate.'
            );
        }

        $client = $this->createClient();
        $fresh  = $client->refreshToken((string) $payload['refresh_token']);

        if (isset($fresh['error'])) {
            throw new AuthenticationException(
                'Token refresh failed: ' . ($fresh['error_description'] ?? $fresh['error'])
            );
        }

        // Google does not echo refresh_token on refresh -- preserve the old one.
        $fresh['refresh_token'] = $payload['refresh_token'];
        $this->persistToken($userId, $fresh);

        return $fresh;
    }

    private function persistToken(string|int $userId, array $token): void
    {
        $expiresAt = null;
        if (!empty($token['expires_in'])) {
            $expiresAt = time() + (int) $token['expires_in'];
        }
        // The client secret belongs in configuration, never in the token row.
        $this->tokenRepo->saveToken(
            $this->app,
            $userId,
            $token,
            $expiresAt,
            GoogleSuiteConfig::clientId(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $record
     */
    private function tokenNeedsRefresh(array $payload, array $record): bool
    {
        $expiresAt = $record['expires_at'] ?? null;
        if ($expiresAt !== null) {
            return ((int) $expiresAt) <= time() + 60;
        }
        if (!empty($payload['expires_at'])) {
            return ((int) $payload['expires_at']) <= time() + 60;
        }
        // No expiry recorded -- refresh conservatively.
        return !empty($payload['refresh_token']);
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(string|int $userId): array
    {
        $record = $this->tokenRepo->findToken($this->app, $userId);
        if ($record === null) {
            return [];
        }
        $payload = json_decode($record['access_token'], true);
        return is_array($payload) ? $payload : [];
    }
}