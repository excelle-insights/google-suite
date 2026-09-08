<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class File implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $googleId = null,
        public readonly int $userId = 1,
        public readonly string $name = '',
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?string $folderGoogleId = null,
        /** @var array<int, string>|null */
        public readonly ?array $parentIds = null,
        public readonly ?string $description = null,
        public readonly ?string $webViewLink = null,
        public readonly ?string $webContentLink = null,
        public readonly ?string $downloadLink = null,
        public readonly ?string $thumbnailLink = null,
        public readonly bool $starred = false,
        public readonly bool $trashed = false,
        public readonly ?string $createdTime = null,
        public readonly ?string $modifiedTime = null,
        public readonly SyncStatus $syncStatus = SyncStatus::Pending,
        public readonly ?string $errorMessage = null,
        public readonly ?string $syncedAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'google_id'        => $this->googleId,
            'user_id'          => $this->userId,
            'name'             => $this->name,
            'mime_type'        => $this->mimeType,
            'size'             => $this->size,
            'folder_google_id' => $this->folderGoogleId,
            'parent_ids'       => $this->parentIds,
            'description'      => $this->description,
            'web_view_link'    => $this->webViewLink,
            'web_content_link' => $this->webContentLink,
            'download_link'    => $this->downloadLink,
            'thumbnail_link'   => $this->thumbnailLink,
            'starred'          => $this->starred,
            'trashed'          => $this->trashed,
            'created_time'     => $this->createdTime,
            'modified_time'    => $this->modifiedTime,
            'sync_status'      => $this->syncStatus->value,
            'error_message'    => $this->errorMessage,
            'synced_at'        => $this->syncedAt,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        $parentIds = $data['parent_ids'] ?? null;
        if (is_string($parentIds)) {
            $parentIds = json_decode($parentIds, true) ?: [];
        }

        return new static(
            id:               $data['id'] ?? null,
            googleId:         $data['google_id'] ?? null,
            userId:           (int) ($data['user_id'] ?? 1),
            name:             $data['name'] ?? '',
            mimeType:         $data['mime_type'] ?? null,
            size:             isset($data['size']) ? (int) $data['size'] : null,
            folderGoogleId:   $data['folder_google_id'] ?? null,
            parentIds:        $parentIds,
            description:      $data['description'] ?? null,
            webViewLink:      $data['web_view_link'] ?? null,
            webContentLink:   $data['web_content_link'] ?? null,
            downloadLink:     $data['download_link'] ?? null,
            thumbnailLink:    $data['thumbnail_link'] ?? null,
            starred:          (bool) ($data['starred'] ?? false),
            trashed:          (bool) ($data['trashed'] ?? false),
            createdTime:      $data['created_time'] ?? null,
            modifiedTime:     $data['modified_time'] ?? null,
            syncStatus:       SyncStatus::tryFrom($data['sync_status'] ?? 'pending') ?? SyncStatus::Pending,
            errorMessage:     $data['error_message'] ?? null,
            syncedAt:         $data['synced_at'] ?? null,
            createdAt:        $data['created_at'] ?? null,
            updatedAt:        $data['updated_at'] ?? null,
        );
    }
}