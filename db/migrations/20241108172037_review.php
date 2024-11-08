<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Review extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        CREATE TABLE review (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reviewer_id INT NOT NULL,
            reviewee_id INT NOT NULL,
            transaction_id INT NOT NULL,
            rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reviewer_id) REFERENCES user(id),
            FOREIGN KEY (reviewee_id) REFERENCES user(id),
            FOREIGN KEY (transaction_id) REFERENCES transaction(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->execute($sql);
    }
}