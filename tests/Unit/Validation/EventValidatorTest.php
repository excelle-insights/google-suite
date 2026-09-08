<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Validation;

use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;
use ExcelleInsights\GoogleSuite\Validation\EventValidator;
use PHPUnit\Framework\TestCase;

final class EventValidatorTest extends TestCase
{
    public function testValidEventPasses(): void
    {
        $validated = EventValidator::validate([
            'summary' => 'Client Meeting',
            'start'   => '2026-06-01 09:00:00',
            'end'     => '2026-06-01 10:00:00',
        ]);

        $this->assertSame('Client Meeting', $validated['summary']);
    }

    public function testMissingStartThrows(): void
    {
        try {
            EventValidator::validate([
                'summary' => 'Meeting',
                'end'     => '2026-06-01 10:00:00',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('start', $e->getErrors());
        }
    }

    public function testInvalidEndDatetimeThrows(): void
    {
        $this->expectException(ValidationException::class);
        EventValidator::validate([
            'summary' => 'Meeting',
            'start'   => '2026-06-01 09:00:00',
            'end'     => 'not-a-datetime',
        ]);
    }

    public function testInvalidAttendeeEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        EventValidator::validate([
            'summary'  => 'Meeting',
            'start'    => '2026-06-01 09:00:00',
            'end'      => '2026-06-01 10:00:00',
            'attendees' => [['email' => 'nope-not-an-email']],
        ]);
    }

    public function testValidAttendeesPass(): void
    {
        $validated = EventValidator::validate([
            'summary'  => 'Meeting',
            'start'    => '2026-06-01 09:00:00',
            'end'      => '2026-06-01 10:00:00',
            'attendees' => [['email' => 'client@example.com', 'name' => 'Client']],
        ]);

        $this->assertCount(1, $validated['attendees']);
    }
}