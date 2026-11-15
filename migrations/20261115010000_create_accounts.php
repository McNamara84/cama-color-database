<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAccounts extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('email', 'string', ['limit' => 254, 'collation' => 'utf8mb4_bin', 'null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'collation' => 'ascii_bin', 'encoding' => 'ascii', 'null' => false])
            // A unique nullable slot permits exactly one bootstrap administrator.
            ->addColumn('admin_slot', 'integer', ['null' => true, 'default' => null])
            ->addColumn('must_change_password', 'boolean', ['default' => true, 'null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['admin_slot'], ['unique' => true])
            ->create();
        $this->table('login_attempts', ['id' => false, 'primary_key' => 'bucket', 'engine' => 'InnoDB'])
            ->addColumn('bucket', 'string', ['limit' => 64, 'collation' => 'ascii_bin', 'encoding' => 'ascii', 'null' => false])
            ->addColumn('attempts', 'integer', ['null' => false])
            ->addColumn('expires_at', 'biginteger', ['null' => false])
            ->addIndex(['expires_at'])
            ->create();
    }
}
