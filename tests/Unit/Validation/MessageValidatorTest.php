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

    public function testHtmlOnlyMessagePasses(): void
    {
        $validated = MessageValidator::validate([
            'to'      => 'client@example.com',
            'subject' => 'Hello',
            'html'    => '<p>HTML body</p>',
        ]);

        $this->assertSame([], $validated);
    }

    public function testMissingBodyAndHtmlThrowsBodyError(): void
    {
        try {
            MessageValidator::validate([
                'to'      => 'client@example.com',
                'subject' => 'Hello',
                'body'    => '   ',
                'html'    => '',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body', $e->getErrors());
        }
    }

    public function testInvalidRecipientThrows(): void
    {
        try {
            MessageValidator::validate([
                'to'      => 'not-an-email',
                'subject' => 'Hello',
                'body'    => 'Body',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to', $e->getErrors());
        }
    }

    public function testInvalidFromThrows(): void
    {
        try {
            MessageValidator::validate([
                'to'      => 'client@example.com',
                'subject' => 'Hello',
                'body'    => 'Body',
                'from'    => 'Not An Address',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('from', $e->getErrors());
        }
    }

    public function testAssociativeRecipientsPassValidation(): void
    {
        $validated = MessageValidator::validate([
            'to'      => ['client@example.com' => 'Client Name'],
            'cc'      => ['finance@example.com' => 'Finance'],
            'subject' => 'Hello',
            'body'    => 'Body',
        ]);

        $this->assertSame([], $validated);
    }

    public function testAttachmentExceedingSizeLimitThrows(): void
    {
        $path = sys_get_temp_dir() . '/google-suite-size-' . uniqid() . '.txt';
        file_put_contents($path, str_repeat('x', 100));

        try {
            $this->expectException(ValidationException::class);
            MessageValidator::validateAttachments([
                'attachments' => [['path' => $path]],
            ], 10);
        } finally {
            @unlink($path);
        }
    }

    public function testZeroSizeLimitDisablesCheck(): void
    {
        $path = sys_get_temp_dir() . '/google-suite-size-' . uniqid() . '.txt';
        file_put_contents($path, str_repeat('x', 100));

        try {
            $attachments = MessageValidator::validateAttachments([
                'attachments' => [['path' => $path]],
            ], 0);

            $this->assertCount(1, $attachments);
        } finally {
            @unlink($path);
        }
    }
}