<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use PDO;

/**
 * Persistence for calendar events in the google_events table.
 */
class EventRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('events');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'google_id', 'calendar_google_id', 'user_id', 'summary',
            'description', 'location', 'start_datetime', 'start_date',
            'start_timezone', 'end_datetime', 'end_date', 'end_timezone',
            'all_day', 'status', 'attendees', 'reminders', 'recurrence',
            'recurring_event_id', 'i_cal_uid', 'html_link', 'organizer',
            'raw_payload', 'sync_status', 'retry_count', 'error_message',
            'synced_at', 'updated_at',
        ];
    }

    public function findByGoogleId(string $googleId, int $userId = 1): ?array
    {
        return $this->findOneBy(['google_id' => $googleId, 'user_id' => $userId]);
    }

    public function findByCalendarId(string $calendarGoogleId, int $userId = 1): array
    {
        return $this->findBy(
            ['calendar_google_id' => $calendarGoogleId, 'user_id' => $userId],
            'start_datetime ASC'
        );
    }

    public function findByDateRange(string $timeMin, string $timeMax, int $userId = 1): array
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM %s
                 WHERE user_id = ?
                   AND start_datetime >= ?
                   AND start_datetime <= ?
                 ORDER BY start_datetime ASC
                 LIMIT 1000',
                $this->quotedTable()
            )
        );
        $stmt->execute([$userId, $timeMin, $timeMax]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateByGoogleId(string $googleId, array $data, int $userId = 1): int
    {
        $row = $this->findByGoogleId($googleId, $userId);
        if ($row === null) {
            return 0;
        }
        return $this->update((string) $row[$this->primaryKey()], $data);
    }
}