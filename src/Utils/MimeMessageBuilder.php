<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Utils;

use ExcelleInsights\GoogleSuite\Validation\EmailValidator;

/**
 * Builds RFC 2822 MIME messages for the Gmail API.
 *
 * Produces a Unicode-safe multipart/alternative message (text/plain and
 * optional text/html) with optional attachments as multipart/mixed parts.
 */
class MimeMessageBuilder
{
    private string $boundary;

    /** @var array<int, array<string, string>> */
    private array $attachments = [];

    public function __construct(
        private readonly mixed $to,
        private readonly string $subject,
        private readonly string $body,
        private readonly string $html = '',
        private readonly string $from = '',
        private readonly mixed $cc = [],
        private readonly mixed $bcc = [],
        private readonly string $messageId = '',
        private readonly array $references = [],
        private readonly mixed $replyTo = [],
        private readonly string $inReplyTo = '',
    ) {
        $this->boundary = $this->freshBoundary();
    }

    /**
     * Add an attachment entry.
     *
     * @param array{path:string, filename:string, mimeType:string} $attachment
     */
    public function addAttachment(array $attachment): self
    {
        $this->attachments[] = $attachment;
        return $this;
    }

    /**
     * @param array<int, array{path:string, filename:string, mimeType:string}> $attachments
     */
    public function setAttachments(array $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }

    /**
     * Build the complete MIME message as a string.
     */
    public function build(): string
    {
        return $this->buildMessage();
    }

    private function buildMessage(): string
    {
        $lines = [];
        if ($this->from !== '') {
            $lines[] = 'From: ' . $this->encodeRecipients($this->from);
        }
        $lines[] = 'To: ' . $this->encodeRecipients($this->to);
        $cc = $this->encodeRecipients($this->cc);
        if ($cc !== '') {
            $lines[] = 'Cc: ' . $cc;
        }
        $bcc = $this->encodeRecipients($this->bcc);
        if ($bcc !== '') {
            $lines[] = 'Bcc: ' . $bcc;
        }
        $replyTo = $this->encodeRecipients($this->replyTo);
        if ($replyTo !== '') {
            $lines[] = 'Reply-To: ' . $replyTo;
        }
        $lines[] = 'Subject: ' . $this->encodeHeader($this->subject);
        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'Date: ' . date(DATE_RFC2822);

        if ($this->messageId !== '') {
            $lines[] = 'Message-ID: ' . $this->messageId;
        }
        if ($this->inReplyTo !== '') {
            $lines[] = 'In-Reply-To: ' . $this->inReplyTo;
        }
        if ($this->references !== []) {
            $lines[] = 'References: ' . implode(' ', array_filter($this->references));
        }

        // Multipart/mixed wrapper (used when there are attachments).
        if ($this->attachments !== []) {
            $lines[] = 'Content-Type: multipart/mixed; boundary=' . $this->boundary;
            $lines[] = '';
            $lines[] = '--' . $this->boundary;
            $lines[] = 'Content-Type: multipart/alternative; boundary=' . $this->boundary . '_alt';
            $lines[] = '';

            $lines = array_merge($lines, $this->alternativeParts());

            foreach ($this->attachments as $attachment) {
                $lines[] = '--' . $this->boundary;
                $lines = array_merge($lines, $this->attachmentPart($attachment));
            }
            $lines[] = '--' . $this->boundary . '--';
        } elseif ($this->html !== '') {
            $lines[] = 'Content-Type: multipart/alternative; boundary=' . $this->boundary . '_alt';
            $lines[] = '';
            $lines = array_merge($lines, $this->alternativeParts());
        } else {
            $lines = array_merge($lines, $this->alternativeParts());
        }

        return $this->joinLines($lines);
    }

    /**
     * @return array<int, string>
     */
    private function alternativeParts(): array
    {
        $lines = [];
        if ($this->html !== '') {
            $lines[] = '--' . $this->boundary . '_alt';
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = chunk_split(base64_encode($this->body), 76, "\r\n");
            $lines[] = '--' . $this->boundary . '_alt';
            $lines[] = 'Content-Type: text/html; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = chunk_split(base64_encode($this->html), 76, "\r\n");
            $lines[] = '--' . $this->boundary . '_alt--';
        } else {
            // Only plain text -- do not fragment into an alt wrapper.
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = chunk_split(base64_encode($this->body), 76, "\r\n");
        }
        return $lines;
    }

    /**
     * @param array{path:string, filename:string, mimeType:string} $attachment
     * @return array<int, string>
     */
    private function attachmentPart(array $attachment): array
    {
        $data = file_get_contents($attachment['path']);
        if ($data === false) {
            $data = '';
        }

        return [
            'Content-Type: ' . $attachment['mimeType'] . '; ' . $this->filenameParameter('name', $attachment['filename']),
            'Content-Disposition: attachment; ' . $this->filenameParameter('filename', $attachment['filename']),
            'Content-Transfer-Encoding: base64',
            '',
            ...explode("\r\n", chunk_split(base64_encode($data), 76, "\r\n")),
        ];
    }

    /**
     * Build a safe filename/name MIME parameter.
     *
     * ASCII names use a quoted string with escaped quotes/backslashes.
     * Non-ASCII names additionally use RFC 2231 parameter continuation.
     */
    private function filenameParameter(string $name, string $filename): string
    {
        if (preg_match('/[\x80-\xFF]/', $filename) === 0) {
            return $name . '="' . addcslashes($filename, "\\\"") . '"';
        }

        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'attachment';
        $fallback = addcslashes($fallback !== '' ? $fallback : 'attachment', "\\\"");

        return $name . '="' . $fallback . '"; '
            . $name . "*=UTF-8''" . rawurlencode($filename);
    }

    /**
     * @param array<int, mixed>|string $recipients
     */
    private function encodeRecipients(mixed $recipients): string
    {
        $normalized = EmailValidator::normalizeRecipients($recipients);
        if ($normalized === '') {
            return '';
        }

        // Encode display names, keep <email> addresses intact.
        $parts = [];
        foreach (explode(',', $normalized) as $recipient) {
            $recipient = trim($recipient);
            if (preg_match('/^(.*)<([^>]+)>$/', $recipient, $m)) {
                $parts[] = $this->encodeHeader(trim($m[1])) . ' <' . trim($m[2]) . '>';
            } else {
                $parts[] = $recipient;
            }
        }
        return implode(', ', $parts);
    }

    private function encodeHeader(string $value): string
    {
        // Values without high/non-ASCII bytes can stay as-is.
        if (preg_match('/[\x80-\xFF]/', $value) === 0) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function freshBoundary(): string
    {
        return 'b_' . bin2hex(random_bytes(12));
    }

    private function joinLines(array $lines): string
    {
        $result = implode("\r\n", $lines);
        // Trailing CRLF required by RFC 2822.
        return $result . "\r\n";
    }
}