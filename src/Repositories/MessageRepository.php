<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use PDO;

/**
 * Persistence for Gmail messages in the google_messages table.
 */
class MessageRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('messages');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'google_id', 'thread_id', 'user_id', 'subject', 'from_address',
            'from_name', 'to_address', 'cc_address', 'bcc_address',
            'reply_to_address', 'snippet', 'body_plain', 'body_html',
            'label_ids', 'is_read', 'is_starred', 'is_draft', 'is_trashed',
            'has_attachments', 'internal_date', 'size_estimate', 'raw_payload',
            'sync_status', 'retry_count', 'error_message', 'last_attempt_at',
            'synced_at', 'updated_at',
        ];
    }

    public function findByGoogleId(string $googleId, int $userId = 1): ?array
    {
        return $this->findOneBy(['google_id' => $googleId, 'user_id' => $userId]);
    }

    public function findByThreadId(string $threadId, int $userId = 1): array
    {
        return $this->findBy(
            ['thread_id' => $threadId, 'user_id' => $userId],
            'internal_date ASC'
        );
    }

    public function findByLabel(string $labelId, int $userId = 1): array
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM %s
                 WHERE user_id = ? AND label_ids LIKE ?
                 ORDER BY internal_date DESC
                 LIMIT 500',
                $this->quotedTable()
            )
        );
        $stmt->execute([$userId, '%"' . $labelId . '"%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $term, int $userId = 1): array
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM %s
                 WHERE user_id = ?
                   AND (subject LIKE ? OR from_address LIKE ? OR snippet LIKE ? OR body_plain LIKE ?)
                 ORDER BY internal_date DESC
                 LIMIT 500',
                $this->quotedTable()
            )
        );
        $like = '%' . $term . '%';
        $stmt->execute([$userId, $like, $like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateByGoogleId(string $googleId, array $data, int $userId = 1): int
    {
        $row = $this->findByGoogleId($googleId, $userId);
        if ($row === null) {
            return 0;
        }
        return $this->update((string) $row[$this->primaryKey()], $data);
    }

    public function deleteByGoogleId(string $googleId, int $userId = 1): int
    {
        $row = $this->findByGoogleId($googleId, $userId);
        if ($row === null) {
            return 0;
        }
        return $this->delete((string) $row[$this->primaryKey()]);
    }
}