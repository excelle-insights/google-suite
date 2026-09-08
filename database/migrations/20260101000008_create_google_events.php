<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateGoogleEvents extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('google_events', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('google_id', 'string', ['limit' => 191])
            ->addColumn('calendar_google_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('user_id', 'biginteger', ['default' => 1, 'signed' => false])
            ->addColumn('summary', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('location', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('start_datetime', 'datetime', ['null' => true])
            ->addColumn('start_date', 'date', ['null' => true])
            ->addColumn('start_timezone', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('end_datetime', 'datetime', ['null' => true])
            ->addColumn('end_date', 'date', ['null' => true])
            ->addColumn('end_timezone', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('all_day', 'boolean', ['default' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'null' => true, 'default' => 'confirmed'])
            ->addColumn('attendees', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG, 'comment' => 'JSON array'])
            ->addColumn('reminders', 'text', ['null' => true, 'comment' => 'JSON object'])
            ->addColumn('recurrence', 'text', ['null' => true, 'comment' => 'JSON array'])
            ->addColumn('recurring_event_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('i_cal_uid', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('html_link', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('organizer', 'text', ['null' => true, 'comment' => 'JSON object'])
            ->addColumn('raw_payload', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('sync_status', 'enum', ['values' => ['pending', 'synced', 'failed'], 'default' => 'pending'])
            ->addColumn('retry_count', 'integer', ['default' => 0])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('synced_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['google_id', 'user_id'], ['unique' => true, 'name' => 'idx_evt_google_user'])
            ->addIndex(['calendar_google_id'], ['name' => 'idx_evt_cal'])
            ->addIndex(['user_id'], ['name' => 'idx_evt_user'])
            ->addIndex(['start_datetime'], ['name' => 'idx_evt_start'])
            ->addIndex(['end_datetime'], ['name' => 'idx_evt_end'])
            ->addIndex(['status'], ['name' => 'idx_evt_status'])
            ->addIndex(['sync_status'], ['name' => 'idx_evt_sync'])
            ->create();
    }
}