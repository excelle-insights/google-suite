<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Controller;

use ExcelleInsights\GoogleSuite\Exceptions\AuthenticationException;
use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

/**
 * Web-facing OAuth flow controller.
 *
 *   $suite = new GoogleSuiteManager($pdo);
 *   $oauth = new OAuthController($suite);
 *   $oauth->redirectToGoogle();          // kick the user to Google's consent screen
 *   echo $oauth->handleCallback();       // on the redirect_uri script
 */
class OAuthController
{
    public function __construct(
        private readonly GoogleSuiteManager $manager,
        private readonly string|int $userId = 1,
        private readonly bool $requireState = true,
    ) {}

    /**
     * Build the Google authorization URL with a fresh CSRF state token.
     * Callers normally use this to render a "Connect Google" link.
     */
    public function getAuthUrl(?string $state = null): string
    {
        if ($state === null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $state = bin2hex(random_bytes(16));
                $_SESSION['google_suite_oauth_state'] = $state;
            } elseif ($this->requireState) {
                throw new AuthenticationException(
                    'An active PHP session is required to issue an OAuth state token. '
                    . 'Start a session, or construct OAuthController with requireState: false for CLI use.'
                );
            }
        }
        return $this->manager->getAuthorizationUrl($state);
    }

    /**
     * Redirect the browser to Google's consent screen and exit.
     */
    public function redirectToGoogle(): never
    {
        header('Location: ' . $this->getAuthUrl());
        exit;
    }

    /**
     * Handle a callback from Google.
     *
     * Verifies the CSRF state, exchanges the code, and stores the token.
     * Returns:
     *   - array{status:'error', message, error?} on failure
     *   - array{status:'success', email?, user_id} on success
     *
     * @param array<string, string> $query GET params (defaults to $_GET).
     * @param string|null $expectedState State token that was issued earlier.
     */
    public function handleCallback(?array $query = null, ?string $expectedState = null): array
    {
        $query = $query ?? $_GET;

        if (isset($query['error'])) {
            return [
                'status'  => 'error',
                'message' => 'Authorization failed: ' . htmlspecialchars((string) $query['error'], ENT_QUOTES, 'UTF-8'),
                'error'   => (string) $query['error'],
            ];
        }

        if (empty($query['code'])) {
            return [
                'status'  => 'error',
                'message' => 'Missing authorization code.',
            ];
        }

        // CSRF verification. Web flows must always have a state token.
        $expectedState = $expectedState ?? $this->sessionState();
        if ($expectedState === null) {
            if ($this->requireState) {
                return [
                    'status'  => 'error',
                    'message' => 'Missing OAuth state. Start a new authorization flow.',
                ];
            }
        } else {
            $actual = $query['state'] ?? '';
            if (!$this->manager->auth()->verifyState($expectedState, $actual)) {
                return [
                    'status'  => 'error',
                    'message' => 'Invalid state parameter. Possible CSRF attempt.',
                ];
            }
            $this->clearSessionState();
        }

        try {
            $this->manager->authenticate((string) $query['code'], $this->userId);
        } catch (AuthenticationException $e) {
            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }

        $email = null;
        try {
            $email = $this->manager->connectedEmail($this->userId);
        } catch (\Throwable) {
            // Email lookup is best-effort.
        }

        return [
            'status'  => 'success',
            'email'   => $email,
            'user_id' => $this->userId,
        ];
    }

    private function sessionState(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['google_suite_oauth_state'])) {
            return null;
        }
        return (string) $_SESSION['google_suite_oauth_state'];
    }

    private function clearSessionState(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['google_suite_oauth_state']);
        }
    }
}