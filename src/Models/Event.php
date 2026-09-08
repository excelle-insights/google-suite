<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class Event implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $googleId = null,
        public readonly ?string $calendarGoogleId = null,
        public readonly int $userId = 1,
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly ?string $location = null,
        public readonly ?string $startDateTime = null,
        public readonly ?string $startDate = null,
        public readonly ?string $startTimeZone = null,
        public readonly ?string $endDateTime = null,
        public readonly ?string $endDate = null,
        public readonly ?string $endTimeZone = null,
        public readonly bool $allDay = false,
        public readonly string $status = 'confirmed',
        /** @var array<int, array<string, mixed>>|null */
        public readonly ?array $attendees = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $reminders = null,
        /** @var array<int, string>|null */
        public readonly ?array $recurrence = null,
        public readonly ?string $recurringEventId = null,
        public readonly ?string $iCalUid = null,
        public readonly ?string $htmlLink = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $organizer = null,
        public readonly ?string $rawPayload = null,
        public readonly SyncStatus $syncStatus = SyncStatus::Pending,
        public readonly ?string $errorMessage = null,
        public readonly ?string $syncedAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'google_id'           => $this->googleId,
            'calendar_google_id'  => $this->calendarGoogleId,
            'user_id'             => $this->userId,
            'summary'             => $this->summary,
            'description'         => $this->description,
            'location'            => $this->location,
            'start_datetime'      => $this->startDateTime,
            'start_date'          => $this->startDate,
            'start_timezone'      => $this->startTimeZone,
            'end_datetime'        => $this->endDateTime,
            'end_date'            => $this->endDate,
            'end_timezone'        => $this->endTimeZone,
            'all_day'             => $this->allDay,
            'status'              => $this->status,
            'attendees'           => $this->attendees,
            'reminders'           => $this->reminders,
            'recurrence'          => $this->recurrence,
            'recurring_event_id'  => $this->recurringEventId,
            'i_cal_uid'           => $this->iCalUid,
            'html_link'           => $this->htmlLink,
            'organizer'           => $this->organizer,
            'raw_payload'         => $this->rawPayload,
            'sync_status'         => $this->syncStatus->value,
            'error_message'       => $this->errorMessage,
            'synced_at'           => $this->syncedAt,
            'created_at'          => $this->createdAt,
            'updated_at'          => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        $decodeJson = static fn (mixed $value): mixed => is_string($value) ? (json_decode($value, true) ?? null) : $value;

        return new static(
            id:                 $data['id'] ?? null,
            googleId:           $data['google_id'] ?? null,
            calendarGoogleId:   $data['calendar_google_id'] ?? null,
            userId:             (int) ($data['user_id'] ?? 1),
            summary:            $data['summary'] ?? null,
            description:        $data['description'] ?? null,
            location:           $data['location'] ?? null,
            startDateTime:      $data['start_datetime'] ?? null,
            startDate:          $data['start_date'] ?? null,
            startTimeZone:      $data['start_timezone'] ?? null,
            endDateTime:        $data['end_datetime'] ?? null,
            endDate:            $data['end_date'] ?? null,
            endTimeZone:        $data['end_timezone'] ?? null,
            allDay:             (bool) ($data['all_day'] ?? false),
            status:             $data['status'] ?? 'confirmed',
            attendees:          $decodeJson($data['attendees'] ?? null),
            reminders:          $decodeJson($data['reminders'] ?? null),
            recurrence:         $decodeJson($data['recurrence'] ?? null),
            recurringEventId:   $data['recurring_event_id'] ?? null,
            iCalUid:            $data['i_cal_uid'] ?? null,
            htmlLink:           $data['html_link'] ?? null,
            organizer:          $decodeJson($data['organizer'] ?? null),
            rawPayload:         $data['raw_payload'] ?? null,
            syncStatus:         SyncStatus::tryFrom($data['sync_status'] ?? 'pending') ?? SyncStatus::Pending,
            errorMessage:       $data['error_message'] ?? null,
            syncedAt:           $data['synced_at'] ?? null,
            createdAt:          $data['created_at'] ?? null,
            updatedAt:          $data['updated_at'] ?? null,
        );
    }
}