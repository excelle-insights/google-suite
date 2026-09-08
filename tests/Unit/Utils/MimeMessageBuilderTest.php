<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Utils;

use ExcelleInsights\GoogleSuite\Utils\MimeMessageBuilder;
use PHPUnit\Framework\TestCase;

final class MimeMessageBuilderTest extends TestCase
{
    public function testBuildsPlainTextMessage(): void
    {
        $mime = (new MimeMessageBuilder(
            to:      ['client@example.com'],
            subject: 'Hello',
            body:    'World',
        ))->build();

        $this->assertStringContainsString('To: client@example.com', $mime);
        $this->assertStringContainsString('Subject: Hello', $mime);
        $this->assertStringContainsString('text/plain; charset=UTF-8', $mime);
        $this->assertStringContainsString(base64_encode('World'), $mime);
        $this->assertStringNotContainsString('multipart', $mime);
    }

    public function testBuildsMultipartAlternative(): void
    {
        $mime = (new MimeMessageBuilder(
            to:      ['client@example.com'],
            subject: 'Hi',
            body:    'Plain',
            html:    '<p>Plain</p>',
        ))->build();

        $this->assertStringContainsString('multipart/alternative', $mime);
        $this->assertStringContainsString('text/plain; charset=UTF-8', $mime);
        $this->assertStringContainsString('text/html; charset=UTF-8', $mime);
    }

    public function testBuildsWithAttachment(): void
    {
        $path = sys_get_temp_dir() . '/google-suite-mime-' . uniqid() . '.bin';
        file_put_contents($path, 'binary-data');

        try {
            $mime = (new MimeMessageBuilder(
                to:      ['client@example.com'],
                subject: 'With file',
                body:    'See attached',
            ))->addAttachment([
                'path'     => $path,
                'filename' => 'doc.pdf',
                'mimeType' => 'application/pdf',
            ])->build();

            $this->assertStringContainsString('multipart/mixed', $mime);
            $this->assertStringContainsString('application/pdf; name="doc.pdf"', $mime);
            $this->assertStringContainsString('Content-Disposition: attachment; filename="doc.pdf"', $mime);
        } finally {
            @unlink($path);
        }
    }

    public function testEncodesNonAsciiSubject(): void
    {
        $mime = (new MimeMessageBuilder(
            to:      ['client@example.com'],
            subject: 'Ünïcode subject',
            body:    'Body',
        ))->build();

        $this->assertStringContainsString('=?UTF-8?B?', $mime);
        $this->assertStringNotContainsString('Ünïcode', $mime);
    }

    public function testMessageEndsWithCrlf(): void
    {
        $mime = (new MimeMessageBuilder(
            to:      ['client@example.com'],
            subject: 'S',
            body:    'B',
        ))->build();

        $this->assertStringEndsWith("\r\n", $mime);
    }
}