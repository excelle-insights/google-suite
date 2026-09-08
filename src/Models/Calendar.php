<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class Calendar implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $googleId = null,
        public readonly int $userId = 1,
        public readonly string $summary = '',
        public readonly ?string $description = null,
        public readonly ?string $location = null,
        public readonly ?string $timeZone = null,
        public readonly bool $isPrimary = false,
        public readonly ?string $accessRole = null,
        public readonly ?string $foregroundColor = null,
        public readonly ?string $backgroundColor = null,
        public readonly bool $selected = false,
        public readonly SyncStatus $syncStatus = SyncStatus::Pending,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'google_id'        => $this->googleId,
            'user_id'          => $this->userId,
            'summary'          => $this->summary,
            'description'      => $this->description,
            'location'         => $this->location,
            'time_zone'        => $this->timeZone,
            'is_primary'       => $this->isPrimary,
            'access_role'      => $this->accessRole,
            'foreground_color' => $this->foregroundColor,
            'background_color' => $this->backgroundColor,
            'selected'         => $this->selected,
            'sync_status'      => $this->syncStatus->value,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id:               $data['id'] ?? null,
            googleId:         $data['google_id'] ?? null,
            userId:           (int) ($data['user_id'] ?? 1),
            summary:          $data['summary'] ?? '',
            description:      $data['description'] ?? null,
            location:         $data['location'] ?? null,
            timeZone:         $data['time_zone'] ?? null,
            isPrimary:        (bool) ($data['is_primary'] ?? false),
            accessRole:       $data['access_role'] ?? null,
            foregroundColor:  $data['foreground_color'] ?? null,
            backgroundColor:  $data['background_color'] ?? null,
            selected:         (bool) ($data['selected'] ?? false),
            syncStatus:       SyncStatus::tryFrom($data['sync_status'] ?? 'pending') ?? SyncStatus::Pending,
            createdAt:        $data['created_at'] ?? null,
            updatedAt:        $data['updated_at'] ?? null,
        );
    }
}