<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250111155520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE users (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE users_shows (created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT NOT NULL, show_id INT NOT NULL, PRIMARY KEY(user_id, show_id))');
        $this->addSql('CREATE INDEX IDX_8B4C550DA76ED395 ON users_shows (user_id)');
        $this->addSql('CREATE INDEX IDX_8B4C550DD0C1FC64 ON users_shows (show_id)');
        $this->addSql('ALTER TABLE users_shows ADD CONSTRAINT FK_8B4C550DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users_shows ADD CONSTRAINT FK_8B4C550DD0C1FC64 FOREIGN KEY (show_id) REFERENCES shows (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users_shows DROP CONSTRAINT FK_8B4C550DA76ED395');
        $this->addSql('ALTER TABLE users_shows DROP CONSTRAINT FK_8B4C550DD0C1FC64');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE users_shows');
    }
}