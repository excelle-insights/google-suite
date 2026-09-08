<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Clients;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

/**
 * Google Drive API client.
 */
class DriveClient extends BaseClient
{
    public function __construct(
        \ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory $clientFactory,
        int $userId = 1,
        ?\ExcelleInsights\GoogleSuite\Contracts\LoggerInterface $logger = null,
    ) {
        parent::__construct($clientFactory, $userId, $logger);
    }

    protected function serviceClass(): string
    {
        return Drive::class;
    }

    // ───────────────────────── Files ─────────────────────────────

    /**
     * Upload a file. The source may be a file path, a resource, or raw
     * string content. A valid resumable upload is used for large files.
     */
    public function uploadFile(
        string $name,
        string $mimeType,
        $source,
        ?string $parentId = null,
        ?string $description = null,
        bool $resumable = false,
    ): DriveFile {
        $metadata = new DriveFile(['name' => $name]);
        if ($mimeType !== '') {
            $metadata->setMimeType($mimeType);
        }
        if ($parentId !== null) {
            $metadata->setParents([$parentId]);
        }
        if ($description !== null) {
            $metadata->setDescription($description);
        }

        $content  = $this->resolveContent($source);
        $mimeType = $mimeType !== '' ? $mimeType : $this->guessMimeType($source, $name);

        $options = [
            'data'       => $content,
            'mimeType'   => $mimeType,
            'uploadType' => $resumable ? 'resumable' : 'multipart',
        ];

        return $this->execute(
            fn () => $this->service()->files->create($metadata, $options),
            'drive.files.create',
        );
    }

    /**
     * Return raw file bytes.
     */
    public function downloadFile(string $fileId): string
    {
        $response = $this->execute(
            fn () => $this->service()->files->get($fileId, ['alt' => 'media']),
            'drive.files.get(media)',
        );
        if (is_string($response)) {
            return $response;
        }
        if (is_object($response) && method_exists($response, 'getBody')) {
            return (string) $response->getBody();
        }
        return (string) $response;
    }

    public function getFile(string $fileId): DriveFile
    {
        return $this->execute(
            fn () => $this->service()->files->get(
                $fileId,
                ['fields' => 'id,name,mimeType,size,parents,description,webViewLink,webContentLink,thumbnailLink,starred,trashed,createdTime,modifiedTime'],
            ),
            'drive.files.get',
        );
    }

    /**
     * List files matching an optional query (Drive query syntax).
     *
     * @return Drive\FileList
     */
    public function listFiles(string $query = '', int $maxResults = 100, string $pageToken = ''): Drive\FileList
    {
        $params = [
            'pageSize'            => max(1, min(1000, $maxResults)),
            'fields'              => 'nextPageToken, files(id,name,mimeType,size,parents,description,webViewLink,webContentLink,thumbnailLink,starred,trashed,createdTime,modifiedTime)',
        ];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }
        return $this->execute(
            fn () => $this->service()->files->listFiles($params),
            'drive.files.list',
        );
    }

    public function deleteFile(string $fileId): void
    {
        $this->execute(
            fn () => $this->service()->files->delete($fileId),
            'drive.files.delete',
        );
    }

    public function updateFile(string $fileId, DriveFile $metadata): DriveFile
    {
        return $this->execute(
            fn () => $this->service()->files->update(
                $fileId,
                $metadata,
                ['fields' => 'id,name,mimeType,size,parents,description,webViewLink,webContentLink,thumbnailLink,starred,trashed,createdTime,modifiedTime'],
            ),
            'drive.files.update',
        );
    }

    public function emptyTrash(): void
    {
        $this->execute(
            fn () => $this->service()->files->emptyTrash(),
            'drive.files.emptyTrash',
        );
    }

    // ───────────────────────── Folders ───────────────────────────

    /**
     * Create a folder (a DriveFile with the folder MIME type).
     */
    public function createFolder(string $name, ?string $parentId = null): DriveFile
    {
        $metadata = new DriveFile([
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if ($parentId !== null) {
            $metadata->setParents([$parentId]);
        }
        return $this->execute(
            fn () => $this->service()->files->create($metadata, ['fields' => 'id,name,mimeType,parents']),
            'drive.folders.create',
        );
    }

    /**
     * List folders, optionally scoped to a parent folder.
     *
     * @return Drive\FileList
     */
    public function listFolders(?string $parentId = null, bool $includeTrashed = false): Drive\FileList
    {
        $query = "mimeType='application/vnd.google-apps.folder'";
        if ($parentId !== null) {
            $query .= " AND '" . addslashes($parentId) . "' in parents";
        }
        if (!$includeTrashed) {
            $query .= ' AND trashed = false';
        }
        return $this->listFiles($query, 1000);
    }

    // ───────────────────────── Helpers ───────────────────────────

    private function resolveContent(mixed $source): mixed
    {
        if (is_resource($source)) {
            return $source;
        }
        if (is_string($source) && file_exists($source) && is_file($source)) {
            return file_get_contents($source);
        }
        return $source;
    }

    private function guessMimeType(mixed $source, string $name): string
    {
        if (is_string($source) && file_exists($source) && function_exists('mime_content_type')) {
            $mime = mime_content_type($source);
            if ($mime !== false) {
                return $mime;
            }
        }
        if (function_exists('finfo_open') && is_string($source) && !str_starts_with($source, "\x00")) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_buffer($finfo, (string) $source);
            finfo_close($finfo);
            if ($mime !== false) {
                return $mime;
            }
        }
        return 'application/octet-stream';
    }
}