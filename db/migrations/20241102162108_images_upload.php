<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ImagesUpload extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        CREATE TABLE images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                auction_item_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (auction_item_id) REFERENCES AuctionItem(id) ON DELETE CASCADE ON UPDATE CASCADE
            );
        SQL;
        $this->execute($sql);
    }
}
