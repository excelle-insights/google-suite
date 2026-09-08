<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Support;

use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use PDO;
use Throwable;

/**
 * Logger that persists log lines as rows in http_request_logs.
 *
 * Never throws: any failure to write the log is swallowed and reported
 * via error_log so logging can never break the application flow.
 */
class DatabaseLogger implements LoggerInterface
{
    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly string $table = '',
    ) {}

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if ($this->pdo === null) {
            return;
        }

        $table = $this->table !== ''
            ? $this->table
            : ($_ENV['GOOGLE_TABLE_PREFIX'] ?? 'google') . '_request_logs';

        $service = $context['service'] ?? '';
        $method  = $context['method'] ?? '';
        $url     = $context['url'] ?? '';
        $status  = $context['status'] ?? null;
        $body    = $context['body'] ?? $message;

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `$table`
                    (service, method, url, request_body, response_code, response_body, level, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $service,
                $method,
                $url,
                json_encode($context['request'] ?? $context, JSON_PARTIAL_OUTPUT_ON_ERROR),
                $status,
                is_string($body) ? $body : json_encode($body, JSON_PARTIAL_OUTPUT_ON_ERROR),
                $level,
                $context['user_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('GoogleSuite DatabaseLogger failed: ' . $e->getMessage());
        }
    }
}