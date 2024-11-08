<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuctionItem extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<< EOD
        CREATE TABLE AuctionItem (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `seller_id` INT(11) NOT NULL,
            `category_id` INT(11) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `start_price` DECIMAL(10, 2) NOT NULL,
            `reserve_price` DECIMAL(10, 2),
            `current_price` DECIMAL(10, 2) DEFAULT 0.00,
            `bid_increment` DECIMAL(10, 2) DEFAULT 1.00,
            `start_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `end_date` DATETIME NOT NULL,
            `status` ENUM('active', 'closed', 'cancelled') NOT NULL DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $this->execute($sql);

    }
}
