<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuctionItem extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<< EOD
        CREATE TABLE AuctionItem (
            `AuctionItemID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `SellerID` INT(11) NOT NULL,
            `CategoryID` INT(11) NOT NULL,
            `ItemName` VARCHAR(255) NOT NULL,
            `Description` TEXT NOT NULL,
            `StartingPrice` DECIMAL(10, 2) NOT NULL,
            `ReservePrice` DECIMAL(10, 2),
          //`CurrentBidPrice` DECIMAL(10, 2) DEFAULT 0.00,
          //`BidIncrement` DECIMAL(10, 2) DEFAULT 1.00,
            `StartingDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `EndDate` DATETIME NOT NULL TIMESTAMP,
          //`Status` ENUM('active', 'closed', 'cancelled')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $this->execute($sql);

    }
}
