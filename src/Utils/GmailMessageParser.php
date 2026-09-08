<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Utils;

use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\MessagePartBody;
use Google\Service\Gmail\MessagePartHeader;

/**
 * Converts Gmail API Message models into plain PHP arrays suitable for
 * local persistence and for building the Message/Attachment models.
 *
 * Extracted headers: Subject, From, To, Cc, Bcc, In-Reply-To, Message-ID.
 * Body parts: first text/plain and first text/html, decoded from
 * base64url. Attachment metadata: file name, MIME type, size, content id.
 */
class GmailMessageParser
{
    /**
     * @return array{
     *   subject:string, from_address:string, from_name:string,
     *   to_address:string, cc_address:string, bcc_address:string,
     *   reply_to_address:string, message_id:string, in_reply_to:string,
     *   body_plain:string, body_html:string,
     *   attachments:array<int, array{google_id:string, filename:string, mime_type:string, size:int, content_id:string, is_inline:bool}>,
     *   label_ids:array<int, string>, snippet:string, internal_date:int,
     *   size_estimate:int, thread_id:string
     * }
     */
    public static function parse(Message $message): array
    {
        $payload = $message->getPayload();
        $headers = $payload !== null ? self::headers($payload) : [];
        $labels  = $message->getLabelIds() ?? [];

        $attachments = $payload !== null ? self::collectAttachments($payload) : [];
        $bodyParts   = $payload !== null ? self::collectBodies($payload) : ['plain' => '', 'html' => ''];

        return [
            'subject'          => $headers['Subject'] ?? '',
            'from_address'     => self::emailOf($headers['From'] ?? ''),
            'from_name'        => self::nameOf($headers['From'] ?? ''),
            'to_address'       => $headers['To'] ?? '',
            'cc_address'       => $headers['Cc'] ?? '',
            'bcc_address'      => $headers['Bcc'] ?? '',
            'reply_to_address' => $headers['Reply-To'] ?? '',
            'message_id'       => $headers['Message-ID'] ?? '',
            'in_reply_to'      => $headers['In-Reply-To'] ?? '',
            'body_plain'       => $bodyParts['plain'],
            'body_html'        => $bodyParts['html'],
            'attachments'      => $attachments,
            'label_ids'        => $labels,
            'snippet'          => (string) $message->getSnippet(),
            'internal_date'    => (int) $message->getInternalDate(),
            'size_estimate'    => (int) $message->getSizeEstimate(),
            'thread_id'        => (string) $message->getThreadId(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function headers(MessagePart $part): array
    {
        $headers = [];
        foreach ($part->getHeaders() as $header) {
            if ($header instanceof MessagePartHeader) {
                $headers[$header->getName()] = (string) $header->getValue();
            }
        }
        return $headers;
    }

    /**
     * Walk the part tree and grab the first text/plain and text/html bodies.
     *
     * @return array{plain:string, html:string}
     */
    private static function collectBodies(MessagePart $part): array
    {
        $plain = '';
        $html  = '';

        $walk = function (MessagePart $current) use (&$walk, &$plain, &$html): void {
            $mimeType = (string) $current->getMimeType();

            if ($mimeType === 'text/plain' && $plain === '') {
                $plain = self::decodePartBody($current->getBody());
            } elseif ($mimeType === 'text/html' && $html === '') {
                $html = self::decodePartBody($current->getBody());
            }

            if ($plain !== '' && $html !== '') {
                return;
            }

            foreach ($current->getParts() as $child) {
                if ($child instanceof MessagePart) {
                    $walk($child);
                }
            }
        };

        $walk($part);
        return ['plain' => $plain, 'html' => $html];
    }

    /**
     * Collect attachment metadata from the whole part tree.
     *
     * @return array<int, array{google_id:string, filename:string, mime_type:string, size:int, content_id:string, is_inline:bool}>
     */
    private static function collectAttachments(MessagePart $part): array
    {
        $attachments = [];

        $walk = function (MessagePart $current) use (&$walk, &$attachments): void {
            $filename = (string) $current->getFilename();
            $body     = $current->getBody();
            $attachId = $body instanceof MessagePartBody ? (string) $body->getAttachmentId() : '';
            $contentId = '';

            foreach ($current->getHeaders() as $header) {
                if ($header->getName() === 'Content-ID') {
                    $contentId = (string) $header->getValue();
                    break;
                }
            }

            if ($filename !== '' && $attachId !== '') {
                $attachments[] = [
                    'google_id' => $attachId,
                    'filename'  => $filename,
                    'mime_type' => (string) $current->getMimeType(),
                    'size'      => $body instanceof MessagePartBody ? (int) $body->getSize() : 0,
                    'content_id'=> $contentId,
                    'is_inline' => self::hasInlineDisposition($current),
                ];
            }

            foreach ($current->getParts() as $child) {
                if ($child instanceof MessagePart) {
                    $walk($child);
                }
            }
        };

        $walk($part);
        return $attachments;
    }

    private static function hasInlineDisposition(MessagePart $part): bool
    {
        foreach ($part->getHeaders() as $header) {
            if ($header->getName() === 'Content-Disposition' && stripos((string) $header->getValue(), 'inline') !== false) {
                return true;
            }
        }
        return false;
    }

    private static function decodePartBody(?MessagePartBody $body): string
    {
        $data = (string) ($body?->getData() ?? '');
        if ($data === '') {
            return '';
        }
        return self::urlSafeBase64Decode($data);
    }

    /**
     * RFC 4648 URL-safe base64 decode (handles unpadded payloads).
     */
    public static function urlSafeBase64Decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }

    private static function emailOf(string $header): string
    {
        if (preg_match('/<([^>]+)>/', $header, $m)) {
            return $m[1];
        }
        return trim($header);
    }

    private static function nameOf(string $header): string
    {
        if (preg_match('/^(.*)<([^>]+)>$/', trim($header), $m)) {
            return trim($m[1], " \t\n\r\0\x0B\"'");
        }
        return '';
    }
}