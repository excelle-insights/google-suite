<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Facade;

use ExcelleInsights\GoogleSuite\Auth\GoogleAuthManager;
use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use ExcelleInsights\GoogleSuite\Services\CalendarService;
use ExcelleInsights\GoogleSuite\Services\DriveService;
use ExcelleInsights\GoogleSuite\Services\GmailService;
use PDO;

/**
 * Single public entry point for all Google Suite operations.
 *
 * Composes the auth manager and the three services (Gmail, Calendar,
 * Drive), hiding all wiring from the consumer.
 *
 * Usage:
 *   $suite = new GoogleSuiteManager($pdo);
 *   $suite->getAuthorizationUrl();
 *   $suite->authenticate($_GET['code']);
 *   $suite->gmail()->listMessages('is:unread');
 *   $suite->sendMessage([...]);
 */
class GoogleSuiteManager
{
    private readonly GmailService $gmail;
    private readonly CalendarService $calendar;
    private readonly DriveService $drive;

    private readonly GoogleAuthManager $auth;

    public function __construct(
        private readonly PDO $pdo,
        ?GoogleAuthManager $auth = null,
        ?LoggerInterface $logger = null,
        private readonly string|int $defaultUserId = 1,
    ) {
        GoogleSuiteConfig::boot();
        $this->auth     = $auth ?? new GoogleAuthManager($pdo);
        $this->gmail    = new GmailService($pdo, $this->auth, $logger, $this->defaultUserId);
        $this->calendar = new CalendarService($pdo, $this->auth, $logger, $this->defaultUserId);
        $this->drive    = new DriveService($pdo, $this->auth, $logger, $this->defaultUserId);
    }

    public function auth(): GoogleAuthManager
    {
        return $this->auth;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // ────────────────────── Authentication ───────────────────────

    public function getAuthorizationUrl(?string $state = null): string
    {
        return $this->auth->getAuthorizationUrl($state);
    }

    /**
     * Exchange an authorization code for tokens and persist them.
     */
    public function authenticate(string $authCode, string|int $userId = 1): array
    {
        return $this->auth->exchangeCodeForTokens($authCode, $userId);
    }

    public function refreshToken(string|int $userId = 1): array
    {
        return $this->auth->refreshAccessToken($userId);
    }

    public function isAuthenticated(string|int $userId = 1): bool
    {
        return !$this->auth->isTokenExpired($userId);
    }

    public function accessToken(string|int $userId = 1): string
    {
        return $this->auth->getValidAccessToken($userId);
    }

    public function connectedEmail(string|int $userId = 1): string
    {
        return $this->auth->fetchAccountEmail($userId);
    }

    public function revokeAccess(string|int $userId = 1): void
    {
        $this->auth->revokeToken($userId);
    }

    // ─────────────────── Service accessors ───────────────────────

    public function gmail(): GmailService
    {
        return $this->gmail;
    }

    public function calendar(): CalendarService
    {
        return $this->calendar;
    }

    public function drive(): DriveService
    {
        return $this->drive;
    }

    // ────────────────── Gmail convenience methods ────────────────

    /**
     * List/search messages with Gmail query syntax.
     */
    public function listMessages(string $query = '', int $maxResults = 100, string $pageToken = '', string|int $userId = 1): array
    {
        return $this->gmail->listMessages($query, $maxResults, $pageToken, $userId);
    }

    public function listMessageIds(string $query = '', int $maxResults = 100, string $pageToken = ''): array
    {
        return $this->gmail->listMessageIds($query, $maxResults, $pageToken);
    }

    public function getMessage(string $messageId, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Message
    {
        return $this->gmail->getMessage($messageId, $userId);
    }

    /**
     * Send an email. See GmailService::sendMessage() for the parameter shape.
     */
    public function sendMessage(array $params, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\SentEmail
    {
        return $this->gmail->sendMessage($params, $userId);
    }

    public function createDraft(array $params, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Message
    {
        return $this->gmail->createDraft($params, $userId);
    }

    public function listLabels(string|int $userId = 1): array
    {
        return $this->gmail->listLabels($userId);
    }

    public function createLabel(string $name, string $type = 'user', string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Label
    {
        return $this->gmail->createLabel($name, $type, $userId);
    }

    public function findLabelByName(string $name, string|int $userId = 1): ?\ExcelleInsights\GoogleSuite\Models\Label
    {
        return $this->gmail->findLabelByName($name, $userId);
    }

    public function getOrCreateLabel(string $name, string $type = 'user', string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Label
    {
        return $this->gmail->getOrCreateLabel($name, $type, $userId);
    }

    public function deleteLabel(string $labelId, string|int $userId = 1): void
    {
        $this->gmail->deleteLabel($labelId, $userId);
    }

    public function markAsRead(string $messageId, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Message
    {
        return $this->gmail->markAsRead($messageId, $userId);
    }

    public function markAsUnread(string $messageId, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Message
    {
        return $this->gmail->markAsUnread($messageId, $userId);
    }

    public function trashMessage(string $messageId, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Message
    {
        return $this->gmail->trashMessage($messageId, $userId);
    }

    public function untrashMessage(string $messageId, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Message
    {
        return $this->gmail->untrashMessage($messageId, $userId);
    }

    public function deleteMessage(string $messageId, string|int $userId = 1): void
    {
        $this->gmail->deleteMessage($messageId, $userId);
    }

    public function listThreads(string $query = '', int $maxResults = 100, string|int $userId = 1): array
    {
        return $this->gmail->listThreads($query, $maxResults, $userId);
    }

    public function getThread(string $threadId, string|int $userId = 1): array
    {
        return $this->gmail->getThread($threadId, $userId);
    }

    public function getAttachment(string $messageId, string $attachmentId, string|int $userId = 1): string
    {
        return $this->gmail->getAttachment($messageId, $attachmentId, $userId);
    }

    public function saveAttachment(string $messageId, string $attachmentId, ?string $destinationDir = null, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Attachment
    {
        return $this->gmail->saveAttachment($messageId, $attachmentId, $destinationDir, $userId);
    }

    // ───────────────── Calendar convenience methods ──────────────

    public function listCalendars(string|int $userId = 1): array
    {
        return $this->calendar->listCalendars($userId);
    }

    public function listEvents(string $calendarId = 'primary', string $timeMin = '', string $timeMax = '', int $maxResults = 100, string $pageToken = '', string|int $userId = 1): array
    {
        return $this->calendar->listEvents($calendarId, $timeMin, $timeMax, $maxResults, $pageToken, $userId);
    }

    public function getEvent(string $eventId, string $calendarId = 'primary', string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Event
    {
        return $this->calendar->getEvent($eventId, $calendarId, $userId);
    }

    public function createEvent(array $params, string $calendarId = 'primary', string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Event
    {
        return $this->calendar->createEvent($params, $calendarId, $userId);
    }

    public function updateEvent(string $eventId, array $params, string $calendarId = 'primary', string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Event
    {
        return $this->calendar->updateEvent($eventId, $params, $calendarId, $userId);
    }

    public function deleteEvent(string $eventId, string $calendarId = 'primary', string|int $userId = 1): void
    {
        $this->calendar->deleteEvent($eventId, $calendarId, $userId);
    }

    // ─────────────────── Drive convenience methods ───────────────

    public function uploadFile(array $params, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\File
    {
        return $this->drive->uploadFile($params, $userId);
    }

    public function downloadFile(string $fileId, string|int $userId = 1): string
    {
        return $this->drive->downloadFile($fileId, $userId);
    }

    public function deleteFile(string $fileId, string|int $userId = 1): void
    {
        $this->drive->deleteFile($fileId, $userId);
    }

    public function listFiles(string $query = '', int $maxResults = 100, string $pageToken = '', string|int $userId = 1): array
    {
        return $this->drive->listFiles($query, $maxResults, $pageToken, $userId);
    }

    public function searchFiles(string $query, int $maxResults = 100, string|int $userId = 1): array
    {
        return $this->drive->searchFiles($query, $maxResults, $userId);
    }

    public function createFolder(string $name, ?string $parentId = null, string|int $userId = 1): \ExcelleInsights\GoogleSuite\Models\Folder
    {
        return $this->drive->createFolder($name, $parentId, $userId);
    }

    public function listFolders(?string $parentId = null, string|int $userId = 1): array
    {
        return $this->drive->listFolders($parentId, $userId);
    }

    public function deleteFolder(string $folderId, string|int $userId = 1): void
    {
        $this->drive->deleteFolder($folderId, $userId);
    }
}