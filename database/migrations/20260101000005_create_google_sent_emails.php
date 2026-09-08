<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGoogleSentEmails extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_sent_emails', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('message_google_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('thread_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('to_address', 'text', ['null' => true])
            ->addColumn('cc_address', 'text', ['null' => true])
            ->addColumn('bcc_address', 'text', ['null' => true])
            ->addColumn('subject', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('body_preview', 'text', ['null' => true])
            ->addColumn('has_attachments', 'boolean', ['default' => false])
            ->addColumn('status', 'enum', ['values' => ['pending', 'sent', 'failed'], 'default' => 'pending'])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('retry_count', 'integer', ['default' => 0])
            ->addColumn('sent_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['message_google_id'], ['name' => 'idx_sent_msg'])
            ->addIndex(['user_id'], ['name' => 'idx_sent_user'])
            ->addIndex(['status'], ['name' => 'idx_sent_status'])
            ->addIndex(['sent_at'], ['name' => 'idx_sent_at'])
            ->create();
    }
}