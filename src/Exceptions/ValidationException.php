<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Exceptions;

/**
 * Thrown when user-supplied input fails validation.
 */
class ValidationException extends GoogleSuiteException
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, string> $errors Field => message map.
     */
    public static function withErrors(array $errors): self
    {
        $first = reset($errors);
        $e = new self($first !== false ? (string) $first : 'Validation failed');
        $e->errors = $errors;
        return $e;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}