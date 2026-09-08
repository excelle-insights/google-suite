<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use PDO;

/**
 * Persistence for Drive folders in the google_folders table.
 */
class FolderRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('folders');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'google_id', 'user_id', 'name', 'parent_google_id', 'path',
            'sync_status', 'created_at', 'updated_at',
        ];
    }

    public function findByGoogleId(string $googleId, int $userId = 1): ?array
    {
        return $this->findOneBy(['google_id' => $googleId, 'user_id' => $userId]);
    }

    public function findByParentId(?string $parentGoogleId, int $userId = 1): array
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM %s
                 WHERE user_id = ? AND parent_google_id %s
                 ORDER BY name ASC
                 LIMIT 500',
                $this->quotedTable(),
                $parentGoogleId === null ? 'IS NULL' : '= ?'
            )
        );
        $stmt->execute($parentGoogleId === null ? [$userId] : [$userId, $parentGoogleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Rebuild the slash-separated path for a folder by walking its parents.
     */
    public function buildPath(string $folderId, int $userId = 1): string
    {
        $segments = [];
        $current  = $folderId;
        $guard    = 0;

        while ($current !== null && $guard < 50) {
            $row = $this->findByGoogleId($current, $userId);
            if ($row === null) {
                break;
            }
            array_unshift($segments, $row['name']);
            $current = $row['parent_google_id'];
            $guard++;
        }

        if ($segments === []) {
            $stmt = $this->pdo->prepare(
                sprintf(
                    'SELECT name FROM %s WHERE google_id = ? AND user_id = ?',
                    $this->quotedTable()
                )
            );
            $stmt->execute([$folderId, $userId]);
            $name = $stmt->fetchColumn();
            return $name === false ? $folderId : (string) $name;
        }

        $path = implode('/', $segments);
        $this->updateByGoogleId($folderId, ['path' => $path], $userId);
        return $path;
    }

    public function updateByGoogleId(string $googleId, array $data, int $userId = 1): int
    {
        $row = $this->findByGoogleId($googleId, $userId);
        if ($row === null) {
            return 0;
        }
        return $this->update((string) $row[$this->primaryKey()], $data);
    }
}