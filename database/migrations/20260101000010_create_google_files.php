<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGoogleFiles extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_files', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('google_id', 'string', ['limit' => 191])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 767])
            ->addColumn('mime_type', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('size', 'biginteger', ['null' => true])
            ->addColumn('folder_google_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('parent_ids', 'text', ['null' => true, 'comment' => 'JSON array'])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('web_view_link', 'string', ['limit' => 2000, 'null' => true])
            ->addColumn('web_content_link', 'string', ['limit' => 2000, 'null' => true])
            ->addColumn('download_link', 'string', ['limit' => 2000, 'null' => true])
            ->addColumn('thumbnail_link', 'string', ['limit' => 2000, 'null' => true])
            ->addColumn('starred', 'boolean', ['default' => false])
            ->addColumn('trashed', 'boolean', ['default' => false])
            ->addColumn('created_time', 'datetime', ['null' => true])
            ->addColumn('modified_time', 'datetime', ['null' => true])
            ->addColumn('sync_status', 'enum', ['values' => ['pending', 'synced', 'failed'], 'default' => 'pending'])
            ->addColumn('retry_count', 'integer', ['default' => 0])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('synced_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['google_id', 'user_id'], ['unique' => true, 'name' => 'idx_file_google_user'])
            ->addIndex(['user_id'], ['name' => 'idx_file_user'])
            ->addIndex(['folder_google_id'], ['name' => 'idx_file_folder'])
            ->addIndex(['mime_type'], ['name' => 'idx_file_mime'])
            ->addIndex(['name'], ['name' => 'idx_file_name'])
            ->addIndex(['trashed'], ['name' => 'idx_file_trashed'])
            ->addIndex(['sync_status'], ['name' => 'idx_file_sync'])
            ->create();
    }
}