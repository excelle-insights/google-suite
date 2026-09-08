<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Auth;

use Google\Client;
use Google\Service;

/**
 * Produces fully-authenticated Google\Client instances (and service
 * objects) for a given user. The access token is resolved via the
 * GoogleAuthManager so expired tokens are refreshed automatically.
 */
class GoogleClientFactory
{
    public function __construct(
        private readonly GoogleAuthManager $authManager,
    ) {}

    public function create(string|int $userId = 1): Client
    {
        $client = $this->authManager->createClient();
        $client->setAccessToken($this->authManager->getValidAccessToken($userId));
        return $client;
    }

    /**
     * @template T of Service
     * @param class-string<T> $serviceClass
     * @return T
     */
    public function createService(string $serviceClass, string|int $userId = 1): Service
    {
        return new $serviceClass($this->create($userId));
    }
}