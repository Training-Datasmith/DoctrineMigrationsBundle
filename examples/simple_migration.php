<?php

declare(strict_types=1);

/**
 * Example: A minimal Doctrine Migration.
 *
 * Generate with: bin/console doctrine:migrations:generate
 * Run with:      bin/console doctrine:migrations:migrate
 */

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial users table';
    }

    /**
     * Apply the migration — creates the users table.
     */
    public function up(Schema $schema): void
    {
        // Use $this->addSql() for all DDL changes so Doctrine tracks them.
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email      VARCHAR(255) NOT NULL UNIQUE,
                password   VARCHAR(255) NOT NULL,
                created_at DATETIME     NOT NULL
            )
        SQL);
    }

    /**
     * Reverse the migration — drops the users table.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
