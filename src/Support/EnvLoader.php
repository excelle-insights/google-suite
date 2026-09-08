<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Support;

use Dotenv\Dotenv;

/**
 * Idempotent environment loader.
 *
 * Looks for a .env in the following order (first match wins):
 *   1. An explicitly provided root (consumer project root)
 *   2. The consumer's project root (parent dirs up from vendor/)
 *   3. The package's own .env or config/env/.env
 */
class EnvLoader
{
    private static bool $loaded = false;

    public static function load(?string $root = null): void
    {
        if (self::$loaded) {
            return;
        }

        $candidates = [];

        if ($root !== null) {
            $candidates[] = $root;
        }

        // Consumer project root: walk up from this file's location.
        $dir = dirname(__DIR__, 3);
        while ($dir !== dirname($dir)) {
            $candidates[] = $dir;
            $dir = dirname($dir);
        }

        // Package-local locations.
        $packageRoot = dirname(__DIR__, 2);
        $candidates[] = $packageRoot;
        $candidates[] = $packageRoot . '/config/env';

        foreach ($candidates as $candidate) {
            if (file_exists($candidate . '/.env')) {
                Dotenv::createImmutable($candidate)->safeLoad();
                self::$loaded = true;
                return;
            }
        }

        // Mark loaded even when no .env exists so later calls are no-ops.
        self::$loaded = true;
    }
}