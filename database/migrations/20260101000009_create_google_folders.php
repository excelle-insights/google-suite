<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGoogleFolders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_folders', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('google_id', 'string', ['limit' => 191])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 500])
            ->addColumn('parent_google_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('path', 'string', ['limit' => 2000, 'null' => true])
            ->addColumn('sync_status', 'enum', ['values' => ['pending', 'synced', 'failed'], 'default' => 'pending'])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['google_id', 'user_id'], ['unique' => true, 'name' => 'idx_fld_google_user'])
            ->addIndex(['user_id'], ['name' => 'idx_fld_user'])
            ->addIndex(['parent_google_id'], ['name' => 'idx_fld_parent'])
            ->addIndex(['name'], ['name' => 'idx_fld_name'])
            ->create();
    }
}