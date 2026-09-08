<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Services;

use ExcelleInsights\GoogleSuite\Auth\GoogleAuthManager;
use ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory;
use ExcelleInsights\GoogleSuite\Clients\DriveClient;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use ExcelleInsights\GoogleSuite\Exceptions\ResourceNotFoundException;
use ExcelleInsights\GoogleSuite\Exceptions\SyncException;
use ExcelleInsights\GoogleSuite\Models\File;
use ExcelleInsights\GoogleSuite\Models\Folder;
use ExcelleInsights\GoogleSuite\Models\SyncStatus;
use ExcelleInsights\GoogleSuite\Repositories\FileRepository;
use ExcelleInsights\GoogleSuite\Repositories\FolderRepository;
use ExcelleInsights\GoogleSuite\Validation\FileValidator;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use PDO;
use Throwable;

/**
 * Google Drive operations (secondary service).
 */
class DriveService extends AbstractService
{
    private DriveClient $client;
    private FileRepository $files;
    private FolderRepository $folders;

    public function __construct(
        private readonly PDO $pdo,
        GoogleAuthManager $authManager,
        ?LoggerInterface $logger = null,
        private readonly string|int $defaultUserId = 1,
    ) {
        parent::__construct($authManager, $logger);

        $factory = new GoogleClientFactory($authManager);
        $this->client   = new DriveClient($factory, (int) $this->defaultUserId, $this->logger);
        $this->files    = new FileRepository($pdo);
        $this->folders  = new FolderRepository($pdo);
    }

    public function client(): DriveClient
    {
        return $this->client;
    }

    // ─────────────────────────── Files ───────────────────────────

    /**
     * Upload a file (persist locally, push to Drive, mark synced).
     *
     * @param array{name:string, mimeType?:string, parentId?:string, path?:string, source?:string|resource, description?:string} $params
     */
    public function uploadFile(array $params, string|int $userId = 1): File
    {
        $validated = FileValidator::validate($params);

        $localId = $this->files->insert([
            'user_id'           => (int) $userId,
            'name'              => $validated['name'],
            'mime_type'         => $validated['mimeType'],
            'folder_google_id'  => $validated['parentId'],
            'description'       => $validated['description'],
            'sync_status'       => SyncStatus::Pending->value,
        ]);

        try {
            $created = $this->client->uploadFile(
                $validated['name'],
                $validated['mimeType'],
                $validated['source'],
                $validated['parentId'],
                $validated['description'],
            );
        } catch (Throwable $e) {
            $this->files->markFailed((string) $localId, $e->getMessage());
            throw $e instanceof SyncException
                ? $e
                : new SyncException('Failed to upload file: ' . $e->getMessage(), 0, $e);
        }

        $this->files->markSynced((string) $localId, (string) $created->getId());
        return $this->persistFile($created, (int) $userId);
    }

    /**
     * Download a file's bytes by Drive ID.
     */
    public function downloadFile(string $fileId, string|int $userId = 1): string
    {
        return $this->client->downloadFile($fileId);
    }

    public function deleteFile(string $fileId, string|int $userId = 1): void
    {
        $this->client->deleteFile($fileId);
        $row = $this->files->findByGoogleId($fileId, (int) $userId);
        if ($row !== null) {
            $this->files->delete((string) $row['id']);
        }
    }

    public function getFile(string $fileId, string|int $userId = 1): File
    {
        return $this->persistFile($this->client->getFile($fileId), (int) $userId);
    }

    /**
     * List files, optionally matching a Drive query.
     *
     * @return array{files:array<int, File>, nextPageToken:?string}
     */
    public function listFiles(string $query = '', int $maxResults = 100, string $pageToken = '', string|int $userId = 1): array
    {
        $response = $this->client->listFiles($query, $maxResults, $pageToken);

        $files = [];
        foreach ($response->getFiles() as $file) {
            $files[] = $this->persistFile($file, (int) $userId);
        }
        return [
            'files'          => $files,
            'nextPageToken'  => $response->nextPageToken,
        ];
    }

    /**
     * Search files by Drive query syntax (e.g. "name contains 'report'").
     *
     * @return array<int, File>
     */
    public function searchFiles(string $query, int $maxResults = 100, string|int $userId = 1): array
    {
        return $this->listFiles($query, $maxResults, '', $userId)['files'];
    }

    // ────────────────────────── Folders ──────────────────────────

    /**
     * Create a Drive folder (persist locally, create on Drive, mark synced).
     */
    public function createFolder(string $name, ?string $parentId = null, string|int $userId = 1): Folder
    {
        $name = trim($name);
        if ($name === '') {
            throw new SyncException('Folder name must not be empty.');
        }

        $localId = $this->folders->insert([
            'user_id'           => (int) $userId,
            'name'              => $name,
            'parent_google_id'  => $parentId,
            'sync_status'       => SyncStatus::Pending->value,
        ]);

        try {
            $created = $this->client->createFolder($name, $parentId);
        } catch (Throwable $e) {
            $this->folders->markFailed((string) $localId, $e->getMessage());
            throw $e instanceof SyncException
                ? $e
                : new SyncException('Failed to create folder: ' . $e->getMessage(), 0, $e);
        }

        $this->folders->markSynced((string) $localId, (string) $created->getId());
        return $this->persistFolder($created, (int) $userId);
    }

    /**
     * List folders, optionally within a parent. Uses the local table when
     * the folder tree has already been cached; otherwise queries Drive.
     *
     * @return array<int, Folder>
     */
    public function listFolders(?string $parentId = null, string|int $userId = 1): array
    {
        $cached = $this->folders->findByParentId($parentId, (int) $userId);
        if ($cached !== []) {
            return array_map(fn (array $row) => Folder::fromArray($row), $cached);
        }

        $response = $this->client->listFolders($parentId);
        $folders  = [];
        foreach ($response->getFiles() as $file) {
            $folders[] = $this->persistFolder($file, (int) $userId);
        }
        return $folders;
    }

    public function deleteFolder(string $folderId, string|int $userId = 1): void
    {
        $this->client->deleteFile($folderId);
        $row = $this->folders->findByGoogleId($folderId, (int) $userId);
        if ($row !== null) {
            $this->folders->delete((string) $row['id']);
        }
    }

    /**
     * Resolve the slash-separated path of a folder.
     */
    public function folderPath(string $folderId, string|int $userId = 1): string
    {
        return $this->folders->buildPath($folderId, (int) $userId);
    }

    // ─────────────────── Local helpers ───────────────────────────

    private function persistFile(DriveFile $file, int $userId): File
    {
        $fileId = (string) $file->getId();
        if ($fileId === '') {
            throw new SyncException('Drive returned a file without an id.');
        }
        $this->files->upsert([
            'google_id'        => $fileId,
            'user_id'          => $userId,
            'name'             => (string) $file->getName(),
            'mime_type'        => $file->getMimeType(),
            'size'             => $file->getSize(),
            'folder_google_id' => $file->getParents()[0] ?? null,
            'parent_ids'       => json_encode($file->getParents() ?? []),
            'description'      => $file->getDescription(),
            'web_view_link'    => $file->getWebViewLink(),
            'web_content_link' => $file->getWebContentLink(),
            'thumbnail_link'   => $file->getThumbnailLink(),
            'starred'          => (bool) $file->getStarred() ? 1 : 0,
            'trashed'          => (bool) $file->getTrashed() ? 1 : 0,
            'created_time'     => self::toDateTime($file->getCreatedTime()),
            'modified_time'    => self::toDateTime($file->getModifiedTime()),
            'sync_status'      => SyncStatus::Synced->value,
        ], ['google_id', 'user_id']);

        $row = $this->files->findByGoogleId($fileId, $userId);
        return File::fromArray($row ?? ['google_id' => $fileId, 'name' => (string) $file->getName()]);
    }

    private function persistFolder(DriveFile $file, int $userId): Folder
    {
        $folderId = (string) $file->getId();
        $parents  = $file->getParents() ?? [];
        $parentId = $parents !== [] ? $parents[0] : null;

        $this->folders->upsert([
            'google_id'        => $folderId,
            'user_id'          => $userId,
            'name'             => (string) $file->getName(),
            'parent_google_id' => $parentId,
            'sync_status'      => SyncStatus::Synced->value,
        ], ['google_id', 'user_id']);

        $row = $this->folders->findByGoogleId($folderId, $userId);
        if ($row !== null) {
            $path = $this->folders->buildPath($folderId, $userId);
            $this->folders->updateByGoogleId($folderId, ['path' => $path], $userId);
            $row = $this->folders->findByGoogleId($folderId, $userId);
        }
        return Folder::fromArray($row ?? ['google_id' => $folderId, 'name' => (string) $file->getName()]);
    }

    private static function toDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        // Google API often returns \DateTimeInterface.
        return $value->format('Y-m-d H:i:s');
    }
}