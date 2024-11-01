<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Category extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        CREATE TABLE category (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        );
        SQL;
        $this->execute($sql);
        // Add example categories
        $categories = [
            'Electronics',
            'Books',
            'Clothing',
            'Home & Garden',
            'Health & Beauty',
            'Toys & Hobbies',
            'Motors',
            'Sporting Goods',
            'Other'
        ];
        foreach ($categories as $category) {
            $this->execute("INSERT INTO category (name) VALUES ('$category')");
        }
    }
}
