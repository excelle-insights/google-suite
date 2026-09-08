<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateHttpRequestLogs extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('http_request_logs', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('service', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('method', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('url', 'text', ['null' => true])
            ->addColumn('request_body', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('response_body', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('response_code', 'integer', ['null' => true])
            ->addColumn('duration_ms', 'float', ['null' => true])
            ->addColumn('level', 'string', ['limit' => 16, 'null' => true, 'default' => 'info'])
            ->addColumn('user_id', 'biginteger', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['service'], ['name' => 'idx_log_service'])
            ->addIndex(['response_code'], ['name' => 'idx_log_code'])
            ->addIndex(['user_id'], ['name' => 'idx_log_user'])
            ->addIndex(['created_at'], ['name' => 'idx_log_created'])
            ->create();
    }
}