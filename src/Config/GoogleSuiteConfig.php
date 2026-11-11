<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Config;

use ExcelleInsights\GoogleSuite\Exceptions\AuthenticationException;
use ExcelleInsights\GoogleSuite\Support\EnvLoader;

/**
 * Static configuration accessor backed by environment variables.
 *
 * Call Config::load() once (or rely on the facade) before reading values.
 */
class GoogleSuiteConfig
{
    private static bool $booted = false;

    public static function boot(?string $root = null): void
    {
        if (self::$booted) {
            return;
        }
        EnvLoader::load($root);
        self::$booted = true;
    }

    public static function appName(): string
    {
        return self::get('GOOGLE_APP_NAME', 'Google Suite Integration');
    }

    public static function clientId(): string
    {
        return self::get('GOOGLE_CLIENT_ID', '');
    }

    public static function clientSecret(): string
    {
        return self::get('GOOGLE_CLIENT_SECRET', '');
    }

    public static function redirectUri(): string
    {
        return self::get('GOOGLE_REDIRECT_URI', '');
    }

    public static function setRedirectUri(string $uri): void
    {
        $_ENV['GOOGLE_REDIRECT_URI'] = $uri;
        $_SERVER['GOOGLE_REDIRECT_URI'] = $uri;
    }

    public static function defaultUserId(): int
    {
        return (int) self::get('GOOGLE_USER_ID', '1');
    }

    /**
     * The Gmail user to operate on, usually "me".
     */
    public static function gmailUser(): string
    {
        return self::get('GMAIL_USER', 'me');
    }

    /**
     * Auth base URL (overridable for testing).
     */
    public static function authBaseUrl(): string
    {
        return self::get('GOOGLE_AUTH_BASE_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
    }

    public static function tokenUrl(): string
    {
        return self::get('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
    }

    public static function tablePrefix(): string
    {
        return self::get('GOOGLE_TABLE_PREFIX', 'google');
    }

    public static function table(string $name): string
    {
        return self::tablePrefix() . '_' . $name;
    }

    /**
     * Default CWD where attachments are persisted when none is given.
     */
    public static function attachmentRoot(): string
    {
        return rtrim(self::get('GOOGLE_ATTACHMENT_ROOT', sys_get_temp_dir() . '/google-suite-attachments'), '/');
    }

    /**
     * Maximum attachment size in bytes. 0 disables the limit.
     */
    public static function attachmentMaxBytes(): int
    {
        return max(0, (int) self::get('GOOGLE_ATTACHMENT_MAX_BYTES', (string) (25 * 1024 * 1024)));
    }

    public static function ensureCredentials(): void
    {
        if (self::clientId() === '' || self::clientSecret() === '') {
            throw new AuthenticationException(
                'GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET must be configured in your environment.'
            );
        }
    }

    private static function get(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : (string) $value;
    }
}