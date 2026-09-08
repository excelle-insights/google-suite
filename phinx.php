<?php

/**
 * Phinx configuration for the Google Suite package.
 *
 * Reads database credentials from the environment or .env file.
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