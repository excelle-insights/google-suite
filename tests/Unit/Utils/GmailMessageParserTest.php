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

    public function testUrlSafeBase64RoundTrip(): void
    {
        $data = "some \x00 binary \xff data";
        $this->assertSame($data, GmailMessageParser::urlSafeBase64Decode($this->encode($data)));
    }

    private function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}