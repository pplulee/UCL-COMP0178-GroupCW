<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TransactionUserRecord extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        ALTER TABLE transaction
        DROP FOREIGN KEY transaction_ibfk_2,
        DROP COLUMN user_id,
        CHANGE COLUMN transaction_price price DECIMAL(10, 2) NOT NULL,
        ADD COLUMN buyer_id INT NOT NULL,
        ADD COLUMN seller_id INT NOT NULL,
        DROP COLUMN transaction_date,
        ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN finished_at TIMESTAMP DEFAULT NULL,
        ADD FOREIGN KEY (buyer_id) REFERENCES user(id),
        ADD FOREIGN KEY (seller_id) REFERENCES user(id);
        SQL;
        $this->execute($sql);
    }
}
