<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;

/**
 * Persistence for message attachments in the google_attachments table.
 */
class AttachmentRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('attachments');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'google_id', 'message_google_id', 'user_id', 'filename',
            'mime_type', 'size', 'content_id', 'is_inline', 'storage_path',
            'sync_status', 'created_at', 'updated_at',
        ];
    }

    public function findByMessageId(string $messageGoogleId, int $userId = 1): array
    {
        return $this->findBy(
            ['message_google_id' => $messageGoogleId, 'user_id' => $userId],
            'id ASC'
        );
    }

    public function findByGoogleId(string $googleId): ?array
    {
        return $this->findOneBy(['google_id' => $googleId]);
    }

    /**
     * Assure an attachment row exists for a message (idempotent upsert
     * keyed on google attachment id + message + user).
     */
    public function upsertForMessage(array $data, int $userId): void
    {
        $this->upsert(array_merge(['user_id' => $userId], $data), ['google_id', 'message_google_id', 'user_id']);
    }
}