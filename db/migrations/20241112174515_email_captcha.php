<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EmailCaptcha extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        CREATE TABLE email_captcha (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            code VARCHAR(16) NOT NULL,
            type ENUM('register', 'reset') NOT NULL,
            expire_at TIMESTAMP NOT NULL DEFAULT DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE)
        );
        SQL;
        $this->execute($sql);
    }
}
