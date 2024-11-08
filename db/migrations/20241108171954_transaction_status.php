<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TransactionStatus extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        ALTER TABLE `transaction`
        ADD COLUMN `status` ENUM('pending_payment', 'pending_ship', 'shipped', 'finished') NOT NULL DEFAULT 'pending_payment';
        SQL;
        $this->execute($sql);
    }
}
