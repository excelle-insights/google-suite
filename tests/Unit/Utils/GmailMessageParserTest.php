<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Utils;

use ExcelleInsights\GoogleSuite\Utils\GmailMessageParser;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\MessagePartBody;
use Google\Service\Gmail\MessagePartHeader;
use PHPUnit\Framework\TestCase;

final class GmailMessageParserTest extends TestCase
{
    public function testParsesMultipartMessage(): void
    {
        $plain = new MessagePart();
        $plain->setMimeType('text/plain');
        $plain->setBody(new MessagePartBody(['data' => $this->encode('Hello plain world')]));

        $html = new MessagePart();
        $html->setMimeType('text/html');
        $html->setBody(new MessagePartBody(['data' => $this->encode('<p>Hello HTML</p>')]));

        $root = new MessagePart();
        $root->setMimeType('multipart/alternative');
        $root->setParts([$plain, $html]);
        $root->setHeaders([
            new MessagePartHeader(['name' => 'From', 'value' => 'Alice <alice@example.com>']),
            new MessagePartHeader(['name' => 'Subject', 'value' => 'Meeting notes']),
            new MessagePartHeader(['name' => 'To', 'value' => 'bob@example.com']),
        ]);

        $message = new Message();
        $message->setPayload($root);
        $message->setLabelIds(['IMPORTANT']);
        $message->setSnippet('Meeting notes');
        $message->setInternalDate('1788837000000');
        $message->setSizeEstimate('1234');
        $message->setThreadId('thread-1');

        $parsed = GmailMessageParser::parse($message);

        $this->assertSame('Meeting notes', $parsed['subject']);
        $this->assertSame('alice@example.com', $parsed['from_address']);
        $this->assertSame('Alice', $parsed['from_name']);
        $this->assertSame('bob@example.com', $parsed['to_address']);
        $this->assertSame('Hello plain world', $parsed['body_plain']);
        $this->assertSame('<p>Hello HTML</p>', $parsed['body_html']);
        $this->assertSame(['IMPORTANT'], $parsed['label_ids']);
        $this->assertSame('thread-1', $parsed['thread_id']);
    }

    public function testCollectsAttachments(): void
    {
        $attachmentBody = new MessagePartBody();
        $attachmentBody->setAttachmentId('attach-1');
        $attachmentBody->setSize(42);

        $filePart = new MessagePart();
        $filePart->setMimeType('application/pdf');
        $filePart->setFilename('report.pdf');
        $filePart->setBody($attachmentBody);

        $root = new MessagePart();
        $root->setMimeType('multipart/mixed');
        $root->setParts([$filePart]);

        $message = new Message();
        $message->setPayload($root);

        $parsed = GmailMessageParser::parse($message);

        $this->assertCount(1, $parsed['attachments']);
        $this->assertSame('attach-1', $parsed['attachments'][0]['google_id']);
        $this->assertSame('report.pdf', $parsed['attachments'][0]['filename']);
        $this->assertSame('application/pdf', $parsed['attachments'][0]['mime_type']);
        $this->assertSame(42, $parsed['attachments'][0]['size']);
        $this->assertFalse($parsed['attachments'][0]['is_inline']);
    }

    public function testDecodesRfc2047Headers(): void
    {
        $root = new MessagePart();
        $root->setHeaders([
            new MessagePartHeader([
                'name'  => 'Subject',
                'value' => '=?UTF-8?B?' . base64_encode('Café résumé') . '?=',
            ]),
            new MessagePartHeader([
                'name'  => 'From',
                'value' => '=?UTF-8?B?' . base64_encode('Jöhn Döe') . '?= <john@example.com>',
            ]),
        ]);

        $message = new Message();
        $message->setPayload($root);

        $parsed = GmailMessageParser::parse($message);

        $this->assertSame('Café résumé', $parsed['subject']);
        $this->assertSame('Jöhn Döe', $parsed['from_name']);
        $this->assertSame('john@example.com', $parsed['from_address']);
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $root = new MessagePart();
        $root->setHeaders([
            new MessagePartHeader(['name' => 'SUBJECT', 'value' => 'Uppercase header']),
            new MessagePartHeader(['name' => 'from', 'value' => 'Alice <alice@example.com>']),
            new MessagePartHeader(['name' => 'to', 'value' => 'bob@example.com']),
            new MessagePartHeader(['name' => 'MESSAGE-ID', 'value' => '<case@example.com>']),
            new MessagePartHeader(['name' => 'references', 'value' => '<root@example.com>']),
        ]);

        $message = new Message();
        $message->setPayload($root);

        $parsed = GmailMessageParser::parse($message);

        $this->assertSame('Uppercase header', $parsed['subject']);
        $this->assertSame('alice@example.com', $parsed['from_address']);
        $this->assertSame('bob@example.com', $parsed['to_address']);
        $this->assertSame('<case@example.com>', $parsed['message_id']);
        $this->assertSame('<root@example.com>', $parsed['references']);
    }

    public function testCollectsInlineAttachmentWithoutFilename(): void
    {
        $body = new MessagePartBody();
        $body->setAttachmentId('inline-attach-1');
        $body->setSize(128);

        $inlinePart = new MessagePart();
        $inlinePart->setMimeType('image/png');
        $inlinePart->setBody($body);
        $inlinePart->setHeaders([
            new MessagePartHeader(['name' => 'Content-ID', 'value' => '<logo@example.com>']),
            new MessagePartHeader(['name' => 'Content-Disposition', 'value' => 'inline']),
        ]);

        $root = new MessagePart();
        $root->setMimeType('multipart/related');
        $root->setParts([$inlinePart]);

        $message = new Message();
        $message->setPayload($root);

        $parsed = GmailMessageParser::parse($message);

        $this->assertCount(1, $parsed['attachments']);
        $attachment = $parsed['attachments'][0];
        $this->assertSame('inline-attach-1', $attachment['google_id']);
        $this->assertSame('<logo@example.com>', $attachment['content_id']);
        $this->assertStringStartsWith('inline-', $attachment['filename']);
        $this->assertTrue($attachment['is_inline']);
    }

    public function testInlineDispositionMarksNamedAttachment(): void
    {
        $body = new MessagePartBody();
        $body->setAttachmentId('attach-inline-2');
        $body->setSize(64);

        $part = new MessagePart();
        $part->setMimeType('image/jpeg');
        $part->setFilename('photo.jpg');
        $part->setBody($body);
        $part->setHeaders([
            new MessagePartHeader(['name' => 'content-disposition', 'value' => 'inline; filename="photo.jpg"']),
        ]);

        $root = new MessagePart();
        $root->setParts([$part]);

        $message = new Message();
        $message->setPayload($root);

        $parsed = GmailMessageParser::parse($message);

        $this->assertSame('photo.jpg', $parsed['attachments'][0]['filename']);
        $this->assertTrue($parsed['attachments'][0]['is_inline']);
    }

    public function testParsesReferencesHeader(): void
    {
        $root = new MessagePart();
        $root->setHeaders([
            new MessagePartHeader(['name' => 'Message-ID', 'value' => '<child@example.com>']),
            new MessagePartHeader(['name' => 'In-Reply-To', 'value' => '<parent@example.com>']),
            new MessagePartHeader(['name' => 'References', 'value' => '<root@example.com> <parent@example.com>']),
        ]);

        $message = new Message();
        $message->setPayload($root);

        $parsed = GmailMessageParser::parse($message);

        $this->assertSame('<child@example.com>', $parsed['message_id']);
        $this->assertSame('<parent@example.com>', $parsed['in_reply_to']);
        $this->assertSame('<root@example.com> <parent@example.com>', $parsed['references']);
    }

    public function testUrlSafeBase64RoundTrip(): void
    {
        $data = "some \x00 binary \xff data";
        $this->assertSame($data, GmailMessageParser::urlSafeBase64Decode($this->encode($data)));
    }

    public function testUrlSafeBase64DecodePreservesZero(): void
    {
        $this->assertSame('0', GmailMessageParser::urlSafeBase64Decode($this->encode('0')));
    }

    private function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}