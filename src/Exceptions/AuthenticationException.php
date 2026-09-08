<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Exceptions;

/**
 * Thrown on OAuth failures, expired or missing tokens, or missing
 * client credentials.
 */
class AuthenticationException extends GoogleSuiteException
{
}