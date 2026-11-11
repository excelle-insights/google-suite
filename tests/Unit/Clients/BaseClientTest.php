<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Clients;

use ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory;
use ExcelleInsights\GoogleSuite\Clients\BaseClient;
use ExcelleInsights\GoogleSuite\Exceptions\SyncException;
use Google\Service;
use Google\Service\Exception as GoogleServiceException;
use PHPUnit\Framework\TestCase;

final class BaseClientTest extends TestCase
{
    public function testSuccessful401RetryReturnsRetryResult(): void
    {
        $factory = $this->createMock(GoogleClientFactory::class);
        $factory->expects($this->once())
            ->method('createService')
            ->willReturn(new Service([]));

        $client = new TestableBaseClient($factory);
        $calls  = 0;

        $result = $client->run(function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                throw new GoogleServiceException('Token expired', 401);
            }
            return 'retried';
        });

        $this->assertSame('retried', $result);
        $this->assertSame(2, $calls);
    }

    public function testRepeated401DoesNotRecurseForever(): void
    {
        $factory = $this->createMock(GoogleClientFactory::class);
        $factory->expects($this->once())
            ->method('createService')
            ->willReturn(new Service([]));

        $client = new TestableBaseClient($factory);
        $calls  = 0;

        try {
            $client->run(function () use (&$calls): never {
                $calls++;
                throw new GoogleServiceException('Token expired', 401);
            });
            $this->fail('Expected SyncException');
        } catch (SyncException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertSame(2, $calls);
        }
    }

    public function testRetriesTransientErrorsThenSucceeds(): void
    {
        $factory = $this->createMock(GoogleClientFactory::class);
        $client  = new TestableBaseClient($factory);
        $calls   = 0;

        $result = $client->run(function () use (&$calls): string {
            $calls++;
            if ($calls < 3) {
                throw new GoogleServiceException('Service unavailable', 503);
            }
            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(3, $calls);
    }

    public function testStopsAfterMaxRetries(): void
    {
        $factory = $this->createMock(GoogleClientFactory::class);
        $client  = new TestableBaseClient($factory);
        $calls   = 0;

        try {
            $client->run(function () use (&$calls): never {
                $calls++;
                throw new GoogleServiceException('Rate limited', 429);
            });
            $this->fail('Expected SyncException');
        } catch (SyncException $e) {
            $this->assertSame(429, $e->getCode());
            // Initial attempt plus three retries.
            $this->assertSame(4, $calls);
        }
    }

    public function testNonRetryableTransientErrorFailsImmediately(): void
    {
        $factory = $this->createMock(GoogleClientFactory::class);
        $client  = new TestableBaseClient($factory);
        $calls   = 0;

        try {
            $client->runNonRetryable(function () use (&$calls): never {
                $calls++;
                throw new GoogleServiceException('Service unavailable', 503);
            });
            $this->fail('Expected SyncException');
        } catch (SyncException $e) {
            $this->assertSame(503, $e->getCode());
            $this->assertSame(1, $calls);
        }
    }

    public function testNonRetryableStillRetriesOnceOn401(): void
    {
        $factory = $this->createMock(GoogleClientFactory::class);
        $factory->expects($this->once())
            ->method('createService')
            ->willReturn(new Service([]));

        $client = new TestableBaseClient($factory);
        $calls  = 0;

        $result = $client->runNonRetryable(function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                throw new GoogleServiceException('Token expired', 401);
            }
            return 'retried';
        });

        $this->assertSame('retried', $result);
        $this->assertSame(2, $calls);
    }
}

final class TestableBaseClient extends BaseClient
{
    protected function serviceClass(): string
    {
        return Service::class;
    }

    public function run(callable $operation): mixed
    {
        return $this->execute($operation, 'test.operation');
    }

    public function runNonRetryable(callable $operation): mixed
    {
        return $this->execute($operation, 'test.operation', 0, false);
    }

    protected function backoff(int $attempt): void
    {
        // Keep retry tests fast; backoff timing is not under test.
    }
}
