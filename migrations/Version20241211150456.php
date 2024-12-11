<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241211150456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE episodes DROP CONSTRAINT fk_7dd55eddad7ed998');
        $this->addSql('DROP INDEX idx_7dd55eddad7ed998');
        $this->addSql('ALTER TABLE episodes RENAME COLUMN shows_id TO show_id');
        $this->addSql('ALTER TABLE episodes ADD CONSTRAINT FK_7DD55EDDD0C1FC64 FOREIGN KEY (show_id) REFERENCES shows (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_7DD55EDDD0C1FC64 ON episodes (show_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE episodes DROP CONSTRAINT FK_7DD55EDDD0C1FC64');
        $this->addSql('DROP INDEX IDX_7DD55EDDD0C1FC64');
        $this->addSql('ALTER TABLE episodes RENAME COLUMN show_id TO shows_id');
        $this->addSql('ALTER TABLE episodes ADD CONSTRAINT fk_7dd55eddad7ed998 FOREIGN KEY (shows_id) REFERENCES shows (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_7dd55eddad7ed998 ON episodes (shows_id)');
    }
}
