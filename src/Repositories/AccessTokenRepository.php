<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use PDO;

/**
 * Persistence for OAuth tokens in the shared api_access_tokens table.
 */
class AccessTokenRepository extends AbstractRepository
{
    public const APP = 'google_suite';

    protected function table(): string
    {
        return 'api_access_tokens';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'app', 'user_id', 'client_id', 'client_secret', 'access_token',
            'expires_at', 'updated_at',
        ];
    }

    /**
     * Find the token row for an app + user, if any.
     */
    public function findToken(string $app, string|int $userId): ?array
    {
        return $this->findOneBy(['app' => $app, 'user_id' => (string) $userId]);
    }

    /**
     * Upsert a JSON-encoded token payload for an app + user.
     *
     * @param array<string, mixed> $tokenPayload
     */
    public function saveToken(
        string $app,
        string|int $userId,
        array $tokenPayload,
        ?int $expiresAt = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
    ): void {
        $json = json_encode($tokenPayload, JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_access_tokens
                (app, user_id, client_id, client_secret, access_token, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                access_token   = VALUES(access_token),
                client_id      = VALUES(client_id),
                client_secret  = VALUES(client_secret),
                expires_at     = VALUES(expires_at),
                updated_at     = NOW()'
        );
        $stmt->execute([
            $app,
            (string) $userId,
            $clientId,
            $clientSecret,
            $json,
            $expiresAt,
        ]);
    }

    /**
     * Delete a stored token row.
     */
    public function deleteToken(string $app, string|int $userId): int
    {
        return $this->pdo
            ->prepare('DELETE FROM api_access_tokens WHERE app = ? AND user_id = ?')
            ->execute([$app, (string) $userId]) ? 1 : 0;
    }
}