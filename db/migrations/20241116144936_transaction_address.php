<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TransactionAddress extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        ALTER TABLE `transaction`
            ADD COLUMN `address` TEXT NOT NULL,
            ADD COLUMN `tracking_number` VARCHAR(255) DEFAULT NULL,
            ADD COLUMN `pay_at` DATETIME DEFAULT NULL,
            ADD COLUMN `ship_at` DATETIME DEFAULT NULL,
            MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY COLUMN `finished_at` DATETIME DEFAULT NULL;
        SQL;
        $this->execute($sql);
    }
}
