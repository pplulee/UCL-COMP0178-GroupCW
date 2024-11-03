<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuctionItemViews extends AbstractMigration
{
    public function up(): void
    {
        $sql = "ALTER TABLE AuctionItem ADD COLUMN views INT(11) DEFAULT 0";
        $this->execute($sql);
    }
}
