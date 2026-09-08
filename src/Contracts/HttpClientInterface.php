<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Contracts;

/**
 * HTTP transport contract, kept intentionally small so consumers can
 * substitute Guzzle or cURL-backed implementations.
 */
interface HttpClientInterface
{
    /**
     * Send an HTTP request.
     *
     * @return array{
     *   status:int,
     *   headers:array,
     *   body:mixed
     * }
     */
    public function send(
        string $method,
        string $url,
        array $headers = [],
        mixed $body = null
    ): array;
}