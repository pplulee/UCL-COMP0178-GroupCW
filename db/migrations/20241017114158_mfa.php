<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Mfa extends AbstractMigration
{
    public function up(): void
    {
//        $mfa = $this->table('mfa_credential', ['id' => 'id', 'comment' => 'MFA Credentials']);
//        $mfa->addColumn('userid', 'integer', ['limit' => 11, 'null' => false])
//            ->addColumn('body', 'text', ['null' => false])
//            ->addColumn('name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
//            ->addColumn('rawid', 'string', ['limit' => 255, 'null' => true, 'default' => null])
//            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
//            ->addColumn('used_at', 'datetime', ['null' => true, 'default' => null])
//            ->addColumn('type', 'string', ['limit' => 255, 'null' => false])
//            ->create();
        $sql = <<<EOD
        CREATE TABLE `mfa_credential` (
            `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'MFA Credentials',
            `userid` INT(11) NOT NULL,
            `body` TEXT NOT NULL,
            `name` VARCHAR(255) DEFAULT NULL,
            `rawid` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `used_at` DATETIME DEFAULT NULL,
            `type` VARCHAR(255) NOT NULL
        );
        EOD;
        $this->execute($sql);
    }
}
