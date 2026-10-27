<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stores fixed-window counters used by the application-level rate limiter.
 */
final class Version20261027091026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the rate_limits table for application-level rate limiting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE rate_limits (
                bucket VARCHAR(191) NOT NULL,
                hits INT NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(bucket)
            )'
        );
        $this->addSql('CREATE INDEX idx_rate_limits_expires_at ON rate_limits (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_rate_limits_expires_at');
        $this->addSql('DROP TABLE rate_limits');
    }
}
