<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241215170014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE episodes (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, tv_maze_episode_id INT NOT NULL, season SMALLINT DEFAULT NULL, number INT DEFAULT NULL, airstamp TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, tv_maze_show_id INT NOT NULL, summary TEXT DEFAULT NULL, name TEXT NOT NULL, runtime SMALLINT DEFAULT NULL, image_medium TEXT DEFAULT NULL, image_original TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, show_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7DD55EDD81B72118 ON episodes (tv_maze_episode_id)');
        $this->addSql('CREATE INDEX IDX_7DD55EDDD0C1FC64 ON episodes (show_id)');
        $this->addSql('CREATE TABLE shows (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, tv_maze_id INT NOT NULL, imdb_id VARCHAR(255) DEFAULT NULL, genres TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, premiered VARCHAR(255) DEFAULT NULL, ended VARCHAR(255) DEFAULT NULL, official_site TEXT DEFAULT NULL, weight SMALLINT NOT NULL, network_name VARCHAR(255) DEFAULT NULL, network_country VARCHAR(255) DEFAULT NULL, web_channel_name VARCHAR(255) DEFAULT NULL, web_channel_country VARCHAR(255) DEFAULT NULL, summary TEXT DEFAULT NULL, name TEXT NOT NULL, runtime SMALLINT DEFAULT NULL, image_medium TEXT DEFAULT NULL, image_original TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6C3BF1441CC40406 ON shows (tv_maze_id)');
        $this->addSql('ALTER TABLE episodes ADD CONSTRAINT FK_7DD55EDDD0C1FC64 FOREIGN KEY (show_id) REFERENCES shows (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE episodes DROP CONSTRAINT FK_7DD55EDDD0C1FC64');
        $this->addSql('DROP TABLE episodes');
        $this->addSql('DROP TABLE shows');
    }
}