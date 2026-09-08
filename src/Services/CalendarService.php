<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Services;

use ExcelleInsights\GoogleSuite\Auth\GoogleAuthManager;
use ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory;
use ExcelleInsights\GoogleSuite\Clients\CalendarClient;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use ExcelleInsights\GoogleSuite\Exceptions\SyncException;
use ExcelleInsights\GoogleSuite\Models\Calendar;
use ExcelleInsights\GoogleSuite\Models\Event;
use ExcelleInsights\GoogleSuite\Models\SyncStatus;
use ExcelleInsights\GoogleSuite\Repositories\CalendarRepository;
use ExcelleInsights\GoogleSuite\Repositories\EventRepository;
use ExcelleInsights\GoogleSuite\Validation\EventValidator;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Calendar as CalendarResource;
use Google\Service\Calendar\Event as EventResource;
use PDO;
use Throwable;

/**
 * Google Calendar operations (secondary service).
 */
class CalendarService extends AbstractService
{
    private CalendarClient $client;
    private CalendarRepository $calendars;
    private EventRepository $events;

    public function __construct(
        private readonly PDO $pdo,
        GoogleAuthManager $authManager,
        ?LoggerInterface $logger = null,
        private readonly string|int $defaultUserId = 1,
    ) {
        parent::__construct($authManager, $logger);

        $factory = new GoogleClientFactory($authManager);
        $this->client    = new CalendarClient($factory, (int) $this->defaultUserId, $this->logger);
        $this->calendars = new CalendarRepository($pdo);
        $this->events    = new EventRepository($pdo);
    }

    public function client(): CalendarClient
    {
        return $this->client;
    }

    // ───────────────────────── Calendars ─────────────────────────

    /**
     * List accessible calendars, cached locally.
     *
     * @return array<int, Calendar>
     */
    public function listCalendars(string|int $userId = 1): array
    {
        $response = $this->client->listCalendars();
        $result   = [];
        foreach ($response->getItems() as $item) {
            $result[] = $this->persistCalendar($item, (int) $userId);
        }
        return $result;
    }

    public function getCalendar(string $calendarId, string|int $userId = 1): Calendar
    {
        $resource = $this->client->getCalendar($calendarId);
        return $this->persistCalendarResource($resource, $calendarId, (int) $userId);
    }

    /**
     * Create a secondary calendar.
     */
    public function createCalendar(array $params, string|int $userId = 1): Calendar
    {
        $resource = new CalendarResource();
        if (!empty($params['summary'])) {
            $resource->setSummary($params['summary']);
        }
        if (!empty($params['description'])) {
            $resource->setDescription($params['description']);
        }
        if (!empty($params['timeZone'])) {
            $resource->setTimeZone($params['timeZone']);
        }

        $created = $this->client->createCalendar($resource);
        return $this->persistCalendarResource($created, (string) $created->getId(), (int) $userId);
    }

    public function deleteCalendar(string $calendarId, string|int $userId = 1): void
    {
        $this->client->deleteCalendar($calendarId);
        $row = $this->calendars->findByGoogleId($calendarId, (int) $userId);
        if ($row !== null) {
            $this->calendars->delete((string) $row['id']);
        }
    }

    // ────────────────────────── Events ───────────────────────────

    /**
     * List events on a calendar within an optional time window.
     *
     * @return array{events:array<int, Event>, nextPageToken:?string}
     */
    public function listEvents(
        string $calendarId = 'primary',
        string $timeMin = '',
        string $timeMax = '',
        int $maxResults = 100,
        string $pageToken = '',
        string|int $userId = 1,
    ): array {
        $response = $this->client->listEvents($calendarId, $timeMin, $timeMax, $maxResults, $pageToken);

        $events = [];
        foreach ($response->getItems() as $event) {
            $events[] = $this->persistEvent($event, $calendarId, (int) $userId);
        }

        return [
            'events'         => $events,
            'nextPageToken'  => $response->nextPageToken,
        ];
    }

    public function getEvent(string $eventId, string $calendarId = 'primary', string|int $userId = 1): Event
    {
        $event = $this->client->getEvent($calendarId, $eventId);
        return $this->persistEvent($event, $calendarId, (int) $userId);
    }

    /**
     * Create an event (persist locally as pending, push to Google, mark synced).
     *
     * @param array<string, mixed> $params
     */
    public function createEvent(array $params, string $calendarId = 'primary', string|int $userId = 1): Event
    {
        EventValidator::validate($params);

        $localId = $this->events->insert($this->mapEventRowPending($params, $calendarId, (int) $userId));

        try {
            $created = $this->client->createEvent($calendarId, $this->buildEventResource($params));
        } catch (Throwable $e) {
            $this->events->markFailed((string) $localId, $e->getMessage());
            throw $e instanceof SyncException
                ? $e
                : new SyncException('Failed to create event: ' . $e->getMessage(), 0, $e);
        }

        $this->events->markSynced((string) $localId, (string) $created->getId());
        return $this->persistEvent($created, $calendarId, (int) $userId);
    }

    /**
     * Update an existing event by its Google ID.
     *
     * @param array<string, mixed> $params Partial fields to patch.
     */
    public function updateEvent(string $eventId, array $params, string $calendarId = 'primary', string|int $userId = 1): Event
    {
        $existing = $this->events->findByGoogleId($eventId, (int) $userId);
        if ($existing === null) {
            $existing = $this->eventToRow($this->client->getEvent($calendarId, $eventId), $calendarId, (int) $userId);
        }

        $merged  = array_merge($existing, $params);
        $occurrenceId = $params['originalStartTime'] ?? null;

        $updated = $this->client->updateEvent(
            $calendarId,
            $eventId,
            $this->buildEventResource($merged, true),
        );

        if ($this->events->findByGoogleId($eventId, (int) $userId) !== null) {
            $this->events->updateByGoogleId($eventId, [
                ...$this->mapEventRowFinal($updated, $calendarId, (int) $userId),
                'sync_status' => 'synced',
            ], (int) $userId);
        }

        return $this->persistEvent($updated, $calendarId, (int) $userId);
    }

    public function deleteEvent(string $eventId, string $calendarId = 'primary', string|int $userId = 1): void
    {
        $this->client->deleteEvent($calendarId, $eventId);
        $row = $this->events->findByGoogleId($eventId, (int) $userId);
        if ($row !== null) {
            $this->events->delete((string) $row['id']);
        }
    }

    // ─────────────────── Local helpers ───────────────────────────

    private function persistCalendar(GoogleCalendar\CalendarListEntry $item, int $userId): Calendar
    {
        $calendarId = (string) $item->getId();
        $this->calendars->upsert([
            'google_id'        => $calendarId,
            'user_id'          => $userId,
            'summary'          => (string) $item->getSummary(),
            'description'      => $item->getDescription(),
            'location'         => $item->getLocation(),
            'time_zone'        => $item->getTimeZone(),
            'is_primary'       => (bool) $item->getPrimary() ? 1 : 0,
            'access_role'      => $item->getAccessRole(),
            'foreground_color' => $item->getForegroundColor(),
            'background_color' => $item->getBackgroundColor(),
            'selected'         => (bool) $item->getSelected() ? 1 : 0,
            'sync_status'      => 'synced',
        ], ['google_id', 'user_id']);

        $row = $this->calendars->findByGoogleId($calendarId, $userId);
        return Calendar::fromArray($row ?? ['google_id' => $calendarId, 'summary' => (string) $item->getSummary()]);
    }

    private function persistCalendarResource(CalendarResource $resource, string $calendarId, int $userId): Calendar
    {
        $this->calendars->upsert([
            'google_id'   => $calendarId,
            'user_id'     => $userId,
            'summary'     => (string) $resource->getSummary(),
            'description' => $resource->getDescription(),
            'time_zone'   => $resource->getTimeZone(),
            'sync_status' => 'synced',
        ], ['google_id', 'user_id']);

        $row = $this->calendars->findByGoogleId($calendarId, $userId);
        return Calendar::fromArray($row ?? ['google_id' => $calendarId, 'summary' => (string) $resource->getSummary()]);
    }

    private function persistEvent(EventResource $event, string $calendarId, int $userId): Event
    {
        if ($event->getId() === null || $event->getId() === '') {
            throw new SyncException('Event returned no id from the Calendar API.');
        }
        $this->events->upsert([
            ...$this->eventToRow($event, $calendarId, $userId),
            'sync_status' => 'synced',
        ], ['google_id', 'user_id']);

        $row = $this->events->findByGoogleId((string) $event->getId(), $userId);
        if ($row === null) {
            $row = $this->eventToRow($event, $calendarId, $userId);
        }
        return Event::fromArray($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventToRow(EventResource $event, string $calendarId, int $userId): array
    {
        $start = $event->getStart();
        $end   = $event->getEnd();

        return [
            'google_id'           => (string) $event->getId(),
            'calendar_google_id'  => $calendarId,
            'user_id'             => $userId,
            'summary'             => $event->getSummary(),
            'description'         => $event->getDescription(),
            'location'            => $event->getLocation(),
            'start_datetime'      => $start?->getDateTime(),
            'start_date'          => $start?->getDate(),
            'start_timezone'      => $start?->getTimeZone(),
            'end_datetime'        => $end?->getDateTime(),
            'end_date'            => $end?->getDate(),
            'end_timezone'        => $end?->getTimeZone(),
            'all_day'             => $start !== null && $start->getDate() !== null ? 1 : 0,
            'status'              => (string) $event->getStatus(),
            'attendees'           => json_encode($this->attendeesToArray($event)),
            'reminders'           => json_encode($event->getReminders()),
            'recurrence'          => json_encode($event->getRecurrence() ?? []),
            'recurring_event_id'  => $event->getRecurringEventId(),
            'i_cal_uid'           => $event->getICalUID(),
            'html_link'           => $event->getHtmlLink(),
            'organizer'           => json_encode($event->getOrganizer()),
            'raw_payload'         => json_encode(['id' => (string) $event->getId()]),
            'sync_status'         => SyncStatus::Synced->value,
            'synced_at'           => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attendeesToArray(EventResource $event): array
    {
        $result = [];
        foreach ($event->getAttendees() ?? [] as $attendee) {
            $result[] = [
                'email'       => $attendee->getEmail(),
                'name'        => $attendee->getDisplayName(),
                'response'    => $attendee->getResponseStatus(),
                'organizer'   => $attendee->getOrganizer(),
                'self'        => $attendee->getSelf(),
            ];
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function mapEventRowPending(array $params, string $calendarId, int $userId): array
    {
        return [
            'calendar_google_id' => $calendarId,
            'user_id'            => $userId,
            'summary'            => $params['summary'] ?? null,
            'description'        => $params['description'] ?? null,
            'location'           => $params['location'] ?? null,
            'start_datetime'     => !empty($params['all_day']) ? null : $params['start'] ?? null,
            'start_date'         => !empty($params['all_day']) ? $params['start'] ?? null : null,
            'start_timezone'     => $params['timeZone'] ?? $params['start_timezone'] ?? null,
            'end_datetime'       => !empty($params['all_day']) ? null : $params['end'] ?? null,
            'end_date'           => !empty($params['all_day']) ? $params['end'] ?? null : null,
            'end_timezone'       => $params['timeZone'] ?? $params['end_timezone'] ?? null,
            'all_day'            => !empty($params['all_day']) ? 1 : 0,
            'status'             => 'confirmed',
            'attendees'          => isset($params['attendees']) ? json_encode($params['attendees']) : null,
            'recurrence'         => isset($params['recurrence']) ? json_encode($params['recurrence']) : null,
            'reminders'          => isset($params['reminders']) ? json_encode($params['reminders']) : null,
            'sync_status'        => SyncStatus::Pending->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEventRowFinal(EventResource $event, string $calendarId, int $userId): array
    {
        $row = $this->eventToRow($event, $calendarId, $userId);
        unset($row['google_id'], $row['sync_status'], $row['synced_at']);
        return $row;
    }

    /**
     * Build a Google\Service\Calendar\Event from user params.
     *
     * @param array<string, mixed> $params
     */
    private function buildEventResource(array $params, bool $partial = false): EventResource
    {
        $event = new EventResource();
        if (!$partial || array_key_exists('summary', $params)) {
            $event->setSummary((string) ($params['summary'] ?? ''));
        }
        if (!$partial || array_key_exists('description', $params)) {
            $event->setDescription($params['description'] ?? null);
        }
        if (!$partial || array_key_exists('location', $params)) {
            $event->setLocation($params['location'] ?? null);
        }
        if (!$partial || array_key_exists('status', $params)) {
            $event->setStatus((string) ($params['status'] ?? 'confirmed'));
        }

        $allDay = !empty($params['all_day']);
        $start  = new GoogleCalendar\EventDateTime();
        $end    = new GoogleCalendar\EventDateTime();
        if ($allDay) {
            $start->setDate((string) $params['start']);
            $end->setDate((string) $params['end']);
        } else {
            $start->setDateTime((string) $params['start']);
            $end->setDateTime((string) $params['end']);
        }
        if (!empty($params['timeZone'])) {
            $start->setTimeZone((string) $params['timeZone']);
            $end->setTimeZone((string) $params['timeZone']);
        }
        if (!$partial || array_key_exists('start', $params)) {
            $event->setStart($start);
        }
        if (!$partial || array_key_exists('end', $params)) {
            $event->setEnd($end);
        }

        if (!empty($params['attendees'])) {
            $attendees = [];
            foreach ($params['attendees'] as $attendee) {
                if (is_string($attendee)) {
                    $attendees[] = new GoogleCalendar\EventAttendee(['email' => $attendee]);
                    continue;
                }
                $attendees[] = new GoogleCalendar\EventAttendee([
                    'email'        => $attendee['email'] ?? '',
                    'displayName'  => $attendee['name'] ?? $attendee['displayName'] ?? null,
                    'responseStatus' => $attendee['response'] ?? $attendee['responseStatus'] ?? null,
                ]);
            }
            $event->setAttendees($attendees);
        }

        if (!empty($params['recurrence']) && is_array($params['recurrence'])) {
            $event->setRecurrence(array_values($params['recurrence']));
        }

        if (!empty($params['reminders']) && is_array($params['reminders'])) {
            $event->setReminders(new GoogleCalendar\EventReminders($params['reminders']));
        }

        return $event;
    }
}