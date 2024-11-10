<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserAddress extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        ALTER TABLE user
        ADD COLUMN address TEXT NOT NULL;
        SQL;
        $this->execute($sql);
    }
}
