<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Integration;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use ExcelleInsights\GoogleSuite\Repositories\AccessTokenRepository;
use ExcelleInsights\GoogleSuite\Repositories\MessageRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Repository smoke tests against a real MySQL instance.
 *
 * Skipped automatically when no local MySQL (root, no password) is reachable.
 */
final class RepositoryIntegrationTest extends TestCase
{
    private PDO $pdo;
    private bool $skip = false;

    protected function setUp(): void
    {
        putenv('GOOGLE_TABLE_PREFIX=google');
        GoogleSuiteConfig::boot(sys_get_temp_dir());

        try {
            $this->pdo = new PDO(
                'mysql:host=127.0.0.1;dbname=google_suite_test',
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (\Throwable) {
            $this->markTestSkipped('No local MySQL google_suite_test database available.');
        }

        $this->pdo->exec(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                google_id VARCHAR(191) NOT NULL,
                thread_id VARCHAR(191) NULL,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                subject VARCHAR(1000) NULL,
                from_address VARCHAR(500) NULL,
                snippet TEXT NULL,
                body_plain TEXT NULL,
                label_ids TEXT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                internal_date BIGINT NULL,
                sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
                retry_count INT NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                last_attempt_at DATETIME NULL,
                synced_at DATETIME NULL,
                created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_google_user (google_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                GoogleSuiteConfig::table('messages'),
            )
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS api_access_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                app VARCHAR(191) NOT NULL,
                user_id VARCHAR(191) NOT NULL,
                client_id VARCHAR(500) NULL,
                client_secret VARCHAR(500) NULL,
                access_token TEXT NULL,
                expires_at BIGINT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_access_app_user (app, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->pdo->exec('DELETE FROM ' . GoogleSuiteConfig::table('messages'));
        $this->pdo->exec('DELETE FROM api_access_tokens');
    }

    public function testMessageCrudAndSyncLifecycle(): void
    {
        $repo = new MessageRepository($this->pdo);

        $id = $repo->insert([
            'google_id' => 'msg-1',
            'user_id'   => 1,
            'subject'   => 'Hello',
            'from_address' => 'alice@example.com',
            'label_ids' => json_encode(['IMPORTANT']),
            'is_read'   => 0,
        ]);

        $this->assertIsNumeric($id);
        $this->assertSame('msg-1', $repo->findByGoogleId('msg-1', 1)['google_id']);

        $repo->markSynced($id, 'msg-1');
        $synced = $repo->find((string) $id);
        $this->assertSame('synced', $synced['sync_status']);
        $this->assertNotNull($synced['synced_at']);
        $this->assertNull($synced['error_message']);

        $repo->markFailed($id, 'rate limited');
        $failed = $repo->find((string) $id);
        $this->assertSame('failed', $failed['sync_status']);
        $this->assertSame(1, (int) $failed['retry_count']);
        $this->assertSame('rate limited', $failed['error_message']);

        $this->assertCount(1, $repo->getPending(5, 1));
    }

    public function testUpsertUpdatesExistingRow(): void
    {
        $repo = new MessageRepository($this->pdo);

        $repo->upsert([
            'google_id' => 'msg-2',
            'user_id'   => 1,
            'subject'   => 'First',
            'sync_status' => 'pending',
        ], ['google_id', 'user_id']);

        $repo->upsert([
            'google_id' => 'msg-2',
            'user_id'   => 1,
            'subject'   => 'Second',
            'sync_status' => 'pending',
        ], ['google_id', 'user_id']);

        $row = $repo->findByGoogleId('msg-2', 1);
        $this->assertSame('Second', $row['subject']);
        $this->assertSame(1, $repo->count(['user_id' => 1]));
    }

    public function testFindByLabelMatchesJsonArray(): void
    {
        $repo = new MessageRepository($this->pdo);

        $repo->insert([
            'google_id'  => 'msg-3',
            'user_id'    => 1,
            'subject'    => 'Notice',
            'label_ids'  => json_encode(['IMPORTANT', 'WORK']),
        ]);

        $rows = $repo->findByLabel('WORK', 1);

        $this->assertCount(1, $rows);
        $this->assertSame('msg-3', $rows[0]['google_id']);
    }

    public function testSearchAcrossColumns(): void
    {
        $repo = new MessageRepository($this->pdo);
        $repo->insert([
            'google_id' => 'msg-4',
            'user_id'   => 1,
            'subject'   => 'Quarterly Invoice',
        ]);

        $this->assertCount(1, $repo->search('Invoice', 1));
        $this->assertCount(0, $repo->search('zzz-nonexistent', 1));
    }

    public function testAccessTokenRepositorySaveAndDelete(): void
    {
        $repo = new AccessTokenRepository($this->pdo);

        $repo->saveToken('google_suite', 2, ['access_token' => 'tok'], time() + 60, 'cid', 'csec');

        $row = $repo->findToken('google_suite', 2);
        $this->assertSame('tok', json_decode($row['access_token'], true)['access_token']);

        $repo->saveToken('google_suite', 2, ['access_token' => 'tok2'], time() + 60, 'cid', 'csec');
        $row = $repo->findToken('google_suite', 2);
        $this->assertSame('tok2', json_decode($row['access_token'], true)['access_token']);

        $this->assertSame(1, $repo->deleteToken('google_suite', 2));
        $this->assertNull($repo->findToken('google_suite', 2));
    }
}