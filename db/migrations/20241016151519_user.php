<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class User extends AbstractMigration
{
    public function up(): void
    {
//        $user = $this->table('user', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
//        $user->addColumn('username', 'string', ['limit' => 255, 'null' => false])
//            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
//            ->addColumn('password', 'string', ['limit' => 255, 'null' => false])
//            ->addColumn('role', 'enum', ['values' => ['SELLER', 'BUYER', 'ADMIN'], 'null' => false])
//            ->addColumn('created_at', 'datetime',['default' => 'CURRENT_TIMESTAMP'])
//            ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
//            ->create();
        $sql = <<<EOD
        CREATE TABLE `user` (
                    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(255) NOT NULL,
                    `email` VARCHAR(255) NOT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `role` ENUM('SELLER', 'BUYER', 'ADMIN') NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `uuid` VARCHAR(36) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;
    $this->execute($sql);
    }
}
