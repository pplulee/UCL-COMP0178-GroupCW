<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class User extends AbstractMigration
{
    public function up(): void
    {
        $user = $this->table('user', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $user->addColumn('username', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('password', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('role', 'enum', ['values' => ['SELLER', 'BUYER', 'ADMIN'], 'null' => false])
            ->addColumn('created_at', 'datetime',['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('uuid', 'string', ['limit' => 255, 'null' => false])
            ->create();
    }
}
