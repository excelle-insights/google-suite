<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGoogleCalendars extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_calendars', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('google_id', 'string', ['limit' => 191])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('summary', 'string', ['limit' => 1000])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('location', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('time_zone', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('is_primary', 'boolean', ['default' => false])
            ->addColumn('access_role', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('foreground_color', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('background_color', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('selected', 'boolean', ['default' => false])
            ->addColumn('sync_status', 'enum', ['values' => ['pending', 'synced', 'failed'], 'default' => 'pending'])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['google_id', 'user_id'], ['unique' => true, 'name' => 'idx_cal_google_user'])
            ->addIndex(['user_id'], ['name' => 'idx_cal_user'])
            ->addIndex(['is_primary'], ['name' => 'idx_cal_primary'])
            ->create();
    }
}