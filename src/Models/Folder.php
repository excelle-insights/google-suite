<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class Folder implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $googleId = null,
        public readonly int $userId = 1,
        public readonly string $name = '',
        public readonly ?string $parentGoogleId = null,
        public readonly ?string $path = null,
        public readonly SyncStatus $syncStatus = SyncStatus::Pending,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'google_id'          => $this->googleId,
            'user_id'            => $this->userId,
            'name'               => $this->name,
            'parent_google_id'   => $this->parentGoogleId,
            'path'               => $this->path,
            'sync_status'        => $this->syncStatus->value,
            'created_at'         => $this->createdAt,
            'updated_at'         => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id:               $data['id'] ?? null,
            googleId:         $data['google_id'] ?? null,
            userId:           (int) ($data['user_id'] ?? 1),
            name:             $data['name'] ?? '',
            parentGoogleId:   $data['parent_google_id'] ?? null,
            path:             $data['path'] ?? null,
            syncStatus:       SyncStatus::tryFrom($data['sync_status'] ?? 'pending') ?? SyncStatus::Pending,
            createdAt:        $data['created_at'] ?? null,
            updatedAt:        $data['updated_at'] ?? null,
        );
    }
}