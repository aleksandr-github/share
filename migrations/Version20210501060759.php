<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210501060759 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE algorithm_run_result (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, timer_handicap_multiplier DOUBLE PRECISION NOT NULL, position_percentage INTEGER NOT NULL, handicap_modifier DOUBLE PRECISION NOT NULL, avg_rank_total_profit DOUBLE PRECISION DEFAULT NULL, rating_total_profit DOUBLE PRECISION DEFAULT NULL)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE algorithm_run_result');
    }
}
