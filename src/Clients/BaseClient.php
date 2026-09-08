<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Clients;

use ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use ExcelleInsights\GoogleSuite\Exceptions\SyncException;
use Google\Service;
use Google\Service\Exception as GoogleServiceException;
use Throwable;

/**
 * Abstract base for the Google API service clients.
 *
 * Holds a Google\Service\* instance and wraps every call uniformly:
 *  - logs the request (service, method, status)
 *  - translates Google\Service\Exception into SyncException
 *  - retries once on a 401 by re-resolving the access token.
 */
abstract class BaseClient
{
    protected ?Service $service = null;

    public function __construct(
        protected readonly GoogleClientFactory $clientFactory,
        protected readonly int $userId = 1,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Lazily build (or access) the Google\Service\* instance.
     *
     * Creating a service resolves the current access token, so it is
     * deferred until the first real API call. That lets the package be
     * constructed (and OAuth URLs generated) before any token exists.
     */
    protected function service(): Service
    {
        return $this->service ??= $this->clientFactory->createService($this->serviceClass(), $this->userId);
    }

    /**
     * The Google\Service\* class to instantiate.
     *
     * @return class-string<Service>
     */
    abstract protected function serviceClass(): string;

    /**
     * Execute a Google API call with logging and uniform error handling.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws SyncException
     */
    protected function execute(callable $operation, string $operationName = ''): mixed
    {
        $started = microtime(true);

        try {
            $result = $operation();
            $this->log($operationName, 200, null);
            return $result;
        } catch (GoogleServiceException $e) {
            $status = $e->getCode();
            $this->log($operationName, $status, $e->getMessage());

            if ($status === 401) {
                // Force a token refresh then retry once.
                $this->refreshAndRetry($operation, $operationName);
            }

            throw new SyncException(
                $this->describeError($operationName, $status, $e->getMessage()),
                $status,
                $e,
            );
        } catch (Throwable $e) {
            $this->log($operationName, $e->getCode() ?: 0, $e->getMessage());
            throw new SyncException(
                $this->describeError($operationName, $e->getCode() ?: 0, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    protected function refreshAndRetry(callable $operation, string $operationName): mixed
    {
        // Rebuild the service with a freshly-resolved token.
        $this->service = $this->clientFactory->createService($this->serviceClass(), $this->userId);
        return $this->execute($operation, $operationName);
    }

    private function log(string $operationName, int $status, ?string $message): void
    {
        if ($this->logger === null) {
            return;
        }
        $context = [
            'service' => $this->serviceName(),
            'method'  => $operationName,
            'status'  => $status,
            'user_id' => $this->userId,
        ];
        if ($message !== null) {
            $context['body'] = $message;
        }
        if ($status >= 400) {
            $this->logger->error("Google API call {$operationName} failed", $context);
        } else {
            $this->logger->info("Google API call {$operationName} succeeded", $context);
        }
    }

    protected function serviceName(): string
    {
        $parts = explode('\\', static::class);
        return strtolower(end($parts));
    }

    private function describeError(string $operationName, int $status, string $message): string
    {
        return sprintf(
            'Google %s failed (HTTP %d): %s',
            $operationName !== '' ? $operationName : $this->serviceName(),
            $status,
            $message,
        );
    }
}