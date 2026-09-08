<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGoogleLabels extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_labels', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('google_id', 'string', ['limit' => 191])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('type', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('message_list_visibility', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('label_list_visibility', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('color_background', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('color_text', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['google_id', 'user_id'], ['unique' => true, 'name' => 'idx_label_google_user'])
            ->addIndex(['user_id'], ['name' => 'idx_label_user'])
            ->addIndex(['type'], ['name' => 'idx_label_type'])
            ->addIndex(['name'], ['name' => 'idx_label_name'])
            ->create();
    }
}