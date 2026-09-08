<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateGoogleMessages extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_messages', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('google_id', 'string', ['limit' => 191])
            ->addColumn('thread_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('subject', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('from_address', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('from_name', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('to_address', 'text', ['null' => true])
            ->addColumn('cc_address', 'text', ['null' => true])
            ->addColumn('bcc_address', 'text', ['null' => true])
            ->addColumn('reply_to_address', 'text', ['null' => true])
            ->addColumn('snippet', 'text', ['null' => true])
            ->addColumn('body_plain', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('body_html', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('label_ids', 'text', ['null' => true, 'comment' => 'JSON array'])
            ->addColumn('is_read', 'boolean', ['default' => false])
            ->addColumn('is_starred', 'boolean', ['default' => false])
            ->addColumn('is_draft', 'boolean', ['default' => false])
            ->addColumn('is_trashed', 'boolean', ['default' => false])
            ->addColumn('has_attachments', 'boolean', ['default' => false])
            ->addColumn('internal_date', 'biginteger', ['null' => true])
            ->addColumn('size_estimate', 'biginteger', ['null' => true])
            ->addColumn('raw_payload', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('sync_status', 'enum', ['values' => ['pending', 'synced', 'failed'], 'default' => 'pending'])
            ->addColumn('retry_count', 'integer', ['default' => 0])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('last_attempt_at', 'timestamp', ['null' => true])
            ->addColumn('synced_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['google_id', 'user_id'], ['unique' => true, 'name' => 'idx_msg_google_user'])
            ->addIndex(['thread_id'], ['name' => 'idx_msg_thread'])
            ->addIndex(['user_id'], ['name' => 'idx_msg_user'])
            ->addIndex(['from_address'], ['name' => 'idx_msg_from'])
            ->addIndex(['internal_date'], ['name' => 'idx_msg_date'])
            ->addIndex(['sync_status'], ['name' => 'idx_msg_status'])
            ->addIndex(['is_read'], ['name' => 'idx_msg_read'])
            ->addIndex(['is_trashed'], ['name' => 'idx_msg_trashed'])
            ->create();

        if ($this->getAdapter()->hasOption('fulltext')) {
            $this->getAdapter()->execute(
                'ALTER TABLE google_messages ADD FULLTEXT INDEX ft_msg_search (
                    subject, snippet, body_plain
                )'
            );
        }
    }
}