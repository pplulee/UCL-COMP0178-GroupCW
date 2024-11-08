<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Bid extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up(): void
    {
        $sql = <<< EOD
        CREATE TABLE IF NOT EXISTS `bid`(
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `auction_item_id` INT NOT NULL,
            `bid_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `bid_price` DECIMAL(10, 2) NOT NULL,
            `bid_status` ENUM('pending', 'won', 'lost') NOT NULL DEFAULT 'pending'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $this->execute($sql);

    }
}