<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Validation;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
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
        } else {
            self::validateRecipients('to', $data['to'], $errors, true);
        }

        if (empty($data['subject']) || !is_string($data['subject'])) {
            $errors['subject'] = 'A non-empty subject is required';
        }

        $body = $data['body'] ?? '';
        $html = $data['html'] ?? '';
        $hasBody = is_string($body) && trim($body) !== '';
        $hasHtml = is_string($html) && trim($html) !== '';
        if (!$hasBody && !$hasHtml) {
            $errors['body'] = 'A plain-text or HTML body is required';
        }

        foreach (['cc', 'bcc'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== []) {
                self::validateRecipients($field, $data[$field], $errors, false);
            }
        }

        if (isset($data['from']) && $data['from'] !== '') {
            self::validateRecipients('from', $data['from'], $errors, true);
        }

        if (!$errors) {
            return [];
        }

        throw ValidationException::withErrors($errors);
    }

    /**
     * Validate one or more recipients in the formats accepted by
     * EmailValidator::normalizeRecipients().
     *
     * @param array<string, string> $errors
     */
    private static function validateRecipients(
        string $field,
        mixed $value,
        array &$errors,
        bool $required,
    ): void {
        $normalized = EmailValidator::normalizeRecipients($value);
        if ($normalized === '') {
            if ($required) {
                $errors[$field] = "The \"$field\" recipient is invalid";
            }
            return;
        }

        foreach (explode(',', $normalized) as $recipient) {
            $email = EmailValidator::extractEmail(trim($recipient));
            if (!EmailValidator::isValid($email)) {
                $errors[$field] = "The \"$field\" recipient contains an invalid email address";
                return;
            }
        }
    }

    /**
     * Validate the attachments array and return it normalized to
     * [['path' => ..., 'filename' => ..., 'mimeType' => ...], ...].
     *
     * @param int|null $maxBytes Maximum size per attachment; null uses config.
     * @return array<int, array{path:string, filename:string, mimeType:string}>
     */
    public static function validateAttachments(array $data, ?int $maxBytes = null): array
    {
        $errors      = [];
        $attachments = $data['attachments'] ?? [];
        $maxBytes  ??= GoogleSuiteConfig::attachmentMaxBytes();

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
            $size = @filesize($path);
            if ($maxBytes > 0 && $size !== false && $size > $maxBytes) {
                $errors[$index] = sprintf(
                    'Attachment exceeds the maximum size of %d bytes',
                    $maxBytes,
                );
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