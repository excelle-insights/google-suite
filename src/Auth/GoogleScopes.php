<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Auth;

/**
 * Google OAuth2 scope URIs, grouped by service.
 */
final class GoogleScopes
{
    // Gmail
    public const GMAIL_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';
    public const GMAIL_SEND     = 'https://www.googleapis.com/auth/gmail.send';
    public const GMAIL_MODIFY   = 'https://www.googleapis.com/auth/gmail.modify';
    public const GMAIL_LABELS   = 'https://www.googleapis.com/auth/gmail.labels';
    public const GMAIL_COMPOSE  = 'https://www.googleapis.com/auth/gmail.compose';
    public const GMAIL_METADATA = 'https://www.googleapis.com/auth/gmail.metadata';

    // Google Calendar
    public const CALENDAR        = 'https://www.googleapis.com/auth/calendar';
    public const CALENDAR_READONLY = 'https://www.googleapis.com/auth/calendar.readonly';
    public const CALENDAR_EVENTS = 'https://www.googleapis.com/auth/calendar.events';

    // Google Drive
    public const DRIVE         = 'https://www.googleapis.com/auth/drive';
    public const DRIVE_FILE    = 'https://www.googleapis.com/auth/drive.file';
    public const DRIVE_READONLY = 'https://www.googleapis.com/auth/drive.readonly';
    public const DRIVE_METADATA = 'https://www.googleapis.com/auth/drive.metadata';

    // User info
    public const USERINFO_EMAIL   = 'https://www.googleapis.com/auth/userinfo.email';
    public const USERINFO_PROFILE = 'https://www.googleapis.com/auth/userinfo.profile';

    /**
     * Scopes required for full Gmail functionality (the primary service).
     *
     * @return array<int, string>
     */
    public static function gmailFull(): array
    {
        return [
            self::GMAIL_READONLY,
            self::GMAIL_SEND,
            self::GMAIL_MODIFY,
            self::GMAIL_LABELS,
            self::GMAIL_COMPOSE,
        ];
    }

    /**
     * Scopes required for full Calendar functionality.
     *
     * @return array<int, string>
     */
    public static function calendarFull(): array
    {
        return [self::CALENDAR, self::CALENDAR_EVENTS];
    }

    /**
     * Scopes required for full Drive functionality.
     *
     * @return array<int, string>
     */
    public static function driveFull(): array
    {
        return [self::DRIVE];
    }

    /**
     * Everything the package can use by default.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(
            self::gmailFull(),
            self::calendarFull(),
            self::driveFull(),
            [self::USERINFO_EMAIL],
        )));
    }
}