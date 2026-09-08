<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGoogleAttachments extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_attachments', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('google_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('message_google_id', 'string', ['limit' => 191])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('filename', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('mime_type', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('size', 'biginteger', ['null' => true])
            ->addColumn('content_id', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('is_inline', 'boolean', ['default' => false])
            ->addColumn('storage_path', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('sync_status', 'enum', ['values' => ['pending', 'synced', 'failed'], 'default' => 'pending'])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['message_google_id'], ['name' => 'idx_att_msg'])
            ->addIndex(['google_id'], ['name' => 'idx_att_google'])
            ->addIndex(['user_id'], ['name' => 'idx_att_user'])
            ->addIndex(['mime_type'], ['name' => 'idx_att_mime'])
            ->create();
    }
}