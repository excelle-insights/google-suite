<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use PDO;

/**
 * Persistence for Drive files in the google_files table.
 */
class FileRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('files');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'google_id', 'user_id', 'name', 'mime_type', 'size',
            'folder_google_id', 'parent_ids', 'description', 'web_view_link',
            'web_content_link', 'download_link', 'thumbnail_link', 'starred',
            'trashed', 'created_time', 'modified_time', 'sync_status',
            'retry_count', 'error_message', 'synced_at', 'updated_at',
        ];
    }

    public function findByGoogleId(string $googleId, int $userId = 1): ?array
    {
        return $this->findOneBy(['google_id' => $googleId, 'user_id' => $userId]);
    }

    public function findByFolderId(string $folderGoogleId, int $userId = 1): array
    {
        return $this->findBy(
            ['folder_google_id' => $folderGoogleId, 'user_id' => $userId],
            'modified_time DESC'
        );
    }

    public function searchByName(string $name, int $userId = 1): array
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM %s
                 WHERE user_id = ? AND (name LIKE ? OR description LIKE ?)
                 ORDER BY modified_time DESC
                 LIMIT 500',
                $this->quotedTable()
            )
        );
        $like = '%' . $name . '%';
        $stmt->execute([$userId, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}