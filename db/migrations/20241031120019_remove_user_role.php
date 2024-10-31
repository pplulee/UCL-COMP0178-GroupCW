<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveUserRole extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE `user` DROP COLUMN `role`;');
        $this->execute('ALTER TABLE `user` ADD COLUMN `admin` BOOLEAN DEFAULT FALSE;');
    }
}