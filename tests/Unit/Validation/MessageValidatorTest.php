<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Validation;

use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;
use ExcelleInsights\GoogleSuite\Validation\MessageValidator;
use PHPUnit\Framework\TestCase;

final class MessageValidatorTest extends TestCase
{
    public function testValidMessagePasses(): void
    {
        $validated = MessageValidator::validate([
            'to'      => 'client@example.com',
            'subject' => 'Hello',
            'body'    => 'Body text',
        ]);

        $this->assertSame([], $validated);
    }

    public function testMissingRecipientThrows(): void
    {
        $this->expectException(ValidationException::class);
        MessageValidator::validate([
            'subject' => 'Hello',
            'body'    => 'Body text',
        ]);
    }

    public function testErrorBagContainsKey(): void
    {
        try {
            MessageValidator::validate(['to' => '', 'subject' => '', 'body' => '']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to', $e->getErrors());
            $this->assertArrayHasKey('subject', $e->getErrors());
            $this->assertArrayHasKey('body', $e->getErrors());
        }
    }

    public function testAttachmentsNormalizeWithDefaults(): void
    {
        $path = sys_get_temp_dir() . '/google-suite-test-' . uniqid() . '.txt';
        file_put_contents($path, 'contents');

        try {
            $attachments = MessageValidator::validateAttachments([
                'attachments' => [['path' => $path]],
            ]);

            $this->assertCount(1, $attachments);
            $this->assertSame($path, $attachments[0]['path']);
            $this->assertStringStartsWith('google-suite-test-', $attachments[0]['filename']);
        } finally {
            @unlink($path);
        }
    }

    public function testMissingAttachmentFileThrows(): void
    {
        $this->expectException(ValidationException::class);
        MessageValidator::validateAttachments([
            'attachments' => [['path' => '/does/not/exist/file.pdf']],
        ]);
    }
}