<?php

/**
 * Bootstrap helper for the example scripts.
 *
 * Provides connectDb(): builds a PDO connection from DB_DSN / DB_USER /
 * DB_PASSWORD environment values (already loaded from the package .env).
 */

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use PDO;

GoogleSuiteConfig::boot(dirname(__DIR__));

/**
 * @return PDO
 */
function connectDb(): PDO
{
    $dsn  = $_ENV['DB_DSN'] ?? getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=google_suite';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * @return LoggerInterface
 */
function connectLogger(PDO $pdo): LoggerInterface
{
    return new \ExcelleInsights\GoogleSuite\Support\DatabaseLogger($pdo);
}