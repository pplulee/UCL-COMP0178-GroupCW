<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EmailQueue extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        CREATE TABLE email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            template VARCHAR(255) NOT NULL,
            params TEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed', 'locked') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            error TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;
        SQL;

        $this->execute($sql);
    }
}
