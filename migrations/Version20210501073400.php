<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210501073400 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__algorithm_run_result AS SELECT id, timer_handicap_multiplier, position_percentage, handicap_modifier, avg_rank_total_profit, rating_total_profit FROM algorithm_run_result');
        $this->addSql('DROP TABLE algorithm_run_result');
        $this->addSql('CREATE TABLE algorithm_run_result (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, timer_handicap_multiplier DOUBLE PRECISION NOT NULL, position_percentage INTEGER NOT NULL, handicap_modifier DOUBLE PRECISION NOT NULL, avg_rank_total_profit VARCHAR(255) DEFAULT NULL, rating_total_profit VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO algorithm_run_result (id, timer_handicap_multiplier, position_percentage, handicap_modifier, avg_rank_total_profit, rating_total_profit) SELECT id, timer_handicap_multiplier, position_percentage, handicap_modifier, avg_rank_total_profit, rating_total_profit FROM __temp__algorithm_run_result');
        $this->addSql('DROP TABLE __temp__algorithm_run_result');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__algorithm_run_result AS SELECT id, timer_handicap_multiplier, position_percentage, handicap_modifier, avg_rank_total_profit, rating_total_profit FROM algorithm_run_result');
        $this->addSql('DROP TABLE algorithm_run_result');
        $this->addSql('CREATE TABLE algorithm_run_result (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, timer_handicap_multiplier DOUBLE PRECISION NOT NULL, position_percentage INTEGER NOT NULL, handicap_modifier DOUBLE PRECISION NOT NULL, avg_rank_total_profit DOUBLE PRECISION DEFAULT NULL, rating_total_profit DOUBLE PRECISION DEFAULT NULL)');
        $this->addSql('INSERT INTO algorithm_run_result (id, timer_handicap_multiplier, position_percentage, handicap_modifier, avg_rank_total_profit, rating_total_profit) SELECT id, timer_handicap_multiplier, position_percentage, handicap_modifier, avg_rank_total_profit, rating_total_profit FROM __temp__algorithm_run_result');
        $this->addSql('DROP TABLE __temp__algorithm_run_result');
    }
}
