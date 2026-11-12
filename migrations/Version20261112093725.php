<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tracks when an unverified account was warned about its upcoming deletion,
 * so the daily cleanup does not send the same reminder twice.
 */
final class Version20261112093725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deletion_notice_sent_at to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD deletion_notice_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP deletion_notice_sent_at');
    }
}
