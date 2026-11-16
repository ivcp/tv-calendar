<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116085632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.remember_key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD remember_key VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP remember_key');
    }
}
