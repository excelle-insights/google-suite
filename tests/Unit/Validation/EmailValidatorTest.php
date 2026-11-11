<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Validation;

use ExcelleInsights\GoogleSuite\Validation\EmailValidator;
use PHPUnit\Framework\TestCase;

final class EmailValidatorTest extends TestCase
{
    public function testValidEmailsAccepted(): void
    {
        $this->assertTrue(EmailValidator::isValid('user@example.com'));
        $this->assertTrue(EmailValidator::isValid('first.last+tag@sub.example.co'));
    }

    public function testInvalidEmailsRejected(): void
    {
        $this->assertFalse(EmailValidator::isValid('not-an-email'));
        $this->assertFalse(EmailValidator::isValid('user@'));
        $this->assertFalse(EmailValidator::isValid(''));
        $this->assertFalse(EmailValidator::isValid(123));
    }

    public function testNormalizeRecipientsWithAssociativeEntries(): void
    {
        $normalized = EmailValidator::normalizeRecipients([
            ['email' => 'alice@example.com', 'name' => 'Alice'],
            ['email' => 'bob@example.com'],
        ]);

        $this->assertSame('Alice <alice@example.com>, bob@example.com', $normalized);
    }

    public function testNormalizeRecipientsWithString(): void
    {
        $this->assertSame('alice@example.com', EmailValidator::normalizeRecipients('alice@example.com'));
    }

    public function testNormalizeRecipientsWithEmailToNameShorthand(): void
    {
        $normalized = EmailValidator::normalizeRecipients([
            'alice@example.com' => 'Alice',
            'bob@example.com'   => '',
        ]);

        $this->assertSame('Alice <alice@example.com>, bob@example.com', $normalized);
    }

    public function testNormalizeRecipientsWithNull(): void
    {
        $this->assertSame('', EmailValidator::normalizeRecipients(null));
    }

    public function testExtractEmailFromDisplayName(): void
    {
        $this->assertSame('alice@example.com', EmailValidator::extractEmail('Alice <alice@example.com>'));
        $this->assertSame('bob@example.com', EmailValidator::extractEmail('bob@example.com'));
    }
}