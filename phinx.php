<?php

/**
 * Phinx configuration for the Google Suite package.
 *
 * Reads database credentials from the environment or .env file.
 *
 * IMPORTANT — table prefix:
 *   The bundled migrations create tables with a hard-coded `google_` prefix
 *   (plus the shared `api_access_tokens` and `http_request_logs` tables).
 *   They do NOT read GOOGLE_TABLE_PREFIX. If your application uses a custom
 *   prefix, copy `database/migrations` into your app and adapt the table names
 *   before running them. Setting GOOGLE_TABLE_PREFIX alone is not enough.
 *
 * IMPORTANT — database connection:
 *   This file is the package's own runner config. It requires DB_DSN (or
 *   MYSQL_DSN), DB_USER and DB_PASSWORD to point at the application database.
 *   For an existing application, prefer copying the migrations into the app's
 *   own `db/migrations` and pointing the app's Phinx config at its existing
 *   PDO connection instead of running this file.
 */
$root = __DIR__;

// Attempt to bootstrap the environment without failing hard when the
// database is not yet configured (e.g. during package development).
$envFile = $root . '/.env';
if (file_exists($root . '/config/env/.env')) {
    $envFile = $root . '/config/env/.env';
}

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (isset($_ENV[$key]) || getenv($key) !== false) {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$prefix = getenv('GOOGLE_TABLE_PREFIX');
if ($prefix !== false && $prefix !== '' && $prefix !== 'google') {
    fwrite(
        STDERR,
        "WARNING: GOOGLE_TABLE_PREFIX is set to '{$prefix}', but the bundled migrations\n"
        . "         hard-code the 'google_' prefix. Copy and adapt the migrations before\n"
        . "         running them against a database that uses a custom prefix.\n"
    );
}

$dsn = getenv('DB_DSN') ?: getenv('MYSQL_DSN') ?: 'mysql:host=127.0.0.1;dbname=google_suite';

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
        'seeds'      => __DIR__ . '/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'development',
        'development'             => [
            'name'       => 'development',
            'connection' => new PDO(
                $dsn,
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASSWORD') ?: ''
            ),
        ],
    ],
];