<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddForeignKey extends AbstractMigration
{
    public function change(): void
    {
        $sql = <<<SQL
        ALTER TABLE `mfa_credential`
        ADD FOREIGN KEY (`userid`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        
        ALTER TABLE `AuctionItem`
        ADD FOREIGN KEY (`seller_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD FOREIGN KEY (`category_id`) REFERENCES `category`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
                                                                                                          
        ALTER TABLE `watch`
        ADD FOREIGN KEY (`buyer_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD FOREIGN KEY (`auction_item_id`) REFERENCES `AuctionItem`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
                                                                                                          
        ALTER TABLE `bid`
        ADD FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD FOREIGN KEY (`auction_item_id`) REFERENCES `AuctionItem`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        
        ALTER TABLE `transaction`
        ADD FOREIGN KEY (`bid_id`) REFERENCES `bid`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD FOREIGN KEY (`auction_item_id`) REFERENCES `AuctionItem`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        SQL;
        $this->execute($sql);
    }
}
