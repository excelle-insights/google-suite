<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Services;

use ExcelleInsights\GoogleSuite\Auth\GoogleAuthManager;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use ExcelleInsights\GoogleSuite\Support\DatabaseLogger;

/**
 * Base service.
 *
 * Concrete services implement the "local-first-then-sync" workflow used
 * throughout the package:
 *
 *   1. Validate input.
 *   2. Persist locally with status=pending.
 *   3. Push to the Google API.
 *   4. On success: mark synced, store the Google ID.
 *   5. On failure: mark failed, capture the error.
 *   6. Return a normalized result.
 *
 * Read operations follow a "fetch and cache" complement: pull from the
 * API and upsert into the local table so it acts as an offline shadow.
 */
abstract class AbstractService
{
    protected readonly LoggerInterface $logger;

    public function __construct(
        protected readonly GoogleAuthManager $authManager,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new DatabaseLogger();
    }
}