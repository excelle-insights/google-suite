<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Validation;

use DateTimeImmutable;
use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;

/**
 * Validates calendar event input.
 *
 * Expected array shape:
 * [
 *   'summary'     => string,
 *   'description' => string (optional),
 *   'location'    => string (optional),
 *   'start'       => 'Y-m-d H:i' | 'Y-m-d\TH:i:s' ... (required),
 *   'end'         => same format (required),
 *   'timezone'    => string (optional, e.g. Africa/Nairobi),
 *   'all_day'     => bool (optional),
 *   'attendees'   => optional [['email'=>, 'name'=>], ...],
 *   'reminders'   => optional ['use_default' => bool, 'overrides' => [...]],
 *   'recurrence'  => optional ['RRULE:...'],
 * ]
 */
class EventValidator
{
    /** @return array<string, mixed> */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['summary']) || !is_string($data['summary'])) {
            $errors['summary'] = 'A non-empty summary is required';
        }

        if (empty($data['start'])) {
            $errors['start'] = '"start" is required';
        } elseif (!self::isValidDateTime($data['start'])) {
            $errors['start'] = 'Invalid start datetime';
        }

        if (empty($data['end'])) {
            $errors['end'] = '"end" is required';
        } elseif (!self::isValidDateTime($data['end'])) {
            $errors['end'] = 'Invalid end datetime';
        }

        if (!empty($data['attendees'])) {
            if (!is_array($data['attendees'])) {
                $errors['attendees'] = 'attendees must be an array';
            } else {
                foreach ($data['attendees'] as $i => $attendee) {
                    if (!is_array($attendee) || empty($attendee['email'])) {
                        $errors['attendees'] = "attendees[$i] must include an email";
                        break;
                    }
                    if (!EmailValidator::isValid($attendee['email'])) {
                        $errors['attendees'] = "attendees[$i] has an invalid email";
                        break;
                    }
                }
            }
        }

        if ($errors) {
            throw ValidationException::withErrors($errors);
        }

        return $data;
    }

    private static function isValidDateTime(mixed $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }
        try {
            new DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}