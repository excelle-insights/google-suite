<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class Attachment implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $googleId = null,
        public readonly string $messageGoogleId = '',
        public readonly int $userId = 1,
        public readonly ?string $filename = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?string $contentId = null,
        public readonly bool $isInline = false,
        public readonly ?string $storagePath = null,
        public readonly SyncStatus $syncStatus = SyncStatus::Pending,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'google_id'             => $this->googleId,
            'message_google_id'     => $this->messageGoogleId,
            'user_id'               => $this->userId,
            'filename'              => $this->filename,
            'mime_type'             => $this->mimeType,
            'size'                  => $this->size,
            'content_id'            => $this->contentId,
            'is_inline'             => $this->isInline,
            'storage_path'          => $this->storagePath,
            'sync_status'           => $this->syncStatus->value,
            'created_at'            => $this->createdAt,
            'updated_at'            => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id:                 $data['id'] ?? null,
            googleId:           $data['google_id'] ?? null,
            messageGoogleId:    $data['message_google_id'] ?? '',
            userId:             (int) ($data['user_id'] ?? 1),
            filename:           $data['filename'] ?? null,
            mimeType:           $data['mime_type'] ?? null,
            size:               isset($data['size']) ? (int) $data['size'] : null,
            contentId:          $data['content_id'] ?? null,
            isInline:           (bool) ($data['is_inline'] ?? false),
            storagePath:        $data['storage_path'] ?? null,
            syncStatus:         SyncStatus::tryFrom($data['sync_status'] ?? 'pending') ?? SyncStatus::Pending,
            createdAt:          $data['created_at'] ?? null,
            updatedAt:          $data['updated_at'] ?? null,
        );
    }
}