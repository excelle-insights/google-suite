<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Validation;

use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;

/**
 * Email-address helpers shared by the other validators.
 */
class EmailValidator
{
    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Normalize a "recipients" value that may be a string or an array of
     * strings (optionally "Name <email>" pairs). Returns a comma-separated
     * string safe for storing and for header building.
     */
    public static function normalizeRecipients(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value)) {
            return trim($value);
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $email = $item['email'] ?? '';
                    $name  = $item['name'] ?? '';
                    $parts[] = $name !== '' && $name !== null
                        ? sprintf('%s <%s>', $name, $email)
                        : $email;
                } elseif (is_string($item)) {
                    $parts[] = trim($item);
                }
            }
            return implode(', ', array_filter($parts));
        }
        return '';
    }

    /**
     * Extract just the email address from a "Name <email>" header value.
     */
    public static function extractEmail(string $header): string
    {
        if (preg_match('/<([^>]+)>/', $header, $m)) {
            return $m[1];
        }
        return trim($header);
    }

    /**
     * Extract the display name (possibly empty) from a "Name <email>" header.
     */
    public static function extractName(string $header): string
    {
        if (preg_match('/^(.*)<([^>]+)>$/', trim($header), $m)) {
            return trim($m[1], " \t\n\r\0\x0B\"'");
        }
        return self::extractEmail($header);
    }
}