<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use PDO;

/**
 * Persistence for sent-email tracking in the google_sent_emails table.
 */
class SentEmailRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('sent_emails');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'message_google_id', 'thread_id', 'user_id', 'to_address',
            'cc_address', 'bcc_address', 'subject', 'body_preview',
            'has_attachments', 'status', 'error_message', 'retry_count',
            'sent_at', 'created_at', 'updated_at',
        ];
    }

    public function findByGoogleMessageId(string $messageGoogleId): ?array
    {
        return $this->findOneBy(['message_google_id' => $messageGoogleId]);
    }

    public function findByStatus(string $status, int $userId = 1): array
    {
        return $this->findBy(['status' => $status, 'user_id' => $userId], 'created_at DESC');
    }

    public function markSent(string|int $id, string $messageGoogleId, ?string $threadId = null): int
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'UPDATE %s
                 SET message_google_id = ?, thread_id = ?, status = ?, error_message = NULL, sent_at = NOW()
                 WHERE %s = ?',
                $this->quotedTable(),
                $this->primaryKey()
            )
        );
        $stmt->execute([$messageGoogleId, $threadId, 'sent', $id]);
        return $stmt->rowCount();
    }
}