<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Clients;

use Google\Service\Calendar;
use Google\Service\Calendar\CalendarListItem;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

/**
 * Google Calendar API client.
 */
class CalendarClient extends BaseClient
{
    public function __construct(
        \ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory $clientFactory,
        int $userId = 1,
        ?\ExcelleInsights\GoogleSuite\Contracts\LoggerInterface $logger = null,
    ) {
        parent::__construct($clientFactory, $userId, $logger);
    }

    protected function serviceClass(): string
    {
        return Calendar::class;
    }

    // ───────────────────────── Events ────────────────────────────

    /**
     * List events on a calendar.
     *
     * @return Calendar\Events
     */
    public function listEvents(
        string $calendarId,
        string $timeMin = '',
        string $timeMax = '',
        int $maxResults = 100,
        string $pageToken = '',
        string $orderBy = 'startTime',
        string $timeZone = '',
    ): Calendar\Events {
        $params = [
            'maxResults'         => max(1, min(2500, $maxResults)),
            'singleEvents'       => true,
            'includeAttendees'   => true,
        ];
        if ($timeMin !== '') {
            $params['timeMin'] = $timeMin;
        }
        if ($timeMax !== '') {
            $params['timeMax'] = $timeMax;
        }
        if ($pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }
        if ($orderBy !== '') {
            $params['orderBy'] = $orderBy;
        }
        if ($timeZone !== '') {
            $params['timeZone'] = $timeZone;
        }

        return $this->execute(
            fn () => $this->service()->events->listEvents($this->resolveCalendar($calendarId), $params),
            'calendar.events.list',
        );
    }

    /**
     * Fetch a single event.
     */
    public function getEvent(string $calendarId, string $eventId): Event
    {
        return $this->execute(
            fn () => $this->service()->events->get($this->resolveCalendar($calendarId), $eventId),
            'calendar.events.get',
        );
    }

    public function createEvent(string $calendarId, Event $event): Event
    {
        return $this->execute(
            fn () => $this->service()->events->insert(
                $this->resolveCalendar($calendarId),
                $event,
                ['sendUpdates' => 'all', 'supportsAttachments' => true],
            ),
            'calendar.events.insert',
        );
    }

    public function updateEvent(string $calendarId, string $eventId, Event $event): Event
    {
        return $this->execute(
            fn () => $this->service()->events->update(
                $this->resolveCalendar($calendarId),
                $eventId,
                $event,
                ['sendUpdates' => 'all', 'supportsAttachments' => true],
            ),
            'calendar.events.update',
        );
    }

    public function patchEvent(string $calendarId, string $eventId, Event $event): Event
    {
        return $this->execute(
            fn () => $this->service()->events->patch(
                $this->resolveCalendar($calendarId),
                $eventId,
                $event,
                ['sendUpdates' => 'all', 'supportsAttachments' => true],
            ),
            'calendar.events.patch',
        );
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        $this->execute(
            fn () => $this->service()->events->delete(
                $this->resolveCalendar($calendarId),
                $eventId,
                ['sendUpdates' => 'all'],
            ),
            'calendar.events.delete',
        );
    }

    // ───────────────────────── Calendars ─────────────────────────

    /**
     * List calendars the user can access.
     *
     * @return Calendar\CalendarList
     */
    public function listCalendars(): Calendar\CalendarList
    {
        return $this->execute(
            fn () => $this->service()->calendarList->listCalendarList(['minAccessRole' => 'reader']),
            'calendar.calendarList.list',
        );
    }

    public function getCalendar(string $calendarId): Calendar\Calendar
    {
        return $this->execute(
            fn () => $this->service()->calendars->get($this->resolveCalendar($calendarId)),
            'calendar.calendars.get',
        );
    }

    /**
     * Create a secondary calendar.
     */
    public function createCalendar(Calendar\Calendar $calendar): Calendar\Calendar
    {
        return $this->execute(
            fn () => $this->service()->calendars->insert($calendar),
            'calendar.calendars.insert',
        );
    }

    public function deleteCalendar(string $calendarId): void
    {
        $this->execute(
            fn () => $this->service()->calendars->delete($this->resolveCalendar($calendarId)),
            'calendar.calendars.delete',
        );
    }

    /**
     * Utility: build an EventDateTime from a PHP-ish datetime string and tz.
     */
    public static function makeEventDateTime(string $datetime, string $timeZone = ''): EventDateTime
    {
        $dt = new EventDateTime();
        $dt->setDateTime($datetime);
        if ($timeZone !== '') {
            $dt->setTimeZone($timeZone);
        }
        return $dt;
    }

    private function resolveCalendar(string $calendarId): string
    {
        return $calendarId === 'primary' ? 'primary' : $calendarId;
    }
}