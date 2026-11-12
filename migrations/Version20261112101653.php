<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Keeps the viewer's show count on queued emails so the deletion reminder can
 * tell unverified users exactly what they are about to lose.
 */
final class Version20261112101653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shows_count to emails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE emails ADD shows_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE emails DROP shows_count');
    }
}
