<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205121607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
        $this->addSql('CREATE INDEX idx_shows_name_trgm ON shows USING gin (LOWER(name) gin_trgm_ops);');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP EXTENSION IF EXISTS pg_trgm;');
        $this->addSql('DROP INDEX idx_shows_name_trgm;');
    }
}
