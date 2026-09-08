<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Validation;

use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;

/**
 * Validates Google Drive file upload input.
 *
 * Expected array shape:
 * [
 *   'name'     => string (filename on Drive),
 *   'mimeType' => string (optional, defaults to application/octet-stream),
 *   'parentId' => string (optional Drive folder id),
 *   'source'   => file path string | string content | resource,
 *   'description' => string (optional),
 * ]
 */
class FileValidator
{
    /** @return array{name:string, mimeType:string, parentId:?string, source:mixed, description:?string} */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['name']) || !is_string($data['name'])) {
            $errors['name'] = 'A non-empty file name is required';
        }

        $source = $data['source'] ?? null;
        if (is_string($source) && !is_resource($source)) {
            if ($source === '') {
                $errors['source'] = '"source" must not be empty';
            }
        } elseif (!is_resource($source) && !empty($data['path']) && is_string($data['path'])) {
            // Allow "path" as an alternative to "source".
            $source = $data['path'];
            $data['source'] = $source;
        }

        if ($source === null || $source === '') {
            $errors['source'] = '"source" (file path, content string, or resource) is required';
        } elseif (is_string($source) && !file_exists($source) && $data['source'] === $source && str_contains($source, '/')) {
            // A path-looking string that doesn't exist is an error only when the
            // caller intended a file (heuristic). Plain content strings pass.
            if (preg_match('#(^|/)\.\.?(/|$)#', $source) || pathinfo($source, PATHINFO_EXTENSION) !== '') {
                $errors['source'] = "Source file not found: $source";
            }
        }

        if ($errors) {
            throw ValidationException::withErrors($errors);
        }

        return [
            'name'        => (string) $data['name'],
            'mimeType'    => (string) ($data['mimeType'] ?? 'application/octet-stream'),
            'parentId'    => isset($data['parentId']) && $data['parentId'] !== '' ? (string) $data['parentId'] : null,
            'source'      => $source,
            'description' => isset($data['description']) && $data['description'] !== '' ? (string) $data['description'] : null,
        ];
    }
}