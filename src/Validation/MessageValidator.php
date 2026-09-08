<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Validation;

use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;

/**
 * Validates input for sending Gmail messages.
 *
 * Expected array shape:
 * [
 *   'to'          => 'user@example.com' | array of emails/['email'=>, 'name'=>],
 *   'subject'     => string,
 *   'body'        => string (plain text),
 *   'html'        => string (optional HTML body),
 *   'cc'          => optional, same shape as 'to',
 *   'bcc'         => optional, same shape as 'to',
 *   'from'        => optional 'Name <email>',
 *   'attachments' => optional [[path, filename?, mimeType?], ...],
 *   'threadId'    => optional string,
 *   'inReplyTo'   => optional message-id
 * ]
 */
class MessageValidator
{
    /** @return array<string, string> */
    public static function validate(array $data): array
    {
        $errors = [];

        if (!isset($data['to']) || $data['to'] === '' || $data['to'] === []) {
            $errors['to'] = 'The "to" recipient is required';
        }

        if (empty($data['subject']) || !is_string($data['subject'])) {
            $errors['subject'] = 'A non-empty subject is required';
        }

        if (empty($data['body']) || !is_string($data['body'])) {
            $errors['body'] = 'A non-empty body is required';
        }

        if (!$errors) {
            return [];
        }

        throw ValidationException::withErrors($errors);
    }

    /**
     * Validate the attachments array and return it normalized to
     * [['path' => ..., 'filename' => ..., 'mimeType' => ...], ...].
     *
     * @return array<int, array{path:string, filename:string, mimeType:string}>
     */
    public static function validateAttachments(array $data): array
    {
        $errors      = [];
        $attachments = $data['attachments'] ?? [];

        if ($attachments === []) {
            return [];
        }
        if (!is_array($attachments)) {
            throw ValidationException::withErrors(['attachments' => 'Attachments must be an array']);
        }

        $normalized = [];
        foreach ($attachments as $i => $attachment) {
            $index = "attachments[$i]";
            if (!is_array($attachment)) {
                $errors[$index] = 'Each attachment must be an array';
                continue;
            }
            $path = $attachment['path'] ?? '';
            if (!is_string($path) || $path === '') {
                $errors[$index] = 'Attachment "path" is required';
                continue;
            }
            if (!file_exists($path)) {
                $errors[$index] = "Attachment file not found: $path";
                continue;
            }
            $normalized[] = [
                'path'     => $path,
                'filename' => $attachment['filename'] ?? basename($path),
                'mimeType' => self::resolveMimeType($attachment, $path),
            ];
        }

        if ($errors) {
            throw ValidationException::withErrors($errors);
        }

        return $normalized;
    }

    private static function resolveMimeType(array $attachment, string $path): string
    {
        if (!empty($attachment['mimeType'])) {
            return (string) $attachment['mimeType'];
        }
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if ($mime !== false && $mime !== '') {
                return $mime;
            }
        }
        return 'application/octet-stream';
    }
}