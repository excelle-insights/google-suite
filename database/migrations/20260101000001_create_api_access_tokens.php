<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateApiAccessTokens extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('api_access_tokens', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('app', 'string', ['limit' => 191])
            ->addColumn('user_id', 'string', ['limit' => 191])
            ->addColumn('client_id', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('client_secret', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('access_token', 'text')
            ->addColumn('expires_at', 'biginteger', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['app', 'user_id'], ['unique' => true, 'name' => 'idx_access_app_user'])
            ->addIndex(['expires_at'], ['name' => 'idx_access_expires'])
            ->create();
    }
}