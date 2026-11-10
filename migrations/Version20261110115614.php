<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stores the TVMaze "updated" timestamp of the last fully synced show payload,
 * so the daily sync can skip shows that have not changed.
 */
final class Version20261110115614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tv_maze_updated_at to shows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shows ADD tv_maze_updated_at BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shows DROP tv_maze_updated_at');
    }
}
