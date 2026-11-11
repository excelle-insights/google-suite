<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Persist RFC 5322 threading headers and add the unique keys required by
 * the repository upserts.
 *
 * Added as a separate migration so installations that already ran the
 * original create migrations keep working.
 */
final class AddGmailHeadersAndUniqueIndexes extends AbstractMigration
{
    public function change(): void
    {
        $this->table('google_messages')
            ->addColumn('message_id', 'string', ['limit' => 998, 'null' => true])
            ->addColumn('in_reply_to', 'string', ['limit' => 998, 'null' => true])
            ->addColumn('references', 'text', ['null' => true])
            ->addIndex(['message_id'], ['name' => 'idx_msg_message_id', 'limit' => 191])
            ->update();

        // Remove duplicate attachment rows before enforcing the unique key.
        $this->execute(
            'DELETE a1 FROM google_attachments a1
             INNER JOIN google_attachments a2
                 ON a1.id > a2.id
                AND a1.google_id = a2.google_id
                AND a1.message_google_id = a2.message_google_id
                AND a1.user_id = a2.user_id
             WHERE a1.google_id IS NOT NULL'
        );

        $this->table('google_attachments')
            ->addIndex(
                ['google_id', 'message_google_id', 'user_id'],
                ['unique' => true, 'name' => 'idx_att_google_msg_user']
            )
            ->update();

        // Remove duplicate sent-message rows before enforcing the unique key.
        $this->execute(
            'DELETE s1 FROM google_sent_emails s1
             INNER JOIN google_sent_emails s2
                 ON s1.id > s2.id
                AND s1.message_google_id = s2.message_google_id
                AND s1.user_id = s2.user_id
             WHERE s1.message_google_id IS NOT NULL'
        );

        $this->table('google_sent_emails')
            ->addIndex(
                ['message_google_id', 'user_id'],
                ['unique' => true, 'name' => 'idx_sent_msg_user']
            )
            ->update();
    }
}
