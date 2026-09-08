<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;

/**
 * Persistence for calendars in the google_calendars table.
 */
class CalendarRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('calendars');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'google_id', 'user_id', 'summary', 'description', 'location',
            'time_zone', 'is_primary', 'access_role', 'foreground_color',
            'background_color', 'selected', 'sync_status',
            'created_at', 'updated_at',
        ];
    }

    public function findByGoogleId(string $googleId, int $userId = 1): ?array
    {
        return $this->findOneBy(['google_id' => $googleId, 'user_id' => $userId]);
    }

    public function findPrimary(int $userId = 1): ?array
    {
        return $this->findOneBy(['is_primary' => 1, 'user_id' => $userId]);
    }

    /**
     * Upsert a batch of calendars returned by the Calendar List API.
     *
     * @param array<int, array<string, mixed>> $calendars
     */
    public function syncCalendars(int $userId, array $calendars): void
    {
        foreach ($calendars as $calendar) {
            $googleId = $calendar['google_id'] ?? $calendar['id'] ?? null;
            if ($googleId === null) {
                continue;
            }
            $this->upsert(array_merge([
                'google_id' => $googleId,
                'user_id'   => $userId,
                'sync_status' => 'synced',
            ], $calendar), ['google_id', 'user_id']);
        }
    }
}